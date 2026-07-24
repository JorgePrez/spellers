# Referencias de diccionarios y glosarios por facultad UFM

**Fecha:** 24/07/2026  
**Objetivo:** listar, por facultad, diccionarios/glosarios en español útiles para construir `dict-ua-<area>`.

## Importante: ¿qué es "copia literal"?

Casi ninguna fuente académica/comercial permite volcar **todas** sus entradas a un `.dic` redistribuible sin permiso.
Para el spellcheck UA conviene distinguir:

| Uso | Significado |
|-----|-------------|
| **Consulta / marco** | Usar para saber qué términos existen y cómo se escriben; **no** pegar el diccionario entero |
| **Extracción de lemas posible** | Se pueden tomar palabras (con atribución / licencia abierta) |
| **Requiere licencia/acuerdo** | Pedir permiso o firmar licencia antes de importar masivamente |

**Regla práctica:** syllabus reales UFM = fuente primaria de lemas; estas referencias = completar y validar ortografía.

---

## 1. Medicina (`dict-ua-med`)

| Referencia | URL | Uso recomendado |
|------------|-----|-----------------|
| DPTM — Diccionario panhispánico de términos médicos (RANME / ALANAM) | https://dptm.es/ | Consulta / marco |
| DTM — Diccionario de términos médicos (RANME) | http://dtme.ranm.es/ | Consulta / marco |
| SNOMED CT edición española (Ministerio de Sanidad, España) | https://www.sanidad.gob.es/profesionales/hcdsns/areaRecursosSem/snomed-ct/preguntas.htm | Consulta / marco; licencia SNOMED aparte |
| CIE-11 (OMS) | https://icd.who.int/ | Consulta / marco |
| MedLexSp (CSIC) — léxico médico para NLP | https://digital.csic.es/handle/10261/270429 | Extracción posible **solo con licencia de investigación** |
| SimpMedLexSp (CSIC) | https://digital.csic.es/handle/10261/349662 | Revisar licencia (suele ser BY-NC) |
| Extensiones diccionario LibreOffice | https://wiki.openoffice.org/wiki/Extension_Dictionaries | Formato técnico OXT/Hunspell |

**Fuente estrella:** DPTM (consulta). **Para lemas masivos:** MedLexSp con licencia.

---

## 2. Odontología (`dict-ua-odo`)

| Referencia | URL | Uso recomendado |
|------------|-----|-----------------|
| ACODES — Diccionario odontológico comentado (PDF 2023) | https://acodes.es/doc/Diccionario_odontol%C3%B3gico_2023.pdf | Consulta / marco |
| ACODES — página del diccionario | https://acodes.inspiriadental.com/diccionario-odontologico/ | Consulta |
| Fundación Dental Española — Diccionario dental | https://fundaciondental.es/salud-bucodental/diccionario-dental/ | Consulta / semilla de lemas básicos |
| Universidad de Chile — Glosario odontológico básico | https://aprendizaje.uchile.cl/recursos-especificos-por-areas-disciplinares/salud/facultad-de-odontologia/odontologia/glosario-odontologico-basico/ | Consulta académica |
| Glosario de prótesis dental (SciELO) | http://www.scielo.org.pe/pdf/reh/v32n3/1019-4355-reh-32-03-343.pdf | Consulta especializada |
| DPTM / DTM (términos orales/maxilofaciales) | https://dptm.es/ | Solape controlado con Medicina |

**Fuente estrella:** ACODES. **Complemento:** Fundación Dental Española + solape con `dict-ua-med`.

---

## 3. Derecho (`dict-ua-der`)

| Referencia | URL | Uso recomendado |
|------------|-----|-----------------|
| **DPEJ** — Diccionario panhispánico del español jurídico (RAE) | https://dpej.rae.es/ | **Principal consulta** (~40000 entradas; gratis en línea; no volcar literal) |
| Ficha RAE del DPEJ | https://www.rae.es/obras-academicas/diccionarios/diccionario-panhispanico-del-espanol-juridico | Contexto |
| DEJ — Diccionario del español jurídico (RAE) | https://www.rae.es/obras-academicas/diccionarios/diccionario-del-espanol-juridico | Consulta (antecesor España) |
| Conceptos Jurídicos — Guatemala | https://www.conceptosjuridicos.com/gt/ | Consulta pedagógica (revisar términos de uso) |
| URL / OJ Guatemala — glosarios bilingües (lado español) | https://www.url.edu.gt/publicacionesurl/pPublicacion.aspx?pb=66 | Lema jurídico local útil |
| Biblioteca Organismo Judicial GT | http://biblioteca.oj.gob.gt/ | Catálogo de glosarios locales |

**Fuente estrella:** DPEJ (RAE). **Localismos GT:** glosarios OJ/URL.

---

## 4. Ciencias Económicas (`dict-ua-eco`)

