# Diseño: spellcheck solo en cronos nuevos/cambiados + reutilizar `PATH_ARCHIVO_REV`

**Fecha:** 28/07/2026 (ajustado: **sin conteo de errores**)  
**Ámbito:** MiU (`Guardar` / `Guardar y Publicar`)  
**Endpoint:** `/spellcheck/mark-llm` (sin cambio de contrato en Flask, salvo dejar de depender del conteo en UI)

---

## 1. Objetivo

1. Ejecutar spellcheck **solo** en cronogramas **nuevos o con archivo cambiado** (`cronograma_revision`).
2. En cronos activos sin cambio: **reutilizar** la revisión previa (`PATH_ARCHIVO_REV`), sin llamar a Flask.
3. UI binaria (sin números):
   - Si existe documento de correcciones (`PATH_ARCHIVO_REV`) → mostrar acceso a ese `rev_*` (modal / botón revisión).
   - Si no existe → estado **verde / correcto**.

**Ya no interesa** `total_errores`, listas de errores en el modal, ni “N error(es)”.

---

## 2. Señal de verdad (única)

| Campo BD | Significado en UI |
|----------|-------------------|
| `PATH_ARCHIVO_REV` **con valor** | Hay documento marcado → usuario puede ver/descargar correcciones; cuenta como “requiere atención” al publicar |
| `PATH_ARCHIVO_REV` **vacío / NULL** | Correcto (verde); no hay doc de correcciones que mostrar |

Al **reemplazar** el archivo original → `PATH_ARCHIVO_REV = NULL` (ya ocurre hoy) → obliga a una pasada fresca.

No se necesitan columnas `SPELLCHECK_TOTAL_ERRORES` ni conteos en sesión para el modal.

---

## 3. Comportamiento deseado

### 3.1 Por cronograma activo

| Caso | Condición | Acción |
|------|-----------|--------|
| **A. Fresco** | En `cronograma_revision` | Llamar `mark-llm`; persistir `PATH_ARCHIVO_REV` si hay marcas; si no hay marcas, dejar/limpiar `PATH_REV` |
| **B. Reutilizar** | Activo, no fresco | No Flask; armar resultado desde BD: `tiene_doc_rev = (PATH_REV != '')` |
| **C. Sin historial claro** | No fresco y nunca pasó por mark (ver §5) | Forzar una pasada mark-llm **una vez** |

### 3.2 Modal al publicar

Simplificar respecto al actual:

- **No** mostrar `(N error(es))`.
- Listar solo cronogramas que **tienen** `PATH_ARCHIVO_REV` (fresco o reutilizado), con nombre + botón ver/descargar revisión.
- Resumen posible (sin conteo de faltas):  
  *“Hay documentos con revisión ortográfica marcada. Puede revisarlos o continuar.”*  
  o bien: *“K de T cronograma(s) tienen documento de correcciones.”* (K = con `PATH_REV`, no “errores”).
- Cronos en verde (sin `PATH_REV`) **no** aparecen en la lista del modal (igual que hoy no listamos los OK).
- `requiere_confirmacion` = existe al menos un resultado con documento de correcciones (`PATH_REV` / `tiene_errores` reinterpretado como “tiene rev”).

### 3.3 Tabla de cronogramas (marcas)

- Con `PATH_REV` → botón “revisión” (doc marcado).
- Sin `PATH_REV` y revisado OK → fila **verde**.
- **Quitar** badges/textos de cantidad de errores en la UI de cronogramas (si aún se muestran).

### 3.4 Guardar

Sin cambio de alcance: solo revisa `cronograma_revision`.  
Misma UI binaria (rev o verde).

---

## 4. Diseño técnico

### 4.1 Función de fusión

`syl_spell_armarRevisionPublicacion($globalConnection, $intSyllabusUAC, $arrCronoIdsFrescos)`

