# Diccionario Derecho UA (es-GT) para spellcheck LibreOffice

Extension Hunspell con terminos de **derecho** (ES + latinismos + instituciones GT).

**Version:** 1.0.0 (~5 400 lemas; ortografia prioritaria)  
**Id:** `org.ua.dictionaries.der-gt`

## Contenido tipico

Civil, penal, procesal, constitucional, administrativo, mercantil, laboral, internacional, latinismos (`habeas`, `exequatur`, `erga omnes` como tokens), instituciones GT.

## Regenerar

```bash
cd spellers-main/dictionaries/dict-ua-der
python gen_all.py
python verify_dic.py
```

## Instalar en el server (ya en el EC2)

```bash
cd /home/ec2-user/libreoffice_spellcheck/dictionaries/dict-ua-der
sed -i 's/\r$//' install_dict_ua_der.sh diagnose_dict_ec2.sh description.xml ua_der_GT.dic ua_der_GT.aff
sudo bash install_dict_ua_der.sh
bash diagnose_dict_ec2.sh
```

## Referencias (marco; no volcado literal)

- DPEJ (RAE) ~40k entradas de consulta
- Conceptos juridicos GT / glosarios OJ-URL
