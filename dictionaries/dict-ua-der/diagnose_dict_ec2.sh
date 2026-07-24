#!/bin/bash
# Diagnostico diccionario derecho UA en EC2.
set -u

LO_PROG="/opt/libreoffice25.8/program"
SPELL_DIR="/home/ec2-user/libreoffice_spellcheck"

echo "========== 1) Servicios =========="
systemctl is-active libreoffice-uno.service spellcheck-flask.service || true

echo
echo "========== 2) unopkg =========="
"$LO_PROG/unopkg" list --shared 2>/dev/null | grep -i -E "org.ua.dictionaries|ua" || echo "(no aparece UA)"

echo
echo "========== 3) SpellChecker UNO =========="
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
    "jur\u00eddico",
    "jurisprudencia",
    "amparo",
    "casaci\u00f3n",
    "feminicidio",
    "fideicomiso",
    "exequatur",
    "usucapi\u00f3n",
    "odontolog\u00eda",
    "endodoncia",
    "juridico",
    "casacion",
    "imagenes",
    "tecnico",
    "hola",
]

print("Locale: es-GT (odo + der + med + es_GT)")
for w in pruebas:
    ok = spell.isValid(w, loc, ())
    print("  %-22s -> valido=%s" % (w, ok))
PY

echo
echo "========== Fin =========="
