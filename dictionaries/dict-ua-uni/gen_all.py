# -*- coding: utf-8 -*-
"""Generate UA university Hunspell dictionary (UTF-8). Orthography-first."""
from __future__ import annotations

import re
from pathlib import Path

from gen_lexicon import all_block_tokens  # type: ignore
from gen_lexicon_more import more_tokens  # type: ignore
from gen_morph import morph_tokens, simple_plurals  # type: ignore
from ortho_priority import filter_orthography_errors  # type: ignore

BASE = Path(__file__).resolve().parent
OUT = BASE / "ua_uni_GT.dic"
SRC = BASE / "source"
EXT = SRC / "external"
SRC.mkdir(parents=True, exist_ok=True)

LETTER = re.compile(
    r"^[A-Za-z\u00c1\u00c9\u00cd\u00d3\u00da\u00dc\u00d1"
    r"\u00e1\u00e9\u00ed\u00f3\u00fa\u00fc\u00f1"
    r"\u00c7\u00e7\u00d6\u00f6]+$"
)

DENY = {"hola", "cosas", "agenda", "airway", "vaccine"}


def valid(w: str) -> bool:
    w = w.strip()
    if not w or not (2 <= len(w) <= 45):
        return False
    if any(c.isdigit() for c in w) or " " in w:
        return False
    if not LETTER.fullmatch(w):
        return False
    return True


def load_expanded() -> set[str]:
    """Load expanded_uni.txt if exists."""
    expanded_file = EXT / "expanded_uni.txt"
    if not expanded_file.exists():
        return set()
    kept: set[str] = set()
    for line in expanded_file.read_text(encoding="utf-8", errors="replace").splitlines():
        line = line.strip()
        if not line or line.startswith("#"):
            continue
        if valid(line):
            kept.add(line)
    return kept


def title_variants(words: set[str]) -> set[str]:
    keys = {
        "universidad",
        "facultad",
        "licenciatura",
        "maestr\u00eda",
        "doctorado",
        "semestre",
        "cr\u00e9dito",
        "syllabus",
        "UFM",
        "PAES",
    }
    keyset = {k.casefold() for k in keys}
    out: set[str] = set()
    for w in words:
        if w.casefold() in keyset and w and w[0].islower():
            out.add(w[0].upper() + w[1:])
    return out



def load_fp_seeds() -> set[str]:
    """FP_SEEDS_LOADER_20260727: syllabus false-positive seeds."""
    p = SRC / "fp_seeds.txt"
    if not p.exists():
        return set()
    out: set[str] = set()
    for ln in p.read_text(encoding="utf-8").splitlines():
        w = ln.strip()
        if w and not w.startswith("#"):
            out.add(w)
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
    for w in load_expanded():
        if w.casefold() not in deny_cf:
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

    for w in load_fp_seeds():
        bag.add(w)
    words = sorted(bag, key=lambda s: (s.casefold(), s))
    dump = SRC / "ua_uni_lexicon_full.txt"
    dump.write_text(
        "# Lexico universitario UA (generado)\n" + "\n".join(words) + "\n",
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
        "cr\u00e9dito",
        "pensum",
        "syllabus",
        "licenciatura",
        "maestr\u00eda",
        "semestre",
        "prerrequisito",
        "r\u00fabrica",
        "acreditaci\u00f3n",
        "PAES",
        "UFM",
        "credito",
        "tecnico",
    ]
    s = set(words)
    for c in checks:
        print(f"  {c}: {c in s}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
