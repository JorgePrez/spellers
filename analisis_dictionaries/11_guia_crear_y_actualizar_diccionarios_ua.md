# Guía: crear y actualizar un diccionario UA (Hunspell / es-GT)

**Fecha:** 24/07/2026  
**Para:** futuros diccionarios por facultad (`dict-ua-<area>`) y mantenimiento de los existentes.  
**Modelos de referencia en el repo:**

| Área | Carpeta | Id extensión | Tamaño orientativo |
|------|---------|--------------|--------------------|
| Medicina | `dictionaries/dict-ua-med/` | `org.ua.dictionaries.med-gt` | amplio (decenas–cientos de miles; con filtro ortográfico) |
| Odontología | `dictionaries/dict-ua-odo/` | `org.ua.dictionaries.odo-gt` | ~6k–12k (especialidad) |
| Derecho | `dictionaries/dict-ua-der/` | `org.ua.dictionaries.der-gt` | ~5k–12k (especialidad + latinismos) |

LibreOffice **fusiona** todos los diccionarios del mismo locale `es-GT` (`es_GT` oficial + cada `ua_*_GT`).

---

## 1. Qué es lo que estamos construyendo

No es un diccionario "completo" del español. Es una **extensión Hunspell complementaria** que:

1. Añade **términos de dominio** (medicina, odontología, derecho, …) que `es_GT` marca como error.
2. **No reemplaza** `es_GT.dic`.
3. Se instala con `unopkg --shared` en el EC2 del servicio `spellers-main`.
4. Debe **nunca** aceptar faltas de ortografía (prioridad ortográfica > vocabulario de área).

---

## 2. Reglas de producto (no negociables)

| Regla | Por qué |
|-------|---------|
| Locale fijo: `es-GT` | El servicio UNO usa `make_locale("es","GT")` |
| UTF-8 + LF (sin CRLF) | Windows corrompe contadores / tildes si se sube mal |
| Ortografía primero | Si existe `técnico`, **no** incluir `tecnico` |
| Un paquete por facultad | `dict-ua-med`, `dict-ua-odo`, `dict-ua-der`, … |
| Fuentes = marco / consulta | No volcar DPTM, DPEJ, ACODES, etc. enteros sin licencia |
| Semilla real = syllabus UFM | Los falsos positivos de documentos reales mandan |
| Tamaño razonable por área | Especialidad: miles–decenas de miles; no "rellenar" a 60k con ruido |

---

## 3. Estructura mínima de carpeta

Crear (copiar plantilla desde `dict-ua-odo` o `dict-ua-der`):

```
dictionaries/dict-ua-<area>/
  description.xml          # id org.ua.dictionaries.<area>-gt + version
  dictionaries.xcu         # registra ua_<area>_GT.aff/.dic en es-GT
  META-INF/manifest.xml
  ua_<area>_GT.aff         # mínimo: SET UTF-8 (install puede copiar es_GT.aff)
  ua_<area>_GT.dic         # línea 1 = conteo; resto = 1 palabra por línea
  gen_all.py               # orquesta léxico + filtro ortografía + escribe .dic
  gen_lexicon*.py          # bloques curados (unicode_escape, ASCII-safe)
  gen_morph.py             # morfología del área (sufijos CON tilde)
  ortho_priority.py        # filtro: sin-tilde / inglés / sufijos malos
  verify_dic.py            # valida conteo, UTF-8, duplicados
  install_dict_ua_<area>.sh
  diagnose_dict_ec2.sh
  README.md
  .gitattributes           # eol=lf, UTF-8
  source/
    ua_<area>_lexicon_full.txt   # dump legible (generado)
    external/
      es_GT.dic / es_ES.dic      # referencia ortográfica (no van en el .oxt)
```

### Metadatos clave

En `description.xml`:

- `identifier`: `org.ua.dictionaries.<area>-gt` (único)
- `version`: subir en cada release (ej. `1.0.0` → `1.1.0`)

En `dictionaries.xcu`:

- Nodo Hunspell distinto (ej. `HunSpellDic_ua_der_GT`)
- `Locales` = `es-GT`
- Rutas `%origin%/ua_<area>_GT.aff` y `.dic`

---

## 4. Cómo se genera el léxico (flujo)

