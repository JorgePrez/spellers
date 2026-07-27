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

## Inventario multi-facultad (24/07/2026, post-expansión)

Todos bajo `spellers-main/dictionaries/`. Expansión tipo odontolía: `gen_expand_<area>.py` + harvest `es_*` + `ortho_priority`.

| Código | Carpeta | Extensión LO | Lemas | Versión |
|--------|---------|--------------|------:|---------|
| med | `dict-ua-med` | `org.ua.dictionaries.med-gt` | 125455 | 2.3.0 |
| odo | `dict-ua-odo` | `org.ua.dictionaries.odo-gt` | 47198 | 1.1.0 |
| der | `dict-ua-der` | `org.ua.dictionaries.der-gt` | 59181 | 1.2.0 |
| eco | `dict-ua-eco` | `org.ua.dictionaries.eco-gt` | 40065 | 1.1.0 |
| arq | `dict-ua-arq` | `org.ua.dictionaries.arq-gt` | 46761 | 1.1.0 |
| pol | `dict-ua-pol` | `org.ua.dictionaries.pol-gt` | 40380 | 1.1.0 |
| psi | `dict-ua-psi` | `org.ua.dictionaries.psi-gt` | 38236 | 1.1.0 |
| uni | `dict-ua-uni` | `org.ua.dictionaries.uni-gt` | 35210 | 1.1.0 |
| ang | `dict-ua-ang` | `org.ua.dictionaries.ang-gt` | 12499 | 1.1.0 |

Motor compartido: `dictionaries/_shared/expand_engine.py`.
Instalación conjunta: `dictionaries/install_all_ua_dicts.sh`.
