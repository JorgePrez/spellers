# Hunspell vs LLM en el spellcheck UA — preguntas y respuestas

**Fecha:** 27/07/2026  
**Contexto:** servicio `spellers-main` (Flask + LibreOffice UNO + diccionarios Hunspell `dict-ua-*` en locale `es-GT`).  
**Producto actual:** marcar errores ortográficos en cronogramas (`POST /spellcheck/mark`); el catedrático corrige; no hay auto-corrección.

Este documento responde dudas estratégicas sobre si “reinventamos la rueda”, cómo encajaría un LLM, costos, y hasta dónde conviene ampliar diccionarios.

---

## 1. ¿Estamos reinventando la rueda? ¿Por qué no mejor un LLM?

**No estamos reinventando la rueda.** Estamos usando la rueda correcta para el trabajo:

| Capa | Qué es | Rol |
|------|--------|-----|
| LibreOffice + Hunspell | Corrector ortográfico maduro, determinista | Detectar tokens inválidos en `es-GT` |
| `es_GT` oficial | Léxico general español Guatemala | Base |
| `dict-ua-*` | Extensiones de dominio (med, odo, der, …) | Bajar falsos positivos de syllabus UFM |

Un LLM **no sustituye bien** a un spellchecker clásico como primera línea, porque:

1. **El problema es léxico + locale, no “entender el texto”.**  
   Un cronograma marca en rojo `amelogénesis` o `casación` porque **no están en el diccionario**, no porque el modelo “no entienda medicina/derecho”.
2. **Hunspell es barato, rápido y reproducible.**  
   Misma palabra → mismo resultado. Un LLM puede dudar, inventar o cambiar criterio entre corridas.
3. **La decisión de producto ya fue: marcar, no reescribir.**  
   Un LLM tiende a “mejorar” estilo, tono o contenido; eso es riesgoso en documentos académicos oficiales.
4. **Ortografía prioritaria es una regla dura.**  
   Si existe `técnico`, nunca aceptar `tecnico`. Eso se implementa limpio en filtros de diccionario; con LLM hay que pelear prompts y aún así hay fugas.
5. **Ya existe infraestructura.**  
   UNO + Flask + S3 + PHP syllabus. Cambiar a “todo LLM” sería otro producto, no un atajo.

**Conclusión:** los diccionarios UA no reinventan spellcheck; **especializan** el spellcheck estándar al dominio UFM. El LLM sería una capa *opcional* encima, no el reemplazo.

---

## 2. Idea: LibreOffice detecta → LLM valida si “realmente” es falta (con contexto)

### Cómo funciona hoy (sin LLM)

```
Documento → LibreOffice abre y tokeniza
         → SpellChecker.isValid(palabra, es-GT)
         → lista de errores únicos
         → se marca en el doc (rev_*)
```

LibreOffice ya da: palabra sospechosa, a veces sugerencias, y posición en el documento (según `spellcheck_core` / mark).

### Enfoque híbrido propuesto

```
1) Hunspell/LO marca candidatos (barato, masivo)
2) Solo esos candidatos van a un LLM (caro, selectivo)
3) El LLM responde: "sí es error" | "falso positivo de dominio" | "dudoso"
4) Solo se marcan en el Word los que el LLM confirma como error
   (o se dejan los dudosos para revisión humana)
```

### ¿Hace falta contexto de las palabras de alrededor?

**Sí, casi siempre.** Una palabra sola es ambigua:

| Token | Sin contexto | Con contexto |
|-------|--------------|--------------|
| `ATM` | ¿sigla? ¿error? | “…articulación temporomandibular (ATM)…” → OK odontología |
| `paper` | ¿inglés suelto? | “…entregar el paper del workshop…” → OK campus (`ang`) |
| `casacion` | casi seguro error | “…recurso de casacion…” → falta de tilde (`casación`) |
| `PAES` | ¿typo? | “…admisión PAES UFM…” → sigla válida (`uni`) |

**Contexto mínimo recomendado por candidato:**

- Oración completa (o ±30–50 tokens alrededor).
- Facultad / diccionario esperado (`med`, `der`, …) si el syllabus lo conoce.
- Locale fijo: español Guatemala; no “reescribir el texto”.
- Reglas duras: no aceptar formas sin tilde si existe la acentuada; no inventar términos.

**Prompt tipo (esquema):**

```text
Eres validador ortográfico en español (Guatemala).
NO corrijas estilo. NO reescribas el documento.
Dado el fragmento y el token marcado, responde JSON:
{ "token": "...", "es_error": true|false, "motivo": "ortografia|dominio|sigla|dudoso" }

Fragmento: "..."
Token marcado: "casacion"
Facultad: derecho
```

