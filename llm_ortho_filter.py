# -*- coding: utf-8 -*-
"""Segunda capa: filtrar candidatos Hunspell/LO con Claude Haiku (solo ortografia).

Politica:
- Todas las palabras deben pasar por el LLM (lotes chicos, reintentos, split a 1).
- Si el LLM falla de forma irrecuperable: fallback LibreOffice (marcar todos los candidatos).
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
motivo: 1 a 4 palabras, SIN comillas ni caracteres especiales; solo para fijar el si/no.
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
                        "description": "1-4 palabras, sin comillas",
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

# Lotes chicos + muchos reintentos: maximizar exito LLM antes de fallback LO
_CHUNK_SIZE = 8
_BATCH_RETRIES = 5
_SINGLE_RETRIES = 6
_MIN_SPLIT = 1


def _user_prompt(words: list[str]) -> str:
    lines = "\n".join(f"- {w}" for w in words)
    return (
        "Para cada palabra: es_error_ortografico true/false y motivo corto "
        "(1-4 palabras, sin comillas). Una entrada por palabra, mismo orden.\n\n"
        f"Palabras ({len(words)}):\n{lines}"
    )


def _chunk(items: list[str], size: int) -> list[list[str]]:
    return [items[i : i + size] for i in range(0, len(items), size)]


def _motivo_corto(raw: Any) -> str:
    text = " ".join(str(raw or "").split())
    if not text:
        return ""
    parts = text.split(" ")
    if len(parts) > 4:
        text = " ".join(parts[:4])
    return text[:40]


def _tokens_for_batch(n: int, base_max: int) -> int:
    # Holgado: evita stop_reason=max_tokens (causa Unterminated string)
    need = 350 + 70 * max(n, 1)
    return max(500, min(int(base_max), need))


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


def _decide_batch(
    batch: list[str],
    *,
    max_tokens: int,
    temperature: float,
) -> dict[str, dict[str, Any]]:
    """Decisiones por palabra lower. Reintenta, parte a 1; conserva parciales."""
    if not batch:
        return {}

    pending = list(batch)
    done: dict[str, dict[str, Any]] = {}
    last_err: Exception | None = None
    tokens = _tokens_for_batch(len(pending), max_tokens)
    retries = _SINGLE_RETRIES if len(pending) == 1 else _BATCH_RETRIES

    for attempt in range(1, retries + 1):
        if not pending:
            return done
        try:
            part = _call_llm_batch(
                pending, tokens=tokens, temperature=temperature
            )
            # Solo aceptar claves del lote pendiente (evita ruido)
            pending_keys = {w.lower() for w in pending}
            for key, val in part.items():
                if key in pending_keys:
                    done[key] = val
            pending = [w for w in pending if w.lower() not in done]
            if not pending:
                return done
            last_err = RuntimeError(
                f"LLM omitio {len(pending)} palabras tras intento {attempt}"
            )
            log.warning(
                "LLM ortho parcial (n_faltan=%s attempt=%s/%s): %s",
                len(pending),
                attempt,
                retries,
                [p for p in pending[:5]],
            )
        except Exception as exc:
            last_err = exc
            log.warning(
                "LLM ortho batch fallo (n=%s attempt=%s/%s tokens=%s): %s",
                len(pending),
                attempt,
                retries,
                tokens,
                exc,
            )
        tokens = min(4096, int(tokens * 1.6) + 100)
        time.sleep(0.4 * attempt)

    if pending and len(pending) > _MIN_SPLIT:
        mid = max(1, len(pending) // 2)
        log.warning(
            "LLM ortho: partiendo faltantes n=%s tras fallos (%s)",
            len(pending),
            last_err,
        )
        left = _decide_batch(
            pending[:mid], max_tokens=max_tokens, temperature=temperature
        )
        right = _decide_batch(
            pending[mid:], max_tokens=max_tokens, temperature=temperature
        )
        done.update(left)
        done.update(right)
        return done

    if pending:
        # 1 palabra irrecuperable → propagar para fallback LibreOffice total
        raise RuntimeError(
            f"LLM irrecuperable para palabra={pending[0]!r}: {last_err}"
        )
    return done


def filter_spelling_errors_with_llm(
    errores: list[dict[str, Any]],
    *,
    max_tokens: int = 3500,
    temperature: float = 0.0,
) -> tuple[list[dict[str, Any]], dict[str, Any]]:
    """
    Filtra candidatos LO. Solo conserva es_error_ortografico=true.

    Si el LLM falla de forma irrecuperable: fallback LibreOffice
    (devuelve todos los candidatos sin filtrar).
    """
    meta: dict[str, Any] = {
        "llm_aplicado": False,
        "candidatos_lo": len(errores or []),
        "confirmados": 0,
        "descartados": 0,
        "descartes": [],
        "error": None,
        "fallback": None,
        "lotes": 0,
    }

    if not errores:
        meta["llm_aplicado"] = True
        return [], meta

    words = [e.get("palabra", "") for e in errores if e.get("palabra")]
    by_lower = {e["palabra"].lower(): e for e in errores if e.get("palabra")}

    decisions: dict[str, dict[str, Any]] = {}
    batches = _chunk(words, _CHUNK_SIZE)
    meta["lotes"] = len(batches)

    try:
        for batch in batches:
            part = _decide_batch(
                batch, max_tokens=max_tokens, temperature=temperature
            )
            decisions.update(part)

        # Segunda pasada: cualquier omitido se fuerza 1 a 1 por LLM
        missing = [w for w in words if w.lower() not in decisions]
        if missing:
            log.warning(
                "LLM ortho: reintento 1:1 para %s omitidas", len(missing)
            )
            for w in missing:
                part = _decide_batch(
                    [w], max_tokens=max_tokens, temperature=temperature
                )
                decisions.update(part)

        meta["llm_aplicado"] = True
    except Exception as exc:
        log.exception(
            "LLM ortho filter fallo total; fallback LibreOffice (candidatos sin filtrar)"
        )
        meta["error"] = str(exc)
        meta["llm_aplicado"] = False
        meta["fallback"] = "libreoffice"
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
        if not err:
            continue
        if decision is None:
            # No deberia ocurrir tras 2a pasada; si ocurre, LO para esa palabra
            kept.append(dict(err))
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
