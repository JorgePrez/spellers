# Diccionario medico UA (es-GT) para spellcheck LibreOffice

Extension Hunspell con **~11 800 terminos medicos** en espanol para reducir falsos positivos en el servicio `spellers-main` (locale `es-GT`).

**Version:** 2.1.0

## Contenido

| Archivo | Descripcion |
|---------|-------------|
| `ua_med_GT.dic` | Lista Hunspell (UTF-8, LF) |
| `ua_med_GT.aff` | Reglas minimas (`SET UTF-8`); en EC2 el install copia `es_GT.aff` |
| `dictionaries.xcu` | Registro LibreOffice, locale `es-GT` |
| `description.xml` | Metadatos (`org.ua.dictionaries.med-gt` v2.1.0) |
| `gen_all.py` + `gen_lexicon_*.py` | Generadores del lexico |
| `filter_freq_medical.py` | Filtra lemas medicos desde lista de frecuencias abierta |
| `verify_dic.py` | Valida conteo, UTF-8 y duplicados |
| `install_dict_ua_med.sh` | Instalacion en EC2 via `unopkg` |
| `source/external/` | Fuentes companion (freq list filtrada) |

## Como se genero (v2.1)

1. Base curada (anatomia, patologias, procedimientos, especialidades)
2. Morfologia medica (raices + sufijos productivos) + farmacos clinicos
3. Filtro morfologico sobre `freq_list.txt` del companion MedLexSp (GitHub)
4. Plurales conservadores + dedupe + denylist de ruido

## Regenerar

```bash
cd spellers-main/dictionaries/dict-ua-med
python filter_freq_medical.py   # si existe source/external/freq_list.txt
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

## Instalar en EC2

```bash
scp -r spellers-main/dictionaries/dict-ua-med \
  ec2-user@3.150.240.23:/home/ec2-user/libreoffice_spellcheck/dictionaries/

ssh ec2-user@3.150.240.23
cd /home/ec2-user/libreoffice_spellcheck/dictionaries/dict-ua-med
sed -i 's/\r$//' install_dict_ua_med.sh diagnose_dict_ec2.sh description.xml ua_med_GT.dic
sudo bash install_dict_ua_med.sh
bash diagnose_dict_ec2.sh
```

## Verificar

```bash
/opt/libreoffice25.8/program/unopkg list --shared | grep -A3 org.ua.dictionaries.med-gt
head -1 /path/via/unopkg/.../ua_med_GT.dic   # debe ser ~11797
```

LibreOffice combina `es_GT.dic` + `ua_med_GT.dic` para locale `es-GT`.
