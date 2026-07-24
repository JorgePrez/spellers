#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Valida ua_der_GT.dic (conteo, UTF-8, duplicados)."""
import re
import sys
from pathlib import Path

DIC = Path(__file__).resolve().parent / "ua_der_GT.dic"


def main() -> int:
    text = DIC.read_text(encoding="utf-8")
    lines = text.splitlines()
    if not lines:
        print("ERROR: archivo vacio")
        return 1
    try:
        declared = int(lines[0].strip())
    except ValueError:
        print("ERROR: primera linea debe ser el numero de palabras")
        return 1
    words = [w.strip() for w in lines[1:] if w.strip()]
    accented = sum(
        1
        for w in words
        if re.search(
            r"[\u00e1\u00e9\u00ed\u00f3\u00fa\u00fc\u00f1\u00c1\u00c9\u00cd\u00d3\u00da\u00dc\u00d1]",
            w,
        )
    )
    exact_dupes = len(words) - len(set(words))
    case_pairs = len(words) - len({w.casefold() for w in words})
    print(f"Archivo: {DIC}")
    print(f"Declaradas: {declared}")
    print(f"En archivo: {len(words)}")
    print(f"Con tilde:  {accented}")
    print(f"Duplicados exactos: {exact_dupes}")
    print(f"Pares solo-mayuscula: {case_pairs}")
    if declared != len(words):
        print("ERROR: el contador de la primera linea no coincide")
        return 1
    if exact_dupes:
        print("ERROR: hay palabras duplicadas exactas")
        return 1
    print("OK")
    return 0


if __name__ == "__main__":
    sys.exit(main())
