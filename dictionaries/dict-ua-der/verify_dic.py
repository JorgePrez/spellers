#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import re
import sys
from pathlib import Path
DIC = Path(__file__).resolve().parent / "ua_der_GT.dic"

def main() -> int:
    text = DIC.read_text(encoding="utf-8")
    lines = text.splitlines()
    declared = int(lines[0].strip())
    words = [w.strip() for w in lines[1:] if w.strip()]
    exact = len(words) - len(set(words))
    print(f"Archivo: {DIC}")
    print(f"Declaradas: {declared}")
    print(f"En archivo: {len(words)}")
    print(f"Duplicados exactos: {exact}")
    if declared != len(words) or exact:
        return 1
    print("OK")
    return 0

if __name__ == "__main__":
    raise SystemExit(main())
