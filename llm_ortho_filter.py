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

# Un solo disparo con TODOS los candidatos; si falla → mitades (y asi sucesivamente).
_SINGLE_ATTEMPTS = 4
_OUTPUT_TOKEN_CAP = 8192


def _user_prompt(words: list[str]) -> str:
    # Lista compacta: menos tokens de entrada; motivo 1-2 palabras → salida mas corta
    lines = "\n".join(f"{i}. {w}" for i, w in enumerate(words, 1))
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


def _tokens_for_batch(n: int, base_max: int) -> int:
    # Holgado para lotes grandes (evita max_tokens / JSON truncado)
    need = 500 + 55 * max(n, 1)
    return max(1024, min(int(base_max), _OUTPUT_TOKEN_CAP, need))


def _parse_items_partial(items: Any) -> dict[str, dict[str, Any]]:
    if not isinstance(items, list):
        raise RuntimeError("Schema invalido: items no es lista")

    decisions: dict[str, dict[str, Any]] = {}
    for item in items:
        if not isinstance(item, dict):
            continue
        w = str(item.get("palabra") or "").strip()
        if not w:
            continue
        decisions[w.lower()] = {
            "es_error_ortografico": bool(item.get("es_error_ortografico")),
            "motivo": _motivo_corto(item.get("motivo")),
        }
    return decisions


def _call_llm_batch(
    batch: list[str],
    *,
    tokens: int,
    temperature: float,
) -> dict[str, dict[str, Any]]:
    result = llm_generate_json_cached(
        SYSTEM_PROMPT,
        _user_prompt(batch),
        ORTHO_SCHEMA,
        max_tokens=tokens,
        temperature=temperature,
        allow_truncated_repair=True,
    )
    return _parse_items_partial(result.get("items"))


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
                words, tokens=tokens, temperature=temperature
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
        tokens = _tokens_for_batch(1, max_tokens)
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
    tokens = _tokens_for_batch(len(pending), max_tokens)
    pending = _one_shot(pending, tokens)
    if not pending:
        if stats is not None:
            stats["exitos_completos"] = stats.get("exitos_completos", 0) + 1
        return done

    # Un reintento solo de faltantes (todavia el grupo mas grande posible)
    if len(pending) < len(batch):
        tokens = min(
            _OUTPUT_TOKEN_CAP,
            _tokens_for_batch(len(pending), max_tokens) + 400,
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
    )
    right = _decide_batch(
        pending[mid:],
        max_tokens=max_tokens,
        temperature=temperature,
        stats=stats,
    )
    done.update(left)
    done.update(right)
    return done


def filter_spelling_errors_with_llm(
    errores: list[dict[str, Any]],
    *,
    max_tokens: int = 8192,
    temperature: float = 0.0,
) -> tuple[list[dict[str, Any]], dict[str, Any]]:
    """
    Filtra candidatos LO. Solo conserva es_error_ortografico=true.

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
    }

    if not errores:
        meta["llm_aplicado"] = True
        meta["lotes"] = 0
        return [], meta

    words = [e.get("palabra", "") for e in errores if e.get("palabra")]
    by_lower = {e["palabra"].lower(): e for e in errores if e.get("palabra")}
    stats: dict[str, int] = {"llamadas": 0, "splits": 0, "exitos_completos": 0}

    try:
        # Todo el grupo de una vez (lo mas grande posible)
        decisions = _decide_batch(
            words,
            max_tokens=max_tokens,
            temperature=temperature,
            stats=stats,
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
