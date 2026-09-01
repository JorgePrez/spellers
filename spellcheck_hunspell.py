# -*- coding: utf-8 -*-
"""
Hunspell nativo via spylls (mismos .dic/.aff que LibreOffice).

No usa SpellChecker UNO. Una palabra es valida si CUALQUIER diccionario
cargado la acepta (es_GT + dict-ua-*).
"""
from __future__ import annotations

import logging
import os
import time
from pathlib import Path
from typing import Any

log = logging.getLogger(__name__)

# Prefijo sin extension: .../es_GT  →  es_GT.aff + es_GT.dic
_DEFAULT_BASE = "/opt/libreoffice25.8/share/extensions/dict-es/es_GT"
# None = todas las facultades UA del repo (+ es_GT).
# Subset: ("med", "uni", "ang"). Override env: SPELLCHECK_HUNSPELL_PACKAGES=med,uni,ang
_DEFAULT_UA_CODES: tuple[str, ...] | None = None

_engine: "MultiHunspell | None" = None
_engine_error: str | None = None


def _parse_ua_codes() -> list[str] | None:
    """None = todas las facultades; lista = subset."""
    raw = (os.environ.get("SPELLCHECK_HUNSPELL_PACKAGES") or "").strip().lower()
    if not raw:
        if _DEFAULT_UA_CODES is None:
            return None
        return list(_DEFAULT_UA_CODES)
    if raw in {"all", "*", "todos"}:
        return None
    return [c.strip() for c in raw.split(",") if c.strip()]


def _repo_ua_prefixes(codes: list[str] | None) -> list[str]:
    root = Path(__file__).resolve().parent / "dictionaries"
    if not root.is_dir():
        return []
    out: list[str] = []
    if codes is None:
        for dic in sorted(root.glob("dict-ua-*/ua_*_GT.dic")):
            out.append(str(dic.with_suffix("")))
        return out
    for code in codes:
        dic = root / f"dict-ua-{code}" / f"ua_{code}_GT.dic"
        if dic.is_file():
            out.append(str(dic.with_suffix("")))
        else:
            log.warning("No existe diccionario UA: %s", dic)
    return out


def _discover_prefixes() -> list[str]:
    """
    Orden: base es_GT, luego UA del repo (o SPELLCHECK_HUNSPELL_EXTRA).
    Env:
      SPELLCHECK_HUNSPELL_BASE=/ruta/es_GT
      SPELLCHECK_HUNSPELL_EXTRA=/a/ua_med_GT:/b/ua_uni_GT
      SPELLCHECK_HUNSPELL_PACKAGES=med,uni,ang   (default)
      SPELLCHECK_HUNSPELL_PACKAGES=all           (todas; pesado)
    """
    base = (os.environ.get("SPELLCHECK_HUNSPELL_BASE") or _DEFAULT_BASE).strip()
    prefixes: list[str] = []
    if base:
        prefixes.append(base)

    extra = (os.environ.get("SPELLCHECK_HUNSPELL_EXTRA") or "").strip()
    if extra:
        for part in extra.split(":"):
            part = part.strip()
            if part:
                prefixes.append(part)
    else:
        prefixes.extend(_repo_ua_prefixes(_parse_ua_codes()))

    # Unicos preservando orden
    seen: set[str] = set()
    uniq: list[str] = []
    for p in prefixes:
        key = os.path.normpath(p)
        if key in seen:
            continue
        seen.add(key)
        uniq.append(p)
    return uniq


class MultiHunspell:
    """Varios Dictionary spylls; lookup OR; suggest desde el primero que aporte."""

    def __init__(self, dictionaries: list[Any], labels: list[str]):
        self._dicts = dictionaries
        self.labels = labels

    @classmethod
    def from_prefixes(cls, prefixes: list[str]) -> "MultiHunspell":
        from spylls.hunspell import Dictionary

        dicts: list[Any] = []
        labels: list[str] = []
        errors: list[str] = []
        for prefix in prefixes:
            aff = prefix + ".aff"
            dic = prefix + ".dic"
            if not (os.path.isfile(aff) and os.path.isfile(dic)):
                errors.append(f"faltan {aff} / {dic}")
                continue
            try:
                d = Dictionary.from_files(prefix)
                dicts.append(d)
                labels.append(os.path.basename(prefix))
                log.info("Hunspell cargado: %s", prefix)
            except Exception as exc:
                errors.append(f"{prefix}: {exc}")
                log.exception("No se pudo cargar %s", prefix)

        if not dicts:
            raise RuntimeError(
                "Ningun diccionario Hunspell cargado. " + "; ".join(errors[:5])
            )
        if errors:
            log.warning("Diccionarios omitidos: %s", "; ".join(errors[:8]))
        return cls(dicts, labels)

    def is_valid(self, word: str) -> bool:
        w = (word or "").strip()
        if not w:
            return True
        for d in self._dicts:
            try:
                if d.lookup(w):
                    return True
            except Exception:
                continue
        return False

    def suggest(self, word: str, limit: int = 5) -> list[str]:
        out: list[str] = []
        seen: set[str] = set()
        for d in self._dicts:
            try:
                for s in d.suggest(word):
                    key = s.lower()
                    if key in seen:
                        continue
                    seen.add(key)
                    out.append(s)
                    if len(out) >= limit:
                        return out
            except Exception:
                continue
        return out


