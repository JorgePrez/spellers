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
    except Exception:
        pass


def _word_pattern(word):
    return re.compile(r"\b" + re.escape(word) + r"\b", re.IGNORECASE)


def _word_in_text(text, original_word):
    return _word_pattern(original_word).search(text or "") is not None


def _mark_word_range(text, doc, word_range, comment_text):
    """Resalta la palabra e inserta comentario con sugerencias (como Word)."""
    highlighted = False
    commented = False

    try:
        highlight_range(word_range)
        highlighted = True
    except Exception:
        pass

    try:
        annotation = create_annotation(doc, comment_text)
        text.insertTextContent(word_range, annotation, False)
        commented = True
    except Exception:
        pass

    return highlighted or commented


def _annotate_text_with_cursor(text, doc, errors):
    """
    Marca cada ocurrencia: resaltado amarillo + comentario Annotation.
    Usado en Word, Excel (texto de celda), PPT y PDF (Draw/Writer).
    """
    if not errors or text is None:
        return 0

    marked = 0
    full_text = text.getString()
    if not full_text:
        return 0

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

                if _mark_word_range(text, doc, word_range, comment_text):
                    marked += 1
            except Exception:
                continue

    return marked


def annotate_searchable(searchable, doc, errors):
    if not errors:
        return 0

    marked = 0

    for error in errors:
        word = error["palabra"]
        comment_text = format_comment_text(error)

        search = searchable.createSearchDescriptor()
        search.SearchString = word
        search.SearchCaseSensitive = False
        search.SearchWords = True

        found = searchable.findFirst(search)
        while found:
            if _mark_word_range(found.getText(), doc, found, comment_text):
                marked += 1

            try:
                end = found.getEnd()
                found = searchable.findNext(end, search)
            except Exception:
                break

    return marked


def annotate_writer(doc, errors):
    marked = annotate_searchable(doc, doc, errors)
    if marked > 0:
        return marked

    try:
        return _annotate_text_with_cursor(doc.getText(), doc, errors)
    except Exception:
        return marked


def _calc_add_cell_comment(sheet, cell, comment_text):
    try:
        annotations = sheet.getAnnotations()
        cell_addr = cell.getCellAddress()
        annotations.insertNew(cell_addr, COMMENT_AUTHOR, comment_text)
        return True
    except Exception:
        pass

    try:
        annotation = cell.Annotation
        if annotation:
            annotation.String = comment_text
            annotation.Author = COMMENT_AUTHOR
            return True
    except Exception:
        pass

    return False


def annotate_calc(doc, errors):
    """
    Excel: resaltado de la palabra en la celda + comentario de celda con sugerencias.
    """
    if not errors:
        return 0

    errors_by_word = {e["palabra"].lower(): e for e in errors}
    marked = 0

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
                value = cell.getString()
                if not value:
                    continue

                cell_errors = [
                    error
                    for word_lower, error in errors_by_word.items()
                    if _word_in_text(value, error["palabra"])
                ]

                if not cell_errors:
                    continue

                comment_text = "\n".join(format_comment_text(e) for e in cell_errors)
                word_marks = 0

                try:
                    cell_text = cell.getText()
                    if cell_text is not None:
                        word_marks = _annotate_text_with_cursor(
                            cell_text, doc, cell_errors
                        )
                except Exception:
                    pass

                comment_added = _calc_add_cell_comment(sheet, cell, comment_text)

                if word_marks == 0:
                    try:
                        cell.CellBackColor = HIGHLIGHT_COLOR
                    except Exception:
                        pass

                if word_marks > 0 or comment_added:
                    marked += max(word_marks, len(cell_errors))

    return marked


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


def _annotate_table_shape(shape, doc, errors):
    marked = 0
    try:
        rows = shape.getRows()
        cols = shape.getColumns()
        for r in range(rows.getCount()):
            for c in range(cols.getCount()):
                try:
                    cell = shape.getCellByPosition(c, r)
                    text = cell.getText()
                    if text is not None:
                        marked += _annotate_text_with_cursor(text, doc, errors)
                except Exception:
                    pass
    except Exception:
        pass
    return marked


def _annotate_draw_shape(shape, doc, errors):
    marked = 0

    try:
        if shape.supportsService("com.sun.star.table.TableShape"):
            return _annotate_table_shape(shape, doc, errors)
    except Exception:
        pass

    try:
        text = shape.getText()
        if text is not None and text.getString().strip():
            marked += _annotate_text_with_cursor(text, doc, errors)
            if marked > 0:
                return marked
    except Exception:
        pass

    try:
        if hasattr(shape, "createSearchDescriptor"):
            marked += annotate_searchable(shape, doc, errors)
    except Exception:
        pass

    return marked


