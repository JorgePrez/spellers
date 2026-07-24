# Diccionario personalizado Hunspell (es-GT)

## Objetivo

Complementar `es_GT.dic` con terminos UA (medicina, siglas, materias) para reducir falsos positivos.

LibreOffice combina diccionarios del **mismo locale**:

```
es_GT.dic (oficial) + ua_med_GT.dic (UA) ? locale es-GT
```

## Estructura de extension (en repo)

```
spellers-main/dictionaries/dict-ua-med/
??? ua_med_GT.dic          # Lista Hunspell (linea 1 = cantidad de palabras)
??? ua_med_GT.aff          # Reglas (minimo: SET UTF-8; o copiar es_GT.aff)
??? dictionaries.xcu       # Registro LO: locale es-GT, DICT_SPELL
??? description.xml        # id: org.ua.dictionaries.med-gt (v2.0.0)
??? META-INF/manifest.xml
??? gen_all.py             # Generador principal
??? gen_lexicon_extra.py   # Bloques lexico (patologias, farmacos, etc.)
??? gen_lexicon_more.py    # Ampliacion + plurales
??? build_ua_med_dic.py    # Alias de regeneracion
??? install_dict_ua_med.sh
??? diagnose_dict_ec2.sh
??? verify_dic.py
```

### dictionaries.xcu (resumen)

- Nodo: `HunSpellDic_ua_med_GT`
- Locations: `%origin%/ua_med_GT.aff %origin%/ua_med_GT.dic`
- Locales: `es-GT`

### Formato ua_med_GT.dic

```
4355
abdominoplastia
...
Cardiología
cardiología
```

- **Linea 1:** numero exacto de palabras siguientes.
- **UTF-8** obligatorio (tildes en palabras).
- **LF** (Unix), no CRLF.
- Se permiten variantes de mayuscula (`Cardiología` + `cardiología`) para syllabus.

### description.xml

- Usar **UTF-8 real** o solo ASCII en el XML.
- Error tipico si cp1252: `Input is not proper UTF-8` en "Médico".

## Referencias terminologicas (marco, no copia literal)

| Fuente | URL |
|--------|-----|
| DPTM (RANME / ALANAM) | https://dptm.es/ |
| DTM RANME | http://dtme.ranm.es/ |
| SNOMED CT (Espana) | https://www.sanidad.gob.es/profesionales/hcdsns/areaRecursosSem/snomed-ct/preguntas.htm |
| Extension dictionaries LO | https://wiki.openoffice.org/wiki/Extension_Dictionaries |

## Contenido actual del .dic (v2.3.0)

- **>= 60 000 palabras** medicas. Build actual ~125k lemas.
- **Prioridad ortografica:** si una forma es falta (p. ej. sin tilde cuando la correcta existe en `es_GT`/`es_ES` o en el lexico), **no** se incluye, aunque sea "variante medica" informal.
- Regenerar: `python filter_freq_medical.py && python gen_all.py && python verify_dic.py`
- Instalar en EC2: `sudo bash install_dict_ua_med.sh`
