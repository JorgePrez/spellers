# -*- coding: utf-8 -*-
"""Scaffold packaging files for all UA faculty dictionaries."""
from __future__ import annotations

from pathlib import Path

ROOT = Path(__file__).resolve().parent

PACKAGES = {
    "der": {
        "name_es": "UA Diccionario Derecho Guatemala (es-GT)",
        "name_en": "UA Law Dictionary Guatemala (es-GT)",
        "version": "1.1.0",
        "probe": ["jur\u00eddico", "jurisprudencia", "amparo", "casaci\u00f3n", "feminicidio"],
    },
    "eco": {
        "name_es": "UA Diccionario Economia Guatemala (es-GT)",
        "name_en": "UA Economics Dictionary Guatemala (es-GT)",
        "version": "1.0.0",
        "probe": ["macroeconom\u00eda", "inflaci\u00f3n", "oligopolio", "externalidad", "PIB"],
    },
    "arq": {
        "name_es": "UA Diccionario Arquitectura Guatemala (es-GT)",
        "name_en": "UA Architecture Dictionary Guatemala (es-GT)",
        "version": "1.0.0",
        "probe": ["arquitectura", "voladizo", "cimentaci\u00f3n", "arquitrabe", "planimetr\u00eda"],
    },
    "pol": {
        "name_es": "UA Diccionario Politica y RRII Guatemala (es-GT)",
        "name_en": "UA Political Science Dictionary Guatemala (es-GT)",
        "version": "1.0.0",
        "probe": ["geopol\u00edtica", "multilateralismo", "soberan\u00eda", "diplomacia", "ONU"],
    },
    "psi": {
        "name_es": "UA Diccionario Psicologia Guatemala (es-GT)",
        "name_en": "UA Psychology Dictionary Guatemala (es-GT)",
        "version": "1.0.0",
        "probe": ["psicolog\u00eda", "cognitivo", "neuropsicolog\u00eda", "psicoterapia", "DSM"],
    },
    "uni": {
        "name_es": "UA Diccionario Universitario Guatemala (es-GT)",
        "name_en": "UA University Terms Dictionary Guatemala (es-GT)",
        "version": "1.0.0",
        "probe": ["syllabus", "cr\u00e9dito", "semestre", "licenciatura", "pensum"],
    },
    "ang": {
        "name_es": "UA Anglicismos universitarios Guatemala (es-GT)",
        "name_en": "UA University Anglicisms Dictionary Guatemala (es-GT)",
        "version": "1.0.0",
        "probe": ["feedback", "deadline", "paper", "abstract", "softskills"],
    },
}


