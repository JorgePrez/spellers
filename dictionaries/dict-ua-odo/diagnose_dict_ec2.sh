#!/bin/bash
# Diagnostico diccionario odontologico UA en EC2.
# Uso: bash diagnose_dict_ec2.sh
set -u

LO_PROG="/opt/libreoffice25.8/program"
DIC_MANUAL="/opt/libreoffice25.8/share/extensions/dict-ua-odo/ua_odo_GT.dic"
SPELL_DIR="/home/ec2-user/libreoffice_spellcheck"

echo "========== 1) Servicios =========="
systemctl is-active libreoffice-uno.service spellcheck-flask.service || true

echo
echo "========== 2) unopkg (extension registrada?) =========="
"$LO_PROG/unopkg" list --shared 2>/dev/null | grep -i -E "org.ua.dictionaries.odo-gt|ua|odo" || echo "(no aparece org.ua.dictionaries.odo-gt)"

echo
echo "========== 3) Carpeta manual share/extensions =========="
if [ -f "$DIC_MANUAL" ]; then
  echo "EXISTE $DIC_MANUAL (copia vieja; opcional: sudo rm -rf /opt/libreoffice25.8/share/extensions/dict-ua-odo)"
else
  echo "No hay copia manual (OK)"
fi

echo
echo "========== 4) SpellChecker UNO =========="
cd "$SPELL_DIR"
"$LO_PROG/python" - <<'PY'
from spellcheck_core import connect, make_locale

ctx = connect()
smgr = ctx.ServiceManager
spell = smgr.createInstanceWithContext(
    "com.sun.star.linguistic2.SpellChecker", ctx
)
loc = make_locale("es", "GT")

pruebas = [
    "odontolog\u00eda",
    "endodoncia",
    "periodontitis",
    "gingivectom\u00eda",
    "ortopantomograf\u00eda",
    "bruxismo",
    "implante",
    "exodoncia",
    "odont\u00f3logo",
    "extracci\u00f3n",
    "extraccion",
    "imagenes",
    "tecnico",
    "hola",
]

print("Locale: es-GT")
for w in pruebas:
    ok = spell.isValid(w, loc, ())
    print("  %-22s -> valido=%s" % (w, ok))
PY

echo
echo "========== Fin =========="
