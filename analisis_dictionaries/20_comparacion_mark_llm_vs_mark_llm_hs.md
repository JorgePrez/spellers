# Comparación de tiempo — mark-llm vs mark-llm-hs (Sistema Endocrino)

**Fecha:** 01/09/2026  
**Archivo:** `3.-SISTEMA-ENDOCRINO-VERSION-ALUMNOS-JULIO-2021.xlsx`  
**Instancia:** t4g.medium  

| Rol | Endpoint | Corrida de referencia |
|-----|----------|------------------------|
| Baseline (LibreOffice + LLM) | `POST /spellcheck/mark-llm-profile` | **242.3 s (~4:02)** · 242 281 ms |
| Nuevo (Hunspell nativo + LLM) | `POST /spellcheck/mark-llm-hs` | **48.1 s** · 48 060 ms |

---

## 1. Hallazgo: el cuello de botella real eran las sugerencias

Al perfilar `mark-llm`, `ms_spell` concentraba ~80 % del tiempo (~195 s de ~242 s). La hipótesis inicial mezclaba “Hunspell vía UNO es lento” con “abrir Calc / `isValid`”.

Con `/spellcheck/mark-llm-profile-nosug` (mismo flujo LibreOffice, **sin** pedir sugerencias al SpellChecker) quedó claro:

- **`isValid` es barato** (orden de décimas de segundo a pocos segundos en este Excel).
- **`suggest` / generar candidatos de corrección es lo caro** (minutos).

Eso aplica en **ambas** implementaciones:

| Camino | Cómo pide sugerencias | Efecto observado |
|--------|------------------------|------------------|
| LibreOffice UNO | `SpellChecker` Linguistic2 → Hunspell interno | `ms_spell` ~195 s con sugerencias ON |
| Hunspell nativo (spylls) | `Dictionary.suggest()` sobre los mismos `.dic` UA | También minutos / CPU 100 %; **no usable** en detección |

Por eso `mark-llm-hs` detecta **sin** sugerencias de diccionario y, si hace falta, pide formas correctas en la **misma** llamada LLM que filtra errores.

---

## 2. Cómo LibreOffice UNO usa Hunspell

No hay un “Hunspell aparte” en el servicio Flask: el chequeo pasa por la API lingüística de LibreOffice.

```
Flask (Python embebido de LibreOffice o del sistema)
  → puente UNO → proceso soffice
      → com.sun.star.linguistic2.SpellChecker
          → motor Hunspell de LibreOffice
              → diccionarios del locale (es-GT)
                 + extensiones UA instaladas (dict-ua-* / unopkg)
```

- **`isValid(palabra, locale, …)`** — lookup: ¿está en el diccionario (con reglas `.aff`)? Relativamente rápido.
- **Sugerencias** — LibreOffice expone la generación de correcciones del mismo motor Hunspell (edits, afinidades, etc.). Con diccionarios grandes (p. ej. `ua_med`) ese algoritmo **domina** el tiempo total.
- Cada llamada cruza el puente UNO (proceso Flask ↔ soffice), así que el coste de `suggest` se suma al overhead IPC.

En resumen: **LibreOffice no inventa otro corrector**; empaqueta Hunspell detrás de Linguistic2. El freno no era “usar LibreOffice”, sino **pedir sugerencias** por ese camino.

---

## 3. Hunspell nativo (spylls): mismo problema en `suggest`

`mark-llm-hs` / `fast-detect-hs` cargan los `.dic`/`.aff` con **spylls** en el proceso Python (sin SpellChecker UNO para detectar).

- **`lookup` / isValid equivalente:** ~0.6 s en el Endocrino (3 paquetes UA + `es_GT`).
- **`suggest` de spylls:** sigue siendo el algoritmo pesado de Hunspell; en pruebas locales/EC2 llegó a minutos y presión de memoria/swap.

Conclusión operativa: **desactivar sugerencias de diccionario en detección** (LibreOffice o Hunspell nativo). Las sugerencias útiles para el usuario van por LLM solo en el path `mark-llm-hs`.

---

## 4. Factor de comparación (solo tiempo)

Mismo Excel. Baseline = corrida `mark-llm-profile` abajo. Nuevo = última `mark-llm-hs` (sugerencias LLM ON, Hunspell `suggest` OFF).

### Timings baseline — `mark-llm-profile`

| Fase | ms | ≈ s |
|------|-----:|----:|
| `ms_spell` | 194 961 | 195.0 |
| `ms_annotate` | 19 059 | 19.1 |
| `ms_extract_text_lo` | 14 617 | 14.6 |
| `ms_llm` | 10 792 | 10.8 |
| `ms_extract_native` | 788 | 0.8 |
| `ms_load_document` | 542 | 0.5 |
| `ms_store` + S3 | ~1 510 | ~1.5 |
| **`ms_total`** | **242 281** | **~4:02** |

### Timings nuevo — `mark-llm-hs`

| Fase | ms | ≈ s |
|------|-----:|----:|
| `ms_llm` (filtro + sugerencias) | 23 439 | 23.4 |
| `ms_annotate` | 20 322 | 20.3 |
| `ms_extraccion` | 1 012 | 1.0 |
| `ms_store` + S3 + load | ~2 717 | ~2.7 |
| `ms_spell` (spylls, sin suggest) | 560 | 0.6 |
| **`ms_total`** | **48 060** | **~0:48** |

### Factor (profile → hs)

| Métrica | mark-llm-profile | mark-llm-hs | Factor |
|---------|------------------:|------------:|--------|
| **Total** | 242.3 s (~4:02) | 48.1 s | **~5.0× más rápido** |
| Spell | 195.0 s | 0.6 s | **~350×** |
| Extracción texto | 14.6 s (LibreOffice) | 1.0 s (openpyxl) | **~15×** |
| LLM | 10.8 s | 23.4 s | mark-llm-hs ~2.2× más lento* |
| Annotate | 19.1 s | 20.3 s | ≈ igual |

\*En `mark-llm-hs` el LLM también genera sugerencias; en profile las sugerencias venían de Hunspell vía LibreOffice (y estaban dentro de `ms_spell`).

### Dónde se fue el tiempo ahorrado

1. Quitar **`suggest` de diccionario** del path de detección (principal).
2. Sustituir SpellChecker UNO por **spylls** solo para `isValid`.
3. Extracción con **openpyxl** (LibreOffice queda para anotar el `rev_*`).

Lo que **no** baja solo con `mark-llm-hs`: **`ms_annotate` ~20 s** (sigue siendo LibreOffice).
