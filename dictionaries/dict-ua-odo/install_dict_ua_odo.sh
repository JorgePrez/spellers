#!/bin/bash
# Instala diccionario odontologico UA con unopkg.
# Uso: sudo bash install_dict_ua_odo.sh
set -eu

SRC_DIR="$(cd "$(dirname "$0")" && pwd)"
LO_PROG="/opt/libreoffice25.8/program"
OXT="/tmp/dict-ua-odo.oxt"
AFF_SRC="/opt/libreoffice25.8/share/extensions/dict-es/es_GT.aff"

echo "==> Preparar archivos"
sed -i 's/\r$//' "$SRC_DIR/ua_odo_GT.dic" "$SRC_DIR/ua_odo_GT.aff" "$SRC_DIR/description.xml" 2>/dev/null || true

export SRC_DIR
"$LO_PROG/python" - <<'PY'
import os
from pathlib import Path

src = Path(os.environ["SRC_DIR"])
for name in ("description.xml", "dictionaries.xcu", "ua_odo_GT.dic"):
    p = src / name
    if not p.exists():
        continue
    raw = p.read_bytes()
    for enc in ("utf-8", "cp1252", "latin-1"):
        try:
            text = raw.decode(enc)
            break
        except UnicodeDecodeError:
            continue
    else:
        raise SystemExit(f"No se pudo decodificar {name}")
    if name.endswith(".dic"):
        lines = [ln.strip() for ln in text.splitlines() if ln.strip()]
        if lines:
            words = lines[1:]
            lines = [str(len(words))] + words
        text = "\n".join(lines) + "\n"
    else:
        text = text.replace("\r\n", "\n").replace("\r", "\n")
        if not text.endswith("\n"):
            text += "\n"
    p.write_bytes(text.encode("utf-8"))
    print(f"    UTF-8 OK: {name}")
PY
if [ -f "$AFF_SRC" ]; then
  cp "$AFF_SRC" "$SRC_DIR/ua_odo_GT.aff"
  sed -i 's/\r$//' "$SRC_DIR/ua_odo_GT.aff"
fi

WORDS=$(tail -n +2 "$SRC_DIR/ua_odo_GT.dic" | sed '/^\s*$/d' | wc -l | tr -d ' ')
sed -i "1s/.*/$WORDS/" "$SRC_DIR/ua_odo_GT.dic"
echo "    Palabras en ua_odo_GT.dic: $WORDS"

echo "==> Empaquetar .oxt"
rm -f "$OXT"
(
  cd "$SRC_DIR"
  zip -q -r "$OXT" \
    description.xml \
    dictionaries.xcu \
    ua_odo_GT.aff \
    ua_odo_GT.dic \
    META-INF/manifest.xml
)

echo "==> Detener servicios (unopkg requiere LO detenido)"
systemctl stop spellcheck-flask.service 2>/dev/null || true
systemctl stop libreoffice-uno.service 2>/dev/null || true
sleep 2

echo "==> Registrar extension con unopkg --shared"
"$LO_PROG/unopkg" remove --shared org.ua.dictionaries.odo-gt 2>/dev/null || true
"$LO_PROG/unopkg" add --shared -f "$OXT" -v

echo "==> Extensiones compartidas instaladas:"
"$LO_PROG/unopkg" list --shared | grep -i ua || "$LO_PROG/unopkg" list --shared | tail -5

echo "==> Iniciar servicios"
systemctl start libreoffice-uno.service
sleep 5
systemctl start spellcheck-flask.service

echo "==> Listo. Prueba:"
echo "    cd /home/ec2-user/libreoffice_spellcheck && sudo bash $SRC_DIR/diagnose_dict_ec2.sh"
