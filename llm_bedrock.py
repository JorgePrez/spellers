# -*- coding: utf-8 -*-
"""Cliente Bedrock (Claude Haiku) con salida JSON schema — espejo de PHP llm_generate_json_cached."""
from __future__ import annotations

import json
import os
from typing import Any

import boto3
from botocore.config import Config

# Mismo inference profile que AWSConfigBedrock::$modelIdHaiku
DEFAULT_MODEL_ID = (
    "arn:aws:bedrock:us-east-1:552102268375:"
    "application-inference-profile/zym8ef4k7anz"
)


def _bedrock_client():
    region = (
        os.environ.get("AWS_REGION")
        or os.environ.get("AWS_DEFAULT_REGION")
        or "us-east-1"
    )
    # Credenciales: env / IAM role / shared config (ya configurado en AWS/EC2)
    return boto3.client(
        "bedrock-runtime",
        region_name=region,
        config=Config(retries={"max_attempts": 3, "mode": "standard"}),
    )


def get_model_id() -> str:
    return os.environ.get("BEDROCK_HAIKU_MODEL_ID", DEFAULT_MODEL_ID)


def llm_generate_json_cached(
    system_prompt: str,
    user_prompt: str,
    schema: dict[str, Any],
    max_tokens: int = 150,
    temperature: float = 0.0,
    *,
    allow_truncated_repair: bool = True,
) -> dict[str, Any]:
    """
    Equivalente Python de AWSConfigBedrock::llm_generate_json_cached:
    - system con cache_control ephemeral
    - output_config.format = json_schema
    - modelId = Haiku inference profile

    Si allow_truncated_repair=True e stop_reason=max_tokens, intenta reparar
    el JSON truncado antes de fallar (para reintentos con mas tokens).
    """
    system_prompt = (system_prompt or "").strip()
    user_prompt = (user_prompt or "").strip()
    if not system_prompt:
        raise ValueError("Debe enviar system prompt")
    if not user_prompt:
        raise ValueError("Debe enviar user prompt")
    if not schema:
        raise ValueError("Debe enviar schema")

    payload = {
        "anthropic_version": "bedrock-2023-05-31",
        "max_tokens": int(max_tokens),
        "temperature": float(temperature),
        "system": [
            {
                "type": "text",
                "text": system_prompt,
                "cache_control": {"type": "ephemeral", "ttl": "1h"},
            }
        ],
        "messages": [
            {
                "role": "user",
                "content": [{"type": "text", "text": user_prompt}],
            }
        ],
        "output_config": {
            "format": {
                "type": "json_schema",
                "schema": schema,
            }
        },
    }

    client = _bedrock_client()
    result = client.invoke_model(
        modelId=get_model_id(),
        contentType="application/json",
        accept="application/json",
        body=json.dumps(payload, ensure_ascii=False).encode("utf-8"),
    )

    raw = result["body"].read()
    response_body = json.loads(raw.decode("utf-8"))

    if not isinstance(response_body, dict):
        raise RuntimeError("La respuesta de Bedrock no es JSON valido")

    text_out = ""
    for block in response_body.get("content") or []:
        if isinstance(block, dict) and block.get("type") == "text":
            text_out += block.get("text") or ""

    text_out = text_out.strip()
    if not text_out:
        raise RuntimeError("Bedrock devolvio content[] sin texto")

    stop_reason = response_body.get("stop_reason")
    truncated = stop_reason == "max_tokens"

    try:
        parsed = json.loads(text_out)
    except json.JSONDecodeError as exc:
        repaired = _try_repair_json_object(text_out)
        if repaired is None:
            tip = " (truncada max_tokens)" if truncated else ""
            raise RuntimeError(
                f"JSON invalido del modelo{tip} ({exc}); preview={text_out[:240]!r}"
            ) from exc
        parsed = repaired
        if truncated and allow_truncated_repair:
            # Parcial reparado: el caller debe validar items completos / reintentar
            return parsed
        if truncated:
            raise RuntimeError(
                "Respuesta LLM truncada (stop_reason=max_tokens); aumentar max_tokens"
            )

    if truncated and not allow_truncated_repair:
        raise RuntimeError(
            "Respuesta LLM truncada (stop_reason=max_tokens); aumentar max_tokens"
        )

    if not isinstance(parsed, dict):
        raise RuntimeError(f"La salida del modelo no es objeto JSON: {text_out[:200]}")
    return parsed


def _try_repair_json_object(text: str) -> dict[str, Any] | None:
    """Intento conservador de cerrar JSON truncado (comillas/llaves/corchetes)."""
    s = (text or "").strip()
    if not s:
        return None
    # Quitar fences markdown por si acaso
    if s.startswith("```"):
        lines = s.splitlines()
        if lines and lines[0].startswith("```"):
            lines = lines[1:]
        if lines and lines[-1].strip().startswith("```"):
            lines = lines[:-1]
        s = "\n".join(lines).strip()

    try:
        out = json.loads(s)
        return out if isinstance(out, dict) else None
    except json.JSONDecodeError:
        pass

    # Cerrar string abierto y estructuras
    in_string = False
    escape = False
    stack: list[str] = []
    for ch in s:
        if in_string:
            if escape:
                escape = False
            elif ch == "\\":
                escape = True
            elif ch == '"':
                in_string = False
            continue
        if ch == '"':
            in_string = True
        elif ch in "{[":
            stack.append("}" if ch == "{" else "]")
        elif ch in "}]" and stack and stack[-1] == ch:
            stack.pop()

    candidate = s
    if in_string:
        candidate += '"'
    while stack:
        candidate += stack.pop()

    # Si items quedó a medias, a veces ayuda quitar la ultima coma
    candidate2 = candidate.replace(",]", "]").replace(",}", "}")
    for cand in (candidate2, candidate):
        try:
            out = json.loads(cand)
            if isinstance(out, dict):
                return out
        except json.JSONDecodeError:
            continue

    # Truncar el ultimo objeto incompleto dentro de "items": [ ... ]
    items_key = '"items"'
    idx = s.find(items_key)
    if idx >= 0:
        bracket = s.find("[", idx)
        if bracket >= 0:
            # Quedarnos con objetos completos hasta la ultima },
            last_complete = s.rfind("}", bracket)
            if last_complete > bracket:
                trimmed = s[: last_complete + 1] + "]}"
                trimmed = trimmed.replace(",]", "]").replace(",}", "}")
                try:
                    out = json.loads(trimmed)
                    if isinstance(out, dict):
                        return out
                except json.JSONDecodeError:
                    pass
    return None
