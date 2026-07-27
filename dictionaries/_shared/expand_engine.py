# -*- coding: utf-8 -*-
"""Shared expansion helpers for UA Hunspell domain dictionaries.

Orthography-first: always run filter_orthography_errors before writing.
"""
from __future__ import annotations

import codecs
import re
import sys
from itertools import product
from pathlib import Path
from typing import Callable, Iterable

LETTER = re.compile(
    r"^[A-Za-z\u00c1\u00c9\u00cd\u00d3\u00da\u00dc\u00d1"
    r"\u00e1\u00e9\u00ed\u00f3\u00fa\u00fc\u00f1"
    r"\u00c7\u00e7\u00d6\u00f6]+$"
)

COMMON_SUFFIXES = [
    codecs.decode(s, "unicode_escape")
    for s in (
        "aci\\u00f3n",
        "aciones",
        "ici\\u00f3n",
        "iciones",
        "si\\u00f3n",
        "siones",
        "log\\u00eda",
        "log\\u00edas",
        "graf\\u00eda",
        "graf\\u00edas",
        "metr\\u00eda",
        "metr\\u00edas",
        "pat\\u00eda",
        "pat\\u00edas",
        "scop\\u00eda",
        "scop\\u00edas",
        "tom\\u00eda",
        "tom\\u00edas",
        "ismo",
        "ismos",
        "ista",
        "istas",
        "idad",
        "idades",
        "ario",
        "aria",
        "arios",
        "arias",
        "orio",
        "oria",
        "orios",
        "orias",
        "ivo",
        "iva",
        "ivos",
        "ivas",
        "ico",
        "ica",
        "icos",
        "icas",
        "\\u00f3gico",
        "\\u00f3gica",
        "\\u00f3gicos",
        "\\u00f3gicas",
        "ente",
        "entes",
        "encia",
        "encias",
        "ura",
        "uras",
        "oso",
        "osa",
        "osos",
        "osas",
        "al",
        "ales",
        "ar",
        "ares",
        "able",
        "ables",
        "ible",
        "ibles",
        "mente",
        "ez",
        "eza",
        "ezas",
        "amiento",
        "amientos",
        "imiento",
        "imientos",
    )
]

COMMON_PREFIXES = (
    "a",
    "an",
    "anti",
    "auto",
    "bi",
    "co",
    "contra",
    "des",
    "extra",
    "hiper",
    "hipo",
    "infra",
    "inter",
    "intra",
    "macro",
    "micro",
    "multi",
    "neo",
    "poli",
    "post",
    "pre",
    "pseudo",
    "re",
    "semi",
    "sub",
    "supra",
    "super",
    "trans",
    "ultra",
)


def dec(s: str) -> str:
    return codecs.decode(s, "unicode_escape")


def valid_word(w: str, min_len: int = 3, max_len: int = 45) -> bool:
    w = w.strip()
    if not w or not (min_len <= len(w) <= max_len):
        return False
    if any(c.isdigit() for c in w) or " " in w:
        return False
    return bool(LETTER.fullmatch(w))


def hunspell_lemma(raw: str) -> str:
    w = raw.strip()
    if not w or w[0].isdigit():
        return ""
    if "/" in w:
        w = w.split("/", 1)[0]
    return w.strip()


def morph_combo(
    roots: Iterable[str],
    suffixes: Iterable[str] | None = None,
    prefixes: Iterable[str] | None = None,
    bases: Iterable[str] | None = None,
) -> set[str]:
    out: set[str] = set()
    sufs = list(suffixes) if suffixes is not None else COMMON_SUFFIXES
    prefs = list(prefixes) if prefixes is not None else list(COMMON_PREFIXES)
    for root, suf in product(roots, sufs):
        w = root + suf
        if 5 <= len(w) <= 42 and LETTER.fullmatch(w):
            out.add(w)
    if bases:
        for pre, base in product(prefs, bases):
            w = pre + base
            if 6 <= len(w) <= 42 and LETTER.fullmatch(w):
                out.add(w)
    return out


def gender_number(words: Iterable[str], title_if: Callable[[str], bool] | None = None) -> set[str]:
    out: set[str] = set()
    pairs = (
        ("ico", "ica"),
        ("ivo", "iva"),
        ("oso", "osa"),
        ("ario", "aria"),
        ("orio", "oria"),
        (dec("\\u00f3gico"), dec("\\u00f3gica")),
        (dec("\\u00e1fico"), dec("\\u00e1fica")),
        (dec("\\u00e9tico"), dec("\\u00e9tica")),
        (dec("\\u00e1tico"), dec("\\u00e1tica")),
        (dec("\\u00edtico"), dec("\\u00edtica")),
    )
    for w in words:
        if not valid_word(w):
            continue
        out.add(w)
        low = w
        for masc, fem in pairs:
            if low.endswith(masc):
                stem = low[: -len(masc)]
                out.update({stem + masc, stem + fem, stem + masc + "s", stem + fem + "s"})
            elif low.endswith(fem):
                stem = low[: -len(fem)]
                out.update({stem + masc, stem + fem, stem + masc + "s", stem + fem + "s"})
        if title_if and title_if(low) and low and low[0].islower():
            out.add(low[0].upper() + low[1:])
    return out


def harvest_reference(
    ext_dir: Path,
    markers: Iterable[str],
    names: tuple[str, ...] = ("es_GT.dic", "es_ES.dic"),
) -> set[str]:
    markers = tuple(markers)
    kept: set[str] = set()
    for name in names:
        path = ext_dir / name
        if not path.exists():
            continue
        for line in path.read_text(encoding="utf-8", errors="replace").splitlines()[1:]:
            w = hunspell_lemma(line)
            if not valid_word(w):
                continue
            low = w.casefold()
            if any(m in low for m in markers):
                kept.add(w)
    return kept


def tokens_from_escaped_block(block: str) -> set[str]:
    return {t for t in codecs.decode(block, "unicode_escape").split() if valid_word(t)}


def write_expanded(
    out_path: Path,
    words: Iterable[str],
    header: str,
) -> list[str]:
    ordered = sorted(set(words), key=lambda s: (s.casefold(), s))
    out_path.parent.mkdir(parents=True, exist_ok=True)
    out_path.write_text(
        f"# {header}\n" + "\n".join(ordered) + "\n",
        encoding="utf-8",
        newline="\n",
    )
    return ordered


def import_ortho(area_dir: Path):
    """Import ortho_priority (or ortho_ang) from the area package dir."""
    sys.path.insert(0, str(area_dir))
    try:
        from ortho_priority import filter_orthography_errors  # type: ignore

        return filter_orthography_errors
    finally:
        if sys.path and sys.path[0] == str(area_dir):
            # leave it; gen scripts also need local imports
            pass


def finalize(
    bag: set[str],
    area_dir: Path,
    force_keep: Iterable[str] = (),
    deny: Iterable[str] = (),
    use_ang: bool = False,
) -> tuple[set[str], dict]:
    sys.path.insert(0, str(area_dir))
    if use_ang:
        from ortho_ang import filter_orthography_errors  # type: ignore
    else:
        from ortho_priority import filter_orthography_errors  # type: ignore

    deny_cf = {d.casefold() for d in deny}
    bag = {w for w in bag if w.casefold() not in deny_cf and valid_word(w)}
    bag, stats = filter_orthography_errors(bag)
    for w in force_keep:
        if valid_word(w) or (use_ang and w):  # ang may allow digits
            bag.add(w)
    return bag, stats
