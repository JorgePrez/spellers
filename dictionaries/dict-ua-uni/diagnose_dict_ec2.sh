#!/bin/bash
set -u
LO_PROG="/opt/libreoffice25.8/program"
SPELL_DIR="/home/ec2-user/libreoffice_spellcheck"
echo "========== unopkg =========="
"$LO_PROG/unopkg" list --shared 2>/dev/null | grep -i -E "org.ua.dictionaries.uni-gt|ua" || echo "(no UA)"
echo
echo "========== SpellChecker UNO =========="
cd "$SPELL_DIR"
"$LO_PROG/python" - <<'PY'
from spellcheck_core import connect, make_locale
ctx = connect()
spell = ctx.ServiceManager.createInstanceWithContext(
    "com.sun.star.linguistic2.SpellChecker", ctx
)
loc = make_locale("es", "GT")
pruebas = [
    "syllabus",
    "crédito",
    "semestre",
    "licenciatura",
    "pensum",
    "imagenes",
    "tecnico",
    "hola"
]
for w in pruebas:
    print("  %-22s -> valido=%s" % (w, spell.isValid(w, loc, ())))
PY
