import os
import re
import shutil
from datetime import datetime, timezone
from pathlib import Path

import uno

from spellcheck_core import (
    connect,
    detect_lo_document_family,
    extract_text,
    find_unique_errors,
    load_document_editable,
    make_locale,
    make_prop,
)
from spellcheck_s3 import (
    derive_correction_keys,
    upload_file_to_s3,
    upload_json_to_s3,
)

COMMENT_AUTHOR = os.environ.get("SPELLCHECK_COMMENT_AUTHOR", "Revision ortografica")

DOCUMENT_FILTERS = {
    ".doc": "MS Word 97",
    ".docx": "Office Open XML Text",
    ".xls": "MS Excel 97",
    ".xlsx": "Calc Office Open XML",
    ".ppt": "MS PowerPoint 97",
    ".pptx": "Impress Office Open XML",
}

HIGHLIGHT_COLOR = int(os.environ.get("SPELLCHECK_MARK_COLOR", "0xFFFF99"), 16)


def _empty_stats(estrategia=""):
    return {
        "resaltados": 0,
        "comentarios": 0,
        "comentarios_fallidos": 0,
        "estrategia": estrategia,
        "fallos": [],
    }


def _merge_stats(target, source):
    if not source:
        return target
    target["resaltados"] += source.get("resaltados", 0)
    target["comentarios"] += source.get("comentarios", 0)
    target["comentarios_fallidos"] += source.get("comentarios_fallidos", 0)
    if source.get("estrategia") and not target.get("estrategia"):
        target["estrategia"] = source["estrategia"]
    target["fallos"].extend(source.get("fallos", []))
    return target


def _stats_total(stats):
    return stats.get("resaltados", 0) + stats.get("comentarios", 0)


def format_comment_text(error):
    tipo = error.get("tipo", "")
    palabra = error.get("palabra", "")
    sugerencias = error.get("sugerencias") or []

    tipo_label = "Falta tilde" if tipo == "tilde" else "Error ortografico"
    if sugerencias:
        sugs = ", ".join(sugerencias[:5])
        return f"{tipo_label}: '{palabra}'. Sugerencias: {sugs}"
    return f"{tipo_label}: '{palabra}'."


def resolve_document_type(doc, file_ext=""):
    ext = (file_ext or "").lower()
    family = detect_lo_document_family(doc)

    if ext == ".pdf":
        if family in ("draw", "writer"):
            return "pdf_" + family
        return "pdf_draw"

    return family


def create_annotation(doc, comment_text):
    annotation = doc.createInstance("com.sun.star.text.textfield.Annotation")
    annotation.Author = COMMENT_AUTHOR
    annotation.Content = comment_text
    return annotation


def highlight_range(found_range):
    try:
        found_range.CharBackColor = HIGHLIGHT_COLOR
        return True
    except Exception:
        return False


def _word_pattern(word):
    return re.compile(r"\b" + re.escape(word) + r"\b", re.IGNORECASE)


def _word_in_text(text, original_word):
    return _word_pattern(original_word).search(text or "") is not None


def _mark_word_range(text, doc, word_range, comment_text, stats, allow_inline_comment=True):
    highlighted = highlight_range(word_range)
    if highlighted:
        stats["resaltados"] += 1

    if not allow_inline_comment:
        return highlighted

    try:
        annotation = create_annotation(doc, comment_text)
        text.insertTextContent(word_range, annotation, False)
        stats["comentarios"] += 1
        return True
    except Exception as e:
        stats["comentarios_fallidos"] += 1
        stats["fallos"].append(f"comentario_inline: {e}")
        return highlighted


def _annotate_text_with_cursor(text, doc, errors, stats, allow_inline_comment=True):
    if not errors or text is None:
        return stats

    try:
        full_text = text.getString()
    except Exception:
        return stats

    if not full_text:
        return stats

    for error in errors:
        word = error["palabra"]
        comment_text = format_comment_text(error)

        for match in _word_pattern(word).finditer(full_text):
            try:
                start = match.start()
                end = match.end()

                cursor = text.createTextCursor()
                cursor.gotoStart(False)
                cursor.goRight(start, False)

                end_cursor = text.createTextCursor()
                end_cursor.gotoStart(False)
                end_cursor.goRight(end, False)

                word_range = text.createTextCursorByRange(cursor)
                word_range.gotoRange(end_cursor, True)

                _mark_word_range(
                    text, doc, word_range, comment_text, stats, allow_inline_comment
                )
            except Exception as e:
                stats["fallos"].append(f"cursor_word '{word}': {e}")

    return stats


