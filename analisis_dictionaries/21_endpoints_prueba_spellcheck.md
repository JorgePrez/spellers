# Endpoints de prueba (Flask spellcheck)

**Fecha:** 01/09/2026  
**Contrato base (multipart):** `file`, `syllabus_uac_cronograma`, `s3_source_key`, `s3_bucket` (opcional), Bearer token.  
**Health:** `GET /health` lista las rutas activas.

---

## Catálogo

### Productivo LLM actual y candidato nuevo

| Endpoint | Para qué sirve |
|----------|----------------|
| `POST /spellcheck/mark-llm` | **Producción MiU (checkbox LLM hoy).** LibreOffice detecta candidatos (Hunspell vía SpellChecker UNO, con sugerencias) → Bedrock Haiku filtra → solo se marcan los confirmados → sube `rev_*` a S3. Sin `timings_ms`. |
| `POST /spellcheck/mark-llm-hs` | **Candidato a reemplazar `mark-llm`.** Hunspell nativo (spylls, sin `suggest`) → LLM (filtro + hasta 3 sugerencias) → LibreOffice **solo para anotar** + S3. Mismos campos multipart. Incluye `timings_ms`. |

Flujo `mark-llm`:

```
archivo
  → LibreOffice (abrir + texto + SpellChecker UNO isValid/suggest)
  → Bedrock Haiku (¿es error ortográfico?)
  → LibreOffice marca confirmados + sube rev_*
```

Flujo `mark-llm-hs`:

```
archivo
  → openpyxl / OOXML (texto)
  → Hunspell nativo isValid (dicts es_GT + UA)
  → Bedrock Haiku (¿error? + hasta 3 sugerencias)
  → LibreOffice solo marca confirmados + sube rev_*
```

### Otros productivos (sin LLM / legacy)

| Endpoint | Para qué sirve |
|----------|----------------|
| `POST /spellcheck/mark` | LibreOffice solo: detecta + marca + S3. Sin LLM. Flujo clásico MiU sin checkbox. |
| `POST /spellcheck` | Spellcheck histórico (respuesta JSON; no es el path de marcar cronogramas). |
| `POST /spellcheck/fix-word` | Variante Word del histórico. |

### Solo detectar (no marcan, no suben `rev_*`)

| Endpoint | Para qué sirve |
|----------|----------------|
| `POST /spellcheck/mark-detect` | LibreOffice: abrir doc + spellcheck. **Sin** LLM. Comparar tiempo de detección “pura” con LibreOffice. |
| `POST /spellcheck/mark-llm-detect` | Igual que `mark-llm` en detección (LibreOffice + Haiku), **sin** anotar ni subir `rev_*`. Ver candidatos vs confirmados en JSON. |
| `POST /spellcheck/fast-detect` | Texto con openpyxl/OOXML (sin abrir Calc) + SpellChecker UNO. Opcional `usar_llm=1`. Medir extracción rápida + Hunspell vía LibreOffice. |
| `POST /spellcheck/fast-detect-hs` | Texto openpyxl/OOXML + **Hunspell nativo (spylls)**. Sin SpellChecker UNO. `usar_llm=1` opcional. `con_sugerencias=1` activa `suggest` (muy lento; por defecto OFF). |

### Flujo completo con tiempos (`timings_ms`)

| Endpoint | Para qué sirve |
|----------|----------------|
| `POST /spellcheck/mark-llm-profile` | Igual que `mark-llm` (LibreOffice + LLM + marcar + S3) **con desglose de ms**. Baseline de rendimiento (p. ej. Endocrino ~4 min). |
| `POST /spellcheck/mark-llm-profile-nosug` | Igual que profile, pero SpellChecker UNO **sin sugerencias** (solo `isValid`). Demostró que el cuello era `suggest`, no `isValid`. |

---

## Resumen rápido (qué usar cuándo)

| Objetivo | Endpoint |
|----------|----------|
| Producción MiU con LLM (hoy) | `mark-llm` |
| Producción rápida (siguiente paso) | `mark-llm-hs` |
| Baseline lento = mismo pipeline que `mark-llm` + tiempos | `mark-llm-profile` |
| Probar si el freno era `suggest` | `mark-llm-profile-nosug` |
| Probar Hunspell nativo sin marcar | `fast-detect-hs` (+ `usar_llm=1`) |
| Solo JSON de errores LibreOffice + LLM | `mark-llm-detect` |

Comparación de tiempos: [20_comparacion_mark_llm_vs_mark_llm_hs.md](20_comparacion_mark_llm_vs_mark_llm_hs.md).

---

## Pasar MiU a `mark-llm-hs`

Sí: cambiar la URL de `mark-llm` → `mark-llm-hs` en el PHP LLM (constante tipo `SYL_SPELLCHECK_MARK_LLM_URL`).

- Mismos campos multipart y misma idea de respuesta (`ok`, `documento_rev`, `archivo_rev`, `tiene_errores`, `total_errores`, `errores`, bloque `llm`, S3).
- El timeout actual (~300 s) sobra (~48 s en el Endocrino).
- Diferencia menor de JSON: candidatos vienen como `candidatos_hunspell` (antes `candidatos_libreoffice`). MiU no depende de ese campo para guardar el `PATH_REV`.

Checklist:

1. Código `mark-llm-hs` desplegado en el EC2 y `spellcheck-flask` reiniciado.
2. `GET /health` lista `/spellcheck/mark-llm-hs`.
3. Cambiar la URL en `syllabus_catedratico_spellcheck_llm.php` (o la constante que use).
4. Probar Guardar / Publicar con checkbox LLM en un cronograma real.

Los endpoints de profile / fast-detect / detect pueden quedarse solo para diagnóstico; MiU no los necesita.