### Pros y contras del híbrido

| Pros | Contras |
|------|---------|
| Reduce falsos positivos que el diccionario aún no cubre | Latencia y costo por documento con muchos candidatos |
| Aprovecha contexto (siglas, anglicismos de campus) | El LLM puede “perdonar” faltas reales (`tecnico`) si el prompt es flojo |
| No tira Hunspell: sigue siendo el filtro 1 | Hay que loguear decisiones (auditoría académica) |
| Encaja con “solo marcar” | Complejidad operativa (API keys, timeouts, cuotas) |

**Veredicto:** la idea es **buena como segunda capa**, no como reemplazo. El contexto alrededor **sí es necesario**. Empieza solo con candidatos Hunspell, nunca con todo el documento palabra por palabra.

---

## 3. ¿Un enfoque solo con LLM no serviría y sería más caro?

**Correcto en ambos puntos.**

### Por qué no sirve solo LLM (para este producto)

1. **Costo:** un cronograma puede tener miles de tokens. Spellcheck clásico es casi gratis en CPU local; LLM cobra por token en **cada** revisión.
2. **No determinista:** hoy el catedrático espera “esta palabra está mal”. Un LLM puede variar.
3. **Riesgo de sobre-corrección:** marca estilo, redacción o “mejora” académica no pedida.
4. **Peor en listas densas de términos técnicos:** un diccionario de dominio gana en `amelogénesis`, `exequatur`, `planimetría`.
5. **Trazabilidad:** “¿por qué se marcó?” con Hunspell = “no está en el léxico”. Con LLM = explicación blanda.

### Cuándo un LLM puro *sí* tendría sentido

- Revisión de **estilo / claridad** (otro producto).
- Sugerencias de redacción al catedrático (opt-in).
- Clasificar el **tipo** de hallazgo (ortografía vs gramática vs formato), encima de LO.

Para **marcar faltas ortográficas en masa en syllabus**, Hunspell (+ diccionarios UA) gana.

---

## 4. ¿Extraer el texto con LibreOffice ayuda a la parte del LLM?

**Sí, mucho.** Ya tienen una ventaja estructural:

| Capacidad LO que ya usan | Cómo ayuda al LLM |
|--------------------------|-------------------|
| Abrir Word/Excel/PPT/PDF vía UNO | No reinventan parsers frágiles |
| Tokenización / spellcheck nativo | Reducen el input del LLM a **candidatos**, no al doc entero |
| Posiciones / marcado en el documento | Pueden marcar solo lo confirmado, sin regenerar el archivo con un modelo |
| Locale `es-GT` unificado | El LLM no tiene que “adivinar” el dialecto del corrector |

Flujo híbrido natural con lo que hay:

```
LibreOffice (extracción + isValid)
    → candidatos + snippet de contexto (+ página/párrafo si se puede)
    → LLM validador (JSON es_error)
    → misma tubería de marcado rev_* / S3
```

Sin LibreOffice, el LLM tendría que parsear formatos, segmentar palabras, y aún así no tendría un corrector determinista de referencia.

**Conclusión:** LO no es solo “motor ortográfico”; es también **pipeline documental**. Eso hace viable (y más barato) un LLM *selectivo*.

---

## 5. ¿Cuál sería la mejor forma, analizando todo lo que tenemos?

### Recomendación de arquitectura (por fases)

**Fase A — Mantener y endurecer lo actual (ahora)**  
Prioridad #1 del producto.

1. Hunspell `es_GT` + paquetes `dict-ua-*` por facultad / uso.
2. Ortografía prioritaria (nunca whitelistear `tecnico` si existe `técnico`).
3. Semilla real = falsos positivos de syllabus UFM (no volcar DPEJ/DPTM enteros).
4. Pruebas con los cronogramas `.docx` de `dictionaries/test_cronogramas/`.

**Fase B — Telemetría de falsos positivos (barato, alto ROI)**  
Antes de gastar en LLM:

1. Log de tokens marcados más frecuentes por facultad.
2. Revisión humana semanal → agregar lemas correctos al `dict-ua-*` correspondiente.
3. Denylist explícita de faltas conocidas.

Esto baja ruido **sin** costo de API.

**Fase C — LLM híbrido opcional (si el ruido residual duele)**  

Condiciones para activarlo:

- Solo si tras A+B siguen muchos falsos positivos “contextuales” (siglas raras, nombres propios, anglicismos nuevos).
- Solo candidatos `isValid == false`.
- Contexto de oración + facultad.
- Respuesta estructurada; por defecto **conservador**: si el LLM duda, **mantener la marca** (mejor falso positivo que dejar pasar `casacion`).
- Feature flag / facultad piloto (p. ej. solo `ang` + `uni`).
- Presupuesto y timeout claros.