def annotate_searchable(searchable, doc, errors, stats, allow_inline_comment=True):
    if not errors:
        return stats

    for error in errors:
        word = error["palabra"]
        comment_text = format_comment_text(error)

        search = searchable.createSearchDescriptor()
        search.SearchString = word
        search.SearchCaseSensitive = False
        search.SearchWords = True

        found = searchable.findFirst(search)
        while found:
            try:
                _mark_word_range(
                    found.getText(), doc, found, comment_text, stats, allow_inline_comment
                )
            except Exception as e:
                stats["fallos"].append(f"searchable '{word}': {e}")

            try:
                end = found.getEnd()
                found = searchable.findNext(end, search)
            except Exception:
                break

    return stats


# ---------------------------------------------------------------------------
# WORD
# ---------------------------------------------------------------------------

def mark_word(doc, errors):
    stats = _empty_stats("word_annotation")

    annotate_searchable(doc, doc, errors, stats)
    if _stats_total(stats) == 0:
        try:
            _annotate_text_with_cursor(doc.getText(), doc, errors, stats)
        except Exception as e:
            stats["fallos"].append(f"word_cursor: {e}")

    return stats


# ---------------------------------------------------------------------------
# EXCEL
# ---------------------------------------------------------------------------

def _calc_cell_display_value(cell):
    try:
        value = cell.getString()
        if value:
            return value
    except Exception:
        pass

    try:
        value = str(cell.getValue())
        if value and value != "0.0":
            return value
    except Exception:
        pass

    try:
        formula = cell.getFormula()
        if formula:
            return cell.getString() or formula
    except Exception:
        pass

    return ""


def _calc_annotation_text(comment_text):
    if COMMENT_AUTHOR:
        return f"[{COMMENT_AUTHOR}] {comment_text}"
    return comment_text


def _calc_set_annotation_visible(cell):
    try:
        annotation = cell.getAnnotation()
        if annotation is not None:
            annotation.setIsVisible(True)
            return True
    except Exception:
        pass

    try:
        annotation = cell.Annotation
        if annotation is not None:
            annotation.setIsVisible(True)
            return True
    except Exception:
        pass

    return False


def _calc_add_cell_comment(sheet, cell, comment_text, stats):
    """insertNew(aCellAddress, aText) - solo 2 parametros en Calc."""
    cell_addr = cell.getCellAddress()
    note_text = _calc_annotation_text(comment_text)

    try:
        sheet.getAnnotations().insertNew(cell_addr, note_text)
        _calc_set_annotation_visible(cell)
        stats["comentarios"] += 1
        return True
    except Exception as e1:
        stats["fallos"].append(f"calc_insertNew: {e1}")

    try:
        struct_addr = uno.createUnoStruct("com.sun.star.table.CellAddress")
        struct_addr.Sheet = cell_addr.Sheet
        struct_addr.Column = cell_addr.Column
        struct_addr.Row = cell_addr.Row
        sheet.getAnnotations().insertNew(struct_addr, note_text)
        _calc_set_annotation_visible(cell)
        stats["comentarios"] += 1
        return True
    except Exception as e2:
        stats["fallos"].append(f"calc_insertNew_struct: {e2}")

    stats["comentarios_fallidos"] += 1
    return False


def _calc_highlight_cell(cell, stats):
    try:
        cell.CellBackColor = HIGHLIGHT_COLOR
        stats["resaltados"] += 1
        return True
    except Exception as e:
        stats["fallos"].append(f"calc_cell_color: {e}")
        return False


