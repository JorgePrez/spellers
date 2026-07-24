# Diccionario odontologico UA (es-GT) para spellcheck LibreOffice

Extension Hunspell con terminos de **odontologia** en espanol.

**Version:** 1.1.0 (~47 000 lemas; ortografia prioritaria)

## Fuentes / marco usadas para ampliar

- Semillas de syllabus (amelogenesis, dentinogenesis, alveoloplastia, pulpectomia, etc.)
- Morfologia dental productiva (raices + sufijos acentuados)
- Filtro dental/oral sobre companion MedLexSp `freq_list` + solape clinico con `dict-ua-med`
- Consulta de glosarios: ACODES, Fundacion Dental Espanola, Odontocat, U. Chile (marco; no volcado literal)

## Regenerar

```bash
cd spellers-main/dictionaries/dict-ua-odo
python gen_expand_odo.py
python gen_all.py
python verify_dic.py
```

## Reinstalar en el server

```bash
cd /home/ec2-user/libreoffice_spellcheck/dictionaries/dict-ua-odo
sed -i 's/\r$//' install_dict_ua_odo.sh diagnose_dict_ec2.sh description.xml ua_odo_GT.dic ua_odo_GT.aff
sudo bash install_dict_ua_odo.sh
bash diagnose_dict_ec2.sh
```

Id: `org.ua.dictionaries.odo-gt` v1.1.0
