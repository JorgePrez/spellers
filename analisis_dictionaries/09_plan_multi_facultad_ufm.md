# Plan: diccionarios por facultad UFM + rendimiento + fuentes existentes

**Fecha:** 24/07/2026  
**Contexto:** spellcheck LibreOffice en EC2 (`es-GT`), hoy `dict-ua-med` v2.2 (>=60k; build ~133k términos).  
**Objetivo:** responder rendimiento, reutilizar diccionarios existentes, y definir un plan controlado para varias facultades.

---

## 1. ¿La cantidad de palabras baja la rapidez de LibreOffice?

### Respuesta corta

**En nuestro rango (miles a decenas de miles), el impacto es bajo.**  
El cuello de botella real del servicio suele ser **abrir el documento + UNO + recorrer texto**, no el tamaño del `.dic`.

### Detalle técnico

| Aspecto | Qué pasa |
|---------|----------|
| Carga del diccionario | Hunspell carga el `.dic` al iniciar / al registrar la extensión. Es **una sola vez** por arranque de LibreOffice UNO. |
| Consulta `isValid(palabra)` | Lookup en estructura hash/trie: **O(1) amortizado** o muy cercano. Pasar de 4k → 40k palabras no multiplica el tiempo por 10. |
| Varias extensiones mismo locale | LibreOffice **fusiona** diccionarios del mismo `es-GT`. Más listas = algo más de memoria y carga inicial, no un spellcheck "lento por palabra". |
| Riesgo real de lentitud | Documentos grandes (PPT/PDF/DOCX), muchas celdas, reinicios frecuentes de LO, o demasiadas extensiones mal empaquetadas. |

### Recomendación práctica

1. **No temer** 5k-50k términos por área si están curados.
2. Evitar **cientos de miles** de basura / duplicados / combinaciones sintéticas.
3. Medir antes de optimizar:

```bash
# En EC2, tras instalar: tiempo de isValid en lote + un /spellcheck/mark real
time /opt/libreoffice25.8/program/python - <<'PY'
# microbenchmark isValid sobre N palabras del diccionario
PY
```

4. Si algún día el lexicon total supera ~100k-200k términos **únicos** en `es-GT`, entonces sí valorar:
   - un solo `.dic` consolidado por locale, o
   - diccionarios por "perfil" (solo Medicina vs solo Derecho) según el tipo de syllabus.

**Conclusión:** con la arquitectura actual (varios `.dic` complementando `es_GT`), **prioridad = calidad y organización del repo**, no micro-optimizar por tamaño.

---

## 2. ¿Se pueden tomar diccionarios que ya existen y usar todas sus palabras?

### Respuesta corta

**Sí, es posible y recomendable**, con tres filtros obligatorios:

1. **Licencia** (¿podemos redistribuir / usar en un servicio?)
2. **Formato** (Hunspell `.dic`/`.aff`, wordlist plana, CSV, etc.)
3. **Calidad** (dedupe, UTF-8, LF, sin ruido, sin inglés mezclado sin control)

### Fuentes ya existentes (útiles)

| Fuente | Qué aporta | Cuidado |
|--------|------------|---------|
| **`es_GT.dic` oficial** (ya en LO) | ~56k español general | Ya está activo; **no hay que copiarlo** al diccionario UA |
| Extensiones LibreOffice / OpenOffice (dominio, técnico) | Listas listas en Hunspell | Revisar licencia de cada OXT |
| Wordlists académicas abiertas (CSIC, repositorios universitarios) | Léxicos especializados | Muchas son **BY-NC** o requieren acuerdo |
| Glosarios UFM / syllabus reales | Máxima relevancia | Mejor fuente de falsos positivos reales |
| RANME / DPTM / SNOMED | Marco terminológico | No copiar literal sin permiso |

### Flujo propuesto para "importar un diccionario existente"

```
fuente (.dic / .txt / .csv)
    → normalizar UTF-8 + LF
    → extraer 1 token por línea (sin frases)
    → filtrar: longitud, dígitos, caracteres raros
    → dedupe casefold (decidir si se guardan MAYÚSCULA + minúscula)
    → restar palabras ya cubiertas por es_GT (opcional, para no inflar)
    → restar denylist (ruido)
    → generar ua_<area>_GT.dic + verify_dic.py
    → unopkg add --shared
```

### ¿"Todas las palabras"?

- **Sí técnicamente** (merge completo).
- **No ciegamente**: importar "todo" de una fuente enorme mete:
  - nombres propios irrelevantes,
  - ortografía de otra variante (es-ES vs es-GT),
  - términos que enmascaran errores reales (peor que falsos positivos).

