# -*- coding: utf-8 -*-
"""Segunda capa: filtrar candidatos Hunspell/LO con Claude Haiku (solo ortografia).

Politica:
- Solo candidatos que LibreOffice/Hunspell marco.
- Preferir el lote MAS GRANDE posible (pocas llamadas = rapidez).
- Si el lote responde completo → listo.
- Si trunca / falla / omite → conservar parciales y partir el resto a la mitad.
- Si el LLM falla de forma irrecuperable: fallback LibreOffice.
"""
from __future__ import annotations

import logging
import time
from typing import Any

from llm_bedrock import llm_generate_json_cached

log = logging.getLogger(__name__)

SYSTEM_PROMPT = """Eres un validador lexico para documentos academicos (syllabus, Guatemala).

Recibiras palabras que LibreOffice/Hunspell ya marco como posibles errores.
Tu UNICA tarea: decidir si cada palabra es un ERROR REAL o una palabra VALIDA que debe pasarse.

NO sugieras correcciones. NO reescribas la palabra. NO propongas alternativas.
(Las sugerencias al usuario las da LibreOffice por separado; tu solo filtras si/no.)

Marca es_error_ortografico=true SOLO si es un typo claro (letra de mas/menos, tilde mal o ausente cuando corresponde, forma claramente mal escrita).

Marca es_error_ortografico=false si la palabra es valida en CUALQUIERA de estos casos:
- espanol correcto (preferido), incluido termino tecnico/academico raro pero real;
- ingles correcto, incluidas palabras gramaticales (the, and, with, of, for, many, inside)
  y terminos tecnicos/academicos (applications, oriented, congestion, cryptography,
  policing, multiple, counter, network, software, etc.);
- anglicismo / prestamo de uso academico o profesional;
- latinismo juridico u otro termino de dominio valido;
- nombre propio, apellido, sigla o acronimo.

Ante duda razonable: es_error_ortografico=false (dejar pasar).
Si parece ingles de campus o titulo de curso en ingles: false.
motivo: max 2 palabras, SIN comillas ni caracteres especiales; solo para fijar el si/no.
Responde UNA entrada por cada palabra recibida, mismo orden. Solo JSON del schema.
"""

# Solo mark-llm-hs: misma decision + sugerencias cortas si es error (sin Hunspell suggest).
SYSTEM_PROMPT_WITH_SUGGESTIONS = """Eres un corrector ortografico para documentos academicos (syllabus, Guatemala).

Recibiras palabras marcadas como posibles errores. Por cada una:
1) Decide si es ERROR REAL (es_error_ortografico=true) o VALIDA (false).
2) Si es error: da hasta 3 formas correctas en sugerencias (palabras sueltas).
3) Si no es error: sugerencias debe ser lista vacia [].

true SOLO si typo claro (letra de mas/menos, tilde mal/ausente, forma mal escrita).
false si: espanol/ingles correcto, termino tecnico, anglicismo, latinismo, nombre propio, sigla.
Ante duda: false y sugerencias=[].

motivo: max 2 palabras, sin comillas.
sugerencias: max 3 strings cortos (la forma correcta preferida primero). NO frases.
Una entrada por palabra, mismo orden. Solo JSON del schema.
"""

ORTHO_SCHEMA: dict[str, Any] = {
    "type": "object",
    "properties": {
        "items": {
            "type": "array",
            "items": {
                "type": "object",
                "properties": {
                    "palabra": {
                        "type": "string",
                        "description": "Palabra candidata tal como se recibio",
                    },
                    "es_error_ortografico": {
                        "type": "boolean",
                        "description": "true solo si es typo real; false si valida",
                    },
                    "motivo": {
                        "type": "string",
                        "description": "max 2 palabras, sin comillas",
                    },
                },
                "required": ["palabra", "es_error_ortografico", "motivo"],
                "additionalProperties": False,
            },
        }
    },
    "required": ["items"],
    "additionalProperties": False,
}