def _calc_highlight_words_in_cell(cell, doc, cell_errors, stats):
    before = stats.get("resaltados", 0)

    try:
        cell_text = cell.getText()
        if cell_text is not None:
            _annotate_text_with_cursor(
                cell_text, doc, cell_errors, stats, allow_inline_comment=False
            )
    except Exception:
        pass

    if stats.get("resaltados", 0) == before:
        try:
            _annotate_text_with_cursor(
                cell, doc, cell_errors, stats, allow_inline_comment=False
            )
        except Exception as e:
            stats["fallos"].append(f"calc_cell_text: {e}")

    return stats


def mark_excel(doc, errors):
    stats = _empty_stats("excel_cell_note")

    if not errors:
        return stats

    errors_by_word = {e["palabra"].lower(): e for e in errors}

    sheets = doc.getSheets()
    for i in range(sheets.getCount()):
        sheet = sheets.getByIndex(i)
        cursor = sheet.createCursor()
        cursor.gotoStartOfUsedArea(False)
        cursor.gotoEndOfUsedArea(True)
        addr = cursor.getRangeAddress()

        for row in range(addr.StartRow, addr.EndRow + 1):
            for col in range(addr.StartColumn, addr.EndColumn + 1):
                cell = sheet.getCellByPosition(col, row)
                value = _calc_cell_display_value(cell)
                if not value:
                    continue

                cell_errors = [
                    error
                    for word_lower, error in errors_by_word.items()
                    if _word_in_text(value, error["palabra"])
                ]

                if not cell_errors:
                    continue

                comment_text = "\n".join(
                    format_comment_text(e) for e in cell_errors
                )

                _calc_highlight_words_in_cell(cell, doc, cell_errors, stats)
                _calc_add_cell_comment(sheet, cell, comment_text, stats)

                if stats["resaltados"] == 0:
                    _calc_highlight_cell(cell, stats)

    return stats


# ---------------------------------------------------------------------------
# POWERPOINT / DRAW SHAPES
# ---------------------------------------------------------------------------

def _iter_draw_shapes(container):
    for i in range(container.getCount()):
        shape = container.getByIndex(i)
        yield shape
        try:
            if shape.supportsService("com.sun.star.drawing.GroupShape"):
                for child in _iter_draw_shapes(shape):
                    yield child
        except Exception:
            pass


def _annotate_table_shape(shape, doc, errors, stats, allow_inline_comment=False):
    try:
        rows = shape.getRows()
        cols = shape.getColumns()
        for r in range(rows.getCount()):
            for c in range(cols.getCount()):
                try:
                    cell = shape.getCellByPosition(c, r)
                    text = cell.getText()
                    if text is not None:
                        _annotate_text_with_cursor(
                            text, doc, errors, stats, allow_inline_comment
                        )
                    else:
                        _annotate_text_with_cursor(
                            cell, doc, errors, stats, allow_inline_comment
                        )
                except Exception as e:
                    stats["fallos"].append(f"ppt_table_cell: {e}")
    except Exception as e:
        stats["fallos"].append(f"ppt_table: {e}")

    return stats


def _annotate_draw_shape(shape, doc, errors, stats, allow_inline_comment=False):
    try:
        if shape.supportsService("com.sun.star.table.TableShape"):
            return _annotate_table_shape(shape, doc, errors, stats, allow_inline_comment)
    except Exception:
        pass

    try:
        text = shape.getText()
        if text is not None and text.getString().strip():
            _annotate_text_with_cursor(
                text, doc, errors, stats, allow_inline_comment
            )
            if stats.get("resaltados", 0) > 0:
                return stats
    except Exception:
        pass

    try:
        if hasattr(shape, "createSearchDescriptor"):
            annotate_searchable(
                shape, doc, errors, stats, allow_inline_comment
            )
    except Exception as e:
        stats["fallos"].append(f"ppt_searchable: {e}")

    return stats


def _get_notes_text(notes_page):
    try:
        shape_count = notes_page.getCount()
    except Exception:
        return None

    for i in range(shape_count):
        shape = notes_page.getByIndex(i)
        try:
            text = shape.getText()
            if text is not None:
                return text
        except Exception:
            pass
        try:
            if shape.supportsService("com.sun.star.text.Text"):
                return shape.getText()
        except Exception:
            pass

    return None