**Regla:** importar **todo lo útil del dominio**, no todo el archivo sin curación.

---

## 3. Varias facultades UFM: ¿cómo buscar diccionarios y cómo organizar el repo?

### Facultades objetivo (fase 1)

| Facultad | Código corto repo | Prioridad sugerida |
|----------|-------------------|--------------------|
| Medicina | `med` | Hecho (v2) |
| Odontología | `odo` | Alta (léxico cercano a medicina + propio) |
| Derecho | `der` | Alta (latinismos + términos jurídicos) |
| Ciencias Económicas | `eco` | Media-alta |
| Arquitectura | `arq` | Media |
| Estudios Políticos y RR.II. | `pol` | Media |

### Dónde buscar términos por área

| Área | Fuentes recomendadas (ideas) |
|------|------------------------------|
| **Medicina** | Ya hecho; ampliar con glosario UA + syllabus marcados |
| **Odontología** | Glosarios odontológicos ES; CIE/procedimientos dentales; syllabus Odontología UFM |
| **Derecho** | Diccionarios jurídicos ES abiertos; latinismos jurídicos; códigos/glosarios académicos |
| **Economía** | Glosarios economía/finanzas ES (Banco de España, glosarios universitarios); términos contables |
| **Arquitectura** | Glosarios arquitectura/construcción ES; normas técnicas; vocabulario de taller |
| **Política / RR.II.** | Glosarios ciencia política / relaciones internacionales ES; organismos (ONU, OEA) como siglas |

**Método práctico más efectivo (mejor que "buscar un .dic mágico"):**

1. Correr spellcheck sobre **syllabus reales** de cada facultad.
2. Exportar lista de "falsos positivos frecuentes".
3. Curar esa lista → semilla del diccionario de esa facultad.
4. Completar con glosario abierto / wordlist especializada.

Eso da **máxima utilidad** y control del repo.

---

## 4. Arquitectura de repositorio (controlada)

### Principio

- **Una extensión Hunspell por área** (como medicina), mismo locale `es-GT`.
- LibreOffice las **suma**; no reemplazan `es_GT`.
- Generación reproducible (`gen_*.py` + `verify` + `install`).
- Documentación por área + un índice global.

### Estructura propuesta

```
spellers-main/dictionaries/
|-- README.md                          # índice global de diccionarios UA
|-- _shared/                           # utilidades comunes
|   |-- verify_dic.py
|   |-- hunspell_normalize.py          # UTF-8, LF, contador, dedupe
|   |-- import_wordlist.py             # importa .dic/.txt externos
|   -- licenses/                      # notas de licencia por fuente
|
|-- dict-ua-med/                       # Medicina (ya existe)
|   |-- ua_med_GT.dic / .aff
|   |-- dictionaries.xcu
|   |-- description.xml                # org.ua.dictionaries.med-gt
|   |-- gen_all.py / gen_lexicon_*.py
|   |-- install_dict_ua_med.sh
|   -- diagnose_dict_ec2.sh
|
|-- dict-ua-odo/                       # Odontología (nuevo)
|   |-- ua_odo_GT.dic / .aff
|   |-- dictionaries.xcu               # Locales: es-GT
|   |-- description.xml                # org.ua.dictionaries.odo-gt
|   |-- gen_all.py
|   -- install_dict_ua_odo.sh
|
|-- dict-ua-der/                       # Derecho
|-- dict-ua-eco/                       # Ciencias Económicas
|-- dict-ua-arq/                       # Arquitectura
-- dict-ua-pol/                       # Estudios Políticos / RR.II.
`

Documentación (análisis):

```
diccionarios_ua_spellcheck/            # nuevo índice global
|-- 00_indice.md
|-- 01_rendimiento_y_limites.md
|-- 02_fuentes_y_licencias.md
|-- 03_arquitectura_multi_facultad.md
|-- 04_proceso_nuevo_diccionario.md
-- facultades/
    |-- med.md
    |-- odo.md
    |-- der.md
    |-- eco.md
    |-- arq.md
    -- pol.md
`

### Identificadores LibreOffice (unopkg)

| Área | Extension id |
|------|----------------|
| Medicina | `org.ua.dictionaries.med-gt` |
| Odontología | `org.ua.dictionaries.odo-gt` |
| Derecho | `org.ua.dictionaries.der-gt` |
| Economía | `org.ua.dictionaries.eco-gt` |
| Arquitectura | `org.ua.dictionaries.arq-gt` |
| Política | `org.ua.dictionaries.pol-gt` |

Todas con `Locales = es-GT` en su `dictionaries.xcu`.

