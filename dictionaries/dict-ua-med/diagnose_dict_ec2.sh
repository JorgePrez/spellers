#!/bin/bash
# Diagnostico diccionario UA en EC2. Uso: bash diagnose_dict_ec2.sh
set -u

LO_PROG="/opt/libreoffice25.8/program"
DIC_MANUAL="/opt/libreoffice25.8/share/extensions/dict-ua-med/ua_med_GT.dic"
SPELL_DIR="/home/ec2-user/libreoffice_spellcheck"

echo "========== 1) Servicios =========="
systemctl is-active libreoffice-uno.service spellcheck-flask.service || true

echo
echo "========== 2) unopkg (extension registrada?) =========="
"$LO_PROG/unopkg" list --shared 2>/dev/null | grep -i -E "ua|med|dict" || echo "(no aparece org.ua.dictionaries.med-gt)"

echo
echo "========== 3) Carpeta manual share/extensions (solo referencia) =========="
if [ -f "$DIC_MANUAL" ]; then
  echo "EXISTE $DIC_MANUAL"
  echo -n "  linea 1 (conteo): "; head -1 "$DIC_MANUAL"
  echo -n "  palabras reales:  "; tail -n +2 "$DIC_MANUAL" | sed '/^\s*$/d' | wc -l
else
  echo "No hay copia manual (OK si solo usas unopkg)"
fi

echo
echo "========== 4) Hunspell directo (si esta instalado) =========="
if command -v hunspell >/dev/null 2>&1; then
  AFF=$(find /opt/libreoffice25.8 -name "ua_med_GT.aff" 2>/dev/null | head -1)
  DIC=$(find /opt/libreoffice25.8 -name "ua_med_GT.dic" 2>/dev/null | head -1)
  if [ -n "$AFF" ] && [ -n "$DIC" ]; then
    DIR=$(dirname "$DIC")
    echo "Probando en $DIR"
    for w in abdominoplastia cardiología hola; do
      printf "%s -> " "$w"
      echo "$w" | hunspell -d ua_med_GT -l -a 2>/dev/null | head -1 || echo "?"
    done
  else
    echo "No se encontro ua_med_GT.aff/.dic en instalacion LO"
  fi
else
  echo "hunspell no instalado (opcional)"
fi

echo
echo "========== 5) SpellChecker UNO (mismo que spellcheck) =========="
cd "$SPELL_DIR"
"$LO_PROG/python" - <<'PY'
# -*- coding: utf-8 -*-
from spellcheck_core import connect, make_locale

ctx = connect()
smgr = ctx.ServiceManager
spell = smgr.createInstanceWithContext(
    "com.sun.star.linguistic2.SpellChecker", ctx
)
loc = make_locale("es", "GT")

pruebas = [
    "abdominoplastia",
    "neuromielitis",
    "Cardiología",
    "cardiología",
    "Cardiologia",
    "Introduccion",
    "hola",
]

print("Locale: es-GT")
for w in pruebas:
    ok = spell.isValid(w, loc, ())
    print(f"  {w:22} -> valido={ok}")

# Intentar listar diccionarios via configuracion
try:
    from com.sun.star.lang import Locale as _  # noqa: F401
    prov = smgr.createInstanceWithContext(
        "com.sun.star.configuration.ConfigurationProvider", ctx
    )
    prop = __import__("uno").createUnoStruct("com.sun.star.beans.PropertyValue")
    prop.Name = "nodepath"
    prop.Value = "/org.openoffice.Office.Linguistic/ServiceManager/Dictionaries"
    access = prov.createInstanceWithArguments(
        "com.sun.star.configuration.ConfigurationAccess", (prop,)
    )
    names = access.getElementNames()
    ua = [n for n in names if "ua" in n.lower() or "med" in n.lower()]
    print("\nNodos diccionario UA en config:", ua if ua else "(ninguno - extension no registrada)")
    print("Total nodos diccionario:", len(names))
except Exception as e:
    print("\nNo se pudo leer lista de diccionarios:", e)
PY

echo
echo "========== Fin =========="
