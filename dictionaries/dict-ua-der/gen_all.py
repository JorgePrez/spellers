# -*- coding: utf-8 -*-
"""Generate UA law Hunspell dictionary (UTF-8). Orthography-first."""
from __future__ import annotations

import re
from pathlib import Path

from gen_lexicon import all_block_tokens  # type: ignore
from gen_lexicon_more import more_tokens  # type: ignore
from gen_lexicon_extra import extra_tokens  # type: ignore
from gen_morph import morph_tokens, simple_plurals  # type: ignore
from ortho_priority import filter_orthography_errors  # type: ignore

BASE = Path(__file__).resolve().parent
OUT = BASE / "ua_der_GT.dic"
SRC = BASE / "source"
SRC.mkdir(parents=True, exist_ok=True)

LETTER = re.compile(
    r"^[A-Za-z\u00c1\u00c9\u00cd\u00d3\u00da\u00dc\u00d1"
    r"\u00e1\u00e9\u00ed\u00f3\u00fa\u00fc\u00f1"
    r"\u00c7\u00e7\u00d6\u00f6]+$"
)

DENY = {
    "airway",
    "agenda",
    "vaccine",
    "cosas",
    "hola",
    "joint",
    "venture",
    "leasing",
    "factoring",
    "clear",
    "aligners",
}

LEGAL_MARKERS = (
    "jurid",
    "legal",
    "legisl",
    "judicial",
    "jurisdic",
    "proces",
    "penal",
    "civil",
    "mercantil",
    "laboral",
    "administrativ",
    "constitucional",
    "contract",
    "obligaci",
    "demand",
    "sentenc",
    "apelac",
    "casaci",
    "amparo",
    "embarg",
    "notific",
    "citaci",
    "emplaz",
    "prescrip",
    "usucap",
    "heredi",
    "testament",
    "suces",
    "matrimon",
    "divorci",
    "filiac",
    "adopci",
    "indemniz",
    "delito",
    "delict",
    "criminal",
    "culpab",
    "imputab",
    "antijurid",
    "tipicidad",
    "cohecho",
    "peculad",
    "prevaric",
    "malvers",
    "extorsi",
    "estafa",
    "usurp",
    "feminicid",
    "homicid",
    "parricid",
    "secuest",
    "extradic",
    "expropi",
    "licitac",
    "adjudic",
    "recurso",
    "cautelar",
    "notarial",
    "registral",
    "fideicomis",
    "usufruct",
    "hipotec",
    "prenda",
    "arbitr",
    "concili",
    "mediaci",
    "diplom",
    "tratado",
    "ratific",
    "jurisprud",
    "habeas",
    "exequatur",
    "affidavit",
    "fiscal",
    "procurad",
    "magistr",
    "juzgado",
    "tribunal",
    "querell",
    "denunci",
)


def valid(w: str) -> bool:
    w = w.strip()
    if not w or not (2 <= len(w) <= 45):
        return False
    if any(c.isdigit() for c in w) or " " in w:
        return False
    if not LETTER.fullmatch(w):
        return False
    return True


def title_variants(words: set[str]) -> set[str]:
    keys = {
        "derecho",
        "constituci\u00f3n",
        "jurisprudencia",
        "amparo",
        "casaci\u00f3n",
        "apelaci\u00f3n",
        "demanda",
        "sentencia",
        "homicidio",
        "feminicidio",
        "fideicomiso",
        "usufructo",
        "exequatur",
    }
    out: set[str] = set()
    for w in words:
        if w.casefold() in {k.casefold() for k in keys} and w and w[0].islower():
            out.add(w[0].upper() + w[1:])
    return out


def collect() -> list[str]:
    bag: set[str] = set()
    deny_cf = {d.casefold() for d in DENY}

    for w in all_block_tokens():
        if valid(w) and w.casefold() not in deny_cf:
            bag.add(w)
    for w in more_tokens():
        if valid(w) and w.casefold() not in deny_cf:
            bag.add(w)
    for w in extra_tokens():
        if valid(w) and w.casefold() not in deny_cf:
            bag.add(w)
    for w in morph_tokens():
        if valid(w) and w.casefold() not in deny_cf:
            bag.add(w)
    bag |= title_variants(bag)
    for w in simple_plurals(bag):
        if valid(w) and w.casefold() not in deny_cf:
            bag.add(w)

    bag, stats = filter_orthography_errors(bag)
    print(
        "Filtro ortografia: "
        f"in={stats['input']} kept={stats['kept']} "
        f"drop_sin_tilde={stats['drop_unaccented_vs_accented']} "
        f"drop_sufijo={stats['drop_bad_medical_ending']} "
        f"drop_en={stats['drop_english']}"
    )

    words = sorted(bag, key=lambda s: (s.casefold(), s))
    dump = SRC / "ua_der_lexicon_full.txt"
    dump.write_text(
        "# Lexico juridico UA (generado)\n" + "\n".join(words) + "\n",
        encoding="utf-8",
        newline="\n",
    )
    return words


def main() -> int:
    words = collect()
    OUT.write_bytes((f"{len(words)}\n" + "\n".join(words) + "\n").encode("utf-8"))
    accented = sum(
        1
        for w in words
        if re.search(
            r"[\u00e1\u00e9\u00ed\u00f3\u00fa\u00fc\u00f1"
            r"\u00c1\u00c9\u00cd\u00d3\u00da\u00dc\u00d1]",
            w,
        )
    )
    print(f"Escrito: {OUT}")
    print(f"Palabras: {len(words)}")
    print(f"Con tilde/enie: {accented}")
    checks = [
        "jur\u00eddico",
        "jurisprudencia",
        "amparo",
        "casaci\u00f3n",
        "feminicidio",
        "fideicomiso",
        "exequatur",
        "habeas",
        "usucapi\u00f3n",
        "juridico",
        "casacion",
        "imagenes",
        "tecnico",
    ]
    s = set(words)
    for c in checks:
        print(f"  {c}: {c in s}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