1. Activos del borrador.
2. Frescos = IDs a revisar ahora (revision + forzados §5).
3. `syl_spell_llm_marcarLote(..., $arrFrescos, true)`.
4. Resto: resultado sintético:
   - `ok = true`
   - `tiene_errores = (PATH_REV !== '')` *(nombre histórico del campo; significa “hay doc de correcciones”)*
   - `path_archivo_rev` + URLs firmadas si aplica
   - `total_errores` / `errores` → **no usar en UI** (pueden ir vacíos o omitirse)
   - `origen = fresco | reutilizado`
5. Fusionar; `con_errores` = algún ítem con `PATH_REV`.
6. Guardar lote en sesión para pintar tabla/modal.

### 4.2 WS `prePublicarSyllabusUAC`

Dejar de llamar lote completo (`null`). Usar fusión con `cronograma_revision` del guardado de esa request.

### 4.3 UI

| Zona | Cambio |
|------|--------|
| `abrirModalPublicarRevision` | Quitar “N error(es)”; listar por nombre + descargar/ver revisión |
| Resumen del modal | Sin conteo de faltas; opcional “K con documento de correcciones” |
| Marcas en tabla | Verde vs botón revisión; sin contador |
| Respuesta API al front | El front puede ignorar `total_errores` / `errores[]` |

### 4.4 Flask

Sin cambio obligatorio. Puede seguir devolviendo `total_errores` / `errores` en JSON; MiU **no los muestra**.  
(Opcional después: no pintar conteos en comentarios del `rev_*` — fuera de este plan de modal.)

### 4.5 BD

**Sin migración** para conteos. Solo `PATH_ARCHIVO_REV`.

---

## 5. Casos borde

1. **Nunca revisado** (`PATH_REV` null, no fresco): forzar mark-llm una vez (no publicar a ciegas).
2. **Revisado OK** (`PATH_REV` null tras mark sin marcas): mismo aspecto que verde.  
   Indistinguible de “nunca revisado” → por eso el forzado del punto 1 si no hay evidencia de pasada.  
   *(Opcional futuro: flag `SPELLCHECK_OK='Y'` solo para evitar re-checks; no es conteo.)*
3. Archivo cambiado fuera de MiU sin re-subir: se reutiliza `PATH_REV` viejo (aceptable).
4. Sin contenido en fresco: seguir bloqueando publicar.

---

## 6. Archivos a tocar

| Archivo | Cambio |
|---------|--------|
| `syllabus_catedratico_spellcheck_llm.php` / `_spellcheck.php` | Fusión publicar + resultado desde `PATH_REV` |
| `syllabus_catedratico_ws.php` | `prePublicar` usa fusión |
| `syllabus_catedratico.php` | Modal y marcas **sin** conteo; solo doc correcciones o verde |
| Este markdown | Plan (ya ajustado) |

---

## 7. Fases

### Fase 1 (este plan)

- Publicar: solo frescos + reutilizar `PATH_REV`.
- UI: documento de correcciones **o** verde; **cero conteos**.
- Forzar check si no hay historial usable.

### Fase 2 (opcional)

- Flag `SPELLCHECK_OK` para no re-forzar cronos ya OK sin `PATH_REV`.
- Badge “revisión previa” vs “recién generada” (sin números).

---

## 8. Criterios de aceptación

1. Publicar con N cronos y 1 archivo nuevo → idealmente **1** llamada `mark-llm` (salvo forzados §5).
2. Modal: lista con nombre + link al `rev_*` cuando exista; **sin** “N error(es)”.
3. Cronos sin `PATH_REV` → verde; no salen en la lista del modal.
4. Reemplazar archivo limpia `PATH_REV` y obliga a revisión fresca.
5. Guardar sigue revisando solo nuevos/cambiados.

---

## 10. Estado de implementación (28/07/2026)

Implementado en MiU:

- `syl_spell_armarRevisionPublicacion` + `syl_spell_resultadoDesdePathRev`
- `prePublicarSyllabusUAC` usa fusión (solo frescos / forzados sin historial de sesión)
- Modal y marcas: documento de correcciones **o** verde; **sin conteo** ni lista de palabras
