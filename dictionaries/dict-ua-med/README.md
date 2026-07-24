# Diccionario medico UA (es-GT) para spellcheck LibreOffice

Extension Hunspell con **>= 60 000 terminos medicos** en espanol para reducir falsos positivos en el servicio `spellers-main` (locale `es-GT`).

**Version:** 2.2.0 (~133 000 lemas en la build actual)

## Contenido

| Archivo | Descripcion |
|---------|-------------|
| `ua_med_GT.dic` | Lista Hunspell (UTF-8, LF) |
| `ua_med_GT.aff` | Reglas minimas (`SET UTF-8`); en EC2 el install copia `es_GT.aff` |
| `dictionaries.xcu` | Registro LibreOffice, locale `es-GT` |
| `description.xml` | Metadatos (`org.ua.dictionaries.med-gt` v2.2.0) |
| `gen_all.py` + `gen_lexicon_*.py` | Generadores del lexico base |
| `gen_expand_60k.py` | Expansion amplia (>=60k): morfologia + freq_list + semillas |
| `filter_freq_medical.py` | Filtra lemas medicos desde lista de frecuencias abierta |
| `verify_dic.py` | Valida conteo, UTF-8 y duplicados |
| `install_dict_ua_med.sh` | Instalacion en EC2 via `unopkg` |
| `source/external/` | Fuentes companion + `expanded_60k.txt` |
| `source/user_examples_med.txt` | Semillas clinicas (incl. ejemplos de validacion) |

## Como se genero (v2.2)

1. Base curada (anatomia, patologias, procedimientos, especialidades)
2. Morfologia medica (raices + sufijos productivos) + farmacos clinicos
3. Expansion amplia (`gen_expand_60k.py`): combos prefijo/raiz/sufijo + filtro amplio de `freq_list.txt` (companion MedLexSp) + variantes genero/numero/Title Case
4. Semillas clinicas (somitas, miofibroblastos, pericitos, farmacodinamica, coagulativa, Cariotipo, mosaicismo, laminillas, electrocardiografica, etc.)
5. Plurales conservadores + dedupe + denylist de ruido

## Regenerar

```bash
cd spellers-main/dictionaries/dict-ua-med
python filter_freq_medical.py   # si existe source/external/freq_list.txt
python gen_expand_60k.py
python gen_all.py
python verify_dic.py
```

## Referencias (marco / companion)

| Referencia | URL | Uso |
|------------|-----|-----|
| DPTM | https://dptm.es/ | Consulta / marco |
| DTM RANME | http://dtme.ranm.es/ | Consulta / marco |
| MedLexSp companion (`freq_list`) | https://github.com/lcampillos/MedLexSp | Filtro de lemas (no es el lexico MedLexSp completo con licencia) |
| CIE-11 | https://icd.who.int/ | Marco |
| Extensiones LO | https://wiki.openoffice.org/wiki/Extension_Dictionaries | Formato OXT |

> No se volco DPTM/MedLexSp completo (licencia). Se curaron lemas + morfologia + filtro abierto del companion.

## Reinstalar en el server (ya estando en el EC2)

Asume que `dict-ua-med` ya esta en:

`/home/ec2-user/libreoffice_spellcheck/dictionaries/dict-ua-med/`

```bash
cd /home/ec2-user/libreoffice_spellcheck/dictionaries/dict-ua-med
sed -i 's/\r$//' install_dict_ua_med.sh diagnose_dict_ec2.sh description.xml ua_med_GT.dic ua_med_GT.aff
sudo bash install_dict_ua_med.sh
bash diagnose_dict_ec2.sh
```

Si queda una copia manual vieja (no registrada con unopkg):

```bash
sudo rm -rf /opt/libreoffice25.8/share/extensions/dict-ua-med
```

Luego vuelve a ejecutar `sudo bash install_dict_ua_med.sh`.

## Verificar

```bash
/opt/libreoffice25.8/program/unopkg list --shared | grep -A3 org.ua.dictionaries.med-gt
```

Esperado: extension `org.ua.dictionaries.med-gt` v2.2.0 y prueba de terminos como `somitas`, `miofibroblastos`, `farmacodinamica` / `abdominoplastia` = validas.

LibreOffice combina `es_GT.dic` + `ua_med_GT.dic` para locale `es-GT`.