def _add_slide_comment_box(doc, page, errors_on_slide, stats):
    """Caja de texto visible en la diapositiva (equivalente a comentario en PPT)."""
    if not errors_on_slide:
        return stats

    comment_text = "\n".join(
        format_comment_text(e) for e in errors_on_slide
    )

    try:
        text_shape = doc.createInstance("com.sun.star.drawing.TextShape")
        text_shape.String = "[Revision ortografica]\n" + comment_text

        size = uno.createUnoStruct("com.sun.star.awt.Size")
        size.Width = 14000
        size.Height = 3500
        text_shape.Size = size

        pos = uno.createUnoStruct("com.sun.star.awt.Point")
        pos.X = 800
        pos.Y = 15500
        text_shape.Position = pos

        text_shape.FillColor = HIGHLIGHT_COLOR
        text_shape.FillTransparence = 15
        text_shape.LineColor = 0xFF6600

        page.add(text_shape)
        stats["comentarios"] += len(errors_on_slide)
        stats["estrategia"] = stats["estrategia"] or "ppt_comment_box"
    except Exception as e:
        stats["fallos"].append(f"ppt_comment_box: {e}")

    return stats


def _add_slide_notes(page, errors_on_slide, stats):
    if not errors_on_slide:
        return stats

    comment_text = "\n".join(
        format_comment_text(e) for e in errors_on_slide
    )
    header = "[Revision ortografica]\n"

    try:
        notes_page = page.getNotesPage()
        if notes_page is None:
            raise RuntimeError("sin notes page")

        notes_text = _get_notes_text(notes_page)
        if notes_text is None:
            raise RuntimeError("no text en notes page")

        cursor = notes_text.createTextCursor()
        cursor.gotoEnd(False)
        existing = notes_text.getString()
        prefix = "\n" if existing.strip() else ""
        notes_text.insertString(
            cursor, prefix + header + comment_text, False
        )
        stats["comentarios"] += len(errors_on_slide)
        if "ppt_comment_box" not in (stats.get("estrategia") or ""):
            stats["estrategia"] = stats["estrategia"] or "ppt_slide_notes"
    except Exception as e:
        stats["fallos"].append(f"ppt_notes: {e}")

    return stats


def mark_ppt(doc, errors):
    stats = _empty_stats("ppt_shape_annotation")

    if not errors:
        return stats

    errors_by_word = {e["palabra"].lower(): e for e in errors}
    pages = doc.getDrawPages()

    for p in range(pages.getCount()):
        page = pages.getByIndex(p)
        slide_text_chunks = []
        errors_on_slide = []

        for shape in _iter_draw_shapes(page):
            _annotate_draw_shape(shape, doc, errors, stats, allow_inline_comment=False)

            try:
                shape_text = ""
                try:
                    shape_text = shape.getString() or ""
                except Exception:
                    try:
                        shape_text = shape.getText().getString() or ""
                    except Exception:
                        pass

                if shape_text:
                    slide_text_chunks.append(shape_text)
            except Exception:
                pass

        slide_full_text = "\n".join(slide_text_chunks)
        seen_on_slide = set()
        for word_lower, error in errors_by_word.items():
            if word_lower in seen_on_slide:
                continue
            if _word_in_text(slide_full_text, error["palabra"]):
                seen_on_slide.add(word_lower)
                errors_on_slide.append(error)

        if errors_on_slide:
            _add_slide_comment_box(doc, page, errors_on_slide, stats)
            _add_slide_notes(page, errors_on_slide, stats)

    return stats


def mark_draw(doc, errors):
    return mark_ppt(doc, errors)


# ---------------------------------------------------------------------------
# PDF
# ---------------------------------------------------------------------------

