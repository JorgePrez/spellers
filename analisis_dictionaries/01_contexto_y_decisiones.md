# Contexto y decisiones de producto

## Proyecto

- **Syllabus catedratico** (PHP) delega revision ortografica a **spellers-main** (Flask en EC2).
- **EC2:** `3.150.240.23`, puerto **5000**.
- Endpoint usado por PHP: `POST /spellcheck/mark`.
- Solo **marca** errores (`rev_*` en S3); **no corrige** automaticamente.

## Decisiones acordadas

| Tema | Decision |
|------|----------|
| Correccion automatica | **No** en esta fase. El catedratico corrige y vuelve a subir. |
| Falsos positivos | **Diccionarios personalizados** Hunspell/LibreOffice (no LLM). |
| Listas Python (`ALLOWLIST`, `KNOWN_OK` en `spellcheck_core.py`) | **No se usaran** para falsos positivos. |
| Locale | **`es-GT`** (Guatemala), definido en `spellcheck_core.py` → `make_locale("es", "GT")`. |

## Flujo ortografico actual

```
Cronograma (Word/Excel/PPT/PDF)
    → PHP llama /spellcheck/mark
    → spellcheck_mark.py abre doc con LibreOffice UNO
    → spellcheck_core.find_unique_errors() + SpellChecker LO
    → Si hay errores: genera rev_* y sube a S3
```

La deteccion usa:

```python
spell.isValid(palabra, locale_es_GT, ())
```

No hay lógica propia de diccionario en Python más allá de `should_ignore()` (siglas, URLs, **correos**, **nombres en Title Case**, etc.). Ver [13_mitigacion_nombres_y_correos.md](13_mitigacion_nombres_y_correos.md).

| Falsos positivos de dominio (medicina, derecho…) | Diccionarios Hunspell `dict-ua-*` |
| Falsos positivos estructurales (nombres, emails) | Filtros en `spellcheck_core.py` |
