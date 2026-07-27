#!/bin/bash
# Instala (o reinstala) todos los diccionarios UA en el EC2.
# Uso: sudo bash install_all_ua_dicts.sh
# Asume: ya estas en el server y las carpetas estan bajo
#   /home/ec2-user/libreoffice_spellcheck/dictionaries/
set -eu
BASE="$(cd "$(dirname "$0")" && pwd)"
AREAS="med odo der eco arq pol psi uni ang"

for a in $AREAS; do
  DIR="$BASE/dict-ua-$a"
  if [ ! -d "$DIR" ]; then
    echo "SKIP dict-ua-$a (no existe)"
    continue
  fi
  SCRIPT="$DIR/install_dict_ua_$a.sh"
  if [ ! -f "$SCRIPT" ]; then
    echo "SKIP $a (sin install script)"
    continue
  fi
  echo "======== INSTALL dict-ua-$a ========"
  sed -i 's/\r$//' "$SCRIPT" "$DIR"/description.xml "$DIR"/ua_"$a"_GT.dic "$DIR"/ua_"$a"_GT.aff 2>/dev/null || true
  bash "$SCRIPT"
done

echo "======== RESUMEN unopkg ========"
/opt/libreoffice25.8/program/unopkg list --shared | grep -i org.ua.dictionaries || true
echo "Listo."
