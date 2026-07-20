import os
import shutil
from datetime import datetime, timezone
from pathlib import Path

import uno

from spellcheck_core import (
    connect,
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
    ".pdf": "writer_pdf_Export",
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


def detect_document_type(doc):
    if doc.supportsService("com.sun.star.sheet.SpreadsheetDocument"):
        return "calc"
    if doc.supportsService("com.sun.star.presentation.PresentationDocument"):
        return "impress"
    if doc.supportsService("com.sun.star.text.TextDocument"):
        return "writer"
    return "unknown"


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


def annotate_searchable(searchable, doc, errors):
    if not errors:
        return 0

    marked = 0

    for error in errors:
        word = error["palabra"]
        comment_text = format_comment_text(error)

        search = searchable.createSearchDescriptor()
        search.SearchString = word
        search.SearchCaseSensitive = True
        search.SearchWords = True

        found = searchable.findFirst(search)
        while found:
            try:
                highlight_range(found)
                annotation = create_annotation(doc, comment_text)
                found.getText().insertTextContent(found, annotation, False)
                marked += 1
            except Exception:
                try:
                    highlight_range(found)
                    marked += 1
                except Exception:
                    pass

            try:
                end = found.getEnd()
                found = searchable.findNext(end, search)
            except Exception:
                break

    return marked


def annotate_writer(doc, errors):
    return annotate_searchable(doc, doc, errors)


def annotate_calc(doc, errors):
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

        annotations = None
        try:
            annotations = sheet.getAnnotations()
        except Exception:
            annotations = None

        for row in range(addr.StartRow, addr.EndRow + 1):
            for col in range(addr.StartColumn, addr.EndColumn + 1):
                cell = sheet.getCellByPosition(col, row)
                value = cell.getString()
                if not value:
                    continue

                cell_errors = []
                for word_lower, error in errors_by_word.items():
                    if _word_in_text(word_lower, value, error["palabra"]):
                        cell_errors.append(error)

                if not cell_errors:
                    continue

                try:
                    cell.CellBackColor = HIGHLIGHT_COLOR
                except Exception:
                    pass

                comment_parts = [format_comment_text(e) for e in cell_errors]
                comment_text = "\n".join(comment_parts)

                if annotations is not None:
                    try:
                        cell_addr = cell.getCellAddress()
                        annotations.insertNew(cell_addr, COMMENT_AUTHOR, comment_text)
                        marked += len(cell_errors)
                        continue
                    except Exception:
                        pass

                try:
                    annotation = cell.Annotation
                    if annotation:
                        annotation.String = comment_text
                        annotation.Author = COMMENT_AUTHOR
                        marked += len(cell_errors)
                except Exception:
                    marked += len(cell_errors)

    return marked


def _word_in_text(word_lower, text, original_word):
    import re
    pattern = r"\b" + re.escape(original_word) + r"\b"
    return re.search(pattern, text, re.IGNORECASE) is not None


def annotate_impress(doc, errors):
    if not errors:
        return 0

    marked = 0
    pages = doc.getDrawPages()

    for p in range(pages.getCount()):
        page = pages.getByIndex(p)
        for s in range(page.getCount()):
            shape = page.getByIndex(s)

            searchable = None
            try:
                if hasattr(shape, "createSearchDescriptor"):
                    searchable = shape
            except Exception:
                searchable = None

            if searchable is not None:
                marked += annotate_searchable(searchable, doc, errors)
                continue

            try:
                text = shape.getText()
                if text and hasattr(text, "createSearchDescriptor"):
                    marked += annotate_searchable(text, doc, errors)
            except Exception:
                pass

    return marked


def annotate_document(doc, doc_type, errors):
    if doc_type == "writer":
        return annotate_writer(doc, errors)
    if doc_type == "calc":
        return annotate_calc(doc, errors)
    if doc_type == "impress":
        return annotate_impress(doc, errors)
    return 0


def store_document(doc, output_path):
    ext = output_path.suffix.lower()
    filter_name = DOCUMENT_FILTERS.get(ext)
    if not filter_name:
        raise ValueError(f"No hay filter name configurado para {ext}")

    out_url = uno.systemPathToFileUrl(str(output_path))
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

    if ext not in DOCUMENT_FILTERS:
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
        doc = load_document_editable(ctx, str(correction_path))
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

        if tiene_errores:
            doc_type = detect_document_type(doc)
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
