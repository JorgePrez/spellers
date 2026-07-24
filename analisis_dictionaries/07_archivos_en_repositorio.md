# Archivos en el repositorio (spellers-main)

## Carpeta principal del diccionario

```
spellers-main/dictionaries/dict-ua-med/
```

| Archivo | Proposito |
|---------|-----------|
| `ua_med_GT.dic` | >=60k palabras medicas (UTF-8, LF); build v2.2 ~133k |
| `ua_med_GT.aff` | `SET UTF-8` (install copia es_GT.aff en EC2) |
| `dictionaries.xcu` | Registro extension LibreOffice |
| `description.xml` | Metadatos; id `org.ua.dictionaries.med-gt` v2.2.0 |
| `META-INF/manifest.xml` | Manifiesto OXT |
| `gen_all.py` | Generador principal del lexico |
| `gen_expand_60k.py` | Expansion amplia a >=60k |
| `gen_lexicon_extra.py` / `gen_lexicon_more.py` | Bloques de terminos |
| `build_ua_med_dic.py` | Alias de regeneracion |
| `install_dict_ua_med.sh` | Instalacion via unopkg |
| `diagnose_dict_ec2.sh` | Diagnostico en EC2 |
| `verify_dic.py` | Valida contador y duplicados del .dic |
| `README.md` | Guia rapida |
| `.gitattributes` | `eol=lf`, UTF-8 |
| `source/ua_med_lexicon_full.txt` | Dump legible generado |
| `source/user_examples_med.txt` | Semillas clinicas de validacion |
| `source/external/expanded_60k.txt` | Lexico expandido intermedio |

## Odontologia (`dict-ua-odo`)

```
spellers-main/dictionaries/dict-ua-odo/
```

| Archivo | Proposito |
|---------|-----------|
| `ua_odo_GT.dic` | ~6.5k terminos odontologicos (v1.0.0) |
| `description.xml` | id `org.ua.dictionaries.odo-gt` |
| `install_dict_ua_odo.sh` | Instalacion via unopkg |
| `ortho_priority.py` | Ortografia prioritaria (igual que medicina) |

## Derecho (`dict-ua-der`)

```
spellers-main/dictionaries/dict-ua-der/
```

| Archivo | Proposito |
|---------|-----------|
| `ua_der_GT.dic` | ~5.4k terminos juridicos (v1.0.0) |
| `description.xml` | id `org.ua.dictionaries.der-gt` |
| `install_dict_ua_der.sh` | Instalacion via unopkg |

## Servicio systemd (referencia)

```
spellers-main/spellcheck-flask.service
```

## Codigo spellcheck (no modificar para diccionario)

```
spellers-main/spellcheck_core.py   # make_locale("es","GT"), SpellChecker UNO
spellers-main/spellcheck_mark.py   # /spellcheck/mark
spellers-main/app.py
```

## Que copiar al servidor

Toda la carpeta:

```
spellers-main/dictionaries/dict-ua-med/
```

Al copiar, verificar en VS Code (barra inferior): **UTF-8** y **LF**.
