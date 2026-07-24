#!/bin/bash
# Instala diccionario medico UA con unopkg (registro real en LibreOffice).
# Uso: sudo bash install_dict_ua_med.sh
set -eu

SRC_DIR="$(cd "$(dirname "$0")" && pwd)"
LO_PROG="/opt/libreoffice25.8/program"
OXT="/tmp/dict-ua-med.oxt"
AFF_SRC="/opt/libreoffice25.8/share/extensions/dict-es/es_GT.aff"

echo "==> Preparar archivos"
sed -i 's/\r$//' "$SRC_DIR/ua_med_GT.dic" "$SRC_DIR/ua_med_GT.aff" 2>/dev/null || true

if [ -f "$AFF_SRC" ]; then
  cp "$AFF_SRC" "$SRC_DIR/ua_med_GT.aff"
  sed -i 's/\r$//' "$SRC_DIR/ua_med_GT.aff"
fi

WORDS=$(tail -n +2 "$SRC_DIR/ua_med_GT.dic" | sed '/^\s*$/d' | wc -l | tr -d ' ')
sed -i "1s/.*/$WORDS/" "$SRC_DIR/ua_med_GT.dic"
echo "    Palabras en ua_med_GT.dic: $WORDS"

echo "==> Empaquetar .oxt"
rm -f "$OXT"
(
  cd "$SRC_DIR"
  zip -q -r "$OXT" \
    description.xml \
    dictionaries.xcu \
    ua_med_GT.aff \
    ua_med_GT.dic \
    META-INF/manifest.xml
)

echo "==> Detener servicios (unopkg requiere LO detenido)"
systemctl stop spellcheck-flask.service 2>/dev/null || true
systemctl stop libreoffice-uno.service 2>/dev/null || true
sleep 2

echo "==> Registrar extension con unopkg --shared"
"$LO_PROG/unopkg" remove --shared org.ua.dictionaries.med-gt 2>/dev/null || true
"$LO_PROG/unopkg" add --shared -f "$OXT" -v

echo "==> Extensiones compartidas instaladas:"
"$LO_PROG/unopkg" list --shared | grep -i ua || "$LO_PROG/unopkg" list --shared | tail -5

echo "==> Iniciar servicios"
systemctl start libreoffice-uno.service
sleep 5
systemctl start spellcheck-flask.service

echo "==> Listo. Prueba:"
echo "    cd /home/ec2-user/libreoffice_spellcheck && sudo bash $SRC_DIR/diagnose_dict_ec2.sh"
