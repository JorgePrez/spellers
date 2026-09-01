# -*- coding: utf-8 -*-
"""
mark-llm-hs: Hunspell nativo (spylls) + LLM; LibreOffice solo para marcar.

Sin sugerencias en deteccion.
"""
from __future__ import annotations

import os
import shutil
import time
from pathlib import Path

from spellcheck_core import (
    connect,
    detect_lo_document_family,
    find_unique_errors,
    load_document_editable,
    make_locale,
)
from spellcheck_fast_detect import extract_text_native
from spellcheck_hunspell import HunspellUnoShim, get_hunspell_engine
from spellcheck_mark import (
    DOCUMENT_FILTERS,
    _close_document,
    _empty_stats,
    _errors_for_response,
    _merge_stats,
    _pdf_add_sticky_notes,
    annotate_document,
    build_errors_report,
    resolve_document_type,
    store_document,
)
from spellcheck_s3 import (
    derive_correction_keys,
    upload_file_to_s3,
    upload_json_to_s3,
)


def _ms(t0: float) -> int:
    return int((time.perf_counter() - t0) * 1000)


def mark_document_hs(
    source_path,
    output_dir,
    s3_bucket,
    s3_source_key,
    metadata,
    *,
    llm_second_layer: bool = True,
):
    """
    openpyxl/OOXML → Hunspell (sin suggest) → LLM
    → si hay errores: LO solo anota + sube rev_* / json.
    """
    t_all = time.perf_counter()
    timings: dict[str, int] = {}

    keys = derive_correction_keys(s3_source_key)
    source = Path(source_path)
    ext = source.suffix.lower()

    if ext not in DOCUMENT_FILTERS and ext != ".pdf":
        raise ValueError(f"Extension no soportada: {ext}")

    t0 = time.perf_counter()
    engine = get_hunspell_engine()
    timings["ms_dict_load"] = _ms(t0)

    t0 = time.perf_counter()
    text, metodo = extract_text_native(source)
    timings["ms_extraccion"] = _ms(t0)

    if not (text or "").strip():
        timings["ms_total"] = _ms(t_all)
        return {
            "ok": True,
            "archivo_original": keys["original_basename"],
            "mensaje": "archivo sin contenido",
            "tiene_errores": False,
            "total_errores": 0,
            "errores": [],
            "capa_llm": bool(llm_second_layer),
            "sin_sugerencias": True,
            "spell_backend": "spylls",
            "extraccion": metodo,
            "timings_ms": timings,
        }

    spell = HunspellUnoShim(engine)
    locale_es = make_locale("es", "GT")

    t0 = time.perf_counter()
    errores = find_unique_errors(
        text, spell, locale_es, use_suggestion_filter=False
    )
    timings["ms_spell"] = _ms(t0)
    candidatos = len(errores)

    llm_meta = None
    if llm_second_layer and errores:
        t0 = time.perf_counter()
        from llm_ortho_filter import filter_spelling_errors_with_llm

        errores, llm_meta = filter_spelling_errors_with_llm(errores)
        timings["ms_llm"] = _ms(t0)
    else:
        timings["ms_llm"] = 0

    tiene_errores = len(errores) > 0
    dictionaries = list(engine.labels)
    s3_paths: dict = {}
    marcacion_detalle = _empty_stats()
    doc_type = "unknown"
    lo_family = None
    pdf_filter = None

    def _llm_block():
        if not (llm_second_layer and llm_meta is not None):
            return None
        return {
            "aplicado": llm_meta.get("llm_aplicado"),
            "confirmados": llm_meta.get("confirmados"),
            "descartados": llm_meta.get("descartados"),
            "descartes": llm_meta.get("descartes"),
            "fallback": llm_meta.get("fallback"),
            "error": llm_meta.get("error"),
        }

    if not tiene_errores:
        timings["ms_load_document"] = 0
        timings["ms_annotate"] = 0
        timings["ms_store"] = 0
        timings["ms_upload_rev_s3"] = 0

        report = build_errors_report(
            {
                "syllabus_uac_cronograma": metadata.get("syllabus_uac_cronograma"),
                "archivo_original": keys["original_basename"],
            },
            [],
            s3_paths,
            marcacion_detalle=None,
        )
        report["capa_llm"] = bool(llm_second_layer)
        report["candidatos_hunspell"] = candidatos
        report["spell_backend"] = "spylls"
        report["sin_sugerencias"] = True
        if llm_meta:
            report["llm"] = llm_meta

        t0 = time.perf_counter()
        upload_json_to_s3(report, s3_bucket, keys["json_key"])
        timings["ms_upload_json_s3"] = _ms(t0)
        timings["ms_total"] = _ms(t_all)

        result = {
            "ok": True,
            "archivo_original": keys["original_basename"],
            "archivo_rev": None,
            "tiene_errores": False,
            "total_errores": 0,
            "errores": [],
            "capa_llm": bool(llm_second_layer),
            "candidatos_hunspell": candidatos,
            "sin_sugerencias": True,
            "spell_backend": "spylls",
            "dictionaries": dictionaries,
            "extraccion": metodo,
            "timings_ms": timings,
        }
        block = _llm_block()
        if block:
            result["llm"] = block
        return result

    # --- Marcar solo con LO ---
    os.makedirs(output_dir, exist_ok=True)
    correction_path = Path(output_dir) / keys["correction_basename"]
    work_path = Path(output_dir) / f"work_hs_{keys['correction_basename']}"
    lo_export_path = Path(output_dir) / f"lo_hs_{keys['correction_basename']}"

    for path in (correction_path, work_path, lo_export_path):
        if path.exists():
            path.unlink()

    shutil.copy2(source_path, work_path)

    ctx = connect()
    doc = None

    try:
        t0 = time.perf_counter()
        doc = load_document_editable(ctx, str(work_path), ext)
        timings["ms_load_document"] = _ms(t0)

        doc_type = resolve_document_type(doc, ext)
        lo_family = detect_lo_document_family(doc)
        if doc_type == "unknown":
            raise ValueError("Tipo de documento no soportado para marcado")

        t0 = time.perf_counter()
        lo_stats = annotate_document(doc, doc_type, errores)
        _merge_stats(marcacion_detalle, lo_stats)
        timings["ms_annotate"] = _ms(t0)

        t0 = time.perf_counter()
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
        timings["ms_store"] = _ms(t0)

        t0 = time.perf_counter()
        s3_paths["documento_rev"] = upload_file_to_s3(
            correction_path,
            s3_bucket,
            keys["correction_key"],
        )
        timings["ms_upload_rev_s3"] = _ms(t0)

        report = build_errors_report(
            {
                "syllabus_uac_cronograma": metadata.get("syllabus_uac_cronograma"),
                "archivo_original": keys["original_basename"],
            },
            errores,
            s3_paths,
            marcacion_detalle=marcacion_detalle,
        )
        report["capa_llm"] = bool(llm_second_layer)
        report["candidatos_hunspell"] = candidatos
        report["spell_backend"] = "spylls"
        report["sin_sugerencias"] = True
        if llm_meta:
            report["llm"] = llm_meta

        t0 = time.perf_counter()
        upload_json_to_s3(report, s3_bucket, keys["json_key"])
        timings["ms_upload_json_s3"] = _ms(t0)
        timings["ms_total"] = _ms(t_all)

        result = {
            "ok": True,
            "archivo_original": keys["original_basename"],
            "archivo_rev": keys["correction_basename"],
            "tipo_documento": doc_type,
            "lo_family": lo_family,
            "tiene_errores": True,
            "total_errores": len(errores),
            "marcacion_detalle": marcacion_detalle,
            "errores": _errors_for_response(errores),
            "capa_llm": bool(llm_second_layer),
            "candidatos_hunspell": candidatos,
            "sin_sugerencias": True,
            "spell_backend": "spylls",
            "dictionaries": dictionaries,
            "extraccion": metodo,
            "timings_ms": timings,
        }
        block = _llm_block()
        if block:
            result["llm"] = block
        if ext == ".pdf" and pdf_filter:
            result["pdf_export_filter"] = pdf_filter
        if "documento_rev" in s3_paths:
            result["documento_rev"] = s3_paths["documento_rev"]["s3_uri"]
        return result

    finally:
        _close_document(doc, save=False)
        for temp_path in (work_path, lo_export_path):
            try:
                if temp_path.exists():
                    temp_path.unlink()
            except Exception:
                pass