| Referencia | URL | Uso recomendado |
|------------|-----|-----------------|
| Banco de España — Glosario de estadísticas | https://www.bde.es/webbe/es/estadisticas/recursos/glosario/terminos-con-a.html | Consulta oficial (macro/finanzas) |
| FMI — IMF Glossary EN-FR-ES | https://www.elibrary.imf.org/display/book/9781589066458/9781589066458.xml | Consulta / terminología macro (~4000) |
| FMI — Terminology Bulletin (ES) | https://doi.org/10.5089/9798400259166.073 | Consulta (facilidades, fintech, etc.) |
| TERMCAT — Economía / empresa (Cercaterm) | https://www.termcat.cat/es/cercaterm/input?type=basic | Consulta (equivalentes ES) |
| Glosarios / syllabus UFM Economía | (interno facultad) | **Mejor semilla de lemas reales** |

**Fuente estrella:** Glosario Banco de España. **Complemento:** IMF Glossary + syllabus UFM.

---

## 5. Arquitectura (`dict-ua-arq`)

| Referencia | URL | Uso recomendado |
|------------|-----|-----------------|
| Wikipedia ES — Anexo: Terminología de la arquitectura | https://es.wikipedia.org/wiki/Anexo:Terminolog%C3%ADa_de_la_arquitectura | **Extracción de lemas posible** (CC BY-SA, con atribución) |
| Compilación de fuentes de terminología arquitectónica | https://terminologiaarquitectonica.wordpress.com/terminologia-arquitectonica/ | Índice de diccionarios (CC BY-NC) |
| ARTEGUIAS — Diccionario/glosario de arquitectura | http://www.arteguias.com/diccionario.htm | Consulta |
| TERMCAT — Diccionari de les arts (arquitectura) | https://www.termcat.cat/es/diccionaris-en-linia/147 | Consulta (equivalentes ES) |
| CLARIN — Diccionario de Arquitectura ES-FR | https://dspace-clarin-it.ilc.cnr.it/items/3c295952-f221-4b35-9296-2f935f58090a | Revisar licencia (reportada CC BY 4.0) |

**Fuente estrella abierta:** Anexo Wikipedia (mejor para lemas legales de extraer). **Validación:** TERMCAT / ARTEGUIAS.

---

## 6. Estudios Políticos y Relaciones Internacionales (`dict-ua-pol`)

| Referencia | URL | Uso recomendado |
|------------|-----|-----------------|
| Tesauro UNBIS (ONU) — español | https://metadata.un.org/skosmos/thesaurus/ar/page/00?clang=es | **Principal marco abierto** |
| Tesauro UNBIS (interfaz alternativa) | https://vocabularyserver.com/unbis/es/ | Consulta |
| Diccionario de RR.II. y Política Exterior (Pereira / Ariel) | https://www.dykinson.com/libros/diccionario-de-relaciones-internacionales-y-politica-exterior-9788434409446/ | Consulta comercial (**no** copia literal) |
| Diccionario LID de diplomacia y RR.II. | editorial Tirant / LID | Consulta comercial |
| DPEJ (RAE) — términos de derecho público / internacional | https://dpej.rae.es/ | Solape útil con Derecho |
| Sitios oficiales ONU / OEA / UE | sitios institucionales | Semilla de siglas y nombres oficiales |

**Fuente estrella abierta:** Tesauro UNBIS. **Complemento:** DPEJ + syllabus UFM.

---

## 7. Resumen: una fuente estrella por facultad

| Facultad | Código | Fuente estrella (consulta) | Fuente más abierta para lemas |
|----------|--------|----------------------------|--------------------------------|
| Medicina | `med` | https://dptm.es/ | MedLexSp (con licencia) / syllabus |
| Odontología | `odo` | ACODES PDF | Fundación Dental + syllabus |
| Derecho | `der` | https://dpej.rae.es/ | Glosarios OJ/URL GT + syllabus |
| Economía | `eco` | Glosario BdE | IMF Glossary + syllabus |
| Arquitectura | `arq` | Wikipedia terminología arquitectura | Wikipedia CC BY-SA |
| Política / RR.II. | `pol` | Tesauro UNBIS | UNBIS + syllabus |

---

## 8. Cómo usarlo sin romper licencias

1. **No** descargar y pegar DPEJ/DPTM entero en el repositorio.
2. **Sí** documentar cada fuente en `_shared/licenses/` (URL, uso, fecha).
3. Flujo seguro:
   - spellcheck de syllabus reales → falsos positivos
   - validar el término contra la fuente estrella
   - meter **solo esos lemas** en `ua_<area>_GT.dic`
4. Si se quiere "todo el diccionario": acuerdo escrito con el dueño, o solo fuentes CC/LGPL/dominio público.

---

## 9. Siguiente paso sugerido

1. Elegir orden de facultades (recomendado: Odontología → Derecho → Economía).
2. Por cada una: carpeta `dictionaries/dict-ua-<area>/` + `REFERENCES.md` (subconjunto de esta tabla).
3. Semilla desde syllabus + validación contra la fuente estrella.
