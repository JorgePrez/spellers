# Segunda capa LLM (Haiku) sobre LibreOffice — `/spellcheck/mark-llm`

**Fecha:** 27/07/2026  
**Modelo:** Claude Haiku 4.5 vía Bedrock inference profile  
`arn:aws:bedrock:us-east-1:552102268375:application-inference-profile/zym8ef4k7anz`  
(mismo que `AWSConfigBedrock::$modelIdHaiku` / `llm_generate_json_cached` en PHP)

## Idea

```
Documento
  → LibreOffice Hunspell (candidatos)
  → Bedrock Haiku (¿es falta ortográfica real?)
  → Solo los confirmados se marcan en el rev_*
```

El endpoint clásico **`POST /spellcheck/mark`** no cambia (sin LLM).

## Endpoint nuevo

`POST /spellcheck/mark-llm`  
Mismos campos multipart que `/spellcheck/mark`:

| Campo | Requerido |
|-------|-----------|
| `file` | sí |
| `syllabus_uac_cronograma` | sí |
| `s3_source_key` | sí |
| `s3_bucket` | no (default env) |
| Header `Authorization: Bearer …` | sí |

## Flujo interno

1. `find_unique_errors` (igual que hoy, con filtros de nombres/correos).
2. `filter_spelling_errors_with_llm` (`llm_ortho_filter.py`):
   - System prompt cacheado (`cache_control` ephemeral 1h), como en PHP.
   - Por ahora **solo la palabra** candidata (sin contexto de oración).
   - Lotes de hasta 40 palabras.
3. `annotate_document` solo con los confirmados.

## Schema JSON de salida del LLM

```json
{
  "type": "object",
  "properties": {
    "items": {
      "type": "array",
      "items": {
        "type": "object",
        "properties": {
          "palabra": { "type": "string" },
          "es_error_ortografico": { "type": "boolean" },
          "motivo": { "type": "string" }
        },
        "required": ["palabra", "es_error_ortografico", "motivo"],
        "additionalProperties": false
      }
    }
  },
  "required": ["items"],
  "additionalProperties": false
}
```

Solo se marca si `es_error_ortografico === true`.

**Sugerencias al usuario:** siempre las de **LibreOffice** (`sugerencias` del candidato original).  
El LLM **no** propone correcciones: solo responde sí/no (+ motivo breve).

**Palabras válidas a dejar pasar:** español preferido, pero también inglés correcto y otros términos académicos válidos (no solo “español del diccionario”).

## Respuesta extra (además de la de `/mark`)

```json
{
  "capa_llm": true,
  "candidatos_libreoffice": 12,
  "total_errores": 5,
  "llm": {
    "aplicado": true,
    "confirmados": 5,
    "descartados": 7,
    "descartes": [{ "palabra": "…", "motivo": "…" }],
    "fallback": null,
    "error": null
  }
}
```

Si Bedrock falla: **fallback a LibreOffice** (`llm.aplicado=false`, `fallback=libreoffice`) para no tumbar el flujo.

## Archivos

| Archivo | Rol |
|---------|-----|
| `llm_bedrock.py` | `llm_generate_json_cached` (boto3) |
| `llm_ortho_filter.py` | Prompt + schema + filtro |
| `spellcheck_mark.py` | `mark_document(..., llm_second_layer=True)` |
| `app.py` | ruta `/spellcheck/mark-llm` |

## Variables de entorno (EC2)

| Variable | Uso |
|----------|-----|
| Credenciales AWS / IAM role | Ya asumidas configuradas |
| `AWS_REGION` o `AWS_DEFAULT_REGION` | default `us-east-1` |
| `BEDROCK_HAIKU_MODEL_ID` | opcional; override del ARN Haiku |

## Ejemplo curl

```bash
curl -X POST "http://127.0.0.1:5000/spellcheck/mark-llm" \
  -H "Authorization: Bearer $SPELLCHECK_BEARER_TOKEN" \
  -F "file=@cronograma.docx" \
  -F "syllabus_uac_cronograma=123" \
  -F "s3_source_key=ruta/en/s3/cronograma.docx"
```

## Reinicio

```bash
sudo systemctl restart spellcheck-flask.service
```
