#!/bin/bash
# Instala el diccionario medico UA en LibreOffice (EC2 spellcheck).
# Uso: sudo bash install_dict_ua_med.sh
set -euo pipefail

SRC_DIR="$(cd "$(dirname "$0")" && pwd)"
DEST="/opt/libreoffice25.8/share/extensions/dict-ua-med"

echo "Instalando en $DEST ..."
mkdir -p "$DEST/META-INF"
cp "$SRC_DIR/ua_med_GT.aff" "$SRC_DIR/ua_med_GT.dic" "$SRC_DIR/dictionaries.xcu" "$SRC_DIR/description.xml" "$DEST/"
cp "$SRC_DIR/META-INF/manifest.xml" "$DEST/META-INF/"
chown -R root:root "$DEST"
chmod -R a+rX "$DEST"

echo "Reiniciando servicios..."
systemctl restart libreoffice-uno.service
sleep 3
systemctl restart spellcheck-flask.service

echo "Listo. Verifica con:"
echo "  wc -l $DEST/ua_med_GT.dic"
echo "  systemctl status libreoffice-uno.service --no-pager | head -10"