```
bloques curados (gen_lexicon*.py)
    + morfología de área (gen_morph.py)     [solo sufijos acentuados]
    + opcional: filtro de listas abiertas
    + plurales conservadores / Title Case puntual
        |
        v
ortho_priority.py   <-- OBLIGATORIO
        |
        v
ua_<area>_GT.dic + source/ua_<area>_lexicon_full.txt
        |
        v
verify_dic.py
```

### Qué poner en los bloques curados

- Anatomía / conceptos / procedimientos / especialidades del área
- Variantes de género útiles (`odontólogo`, `odontóloga`)
- Siglas locales solo si aparecen en syllabus (PNC, SAT, ATM, …)
- Latinismos (derecho) como **tokens sueltos** (`habeas`, `exequatur`)

### Qué NO poner

- Formas sin tilde si la correcta existe (`casacion` si hay `casación`)
- Inglés genérico (`the`, `ing`, `tion`, …)
- Frases multi-palabra en una sola línea (Hunspell valida **token a token**)
- Basura sintética (`hipercardio…`) o combinaciones absurdas solo para inflar conteo

### Codificación en generadores (Windows)

Los `.py` fuente deben ser **ASCII + escapes `\u00e1`** (o UTF-8 real bien guardado).  
Evitar pegar tildes "a ojo" desde PowerShell: corrompe archivos.

---

## 5. Filtro ortográfico (`ortho_priority.py`)

Prioridad: **falta de ortografía > vocabulario de área**.

Hace, entre otras cosas:

1. Carga `es_GT.dic` / `es_ES.dic` de referencia.
2. Si una forma **sin tilde** coincide (sin acentos) con una forma **con tilde** en la referencia o en el propio léxico → **se elimina** la sin tilde.
3. Elimina sufijos productivos mal acentuados (`-cion`, `-logia`, `-dinamica`, …).
4. Elimina ruido inglés típico.

**Nunca** desactivar este filtro "para tener más palabras".

Referencias `es_GT`/`es_ES` se pueden copiar desde:

`dictionaries/dict-ua-med/source/external/`

---

## 6. Tamaños orientativos (calidad > cantidad)

| Tipo de área | Rango sano | Nota |
|--------------|------------|------|
| Especialidad (odo, der, eco, arq, pol) | **5 000–15 000** | Suficiente + curado |
| Área muy amplia (medicina) | **decenas de miles+** | Solo con filtro ortográfico fuerte |
| Evitar | inflar a 60k+ con ruido | White-listea faltas y basura |

Mejor fuente de crecimiento: **falsos positivos reales** de syllabus, no scraping ciego.

---

## 7. Crear un diccionario NUEVO (checklist)

1. Elegir código corto: `eco`, `arq`, `pol`, …  
2. Copiar carpeta `dict-ua-odo` → `dict-ua-<area>` (plantilla limpia).  
3. Renombrar archivos `ua_odo_*` → `ua_<area>_*`.  
4. Cambiar en XML/scripts:
   - id `org.ua.dictionaries.<area>-gt`
   - nombres de `.dic` / `.aff` / `.oxt`
   - textos de diagnóstico  
5. Reescribir bloques de léxico del área (ver `10_referencias_diccionarios_por_facultad.md`).  
6. Ajustar raíces/sufijos en `gen_morph.py` al dominio.  
7. Asegurar `source/external/es_GT.dic` (+ `es_ES.dic`).  
8. Ejecutar:

```bash
cd dictionaries/dict-ua-<area>
python gen_all.py
python verify_dic.py
```

9. Probar localmente palabras típicas del área y faltas (`imagenes`, `tecnico`) → deben fallar.  
10. Subir carpeta al EC2 e instalar (sección 9).  
11. Documentar en `analisis_dictionaries/00_indice.md` y `07_archivos_en_repositorio.md`.  
12. Subir `version` en `description.xml`.

---

## 8. Actualizar un diccionario EXISTENTE

### A) Añadir términos (caso normal)

1. Agregar lemas a `gen_lexicon*.py` (con tilde correcta) **o** a un `source/user_examples_<area>.txt`.  
2. Regenerar:

```bash
cd dictionaries/dict-ua-<area>
python gen_all.py
python verify_dic.py
```

3. Bump de versión en `description.xml` (ej. `1.0.0` → `1.1.0`).  
4. Subir al server y reinstalar (sección 9).

### B) Corregir que se aceptaba una falta