ORTHO_SCHEMA_WITH_SUGGESTIONS: dict[str, Any] = {
    "type": "object",
    "properties": {
        "items": {
            "type": "array",
            "items": {
                "type": "object",
                "properties": {
                    "palabra": {
                        "type": "string",
                        "description": "Palabra candidata tal como se recibio",
                    },
                    "es_error_ortografico": {
                        "type": "boolean",
                        "description": "true solo si es typo real; false si valida",
                    },
                    "motivo": {
                        "type": "string",
                        "description": "max 2 palabras, sin comillas",
                    },
                    "sugerencias": {
                        "type": "array",
                        "items": {"type": "string"},
                        "description": "si error: 1-3 formas correctas; si no: []",
                    },
                },
                "required": [
                    "palabra",
                    "es_error_ortografico",
                    "motivo",
                    "sugerencias",
                ],
                "additionalProperties": False,
            },
        }
    },
    "required": ["items"],
    "additionalProperties": False,
}

# Un solo disparo con TODOS los candidatos; si falla → mitades (y asi sucesivamente).
_SINGLE_ATTEMPTS = 4
_OUTPUT_TOKEN_CAP = 8192


def _user_prompt(words: list[str], *, include_suggestions: bool = False) -> str:
    lines = "\n".join(f"{i}. {w}" for i, w in enumerate(words, 1))
    if include_suggestions:
        return (
            "Por cada palabra: es_error_ortografico true/false, motivo (max 2 palabras), "
            "y sugerencias (1-3 formas correctas si true; [] si false). "
            "UNA entrada por palabra, mismo orden.\n\n"
            f"n={len(words)}\n{lines}"
        )
    return (
        "Decide es_error_ortografico true/false por cada palabra. "
        "motivo: max 2 palabras, sin comillas. "
        "UNA entrada por palabra, mismo orden.\n\n"
        f"n={len(words)}\n{lines}"
    )


def _motivo_corto(raw: Any) -> str:
    text = " ".join(str(raw or "").split())
    if not text:
        return ""
    parts = text.split(" ")
    if len(parts) > 2:
        text = " ".join(parts[:2])
    return text[:24]


def _parse_sugerencias(raw: Any, *, is_error: bool) -> list[str]:
    if not is_error:
        return []
    out: list[str] = []
    seen: set[str] = set()
    items: list[Any]
    if isinstance(raw, list):
        items = raw
    elif isinstance(raw, str):
        items = [p for p in raw.replace(";", ",").split(",")]
    else:
        return []
    for item in items:
        s = " ".join(str(item or "").split()).strip(" .,;:\"'")
        if not s or len(s) > 40:
            continue
        key = s.lower()
        if key in seen:
            continue
        seen.add(key)
        out.append(s)
        if len(out) >= 3:
            break
    return out


def _tokens_for_batch(n: int, base_max: int, *, include_suggestions: bool = False) -> int:
    per = 80 if include_suggestions else 55
    need = 500 + per * max(n, 1)
    return max(1024, min(int(base_max), _OUTPUT_TOKEN_CAP, need))


def _parse_items_partial(
    items: Any, *, include_suggestions: bool = False
) -> dict[str, dict[str, Any]]:
    if not isinstance(items, list):
        raise RuntimeError("Schema invalido: items no es lista")

    decisions: dict[str, dict[str, Any]] = {}
    for item in items:
        if not isinstance(item, dict):
            continue
        w = str(item.get("palabra") or "").strip()
        if not w:
            continue
        is_err = bool(item.get("es_error_ortografico"))
        row: dict[str, Any] = {
            "es_error_ortografico": is_err,
            "motivo": _motivo_corto(item.get("motivo")),
        }
        if include_suggestions:
            row["sugerencias"] = _parse_sugerencias(
                item.get("sugerencias"), is_error=is_err
            )
        decisions[w.lower()] = row
    return decisions


