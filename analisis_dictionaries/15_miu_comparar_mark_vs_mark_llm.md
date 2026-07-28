# MiU: comparar `/spellcheck/mark` vs `/spellcheck/mark-llm`

## Qué cambiar (y qué no)

| Archivo | Cambio |
|---------|--------|
| `syllabus_catedratico_spellcheck.php` | **No tocar** el flujo productivo (sigue en `/mark`) |
| `syllabus_catedratico_spellcheck_llm.php` | **Nuevo** — llama a `/spellcheck/mark-llm` |
| `syllabus_catedratico_ws.php` | Acción nueva `prePublicarSyllabusUAC_llm` |
| `syllabus_catedratico.php` | **Opcional** (UI). Para comparar basta llamar el ACTION nuevo |

## Cómo comparar

1. Flujo normal (LibreOffice solo):  
   `ACTION=prePublicarSyllabusUAC` → `syl_spell_marcarLote` → `/spellcheck/mark`

2. Flujo LLM (comparación):  
   `ACTION=prePublicarSyllabusUAC_llm` → `syl_spell_llm_marcarLote(..., false)` → `/spellcheck/mark-llm`  
   - **No** escribe `PATH_ARCHIVO_REV` (no pisa la revisión LO).  
   - En la respuesta JSON verás `candidatos_libreoffice`, `llm.descartes`, `total_errores`.

### Ejemplo (mismas cookies de sesión MiU)

```javascript
// LO solo (actual)
$.post('syllabus_catedratico_ws.php', {
  ACTION: 'prePublicarSyllabusUAC',
  cimp: CIMP
}, function (r) { console.log('LO', r.revision); }, 'json');

// LO + LLM (comparar)
$.post('syllabus_catedratico_ws.php', {
  ACTION: 'prePublicarSyllabusUAC_llm',
  cimp: CIMP
}, function (r) { console.log('LLM', r.revision); }, 'json');
```

Compara por cronograma:
- `total_errores` (LO) vs `total_errores` (LLM, filtrado)
- `candidatos_libreoffice` vs `llm.confirmados` / `llm.descartados`

## Cuando quieras pasar a producción LLM

En `syllabus_catedratico_ws.php`, en `prePublicarSyllabusUAC` (y el marcar de `guardarSyllabusUAC` si aplica), cambiar:

```php
$arrRevision = syl_spell_marcarLote(...);
// por:
$arrRevision = syl_spell_llm_marcarLote($globalConnection, $intIdBorrador, null, true);
```

El último `true` hace que sí guarde `PATH_ARCHIVO_REV`.

## Timeouts

`SYL_SPELLCHECK_MARK_LLM_TIMEOUT` = 300 s (Haiku + LO). Ajustable en el PHP nuevo.
