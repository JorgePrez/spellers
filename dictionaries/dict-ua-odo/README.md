# Diccionario odontologico UA (es-GT) para spellcheck LibreOffice

Extension Hunspell con terminos de **odontologia** en espanol para reducir falsos positivos en `spellers-main` (locale `es-GT`).

**Version:** 1.0.0 (~6 500 lemas odontologicos; ortografia prioritaria)

## Lecciones aplicadas (desde medicina)

- Complementa `es_GT` (no lo reemplaza)
- **Ortografia tiene prioridad** sobre el lexico (`ortho_priority.py`): nunca se aceptan faltas ni formas sin tilde si existe la correcta
- UTF-8 + LF; instalacion via `unopkg --shared`
- Fuentes = marco/consulta (ACODES, Fundacion Dental, etc.); no volcado literal con licencia cerrada

## Contenido

| Archivo | Descripcion |
|---------|-------------|
| `ua_odo_GT.dic` | Lista Hunspell |
| `ua_odo_GT.aff` | `SET UTF-8` (install copia `es_GT.aff` en EC2) |
| `dictionaries.xcu` | Registro LibreOffice `es-GT` |
| `description.xml` | Metadatos v1.0.0 |
| `gen_all.py` / `gen_lexicon*.py` / `gen_morph.py` | Generadores |
| `ortho_priority.py` | Filtro ortografia prioritario |
| `install_dict_ua_odo.sh` | Instalacion en EC2 |
| `diagnose_dict_ec2.sh` | Pruebas UNO |

## Regenerar

```bash
cd spellers-main/dictionaries/dict-ua-odo
python gen_all.py
python verify_dic.py
```

## Reinstalar en el server (ya estando en el EC2)

Sube la carpeta `dictionaries/dict-ua-odo/` al server y ejecuta:

```bash
cd /home/ec2-user/libreoffice_spellcheck/dictionaries/dict-ua-odo
sed -i 's/\r$//' install_dict_ua_odo.sh diagnose_dict_ec2.sh description.xml ua_odo_GT.dic ua_odo_GT.aff
sudo bash install_dict_ua_odo.sh
bash diagnose_dict_ec2.sh
```

Verificar:

```bash
/opt/libreoffice25.8/program/unopkg list --shared | grep -A3 org.ua.dictionaries.odo-gt
```

LibreOffice fusiona `es_GT` + `ua_med_GT` + `ua_odo_GT` para `es-GT`.

## Referencias (marco)

- ACODES ù Diccionario odontologico comentado
- Fundacion Dental Espanola ù Diccionario dental
- DPTM (solape oral/maxilofacial con medicina)