1. Confirmar que la forma mala está en el `.dic`.  
2. Reforzar `ortho_priority.py` / denylist / quitar la forma de la semilla.  
3. Regenerar + verificar que la falta ya **no** aparece.  
4. Reinstalar.

### C) Medicina (pipeline largo)

```bash
cd dictionaries/dict-ua-med
python filter_freq_medical.py   # si hay freq_list.txt
python gen_expand_60k.py        # expansión amplia
python gen_all.py               # incluye ortho_priority
python verify_dic.py
```

---

## 9. Instalar / actualizar en el EC2

Asume que ya estás en el server y la carpeta está en:

`/home/ec2-user/libreoffice_spellcheck/dictionaries/dict-ua-<area>/`

```bash
cd /home/ec2-user/libreoffice_spellcheck/dictionaries/dict-ua-<area>
sed -i 's/\r$//' install_dict_ua_<area>.sh diagnose_dict_ec2.sh description.xml ua_<area>_GT.dic ua_<area>_GT.aff
sudo bash install_dict_ua_<area>.sh
bash diagnose_dict_ec2.sh
```

Comprobar:

```bash
/opt/libreoffice25.8/program/unopkg list --shared | grep org.ua.dictionaries
```

Si no toma el cambio:

```bash
sudo rm -rf /opt/libreoffice25.8/share/extensions/dict-ua-<area>
sudo bash install_dict_ua_<area>.sh
```

El script de install suele: normalizar UTF-8, empaquetar `.oxt`, `unopkg remove` + `add --shared`, reiniciar `libreoffice-uno` y `spellcheck-flask`.

### Instalar varios a la vez (ej. odo + der)

Repetir el bloque por cada carpeta. LibreOffice los combina todos en `es-GT`.

---

## 10. Pruebas mínimas antes de dar por bueno

| Prueba | Esperado |
|--------|----------|
| Término típico del área con tilde | `isValid` = true |
| Misma palabra sin tilde (si la correcta lleva tilde) | `isValid` = false |
| Faltas generales (`imagenes`, `tecnico`, `revision`) | false (las cubre `es_GT` / no están en UA) |
| Palabra basura inventada | false |
| `verify_dic.py` | OK (conteo = líneas, 0 duplicados exactos) |
| `unopkg list` | aparece `org.ua.dictionaries.<area>-gt` con la versión nueva |

---

## 11. Problemas típicos (y solución)

| Síntoma | Causa habitual | Qué hacer |
|---------|----------------|-----------|
| Deja pasar `tecnico` / `imagenes` | Formas sin tilde entraron al `.dic` | Regenerar con `ortho_priority`; reinstalar |
| Tildes rotas / mojibake | Archivo en cp1252 / CRLF desde Windows | `sed` + reescritura UTF-8 del install; regenerar en UTF-8 |
| Contador no coincide | Línea 1 del `.dic` desfasada | `verify_dic.py` + install recalcula |
| Extensión no aparece | Copia manual en `share/extensions` o no se usó unopkg | `unopkg add --shared`; borrar copia manual vieja |
| "No cambió nada" tras subir archivos | No se reinstaló / servicios viejos | volver a `install_*.sh` |

Detalle histórico: `05_problemas_encontrados_y_soluciones.md`.

---

## 12. Licencias y fuentes

- Ver listado por facultad: [`10_referencias_diccionarios_por_facultad.md`](10_referencias_diccionarios_por_facultad.md).  
- Usar diccionarios grandes (DPEJ, DPTM, ACODES, MedLexSp) como **consulta / marco**, no dump literal, salvo licencia clara.  
- Listas companion abiertas (ej. `freq_list` MedLexSp) solo con filtro de dominio + ortografía.  
- Atribuir en el README del paquete cuando se reutilice material abierto.

---

## 13. Resumen operativo (una página)

**Crear:** plantilla OXT + léxico curado + morph acentuado + `ortho_priority` + `verify` + install.  
**Actualizar:** editar semillas → `gen_all.py` → `verify_dic.py` → bump versión → subir → `sudo bash install_*.sh`.  
**Prioridad:** ortografía correcta siempre; el tamaño es secundario.  
**Probar:** términos de área OK + faltas sin tilde NO + `unopkg list` muestra la versión nueva.

Plantillas listas para copiar:

- Especialidad corta: `dictionaries/dict-ua-odo/` o `dictionaries/dict-ua-der/`
- Área masiva + expansión: `dictionaries/dict-ua-med/`
