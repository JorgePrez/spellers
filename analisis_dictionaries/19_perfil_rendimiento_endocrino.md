# Perfil de rendimiento — mark-llm (Sistema Endocrino)

**Fecha:** 31/08/2026  
**Endpoint:** `POST /spellcheck/mark-llm-profile`  
**Archivo:** `3.-SISTEMA-ENDOCRINO-VERSION-ALUMNOS-JULIO-2021.xlsx`  
**Tipo:** Calc · `text_chars` ≈ 215 916 · extract nativo openpyxl ≈ 207 533  

---

## Resumen ejecutivo

| Métrica | Valor |
|---------|-------|
| **Total** | **243 410 ms ≈ 4:03** |
| Candidatos LibreOffice | 94 |
| Confirmados LLM | **33** (−65 %) |
| Descartados LLM | **61** |
| `llm.aplicado` | `true` |
| `fallback` | `null` (ya no cae a marcar los 94) |

**Cuello de botella:** `ms_spell` (SpellChecker UNO) ≈ **80 %** del tiempo.  
**LLM:** funciona y filtra bien; **no** es el freno (~4 % del total).

---

## Timings (esta corrida)

| Fase | ms | s | % del total |
|------|-----:|----:|------------:|
| `ms_spell` | 193 821 | 193.8 | **79.6 %** |
| `ms_annotate` | 20 210 | 20.2 | 8.3 % |
| `ms_extract_text_lo` | 15 311 | 15.3 | 6.3 % |
| `ms_llm` | 10 753 | 10.8 | 4.4 % |
| `ms_extract_native` | 1 380 | 1.4 | 0.6 % |
| `ms_store` + S3 + `ms_load_document` | ~1 924 | ~1.9 | ~0.8 % |
| **`ms_total`** | **243 410** | **243.4** | 100 % |

### Comparación con corrida previa (mismo Excel)

| Aspecto | Antes (profile) | Ahora |
|---------|-----------------|-------|
| `ms_spell` | ~194 s | ~194 s (igual) |
| `ms_annotate` | ~27 s | ~20 s (menos lemas a comentar) |
| `ms_llm` | ~4 s (y a veces fallaba JSON) | ~11 s, **aplicado OK** |
| Errores marcados | 94 (fallback LO) o pocos si LLM ok | **33** filtrados |
| `fallback` | a veces `libreoffice` | **`null`** |

Conclusión de tiempo: **el total sigue ~4 min** porque Hunspell vía UNO no cambió. Mejoró la **calidad** (menos falsos positivos), no el wall-clock.

---

## Qué demuestra cada fase

1. **`ms_load_document` ~0.6 s** — Abrir el xlsx en Calc **no** es el problema.
2. **`ms_extract_native` ~1.4 s vs `ms_extract_text_lo` ~15 s** — Leer celdas por UNO es caro; openpyxl ya es ~10× más rápido.
3. **`ms_spell` ~194 s** — Dominante: `isValid` / sugerencias por palabra única vía UNO + diccionarios.
4. **`ms_llm` ~11 s** — Segunda capa viable; lote grande puede costar un poco más que lotes chicos, pero sigue siendo marginal.
5. **`ms_annotate` ~20 s** — 62 comentarios / 92 resaltados con 33 lemas (muchas ocurrencias por lema).

---

## Capa LLM — resultado

- **61 descartes** típicos: inglés (`from`, `edition`, `pharmacology`…), términos médicos/anatómicos (`Neuroendócrina`, `lactotropos`, `hiperpituitarismo`…), academicismos.
- **33 confirmados** en su mayoría typos reales: tildes (`clinica`, `exámen`, `cancer`), letras (`sitemas`, `emvarazo`, `deficienica`).

### Revisar (calidad)

| Tipo | Ejemplos | Nota |
|------|----------|------|
| Posible falso negativo (descartó de más) | `menopaúsica`, `Adenohipofisis`, `carbegolina`, `adipositivos`, `Líbido` | Candidatos a no dejar pasar / añadir a dict con forma correcta |
| Fragmentos / ruido | `fel`, `ime`, `mpe`, `ejer`, `vda`, `vás`, `miU` | El LLM los marca; puede molestar en el Excel |

---

## Prioridades para bajar de ~4 minutos

| Prioridad | Acción | Impacto esperado |
|-----------|--------|------------------|
| **1** | Spell con **Hunspell nativo** (mismos `.dic` UA), sin `SpellChecker` UNO por token | Quitar ~3:14 |
| **2** | Extracción solo **openpyxl** en el path productivo (LO solo para anotar si hace falta) | ~−15 s |
| **3** | Mantener anotar solo confirmados LLM (ya) | annotate ya razonable |
| **4** | Allowlist / `dict-ua-llm` de descartes estables | Menos candidatos LO → algo menos de spell + LLM |

Optimizar más el batch del LLM **no** bajará el tiempo total de forma notable mientras `ms_spell` sea ~80 %.

---

## Artefacto visual

Canvas actualizado en el IDE: `perfil-endocrino-mark-llm.canvas.tsx`.