def annotate_draw_pages(doc, errors):
    """PowerPoint y PDF (modo Draw): resaltado + comentario en cada shape."""
    if not errors:
        return 0

    marked = 0
    pages = doc.getDrawPages()

    for p in range(pages.getCount()):
        page = pages.getByIndex(p)
        for shape in _iter_draw_shapes(page):
            marked += _annotate_draw_shape(shape, doc, errors)

    return marked


def annotate_impress(doc, errors):
    return annotate_draw_pages(doc, errors)


def annotate_pdf_draw(doc, errors):
    return annotate_draw_pages(doc, errors)


def annotate_pdf_writer(doc, errors):
    return annotate_writer(doc, errors)


def annotate_document(doc, doc_type, errors):
    if doc_type == "writer":
        return annotate_writer(doc, errors)
    if doc_type == "calc":
        return annotate_calc(doc, errors)
    if doc_type in ("impress",):
        return annotate_impress(doc, errors)
    if doc_type == "pdf_draw":
        return annotate_pdf_draw(doc, errors)
    if doc_type == "pdf_writer":
        return annotate_pdf_writer(doc, errors)
    if doc_type.startswith("pdf"):
        return annotate_pdf_draw(doc, errors)
    if doc_type == "draw":
        return annotate_draw_pages(doc, errors)
    return 0


def _pdf_export_filter(doc):
    if doc.supportsService("com.sun.star.presentation.PresentationDocument"):
        return "impress_pdf_Export"
    if doc.supportsService("com.sun.star.drawing.DrawingDocument"):
        return "draw_pdf_Export"
    return "writer_pdf_Export"


def store_document(doc, output_path):
    ext = output_path.suffix.lower()
    out_url = uno.systemPathToFileUrl(str(output_path))

    if ext == ".pdf":
        filter_name = _pdf_export_filter(doc)
        filter_data = (
            make_prop("SelectPdfVersion", 1),
            make_prop("ExportNotesPages", True),
            make_prop("ExportNotes", True),
        )
        props = (
            make_prop("FilterName", filter_name),
            make_prop("Overwrite", True),
            make_prop(
                "FilterData",
                uno.Any("[]com.sun.star.beans.PropertyValue", filter_data),
            ),
        )
        doc.storeAsURL(out_url, props)
        return

    filter_name = DOCUMENT_FILTERS.get(ext)
    if not filter_name:
        raise ValueError(f"No hay filter name configurado para {ext}")

    props = (
        make_prop("FilterName", filter_name),
        make_prop("Overwrite", True),
    )
    doc.storeAsURL(out_url, props)


def build_errors_report(metadata, errores, s3_paths):
    return {
        "generado_en": datetime.now(timezone.utc).isoformat(),
        "syllabus_uac_cronograma": metadata.get("syllabus_uac_cronograma"),
        "archivo_original": metadata.get("archivo_original"),
        "tiene_errores": len(errores) > 0,
        "total_errores": len(errores),
        "errores": errores,
        "documento_correccion": s3_paths.get("documento_correccion"),
        "reporte_errores": s3_paths.get("reporte_errores"),
    }


def mark_document(source_path, output_dir, s3_bucket, s3_source_key, metadata):
    keys = derive_correction_keys(s3_source_key)
    source = Path(source_path)
    ext = source.suffix.lower()

    if ext not in DOCUMENT_FILTERS and ext != ".pdf":
        raise ValueError(f"Extension no soportada: {ext}")

    os.makedirs(output_dir, exist_ok=True)
    correction_path = Path(output_dir) / keys["correction_basename"]

    if correction_path.exists():
        correction_path.unlink()

    shutil.copy2(source_path, correction_path)

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
        doc = load_document_editable(ctx, str(correction_path), ext)
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
        marcaciones = 0
        doc_type = resolve_document_type(doc, ext)
        lo_family = detect_lo_document_family(doc)

        if tiene_errores:
            if doc_type == "unknown":
                raise ValueError("Tipo de documento no soportado para marcado")

            marcaciones = annotate_document(doc, doc_type, errores)
            store_document(doc, correction_path)

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
        )

        s3_paths["reporte_errores"] = upload_json_to_s3(
            report,
            s3_bucket,
            keys["json_key"],
        )

        result = {
            "ok": True,
            "archivo_original": keys["original_basename"],
            "archivo_correccion": keys["correction_basename"] if tiene_errores else None,
            "tipo_documento": doc_type,
            "lo_family": lo_family,
            "tiene_errores": tiene_errores,
            "total_errores": len(errores),
            "total_marcaciones": marcaciones,
            "errores": errores,
        }

        if tiene_errores:
            result["documento_correccion"] = s3_paths["documento_correccion"]

        result["reporte_errores"] = s3_paths["reporte_errores"]

        return result

    finally:
        if doc is not None:
            try:
                doc.close(True)
            except Exception:
                try:
                    doc.dispose()
                except Exception:
                    pass

        if not tiene_errores:
            try:
                if correction_path.exists():
                    correction_path.unlink()
            except Exception:
                pass
