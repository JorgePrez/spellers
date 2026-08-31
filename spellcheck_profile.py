# -*- coding: utf-8 -*-
"""
Perfilado del flujo completo mark-llm (LO + LLM + marcar + S3).
Devuelve ms por fase. No reemplaza mark-llm; solo diagnostico.
"""
from __future__ import annotations

import shutil
import time
from pathlib import Path

from spellcheck_core import (
    connect,
    detect_lo_document_family,
    extract_text,
    find_unique_errors,
    load_document_editable,
    make_locale,
)
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


def mark_document_profiled(
    source_path,
    output_dir,
    s3_bucket,
    s3_source_key,
    metadata,
    *,
    llm_second_layer: bool = True,
    also_time_native_extract: bool = True,
):
    """
    Igual que mark-llm con annotate=True, pero mide cada fase.
    No aplica trampas demo (quiere tiempos reales).
    """
    t_all = time.perf_counter()
    timings: dict[str, int] = {}
    profile_meta: dict = {}

    keys = derive_correction_keys(s3_source_key)
    source = Path(source_path)
    ext = source.suffix.lower()

    if ext not in DOCUMENT_FILTERS and ext != ".pdf":
        raise ValueError(f"Extension no soportada: {ext}")

    # --- Extra: extraccion nativa (no forma parte del pipeline LO) ---
    if also_time_native_extract and ext in {".xlsx", ".docx", ".pptx", ".pdf"}:
        t0 = time.perf_counter()
        try:
            from spellcheck_fast_detect import extract_text_native

            _txt_native, metodo = extract_text_native(source)
            timings["ms_extract_native"] = _ms(t0)
            profile_meta["extract_native_metodo"] = metodo
            profile_meta["extract_native_chars"] = len(_txt_native or "")
        except Exception as e:
            timings["ms_extract_native"] = _ms(t0)
            profile_meta["extract_native_error"] = str(e)

    os.makedirs(output_dir, exist_ok=True)
    correction_path = Path(output_dir) / keys["correction_basename"]
    work_path = Path(output_dir) / f"work_profile_{keys['correction_basename']}"
    lo_export_path = Path(output_dir) / f"lo_profile_{keys['correction_basename']}"

    for path in (correction_path, work_path, lo_export_path):
        if path.exists():
            path.unlink()

    t0 = time.perf_counter()
    shutil.copy2(source_path, work_path)
    timings["ms_copy_work"] = _ms(t0)

    t0 = time.perf_counter()
    ctx = connect()
    smgr = ctx.ServiceManager
    spell = smgr.createInstanceWithContext(
        "com.sun.star.linguistic2.SpellChecker",
        ctx,
    )
    locale_es = make_locale("es", "GT")
    timings["ms_connect_spellchecker"] = _ms(t0)

    doc = None
    tiene_errores = False
    llm_meta = None
    errores: list = []
    candidatos_lo = 0
    s3_paths: dict = {}
    marcacion_detalle = _empty_stats()
    doc_type = "unknown"
    lo_family = None
    pdf_filter = None
    text_chars = 0

    try:
        t0 = time.perf_counter()
        doc = load_document_editable(ctx, str(work_path), ext)
        timings["ms_load_document"] = _ms(t0)

        t0 = time.perf_counter()
        text = extract_text(doc)
        timings["ms_extract_text_lo"] = _ms(t0)
        text_chars = len(text or "")

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
                "perfilado": True,
                "timings_ms": timings,
                "profile_meta": profile_meta,
                "text_chars": text_chars,
            }

        t0 = time.perf_counter()
        errores = find_unique_errors(text, spell, locale_es)
        timings["ms_spell"] = _ms(t0)
        candidatos_lo = len(errores)

        if llm_second_layer and errores:
            t0 = time.perf_counter()
            from llm_ortho_filter import filter_spelling_errors_with_llm

            errores, llm_meta = filter_spelling_errors_with_llm(errores)
            timings["ms_llm"] = _ms(t0)
        else:
            timings["ms_llm"] = 0

        tiene_errores = len(errores) > 0
        doc_type = resolve_document_type(doc, ext)
        lo_family = detect_lo_document_family(doc)

        if tiene_errores:
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
        else:
            timings["ms_annotate"] = 0
            timings["ms_store"] = 0
            timings["ms_upload_rev_s3"] = 0

        report = build_errors_report(
            {
                "syllabus_uac_cronograma": metadata.get("syllabus_uac_cronograma"),
                "archivo_original": keys["original_basename"],
            },
            errores,
            s3_paths,
            marcacion_detalle=marcacion_detalle if tiene_errores else None,
        )
        if llm_second_layer:
            report["capa_llm"] = True
            report["candidatos_libreoffice"] = candidatos_lo
            if llm_meta:
                report["llm"] = llm_meta
        report["perfilado"] = True
        report["timings_ms"] = timings

        t0 = time.perf_counter()
        s3_paths["reporte_errores"] = upload_json_to_s3(
            report,
            s3_bucket,
            keys["json_key"],
        )
        timings["ms_upload_json_s3"] = _ms(t0)

        timings["ms_total"] = _ms(t_all)

        result = {
            "ok": True,
            "archivo_original": keys["original_basename"],
            "archivo_rev": keys["correction_basename"] if tiene_errores else None,
            "tipo_documento": doc_type,
            "lo_family": lo_family,
            "tiene_errores": tiene_errores,
            "total_errores": len(errores),
            "marcacion_detalle": marcacion_detalle if tiene_errores else None,
            "errores": _errors_for_response(errores),
            "capa_llm": bool(llm_second_layer),
            "candidatos_libreoffice": candidatos_lo,
            "perfilado": True,
            "text_chars": text_chars,
            "timings_ms": timings,
            "profile_meta": profile_meta,
        }

        if llm_second_layer and llm_meta is not None:
            result["llm"] = {
                "aplicado": llm_meta.get("llm_aplicado"),
                "confirmados": llm_meta.get("confirmados"),
                "descartados": llm_meta.get("descartados"),
                "descartes": llm_meta.get("descartes"),
                "fallback": llm_meta.get("fallback"),
                "error": llm_meta.get("error"),
            }

        if tiene_errores and ext == ".pdf" and pdf_filter:
            result["pdf_export_filter"] = pdf_filter

        if tiene_errores and "documento_rev" in s3_paths:
            result["documento_rev"] = s3_paths["documento_rev"]["s3_uri"]

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
