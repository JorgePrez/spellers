import os
import re
from pathlib import Path

import uno

from spellcheck_core import connect, make_locale, check_word

WORD_RE = re.compile(r"[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+(?:'[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+)?")

WORD_FILTERS = {
    ".doc": "MS Word 97",
    ".docx": "Office Open XML Text",
}

DEFAULT_BUCKET = os.environ.get("SPELLCHECK_OUTPUT_BUCKET", "pruebas-cronograma")
DEFAULT_PREFIX = os.environ.get("SPELLCHECK_OUTPUT_PREFIX", "spellcheck-corrected")


def make_prop(name, value):
    prop = uno.createUnoStruct("com.sun.star.beans.PropertyValue")
    prop.Name = name
    prop.Value = value
    return prop


def open_word_document(path):
    ctx = connect()
    desktop = ctx.ServiceManager.createInstanceWithContext(
        "com.sun.star.frame.Desktop",
        ctx
    )

    abs_path = os.path.abspath(path)
    file_url = uno.systemPathToFileUrl(abs_path)

    props = (
        make_prop("Hidden", True),
    )

    doc = desktop.loadComponentFromURL(file_url, "_blank", 0, props)
    return ctx, doc


def output_path_for(source_path, output_dir):
    source = Path(source_path)
    return Path(output_dir) / f"{source.stem}_corregido{source.suffix}"


def match_case(reference, suggestion):
    if not suggestion:
        return suggestion

    if reference.isupper():
        return suggestion.upper()

    if reference[:1].isupper() and reference[1:].islower():
        return suggestion[:1].upper() + suggestion[1:]

    return suggestion


def collect_corrections(doc):
    ctx = connect()
    smgr = ctx.ServiceManager

    spell = smgr.createInstanceWithContext(
        "com.sun.star.linguistic2.SpellChecker",
        ctx
    )

    locale_es = make_locale("es", "GT")

    try:
        text = doc.getText().getString()
    except Exception:
        text = ""

    if not text.strip():
        return []

    seen = set()
    corrections = []

    for word in WORD_RE.findall(text):
        if word in seen:
            continue
        seen.add(word)

        result = check_word(word, spell, locale_es)
        if not result:
            continue

        suggestions = result.get("sugerencias") or []
        if not suggestions:
            continue

        suggestion = match_case(word, suggestions[0])

        if suggestion and suggestion != word:
            corrections.append({
                "original": word,
                "replacement": suggestion,
            })

    return corrections


def apply_corrections(doc, corrections):
    if not corrections:
        return

    for item in sorted(corrections, key=lambda x: len(x["original"]), reverse=True):
        original = item["original"]
        replacement = item["replacement"]

        search = doc.createReplaceDescriptor()
        search.SearchString = original
        search.ReplaceString = replacement
        search.SearchWords = True
        search.SearchCaseSensitive = True
        search.SearchRegularExpression = False

        doc.replaceAll(search)


def store_word_document(doc, output_path):
    ext = output_path.suffix.lower()
    filter_name = WORD_FILTERS.get(ext)
    if not filter_name:
        raise ValueError(f"No hay filter name configurado para {ext}")

    out_url = uno.systemPathToFileUrl(str(output_path))

    props = (
        make_prop("FilterName", filter_name),
        make_prop("Overwrite", True),
    )

    doc.storeAsURL(out_url, props)


def upload_to_s3(local_path):
    import boto3

    bucket = DEFAULT_BUCKET
    key = f"{DEFAULT_PREFIX}/{local_path.name}"

    s3 = boto3.client("s3")
    s3.upload_file(str(local_path), bucket, key)

    return {
        "s3_bucket": bucket,
        "s3_key": key,
        "s3_uri": f"s3://{bucket}/{key}",
    }


def correct_word_document(source_path, output_dir):
    source_ext = Path(source_path).suffix.lower()
    if source_ext not in (".doc", ".docx"):
        raise ValueError("Solo se soportan archivos Word .doc y .docx en este endpoint")

    if not os.path.isdir(output_dir):
        os.makedirs(output_dir, exist_ok=True)

    ctx, doc = open_word_document(source_path)
    output_path = output_path_for(source_path, output_dir)

    if output_path.exists():
        output_path.unlink()

    try:
        if not doc.supportsService("com.sun.star.text.TextDocument"):
            raise ValueError("El archivo no se abrió como documento Writer")

        corrections = collect_corrections(doc)

        apply_corrections(doc, corrections)
        store_word_document(doc, output_path)

        s3_info = upload_to_s3(output_path)

        return {
            "ok": True,
            "archivo_original": os.path.basename(source_path),
            "archivo_corregido": output_path.name,
            "local_path": str(output_path),
            "total_correcciones_aplicadas": len(corrections),
            "correcciones_aplicadas": corrections,
            **s3_info,
        }

    finally:
        try:
            doc.close(True)
        except Exception:
            try:
                doc.dispose()
            except Exception:
                pass