**Fase D — No hacer (por ahora)**  

- Reemplazar Hunspell por LLM.
- Auto-corregir el documento con LLM.
- Enviar el documento completo al modelo en cada `/spellcheck/mark`.

### Diagrama objetivo

```
Syllabus PHP
   → POST /spellcheck/mark
      → LO UNO: abrir + Hunspell es-GT (+ dict-ua-*)
      → candidatos
      → [opcional] LLM filtro de falsos positivos
      → marcar rev_* → S3
```

**Mejor forma hoy:** **A + B**. El híbrido C es un *plus* medible, no el centro.

---

## 6. ¿Conviene ampliar más los diccionarios? ¿Hasta qué punto?

### Sí conviene ampliar, pero con techo de calidad

Ampliar ayuda mientras:

- los lemas nuevos son **reales** en syllabus UFM, y
- pasan el filtro de ortografía prioritaria.

Ampliar **deja de convenir** cuando:

- se generan combinaciones sintéticas absurdas solo para subir el conteo,
- se cuelan formas sin tilde,
- o el paquete empieza a aceptar ruido que `es_GT` correctamente rechazaba.

### Puntos de referencia actuales (aprox.)

| Área | Lemas | Lectura |
|------|------:|---------|
| Medicina | ~125k | Ya muy amplio; crecer solo por falsos positivos reales |
| Odontología / Derecho / Arquitectura | ~47k–59k | Rango sano tipo “especialidad expandida” |
| Eco / Pol / Psi / Uni | ~35k–40k | Suficiente para v1; crecer por syllabus |
| Anglicismos | ~12k | Dominio estrecho; no forzar a 40k |

### Regla práctica de “hasta dónde”

1. **Señal de parar de inflar morph:** si al revisar 50 falsos positivos nuevos del syllabus, ≥80% ya están cubiertos o son typos reales → el léxico está “maduro”.
2. **Crecer por evidencia, no por meta de N palabras.**  
   Fuente primaria = documentos UFM marcados en rojo por error.
3. **Un paquete por facultad**, no un mega-diccionario único.
4. **Tope blando por especialidad:** ~40k–60k suele ser el punto de rendimientos decrecientes (odo/der/arq ya están ahí). Medicina es excepción por amplitud clínica.
5. **Nunca** desactivar `ortho_priority` para “ganar lemas”.
6. **Anglicismos (`ang`)** crecen con uso real de campus, no con morph español.

### Relación con el LLM

- Si el diccionario está bien mantenido (Fase B), el LLM híbrido se necesita **menos**.
- Si amplían mal (ruido), el LLM tendrá que “reparar” basura léxica → peor de los mundos (caro + impredecible).

**Respuesta corta:** sí ampliar, **hasta cubrir falsos positivos reales de syllabus**; no hasta un número arbitrario. Con el tamaño actual, el ROI de “más morph sintético” baja; el ROI de “lemas sacados de documentos reales” sigue alto.

---

## Resumen ejecutivo

| # | Pregunta | Respuesta corta |
|---|----------|-----------------|
| 1 | ¿Reinventamos la rueda / mejor LLM? | No. Hunspell+dominio es el enfoque correcto para marcar ortografía. |
| 2 | ¿LO → LLM con contexto? | Buena **segunda capa**; sí necesita contexto de oración. |
| 3 | ¿Solo LLM? | No sirve bien para este producto y sale más caro. |
| 4 | ¿LO ayuda al LLM? | Sí: extracción, candidatos y marcado ya listos. |
| 5 | ¿Mejor forma? | Diccionarios UA + telemetría de FP; LLM híbrido opcional después. |
| 6 | ¿Ampliar más? | Sí, por evidencia de syllabus; techo de calidad, no de vanity count. |

---

## Siguiente paso sugerido (si se decide experimentar LLM)

1. Medir top-100 tokens marcados por facultad en una semana real.  
2. Meter en diccionario lo que sea lema correcto.  
3. Solo el residuo contextual → piloto LLM con feature flag, JSON estricto y presupuesto.  
4. Comparar: precisión / costo / latencia vs solo Hunspell.

Documentos relacionados:

- [01_contexto_y_decisiones.md](01_contexto_y_decisiones.md)
- [11_guia_crear_y_actualizar_diccionarios_ua.md](11_guia_crear_y_actualizar_diccionarios_ua.md)
- [../dictionaries/README.md](../dictionaries/README.md)