def _call_llm_batch(
    batch: list[str],
    *,
    tokens: int,
    temperature: float,
    include_suggestions: bool = False,
) -> dict[str, dict[str, Any]]:
    system = (
        SYSTEM_PROMPT_WITH_SUGGESTIONS if include_suggestions else SYSTEM_PROMPT
    )
    schema = (
        ORTHO_SCHEMA_WITH_SUGGESTIONS if include_suggestions else ORTHO_SCHEMA
    )
    result = llm_generate_json_cached(
        system,
        _user_prompt(batch, include_suggestions=include_suggestions),
        schema,
        max_tokens=tokens,
        temperature=temperature,
        allow_truncated_repair=True,
    )
    return _parse_items_partial(
        result.get("items"), include_suggestions=include_suggestions
    )


def _merge_partial(
    pending: list[str],
    done: dict[str, dict[str, Any]],
    part: dict[str, dict[str, Any]],
) -> list[str]:
    pending_keys = {w.lower() for w in pending}
    for key, val in part.items():
        if key in pending_keys and key not in done:
            done[key] = val
    return [w for w in pending if w.lower() not in done]


def _decide_batch(
    batch: list[str],
    *,
    max_tokens: int,
    temperature: float,
    stats: dict[str, int] | None = None,
    include_suggestions: bool = False,
) -> dict[str, dict[str, Any]]:
    """
    1) Una llamada con el grupo completo (lo mas grande posible).
    2) Si parcial → un reintento solo con faltantes (sigue siendo grande).
    3) Si sigue fallando → partir a la mitad y repetir.
    """
    if not batch:
        return {}

    pending = list(batch)
    done: dict[str, dict[str, Any]] = {}
    last_err: Exception | None = None

    def _one_shot(words: list[str], tokens: int) -> list[str]:
        nonlocal last_err
        if stats is not None:
            stats["llamadas"] = stats.get("llamadas", 0) + 1
        try:
            part = _call_llm_batch(
                words,
                tokens=tokens,
                temperature=temperature,
                include_suggestions=include_suggestions,
            )
            left = _merge_partial(words, done, part)
            if left:
                last_err = RuntimeError(
                    f"parcial: ok={len(words) - len(left)} faltan={len(left)}"
                )
                log.warning(
                    "LLM ortho parcial n=%s faltan=%s",
                    len(words),
                    len(left),
                )
            return left
        except Exception as exc:
            last_err = exc
            log.warning(
                "LLM ortho fallo n=%s tokens=%s: %s",
                len(words),
                tokens,
                exc,
            )
            return words

    if len(pending) == 1:
        tokens = _tokens_for_batch(
            1, max_tokens, include_suggestions=include_suggestions
        )
        for attempt in range(1, _SINGLE_ATTEMPTS + 1):
            pending = _one_shot(pending, tokens)
            if not pending:
                return done
            tokens = min(_OUTPUT_TOKEN_CAP, int(tokens * 1.4) + 100)
            time.sleep(0.12 * attempt)
        raise RuntimeError(
            f"LLM irrecuperable para palabra={batch[0]!r}: {last_err}"
        )

    # Disparo grande
    tokens = _tokens_for_batch(
        len(pending), max_tokens, include_suggestions=include_suggestions
    )
    pending = _one_shot(pending, tokens)
    if not pending:
        if stats is not None:
            stats["exitos_completos"] = stats.get("exitos_completos", 0) + 1
        return done

    # Un reintento solo de faltantes (todavia el grupo mas grande posible)
    if len(pending) < len(batch):
        tokens = min(
            _OUTPUT_TOKEN_CAP,
            _tokens_for_batch(
                len(pending), max_tokens, include_suggestions=include_suggestions
            )
            + 400,
        )
        pending = _one_shot(pending, tokens)
        if not pending:
            return done

    # Fallo o sigue incompleto → mitades (grupos mas pequenos)
    mid = max(1, len(pending) // 2)
    log.info(
        "LLM ortho: split n=%s -> %s+%s (%s)",
        len(pending),
        mid,
        len(pending) - mid,
        last_err,
    )
    if stats is not None:
        stats["splits"] = stats.get("splits", 0) + 1
    left = _decide_batch(
        pending[:mid],
        max_tokens=max_tokens,
        temperature=temperature,
        stats=stats,
        include_suggestions=include_suggestions,
    )
    right = _decide_batch(
        pending[mid:],
        max_tokens=max_tokens,
        temperature=temperature,
        stats=stats,
        include_suggestions=include_suggestions,
    )
    done.update(left)
    done.update(right)
    return done


def filter_spelling_errors_with_llm(
    errores: list[dict[str, Any]],
    *,
    max_tokens: int = 8192,
    temperature: float = 0.0,
    include_suggestions: bool = False,
) -> tuple[list[dict[str, Any]], dict[str, Any]]:
    """
    Filtra candidatos LO. Solo conserva es_error_ortografico=true.

    include_suggestions=True (mark-llm-hs): en la misma llamada pide
    hasta 3 formas correctas por error confirmado.

    Velocidad: UNA llamada con todas las palabras candidatas.
    Si falla/trunca → reintento de faltantes, luego mitades.
    Fallback LibreOffice solo si el LLM es irrecuperable.
    """
    meta: dict[str, Any] = {
        "llm_aplicado": False,
        "candidatos_lo": len(errores or []),
        "confirmados": 0,
        "descartados": 0,
        "descartes": [],
        "error": None,
        "fallback": None,
        "lotes": 1,
        "llamadas_llm": 0,
        "splits": 0,
        "con_sugerencias_llm": bool(include_suggestions),
    }

    if not errores:
        meta["llm_aplicado"] = True
        meta["lotes"] = 0
        return [], meta

    words = [e.get("palabra", "") for e in errores if e.get("palabra")]
    by_lower = {e["palabra"].lower(): e for e in errores if e.get("palabra")}
    stats: dict[str, int] = {"llamadas": 0, "splits": 0, "exitos_completos": 0}

    try:
        decisions = _decide_batch(
            words,
            max_tokens=max_tokens,
            temperature=temperature,
            stats=stats,
            include_suggestions=include_suggestions,
        )

        still = [w for w in words if w.lower() not in decisions]
        if still:
            raise RuntimeError(
                f"Sin decision LLM para {len(still)} palabras: {still[:8]}"
            )

        meta["llm_aplicado"] = True
        meta["llamadas_llm"] = stats.get("llamadas", 0)
        meta["splits"] = stats.get("splits", 0)
        meta["lotes"] = max(1, stats.get("llamadas", 1))
    except Exception as exc:
        log.exception(
            "LLM ortho filter fallo total; fallback LibreOffice (candidatos sin filtrar)"
        )
        meta["error"] = str(exc)
        meta["llm_aplicado"] = False
        meta["fallback"] = "libreoffice"
        meta["llamadas_llm"] = stats.get("llamadas", 0)
        meta["splits"] = stats.get("splits", 0)
        meta["confirmados"] = len(errores)
        meta["descartados"] = 0
        meta["descartes"] = []
        return list(errores), meta

    kept: list[dict[str, Any]] = []
    discarded: list[dict[str, Any]] = []

    for w in words:
        key = w.lower()
        decision = decisions.get(key)
        err = by_lower.get(key)
        if not err or decision is None:
            continue
        if decision["es_error_ortografico"]:
            enriched = dict(err)
            enriched["llm_motivo"] = decision.get("motivo") or ""
            if include_suggestions:
                enriched["sugerencias"] = list(decision.get("sugerencias") or [])
            kept.append(enriched)
        else:
            discarded.append(
                {
                    "palabra": err.get("palabra"),
                    "motivo": decision.get("motivo") or "",
                }
            )

    meta["confirmados"] = len(kept)
    meta["descartados"] = len(discarded)
    meta["descartes"] = discarded[:100]
    return kept, meta