def _pdf_add_sticky_notes(source_pdf_path, output_pdf_path, errors):
    stats = _empty_stats("pdf_pymupdf_notes")

    try:
        import fitz
    except ImportError:
        stats["fallos"].append("pymupdf no instalado (pip install pymupdf)")
        stats["comentarios_fallidos"] = len(errors)
        return stats

    source_pdf_path = Path(source_pdf_path)
    output_pdf_path = Path(output_pdf_path)

    if output_pdf_path.exists():
        output_pdf_path.unlink()

    doc = fitz.open(str(source_pdf_path))

    try:
        for page in doc:
            for error in errors:
                word = error["palabra"]
                comment_text = format_comment_text(error)

                try:
                    rects = page.search_for(word)
                except Exception:
                    rects = page.search_for(word, quads=False)

                for rect in rects:
                    try:
                        hl = page.add_highlight_annot(rect)
                        hl.set_colors(stroke=(1, 1, 0))
                        hl.update()
                        stats["resaltados"] += 1
                    except Exception as e:
                        stats["fallos"].append(f"pdf_hl '{word}': {e}")

                    try:
                        point = fitz.Point(rect.x0, rect.y0)
                        annot = page.add_text_annot(point, comment_text)
                        annot.set_info(title=COMMENT_AUTHOR, content=comment_text)
                        annot.update()
                        stats["comentarios"] += 1
                    except Exception as e:
                        stats["comentarios_fallidos"] += 1
                        stats["fallos"].append(f"pdf_note '{word}': {e}")

        doc.save(str(output_pdf_path), garbage=4)
    finally:
        doc.close()

    return stats


def _close_document(doc, save=False):
    if doc is None:
        return
    try:
        doc.close(save)
    except Exception:
        try:
            doc.close(False)
        except Exception:
            try:
                doc.dispose()
            except Exception:
                pass


def mark_pdf_writer(doc, errors):
    return mark_word(doc, errors)


def mark_pdf_draw(doc, errors):
    return mark_ppt(doc, errors)


def annotate_document(doc, doc_type, errors):
    if doc_type == "writer":
        return mark_word(doc, errors)
    if doc_type == "calc":
        return mark_excel(doc, errors)
    if doc_type == "impress":
        return mark_ppt(doc, errors)
    if doc_type == "pdf_writer":
        return mark_pdf_writer(doc, errors)
    if doc_type == "pdf_draw":
        return mark_pdf_draw(doc, errors)
    if doc_type.startswith("pdf"):
        return mark_pdf_draw(doc, errors)
    if doc_type == "draw":
        return mark_draw(doc, errors)
    return _empty_stats("unknown")


def _pdf_export_filters(doc):
    filters = []
    if doc.supportsService("com.sun.star.drawing.DrawingDocument"):
        filters.append("draw_pdf_Export")
    if doc.supportsService("com.sun.star.presentation.PresentationDocument"):
        filters.append("impress_pdf_Export")
    if doc.supportsService("com.sun.star.text.TextDocument"):
        filters.append("writer_pdf_Export")
    if not filters:
        filters.append("draw_pdf_Export")
    return filters


def _store_pdf(doc, output_path):
    out_url = uno.systemPathToFileUrl(str(output_path))
    last_error = None

    for filter_name in _pdf_export_filters(doc):
        props = (
            make_prop("FilterName", filter_name),
            make_prop("Overwrite", True),
        )
        try:
            doc.storeToURL(out_url, props)
            return filter_name
        except Exception as e:
            last_error = e

    raise RuntimeError(f"No se pudo exportar PDF: {last_error}")


def store_document(doc, output_path, file_ext=""):
    ext = (file_ext or output_path.suffix).lower()

    if ext == ".pdf":
        return _store_pdf(doc, output_path)

    filter_name = DOCUMENT_FILTERS.get(ext)
    if not filter_name:
        raise ValueError(f"No hay filter name configurado para {ext}")

    out_url = uno.systemPathToFileUrl(str(output_path))
    props = (
        make_prop("FilterName", filter_name),
        make_prop("Overwrite", True),
    )
    doc.storeAsURL(out_url, props)
    return filter_name


def build_errors_report(metadata, errores, s3_paths, marcacion_detalle=None):
    report = {
        "generado_en": datetime.now(timezone.utc).isoformat(),
        "syllabus_uac_cronograma": metadata.get("syllabus_uac_cronograma"),
        "archivo_original": metadata.get("archivo_original"),
        "tiene_errores": len(errores) > 0,
        "total_errores": len(errores),
        "errores": errores,
        "documento_correccion": s3_paths.get("documento_correccion"),
        "reporte_errores": s3_paths.get("reporte_errores"),
    }
    if marcacion_detalle is not None:
        report["marcacion_detalle"] = marcacion_detalle
    return report