class HunspellUnoShim:
    """API compatible con SpellChecker UNO usada por find_unique_errors."""

    def __init__(self, engine: MultiHunspell):
        self._engine = engine

    def isValid(self, word, locale, opts=()):
        return self._engine.is_valid(word)

    def spell(self, word, locale, opts=()):
        alts = self._engine.suggest(word, limit=5)
        if not alts:
            return None
        return _Alternatives(alts)


class _Alternatives:
    def __init__(self, items: list[str]):
        self._items = items

    def getAlternatives(self):
        return tuple(self._items)


def get_hunspell_engine(*, force_reload: bool = False) -> MultiHunspell:
    global _engine, _engine_error
    if _engine is not None and not force_reload:
        return _engine
    if _engine_error and not force_reload:
        raise RuntimeError(_engine_error)

    t0 = time.perf_counter()
    try:
        prefixes = _discover_prefixes()
        _engine = MultiHunspell.from_prefixes(prefixes)
        _engine_error = None
        log.info(
            "MultiHunspell listo en %.1fs (%s dicts: %s)",
            time.perf_counter() - t0,
            len(_engine.labels),
            ", ".join(_engine.labels),
        )
        return _engine
    except Exception as exc:
        _engine = None
        _engine_error = str(exc)
        raise


def hunspell_status() -> dict[str, Any]:
    try:
        eng = get_hunspell_engine()
        return {
            "ok": True,
            "backend": "spylls",
            "dictionaries": list(eng.labels),
            "count": len(eng.labels),
        }
    except Exception as exc:
        return {"ok": False, "backend": "spylls", "error": str(exc)}


def detect_fast_hunspell(
    source_path: str | Path,
    *,
    llm_second_layer: bool = False,
    with_suggestions: bool = True,
) -> dict:
    """
    openpyxl/OOXML + Hunspell nativo (+ LLM opcional).
    Sin abrir Calc/Writer y sin SpellChecker UNO.
    """
    from spellcheck_core import find_unique_errors, make_locale
    from spellcheck_fast_detect import _errors_for_response, extract_text_native

    t0 = time.perf_counter()
    source = Path(source_path)

    t_ext0 = time.perf_counter()
    text, metodo = extract_text_native(source)
    ms_extract = int((time.perf_counter() - t_ext0) * 1000)

    if not (text or "").strip():
        return {
            "ok": True,
            "archivo_original": source.name,
            "mensaje": "archivo sin contenido",
            "tiene_errores": False,
            "total_errores": 0,
            "errores": [],
            "capa_llm": bool(llm_second_layer),
            "solo_detectar": True,
            "spell_backend": "spylls",
            "extraccion": metodo,
            "ms_extraccion": ms_extract,
            "ms_spell": 0,
            "ms_llm": 0,
            "ms_total": int((time.perf_counter() - t0) * 1000),
        }

    t_load0 = time.perf_counter()
    engine = get_hunspell_engine()
    ms_dict_load = int((time.perf_counter() - t_load0) * 1000)

    spell = HunspellUnoShim(engine)
    locale_es = make_locale("es", "GT")

    t_sp0 = time.perf_counter()
    # Sin sugerencias: no usar classify_word (si no, candidatos=0 siempre).
    errores = find_unique_errors(
        text,
        spell,
        locale_es,
        use_suggestion_filter=bool(with_suggestions),
    )
    ms_spell = int((time.perf_counter() - t_sp0) * 1000)
    candidatos = len(errores)

    llm_meta = None
    ms_llm = 0
    if llm_second_layer and errores:
        t_llm0 = time.perf_counter()
        from llm_ortho_filter import filter_spelling_errors_with_llm

        errores, llm_meta = filter_spelling_errors_with_llm(errores)
        ms_llm = int((time.perf_counter() - t_llm0) * 1000)

    result = {
        "ok": True,
        "archivo_original": source.name,
        "archivo_rev": None,
        "tiene_errores": len(errores) > 0,
        "total_errores": len(errores),
        "errores": _errors_for_response(errores),
        "capa_llm": bool(llm_second_layer),
        "candidatos_hunspell": candidatos,
        "solo_detectar": True,
        "spell_backend": "spylls",
        "dictionaries": list(engine.labels),
        "extraccion": metodo,
        "ms_extraccion": ms_extract,
        "ms_dict_load": ms_dict_load,
        "ms_spell": ms_spell,
        "ms_llm": ms_llm,
        "ms_total": int((time.perf_counter() - t0) * 1000),
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

    return result
