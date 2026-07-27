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
) -> dict[str, Any]:
    """
    Equivalente Python de AWSConfigBedrock::llm_generate_json_cached:
    - system con cache_control ephemeral
    - output_config.format = json_schema
    - modelId = Haiku inference profile
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

    parsed = json.loads(text_out)
    if not isinstance(parsed, dict):
        raise RuntimeError(f"La salida del modelo no es objeto JSON: {text_out[:200]}")
    return parsed
