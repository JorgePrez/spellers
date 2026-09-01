# -*- coding: utf-8 -*-
"""
Comprobar si un cronograma tiene texto extraible.

Sin SpellChecker, sin LLM, sin marcar ni S3.
Compatible con MiU: si no hay texto -> mensaje "archivo sin contenido".
"""
from __future__ import annotations

import time
from pathlib import Path

from spellcheck_fast_detect import extract_text_native

_NATIVE_EXTS = {".xlsx", ".docx", ".pptx", ".pdf"}
_LEGACY_EXTS = {".xls", ".doc", ".ppt"}


def _extract_text_libreoffice(path: Path) -> tuple[str, str]:
    """Fallback para binarios viejos: abrir en LibreOffice solo para leer texto."""
    from spellcheck_core import connect, extract_text, load_document_editable
    from spellcheck_mark import _close_document

    ctx = connect()
    doc = None
    try:
        doc = load_document_editable(ctx, str(path), path.suffix.lower())
        text = extract_text(doc) or ""
        return text, "libreoffice_extract"
    finally:
        _close_document(doc, save=False)


def check_has_content(source_path: str | Path) -> dict:
    """
    Returns dict con ok, tiene_contenido, mensaje (si vacio), extraccion, ms_*.
    """
    t0 = time.perf_counter()
    source = Path(source_path)
    ext = source.suffix.lower()

    if ext in _NATIVE_EXTS:
        text, metodo = extract_text_native(source)
    elif ext in _LEGACY_EXTS:
        text, metodo = _extract_text_libreoffice(source)
    else:
        raise ValueError(f"Extension no soportada: {ext}")

    text_stripped = (text or "").strip()
    tiene_contenido = bool(text_stripped)
    ms_total = int((time.perf_counter() - t0) * 1000)

    result = {
        "ok": True,
        "archivo_original": source.name,
        "tiene_contenido": tiene_contenido,
        "sin_contenido": not tiene_contenido,
        "text_chars": len(text_stripped),
        "extraccion": metodo,
        "ms_total": ms_total,
    }

    if not tiene_contenido:
        # Misma senal que mark / mark-llm (MiU mira este mensaje).
        result["mensaje"] = "archivo sin contenido"

    return result
