# Diccionario medico UA (es-GT) para spellcheck LibreOffice

Extension Hunspell con **~4300+ terminos medicos** en espanol para reducir falsos positivos en el servicio `spellers-main` (locale `es-GT`).

## Contenido

| Archivo | Descripcion |
|---------|-------------|
| `ua_med_GT.dic` | Lista Hunspell (UTF-8, LF) |
| `ua_med_GT.aff` | Reglas minimas (`SET UTF-8`); en EC2 el install copia `es_GT.aff` |
| `dictionaries.xcu` | Registro LibreOffice, locale `es-GT` |
| `description.xml` | Metadatos (`org.ua.dictionaries.med-gt` v2.0.0) |
| `META-INF/manifest.xml` | Manifiesto OXT |
| `gen_all.py` + `gen_lexicon_*.py` | Generador del lexico (ASCII + escapes Unicode) |
| `build_ua_med_dic.py` | Alias que regenera el `.dic` |
| `verify_dic.py` | Valida conteo, UTF-8 y duplicados |
| `install_dict_ua_med.sh` | Instalacion en EC2 via `unopkg` |
| `diagnose_dict_ec2.sh` | Diagnostico post-instalacion |
| `source/ua_med_lexicon_full.txt` | Dump legible del lexico generado |

## Regenerar el diccionario (local)

```bash
cd spellers-main/dictionaries/dict-ua-med
python gen_all.py
python verify_dic.py
```

## Referencias (marco conceptual, no copia literal)

| Referencia | URL |
|------------|-----|
| DPTM (RANME / ALANAM) | https://dptm.es/ |
| DTM RANME | http://dtme.ranm.es/ |
| SNOMED CT (Espana) | https://www.sanidad.gob.es/profesionales/hcdsns/areaRecursosSem/snomed-ct/preguntas.htm |
| CIE-11 (OMS) | https://icd.who.int/ |
| Extensiones diccionario LO | https://wiki.openoffice.org/wiki/Extension_Dictionaries |

Cobertura: anatomia, patologias, procedimientos, farmacologia, laboratorio, imagenologia, especialidades (mayuscula + minuscula para syllabus), siglas clinicas y terminos academicos UA.

## Instalar en EC2 (metodo correcto: unopkg)

1. Subir la carpeta actualizada al servidor (ruta del servicio):

```bash
scp -r spellers-main/dictionaries/dict-ua-med \
  ec2-user@3.150.240.23:/home/ec2-user/libreoffice_spellcheck/dictionaries/
```

2. En el EC2:

```bash
cd /home/ec2-user/libreoffice_spellcheck/dictionaries/dict-ua-med
sed -i 's/\r$//' install_dict_ua_med.sh diagnose_dict_ec2.sh description.xml ua_med_GT.dic
sudo bash install_dict_ua_med.sh
```

El script convierte a UTF-8, recalcula el contador del `.dic`, empaqueta `.oxt`, ejecuta `unopkg add --shared -f` y reinicia servicios.

3. Verificar:

```bash
/opt/libreoffice25.8/program/unopkg list --shared | grep -A3 org.ua.dictionaries.med-gt
bash diagnose_dict_ec2.sh
```

### Prueba SpellChecker (usar escapes Unicode en el shell)

```bash
cd /home/ec2-user/libreoffice_spellcheck
/opt/libreoffice25.8/program/python - <<'PY'
from spellcheck_core import connect, make_locale
ctx = connect()
spell = ctx.ServiceManager.createInstanceWithContext(
    "com.sun.star.linguistic2.SpellChecker", ctx
)
loc = make_locale("es", "GT")
for w in [
    "abdominoplastia",
    "neuromielitis",
    "Cardiolog\u00eda",
    "cardiolog\u00eda",
    "pancreatoduodenectom\u00eda",
    "Introduccion",
    "hola",
]:
    print("%-28s -> valido=%s" % (w, spell.isValid(w, loc, ())))
PY
```

Esperado: medicas `True`; `Introduccion` `False`; `hola` `True` (diccionario base).

## Notas importantes

- LibreOffice **combina** `es_GT.dic` + `ua_med_GT.dic` para locale `es-GT`.
- No basta copiar carpetas a `share/extensions`; hay que usar **`unopkg`**.
- Archivos en **UTF-8** y **LF** (no CRLF). Ver `.gitattributes`.
- Eliminar copia manual vieja si existe:

```bash
sudo rm -rf /opt/libreoffice25.8/share/extensions/dict-ua-med
```

## Anadir palabras

1. Preferible: anadir a `gen_lexicon_extra.py` / `gen_lexicon_more.py` (escapes `\uXXXX`) y correr `python gen_all.py`.
2. O editar `ua_med_GT.dic` a mano (UTF-8, LF) y actualizar la linea 1 al total.
3. Reinstalar con `sudo bash install_dict_ua_med.sh`.
