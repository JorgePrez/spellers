# -*- coding: utf-8 -*-
"""Segunda capa: filtrar candidatos Hunspell/LO con Claude Haiku (solo ortografia)."""
from __future__ import annotations

import logging
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
Responde solo con el JSON del schema.
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
                        "description": "true solo si es typo real; false si la palabra es valida (ES o EN u otro uso academico)",
                    },
                },
                "required": ["palabra", "es_error_ortografico"],
                "additionalProperties": False,
            },
        }
    },
    "required": ["items"],
    "additionalProperties": False,
}

# Lotes para no saturar max_tokens / contexto
_CHUNK_SIZE = 40


def _user_prompt(words: list[str]) -> str:
    lines = "\n".join(f"- {w}" for w in words)
    return (
        "Para cada palabra: solo indica si es error ortografico real (true) "
        "o palabra valida a dejar pasar (false). "
        "No propongas correcciones. Mismo orden.\n\n"
        f"Palabras:\n{lines}"
    )


def _chunk(items: list[str], size: int) -> list[list[str]]:
    return [items[i : i + size] for i in range(0, len(items), size)]


def filter_spelling_errors_with_llm(
    errores: list[dict[str, Any]],
    *,
    max_tokens: int = 800,
    temperature: float = 0.0,
) -> tuple[list[dict[str, Any]], dict[str, Any]]:
    """
    Filtra la lista de errores de find_unique_errors.
    Solo conserva los que el LLM marca con es_error_ortografico=true.

    Importante: las sugerencias del error conservado siguen siendo las de
    LibreOffice (no las genera el LLM). El LLM solo aporta el booleano si/no.

    Returns:
        (errores_confirmados, meta)
    """
    meta: dict[str, Any] = {
        "llm_aplicado": False,
        "candidatos_lo": len(errores or []),
        "confirmados": 0,
        "descartados": 0,
        "descartes": [],
        "error": None,
    }

    if not errores:
        meta["llm_aplicado"] = True
        return [], meta

    words = [e.get("palabra", "") for e in errores if e.get("palabra")]
    by_lower = {e["palabra"].lower(): e for e in errores if e.get("palabra")}

    decisions: dict[str, bool] = {}

    try:
        for batch in _chunk(words, _CHUNK_SIZE):
            result = llm_generate_json_cached(
                SYSTEM_PROMPT,
                _user_prompt(batch),
                ORTHO_SCHEMA,
                max_tokens=max(200, min(max_tokens, 80 + 18 * len(batch))),
                temperature=temperature,
            )
            items = result.get("items") or []
            if not isinstance(items, list):
                raise RuntimeError("Schema invalido: items no es lista")

            for item in items:
                if not isinstance(item, dict):
                    continue
                w = str(item.get("palabra") or "").strip()
                if not w:
                    continue
                decisions[w.lower()] = bool(item.get("es_error_ortografico"))

            # Si el modelo omite alguna palabra del lote, ante duda: NO marcar
            for w in batch:
                if w.lower() not in decisions:
                    decisions[w.lower()] = False

        meta["llm_aplicado"] = True
    except Exception as exc:
        log.exception("LLM ortho filter failed; fallback a candidatos LibreOffice")
        meta["error"] = str(exc)
        meta["llm_aplicado"] = False
        meta["fallback"] = "libreoffice"
        # Disponibilidad: si Bedrock falla, conservar marcas LO
        meta["confirmados"] = len(errores)
        return list(errores), meta

    kept: list[dict[str, Any]] = []
    discarded: list[dict[str, Any]] = []

    for w in words:
        key = w.lower()
        is_error = decisions.get(key, False)
        err = by_lower.get(key)
        if not err:
            continue
        if is_error:
            kept.append(dict(err))
        else:
            discarded.append({"palabra": err.get("palabra")})

    meta["confirmados"] = len(kept)
    meta["descartados"] = len(discarded)
    meta["descartes"] = discarded[:100]
    return kept, meta