### Script de instalación global (fase 2)

```bash
dictionaries/install_all_ua_dicts.sh
# llama a cada install_dict_ua_*.sh en orden
# un solo restart de libreoffice-uno + flask al final
```

---

## 5. Decisiones de producto a acordar

| Decisión | Opciones | Recomendación |
|----------|----------|---------------|
| ¿Un `.dic` gigante o varios por facultad? | A) uno solo `ua_all` B) uno por facultad | **B** (control, ownership, rollback) |
| ¿Locale? | solo `es-GT` / varios | **solo `es-GT`** (igual que el servicio) |
| ¿Importar wordlists externas enteras? | sí ciego / sí con filtros | **sí con filtros + licencia** |
| ¿Restar términos ya en `es_GT`? | sí / no | **sí opcional** (diccionario UA más chico y claro) |
| ¿MAYÚSCULA + minúscula? | solo minúscula / ambas | **ambas para nombres de materias** (syllabus) |
| ¿Quién cura? | IT / facultad / ambos | semilla IT + validación facultad |

---

## 6. Plan de trabajo por fases

### Fase 0 - Cerrar medicina (ahora)

- [x] `dict-ua-med` v2 regenerado
- [ ] Confirmar pruebas en EC2 (`diagnose_dict_ec2.sh`)
- [ ] Congelar proceso como **plantilla** para las demás áreas

### Fase 1 - Herramientas compartidas (`_shared/`)

- [ ] Extraer `verify_dic.py` / normalizador UTF-8 a `_shared/`
- [ ] `import_wordlist.py` (entrada: `.dic` o `.txt`)
- [ ] Plantilla de carpeta `dict-ua-TEMPLATE/`
- [ ] `dictionaries/README.md` índice

### Fase 2 - Odontología (piloto #2)

- [ ] Crear `dict-ua-odo` clonando plantilla
- [ ] Semilla: falsos positivos de syllabus Odontología + glosario odontológico
- [ ] Instalar en EC2 + probar
- [ ] Documentar en `facultades/odo.md`

### Fase 3 - Derecho

- [ ] Misma plantilla
- [ ] Énfasis en latinismos y términos jurídicos frecuentes en syllabus

### Fase 4 - Economía, Arquitectura, Política

- [ ] Una facultad a la vez (no las tres en paralelo en el mismo PR/deploy)
- [ ] Misma checklist de calidad

### Fase 5 - Operación

- [ ] `install_all_ua_dicts.sh`
- [ ] Benchmark ligero post-install (carga LO + `isValid` + 1 mark real)
- [ ] Política de actualización: "solo vía gen_all + verify + unopkg"

---

## 7. Checklist de calidad (cada diccionario nuevo)

1. UTF-8 sin BOM, LF
2. Línea 1 del `.dic` = conteo exacto
3. Sin duplicados exactos
4. `verify_dic.py` → OK
5. `description.xml` ASCII-safe o UTF-8 real
6. `unopkg add --shared` (no copia manual a `share/extensions`)
7. Prueba: término del área = `True`; error ortográfico control = `False`
8. Nota de licencia de fuentes en `_shared/licenses/`
9. Entrada en índice global del repo

---

## 8. Riesgos y mitigaciones

| Riesgo | Mitigación |
|--------|------------|
| Repo desordenado con 6 carpetas distintas | Plantilla + `_shared` + README índice |
| Licencias incompatibles | Registrar fuente; no publicar wordlists restringidas si no aplica |
| Un diccionario "tapa" errores reales | Curación; no importar listas generales enormes |
| Deploy olvida una extensión | `install_all` + checklist |
| CRLF / encoding Windows | `.gitattributes` + normalize en install |

---

## 9. Respuestas directas (resumen)

1. **¿Más palabras = más lento?**  
   Casi no, en el rango que usamos. Importa más la calidad y no reiniciar LO en vano.

2. **¿Tomar diccionarios existentes enteros?**  
   Sí, vía importador + filtros + licencia. No fusionar a ciegas.

3. **¿Varias facultades?**  
   Un paquete `dict-ua-<area>` por facultad, mismo `es-GT`, plantilla igual a medicina, fuentes = syllabus reales + glosarios abiertos del área, herramientas en `_shared/`.

---

## 10. Siguiente paso sugerido

Cuando apruebes este plan:

1. Crear `dictionaries/_shared/` + plantilla.
2. Empezar **Odontología** como segunda facultad (léxico cercano a medicina, ROI alto).
3. Dejar Derecho como tercera.

Si prefieres otro orden (p. ej. Derecho antes que Odontología), indícalo y ajustamos la Fase 2/3.
