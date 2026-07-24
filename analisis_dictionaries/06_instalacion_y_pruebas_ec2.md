# Instalacion y pruebas en EC2

## Requisitos previos

- Codigo en: `/home/ec2-user/libreoffice_spellcheck/`
- Diccionario en: `/home/ec2-user/libreoffice_spellcheck/dictionaries/dict-ua-med/`
- LibreOffice detenido durante `unopkg` (el script lo hace).

## Reinstalar en el server (ya estando en el EC2)

Cuando los archivos de `dict-ua-med` ya estan en el servidor (actualizados), ejecuta:

```bash
cd /home/ec2-user/libreoffice_spellcheck/dictionaries/dict-ua-med
sed -i 's/\r$//' install_dict_ua_med.sh diagnose_dict_ec2.sh description.xml ua_med_GT.dic ua_med_GT.aff
sudo bash install_dict_ua_med.sh
bash diagnose_dict_ec2.sh
```

El script:

1. Convierte archivos a UTF-8
2. Recalcula contador del .dic
3. Copia `es_GT.aff` como `ua_med_GT.aff` (si existe)
4. Empaqueta `.oxt`
5. `unopkg remove` + `unopkg add --shared -f`
6. Reinicia `libreoffice-uno` y `spellcheck-flask`

### Limpieza opcional (copia manual vieja)

```bash
sudo rm -rf /opt/libreoffice25.8/share/extensions/dict-ua-med
```

Luego vuelve a correr `sudo bash install_dict_ua_med.sh`.

## Verificar extension

```bash
/opt/libreoffice25.8/program/unopkg list --shared | grep -A3 org.ua.dictionaries.med-gt
```

## Prueba SpellChecker (sin tildes en el shell - usar \u en Python)

```bash
cd /home/ec2-user/libreoffice_spellcheck
/opt/libreoffice25.8/program/python - <<'PY'
from spellcheck_core import connect, make_locale

ctx = connect()
spell = ctx.ServiceManager.createInstanceWithContext(
    "com.sun.star.linguistic2.SpellChecker", ctx
)
loc = make_locale("es", "GT")

pruebas = [
    "abdominoplastia",
    "neuromielitis",
    "Cardiolog\u00eda",
    "cardiolog\u00eda",
    "pancreatoduodenectom\u00eda",
    "hepatoesplenomegalia",
    "Cardiologia",
    "Introduccion",
    "hola",
]

for w in pruebas:
    print("%-28s -> valido=%s" % (w, spell.isValid(w, loc, ())))
PY
```

### Resultado esperado

| Palabra | valido |
|---------|--------|
| abdominoplastia | True |
| neuromielitis | True |
| Cardiología | True |
| cardiología | True |
| pancreatoduodenectomía | True |
| hepatoesplenomegalia | True |
| Cardiologia | False |
| Introduccion | False |
| hola | True |

## Diagnostico completo

```bash
bash diagnose_dict_ec2.sh
```

## Anadir palabra manualmente

1. Editar `ua_med_GT.dic` (UTF-8, LF) **o** regenerar con `python gen_all.py`.
2. Anadir una palabra por linea.
3. Actualizar linea 1 al total de palabras (el install lo hace solo).
4. Reejecutar `sudo bash install_dict_ua_med.sh`.

## Limpiar copia manual vieja

```bash
sudo rm -rf /opt/libreoffice25.8/share/extensions/dict-ua-med
sudo systemctl restart libreoffice-uno.service
sleep 3
sudo systemctl restart spellcheck-flask.service
```