def write(path: Path, text: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(text, encoding="utf-8", newline="\n")


def description_xml(code: str, meta: dict) -> str:
    return f"""<?xml version="1.0" encoding="UTF-8"?>
<description xmlns="http://openoffice.org/extensions/description/2006" xmlns:d="http://openoffice.org/extensions/description/2006">
  <identifier value="org.ua.dictionaries.{code}-gt"/>
  <version value="{meta['version']}"/>
  <display-name>
    <name lang="es">{meta['name_es']}</name>
    <name lang="en">{meta['name_en']}</name>
  </display-name>
  <dependencies>
    <OpenOffice.org-minimal-version value="3.0" d:name="OpenOffice.org 3.0"/>
  </dependencies>
</description>
"""


def dictionaries_xcu(code: str) -> str:
    return f"""<?xml version="1.0" encoding="UTF-8"?>
<oor:component-data xmlns:oor="http://openoffice.org/2001/registry" xmlns:xs="http://www.w3.org/2001/XMLSchema" oor:name="Linguistic" oor:package="org.openoffice.Office">
  <node oor:name="ServiceManager">
    <node oor:name="Dictionaries">
      <node oor:name="HunSpellDic_ua_{code}_GT" oor:op="fuse">
        <prop oor:name="Locations" oor:type="oor:string-list">
          <value>%origin%/ua_{code}_GT.aff %origin%/ua_{code}_GT.dic</value>
        </prop>
        <prop oor:name="Format" oor:type="xs:string">
          <value>DICT_SPELL</value>
        </prop>
        <prop oor:name="Locales" oor:type="oor:string-list">
          <value>es-GT</value>
        </prop>
      </node>
    </node>
  </node>
</oor:component-data>
"""


def install_sh(code: str) -> str:
    return f"""#!/bin/bash
# Instala dict-ua-{code}. Uso: sudo bash install_dict_ua_{code}.sh
set -eu
SRC_DIR="$(cd "$(dirname "$0")" && pwd)"
LO_PROG="/opt/libreoffice25.8/program"
OXT="/tmp/dict-ua-{code}.oxt"
AFF_SRC="/opt/libreoffice25.8/share/extensions/dict-es/es_GT.aff"

echo "==> Preparar archivos"
sed -i 's/\\r$//' "$SRC_DIR/ua_{code}_GT.dic" "$SRC_DIR/ua_{code}_GT.aff" "$SRC_DIR/description.xml" 2>/dev/null || true
export SRC_DIR
"$LO_PROG/python" - <<'PY'
import os
from pathlib import Path
src = Path(os.environ["SRC_DIR"])
for name in ("description.xml", "dictionaries.xcu", "ua_{code}_GT.dic"):
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
        raise SystemExit(f"No se pudo decodificar {{name}}")
    if name.endswith(".dic"):
        lines = [ln.strip() for ln in text.splitlines() if ln.strip()]
        if lines:
            words = lines[1:]
            lines = [str(len(words))] + words
        text = "\\n".join(lines) + "\\n"
    else:
        text = text.replace("\\r\\n", "\\n").replace("\\r", "\\n")
        if not text.endswith("\\n"):
            text += "\\n"
    p.write_bytes(text.encode("utf-8"))
    print(f"    UTF-8 OK: {{name}}")
PY
if [ -f "$AFF_SRC" ]; then
  cp "$AFF_SRC" "$SRC_DIR/ua_{code}_GT.aff"
  sed -i 's/\\r$//' "$SRC_DIR/ua_{code}_GT.aff"
fi
WORDS=$(tail -n +2 "$SRC_DIR/ua_{code}_GT.dic" | sed '/^\\s*$/d' | wc -l | tr -d ' ')
sed -i "1s/.*/$WORDS/" "$SRC_DIR/ua_{code}_GT.dic"
echo "    Palabras: $WORDS"
rm -f "$OXT"
( cd "$SRC_DIR" && zip -q -r "$OXT" description.xml dictionaries.xcu ua_{code}_GT.aff ua_{code}_GT.dic META-INF/manifest.xml )
systemctl stop spellcheck-flask.service 2>/dev/null || true
systemctl stop libreoffice-uno.service 2>/dev/null || true
sleep 2
"$LO_PROG/unopkg" remove --shared org.ua.dictionaries.{code}-gt 2>/dev/null || true
"$LO_PROG/unopkg" add --shared -f "$OXT" -v
"$LO_PROG/unopkg" list --shared | grep -i ua || true
systemctl start libreoffice-uno.service
sleep 5
systemctl start spellcheck-flask.service
echo "==> Listo dict-ua-{code}"
"""


def diagnose_sh(code: str, probes: list[str]) -> str:
    probes_py = ",\n    ".join(f'"{p}"' for p in probes + ["imagenes", "tecnico", "hola"])
    return f"""#!/bin/bash
set -u
LO_PROG="/opt/libreoffice25.8/program"
SPELL_DIR="/home/ec2-user/libreoffice_spellcheck"
echo "========== unopkg =========="
"$LO_PROG/unopkg" list --shared 2>/dev/null | grep -i -E "org.ua.dictionaries.{code}-gt|ua" || echo "(no UA)"
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
    {probes_py}
]
for w in pruebas:
    print("  %-22s -> valido=%s" % (w, spell.isValid(w, loc, ())))
PY
"""


def verify_py(code: str) -> str:
    return f'''#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import re
import sys
from pathlib import Path
DIC = Path(__file__).resolve().parent / "ua_{code}_GT.dic"

def main() -> int:
    text = DIC.read_text(encoding="utf-8")
    lines = text.splitlines()
    declared = int(lines[0].strip())
    words = [w.strip() for w in lines[1:] if w.strip()]
    exact = len(words) - len(set(words))
    print(f"Archivo: {{DIC}}")
    print(f"Declaradas: {{declared}}")
    print(f"En archivo: {{len(words)}}")
    print(f"Duplicados exactos: {{exact}}")
    if declared != len(words) or exact:
        return 1
    print("OK")
    return 0

if __name__ == "__main__":
    raise SystemExit(main())
'''


def main() -> int:
    for code, meta in PACKAGES.items():
        base = ROOT / f"dict-ua-{code}"
        base.mkdir(parents=True, exist_ok=True)
        (base / "META-INF").mkdir(exist_ok=True)
        (base / "source" / "external").mkdir(parents=True, exist_ok=True)
        write(base / "description.xml", description_xml(code, meta))
        write(base / "dictionaries.xcu", dictionaries_xcu(code))
        write(base / f"ua_{code}_GT.aff", "SET UTF-8\n")
        write(
            base / "META-INF" / "manifest.xml",
            '<?xml version="1.0" encoding="UTF-8"?>\n'
            '<manifest:manifest xmlns:manifest="http://openoffice.org/2001/manifest">\n'
            '  <manifest:file-entry manifest:media-type="application/vnd.sun.star.configuration-data" '
            'manifest:full-path="dictionaries.xcu"/>\n'
            "</manifest:manifest>\n",
        )
        write(base / f"install_dict_ua_{code}.sh", install_sh(code))
        write(base / "diagnose_dict_ec2.sh", diagnose_sh(code, meta["probe"]))
        write(base / "verify_dic.py", verify_py(code))
        write(
            base / ".gitattributes",
            f"dictionaries/dict-ua-{code}/* text eol=lf working-tree-encoding=UTF-8\n",
        )
        # copy ortho from odo if missing
        ortho_src = ROOT / "dict-ua-odo" / "ortho_priority.py"
        ortho_dst = base / "ortho_priority.py"
        if ortho_src.exists() and code != "ang":
            ortho_dst.write_bytes(ortho_src.read_bytes())
        print("scaffolded", code)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