def mark_document(source_path, output_dir, s3_bucket, s3_source_key, metadata):
    keys = derive_correction_keys(s3_source_key)
    source = Path(source_path)
    ext = source.suffix.lower()

    if ext not in DOCUMENT_FILTERS and ext != ".pdf":
        raise ValueError(f"Extension no soportada: {ext}")

    os.makedirs(output_dir, exist_ok=True)
    correction_path = Path(output_dir) / keys["correction_basename"]
    work_path = Path(output_dir) / f"work_{keys['correction_basename']}"
    lo_export_path = Path(output_dir) / f"lo_{keys['correction_basename']}"

    for path in (correction_path, work_path, lo_export_path):
        if path.exists():
            path.unlink()

    shutil.copy2(source_path, work_path)

    ctx = connect()
    smgr = ctx.ServiceManager
    spell = smgr.createInstanceWithContext(
        "com.sun.star.linguistic2.SpellChecker",
        ctx
    )
    locale_es = make_locale("es", "GT")

    doc = None
    tiene_errores = False
    try:
        doc = load_document_editable(ctx, str(work_path), ext)
        text = extract_text(doc)

        if not text.strip():
            return {
                "ok": False,
                "archivo": keys["original_basename"],
                "error": "No se pudo extraer texto del archivo",
            }

        errores = find_unique_errors(text, spell, locale_es)
        tiene_errores = len(errores) > 0

        s3_paths = {}
        marcacion_detalle = _empty_stats()
        doc_type = resolve_document_type(doc, ext)
        lo_family = detect_lo_document_family(doc)
        pdf_filter = None

        if tiene_errores:
            if doc_type == "unknown":
                raise ValueError("Tipo de documento no soportado para marcado")

            lo_stats = annotate_document(doc, doc_type, errores)
            _merge_stats(marcacion_detalle, lo_stats)

            if ext == ".pdf":
                pdf_filter = store_document(doc, lo_export_path, ext)
                _close_document(doc, save=False)
                doc = None

                pdf_stats = _pdf_add_sticky_notes(
                    lo_export_path, correction_path, errores
                )
                _merge_stats(marcacion_detalle, pdf_stats)
                if pdf_stats.get("estrategia"):
                    marcacion_detalle["estrategia"] = (
                        f"{marcacion_detalle.get('estrategia', '')}+pdf_pymupdf"
                    ).strip("+")
            else:
                pdf_filter = store_document(doc, correction_path, ext)
                _close_document(doc, save=False)
                doc = None

            s3_paths["documento_correccion"] = upload_file_to_s3(
                correction_path,
                s3_bucket,
                keys["correction_key"],
            )

        report = build_errors_report(
            {
                "syllabus_uac_cronograma": metadata.get("syllabus_uac_cronograma"),
                "archivo_original": keys["original_basename"],
            },
            errores,
            s3_paths,
            marcacion_detalle=marcacion_detalle if tiene_errores else None,
        )

        s3_paths["reporte_errores"] = upload_json_to_s3(
            report,
            s3_bucket,
            keys["json_key"],
        )

        total_marcaciones = (
            marcacion_detalle.get("resaltados", 0)
            + marcacion_detalle.get("comentarios", 0)
        )

        result = {
            "ok": True,
            "archivo_original": keys["original_basename"],
            "archivo_correccion": keys["correction_basename"] if tiene_errores else None,
            "tipo_documento": doc_type,
            "lo_family": lo_family,
            "tiene_errores": tiene_errores,
            "total_errores": len(errores),
            "total_marcaciones": total_marcaciones,
            "marcacion_detalle": marcacion_detalle if tiene_errores else None,
            "errores": errores,
        }

        if tiene_errores and ext == ".pdf" and pdf_filter:
            result["pdf_export_filter"] = pdf_filter

        if tiene_errores:
            result["documento_correccion"] = s3_paths["documento_correccion"]

        result["reporte_errores"] = s3_paths["reporte_errores"]

        return result

    finally:
        _close_document(doc, save=False)

        if not tiene_errores:
            try:
                if correction_path.exists():
                    correction_path.unlink()
            except Exception:
                pass

        for temp_path in (work_path, lo_export_path):
            try:
                if temp_path.exists():
                    temp_path.unlink()
            except Exception:
                pass
