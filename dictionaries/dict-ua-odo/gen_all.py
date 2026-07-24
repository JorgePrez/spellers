# -*- coding: utf-8 -*-
"""Generate UA odontology Hunspell dictionary (UTF-8). Orthography-first."""
from __future__ import annotations

import codecs
import re
from pathlib import Path

from gen_lexicon import all_block_tokens  # type: ignore
from gen_lexicon_more import more_tokens  # type: ignore
from gen_morph import morph_tokens, simple_plurals  # type: ignore
from ortho_priority import filter_orthography_errors  # type: ignore

BASE = Path(__file__).resolve().parent
OUT = BASE / "ua_odo_GT.dic"
SRC = BASE / "source"
SRC.mkdir(parents=True, exist_ok=True)
MED_FREQ = (
    BASE.parent / "dict-ua-med" / "source" / "external" / "freq_list.txt"
)
EXT_EXPANDED = SRC / "external" / "expanded_odo.txt"
USER_SEED = SRC / "user_examples_odo.txt"

LETTER = re.compile(
    r"^[A-Za-z\u00c1\u00c9\u00cd\u00d3\u00da\u00dc\u00d1"
    r"\u00e1\u00e9\u00ed\u00f3\u00fa\u00fc\u00f1"
    r"\u00c7\u00e7\u00d6\u00f6]+$"
)

DENY = {
    "airway",
    "agenda",
    "agar",
    "vaccine",
    "cosas",
    "hola",
    "extraccion",  # keep only extracci\u00f3n via ortho/blocks
}

DENTAL_MARKERS = (
    "odont",
    "dent",
    "gingiv",
    "periodont",
    "endodont",
    "ortodont",
    "prostodont",
    "maxil",
    "mandib",
    "alveol",
    "pulp",
    "radicul",
    "apical",
    "oclus",
    "vestibul",
    "lingual",
    "palatin",
    "mesial",
    "distal",
    "incisal",
    "molar",
    "canin",
    "premolar",
    "incisiv",
    "implan",
    "exodon",
    "caries",
    "cariad",
    "amalgam",
    "composit",
    "ionomer",
    "ion\u00f3mer",
    "gutaper",
    "bracket",
    "ortopantom",
    "cefalom",
    "fluor",
    "sellant",
    "brux",
    "halit",
    "xerostom",
    "afta",
    "leucoplas",
    "candidias",
    "pericoronar",
    "tartrect",
    "raspado",
    "alisado",
    "curetaj",
    "frenect",
    "apicect",
    "gingivect",
    "alveoloplast",
    "osteointegr",
    "pr\u00f3tesis",
    "protesis",
    "carilla",
    "incrust",
    "obturac",
    "conducto",
    "endod",
    "buco",
    "oral",
    "saliv",
    "parotid",
    "submandib",
    "atm",
    "condil",
    "maseter",
    "trigemin",
    "anestesi",
    "lidocain",
    "articain",
    "mepivacain",
    "bupivacain",
    "odontogram",
    "bitewing",
    "panoram",
    "cbct",
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


def looks_dental(w: str) -> bool:
    low = w.casefold()
    return any(m in low for m in DENTAL_MARKERS)


def from_med_freq_dental() -> set[str]:
    kept: set[str] = set()
    if not MED_FREQ.exists():
        return kept
    for line in MED_FREQ.read_text(encoding="utf-8").splitlines():
        parts = line.split()
        if not parts:
            continue
        w = parts[0]
        if not valid(w):
            continue
        if looks_dental(w):
            kept.add(w)
    return kept


def title_variants(words: set[str]) -> set[str]:
    """Add Title case for a small set of specialty nouns (syllabus headings)."""
    out: set[str] = set()
    keys = (
        "odontolog\u00eda",
        "endodoncia",
        "periodoncia",
        "ortodoncia",
        "prostodoncia",
        "implantolog\u00eda",
        "odontopediatr\u00eda",
        "exodoncia",
        "caries",
        "gingivitis",
        "periodontitis",
        "bruxismo",
        "halitosis",
        "xerostom\u00eda",
    )
    keyset = {k.casefold() for k in keys}
    for w in words:
        if w.casefold() in keyset and w and w[0].islower():
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
    for w in morph_tokens():
        if valid(w) and w.casefold() not in deny_cf:
            bag.add(w)
    for w in from_med_freq_dental():
        if w.casefold() not in deny_cf:
            bag.add(w)
    for path in (EXT_EXPANDED, USER_SEED):
        if not path.exists():
            continue
        for line in path.read_text(encoding="utf-8").splitlines():
            w = line.strip()
            if not w or w.startswith("#"):
                continue
            if valid(w) and w.casefold() not in deny_cf:
                bag.add(w)
    bag |= title_variants(bag)
    for w in simple_plurals(bag):
        if valid(w) and w.casefold() not in deny_cf:
            bag.add(w)

    bag, stats = filter_orthography_errors(bag)
    # Force curated clinical lemmas (correct orthography)
    for w in (
        "amelog\u00e9nesis",
        "dentinog\u00e9nesis",
        "cementog\u00e9nesis",
        "desmineralizaci\u00f3n",
        "remineralizaci\u00f3n",
        "alveoloplastia",
        "gingivoplastia",
        "mixoma",
        "cementoblastoma",
        "radiolucidez",
        "semiajustable",
        "pulpectom\u00eda",
        "ameloblasto",
        "odontoblasto",
        "cementoblasto",
        "ameloblastoma",
        "queratoquiste",
        "conductometr\u00eda",
        "furcaci\u00f3n",
        "odontectom\u00eda",
        "osteotom\u00eda",
        "osteointegraci\u00f3n",
    ):
        bag.add(w)
        bag.discard("odonctectom\u00eda")
    print(
        "Filtro ortografia: "
        f"in={stats['input']} kept={stats['kept']} "
        f"drop_sin_tilde={stats['drop_unaccented_vs_accented']} "
        f"drop_sufijo={stats['drop_bad_medical_ending']} "
        f"drop_en={stats['drop_english']}"
    )

    words = sorted(bag, key=lambda s: (s.casefold(), s))
    dump = SRC / "ua_odo_lexicon_full.txt"
    dump.write_text(
        "# Lexico odontologico UA (generado)\n" + "\n".join(words) + "\n",
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
    print(f"Bytes UTF-8: {OUT.stat().st_size}")
    checks = [
        "odontolog\u00eda",
        "amelog\u00e9nesis",
        "dentinog\u00e9nesis",
        "cementog\u00e9nesis",
        "desmineralizaci\u00f3n",
        "remineralizaci\u00f3n",
        "alveoloplastia",
        "mixoma",
        "cementoblastoma",
        "radiolucidez",
        "semiajustable",
        "pulpectom\u00eda",
        "ameloblasto",
        "odontectom\u00eda",
        "queratoquiste",
        "conductometr\u00eda",
        "odontologo",
        "imagenes",
        "tecnico",
    ]
    s = set(words)
    for c in checks:
        print(f"  {c}: {c in s}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
