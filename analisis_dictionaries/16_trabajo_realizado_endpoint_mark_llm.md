# Qué se hizo para tener el endpoint `/spellcheck/mark-llm`

**Fecha:** 28/07/2026  
**Relacionado:** [14_endpoint_mark_llm_segunda_capa.md](14_endpoint_mark_llm_segunda_capa.md) (diseño técnico), [15_miu_comparar_mark_vs_mark_llm.md](15_miu_comparar_mark_vs_mark_llm.md) (MiU).

Documento de **trabajo realizado**: archivos, integración MiU, despliegue EC2 y lección del 404.

---

## Objetivo

Añadir una **segunda capa LLM** (Claude Haiku vía Bedrock) sobre los candidatos de LibreOffice/Hunspell, **sin modificar** el endpoint productivo `/spellcheck/mark`.

Flujo:

```
Documento
  → LibreOffice (candidatos)
  → Bedrock Haiku (¿es falta ortográfica real?)
  → Solo los confirmados se marcan en rev_*
```

---

## 1. Backend Flask (EC2)

### Archivos nuevos / tocados

| Archivo | Qué se hizo |
|---------|-------------|
| `llm_bedrock.py` | Cliente Bedrock (`llm_generate_json_cached`), espejo del patrón PHP |
| `llm_ortho_filter.py` | Prompt + schema JSON + filtro sí/no por lotes |
| `spellcheck_mark.py` | Flag `llm_second_layer=True` en `mark_document` |
| `app.py` | Ruta nueva `POST /spellcheck/mark-llm` |

### Modelo

- ARN: `arn:aws:bedrock:us-east-1:552102268375:application-inference-profile/zym8ef4k7anz`
- Mismo perfil que `AWSConfigBedrock::$modelIdHaiku` en PHP
- El LLM solo decide **sí/no**; las **sugerencias** siguen siendo de LibreOffice
- Si Bedrock falla → **fallback** a marcas solo LO (`llm.fallback=libreoffice`)

### Contrato del endpoint

- Mismos campos multipart que `/mark`: `file`, `syllabus_uac_cronograma`, `s3_source_key`, `s3_bucket` opcional
- Header `Authorization: Bearer …`
- Respuesta extra: `capa_llm`, `candidatos_libreoffice`, bloque `llm` (confirmados/descartados/fallback)

Detalle de schema/prompt: ver documento **14**.

---

## 2. Front MiU (servidor PHP, distinto al EC2)

Para **probar rendimiento** sin sustituir el flujo productivo:

| Archivo | Qué se hizo |
|---------|-------------|
| `MiU/syllabus_catedratico_spellcheck_llm.php` | Cliente curl a `/spellcheck/mark-llm` (`syl_spell_llm_marcarLote`) |
| `MiU/syllabus_catedratico_ws.php` | Si `POST usar_spellcheck_llm=1` → LLM; si no → `/mark` |
| `MiU/syllabus_catedratico.php` | Checkbox bajo los botones: *Usar revisión ortográfica con LLM (prueba)* |

Comportamiento:

- Checkbox **apagado** → Guardar / Guardar y Publicar usan `/spellcheck/mark`
- Checkbox **encendido** → mismos botones usan `/spellcheck/mark-llm`

---

## 3. Despliegue en el EC2 (Flask)

1. Copiar al working directory del servicio (`/home/ec2-user/libreoffice_spellcheck/`):
   - `app.py`
   - `llm_bedrock.py`
   - `llm_ortho_filter.py`
   - `spellcheck_mark.py` (con soporte LLM)
2. Reiniciar:

```bash
sudo systemctl restart spellcheck-flask.service
sudo systemctl status spellcheck-flask.service --no-pager
```

3. Comprobar IAM Bedrock (role de la instancia), opcional:

```bash
aws sts get-caller-identity
aws bedrock-runtime converse \
  --region us-east-1 \
  --model-id arn:aws:bedrock:us-east-1:552102268375:application-inference-profile/zym8ef4k7anz \
  --messages '[{"role":"user","content":[{"text":"di ok"}]}]' \
  --query 'output.message.content[0].text' --output text
```

4. Ver tráfico:

```bash
sudo journalctl -u spellcheck-flask.service -f
```

---

## 4. Incidente: 404 en `mark-llm` (28/07/2026)

### Síntoma

- En MiU: badge **“No verificado”** en cronogramas
- En log Flask: `POST /spellcheck/mark-llm` → **404**
- `POST /spellcheck/mark` → **200**
- Bedrock desde CLI en el EC2 → **OK** (IAM no era el problema)

### Causa

Los archivos **ya estaban** en disco, pero el servicio **no se había reiniciado**. Flask seguía con el proceso viejo en memoria (sin la ruta nueva).

### Solución

```bash
sudo systemctl restart spellcheck-flask.service
```

Tras el reinicio: `POST /spellcheck/mark-llm` → **200**.

### Lección

Tras subir o editar `app.py` / módulos importados: **siempre** `restart` de `spellcheck-flask.service`. Un `grep mark-llm app.py` en disco no basta si el proceso no se recargó.

---

## 5. Cómo verificar que quedó bien

```bash
# En el código desplegado
grep -n "mark-llm" /home/ec2-user/libreoffice_spellcheck/app.py

# En el log, tras una prueba con checkbox LLM
# debe aparecer: POST /spellcheck/mark-llm ... 200
sudo journalctl -u spellcheck-flask.service -n 50 --no-pager
```

Comparar en MiU:

1. Sin checkbox → tiempo y marcas de `/mark`
2. Con checkbox → tiempo y marcas de `/mark-llm` (menos falsos positivos esperados; más latencia por Haiku)

---

## 6. Qué no se cambió

- `/spellcheck/mark` sigue igual (producción por defecto)
- Diccionarios Hunspell UA sin cambios por este endpoint
- El checkbox MiU es **solo prueba**; quitarlo o dejar LLM por defecto es decisión posterior
