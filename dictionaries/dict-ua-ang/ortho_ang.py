# -*- coding: utf-8 -*-
"""Orthography filter for anglicism dictionary.

Keeps English/loanword forms (-ing, -tion, etc.). Only drops missing-tilde
variants of accented Spanish lemmas and unaccented productive Spanish endings.
"""
from __future__ import annotations

import re
import unicodedata
from pathlib import Path

EXT = Path(__file__).resolve().parent / "source" / "external"

LETTER = re.compile(
    r"^[A-Za-z0-9\u00c1\u00c9\u00cd\u00d3\u00da\u00dc\u00d1"
    r"\u00e1\u00e9\u00ed\u00f3\u00fa\u00fc\u00f1"
    r"\u00c7\u00e7\u00d6\u00f6]+$"
)

ALWAYS_BAD_UNACCENTED_ENDINGS = (
    "cion",
    "ciones",
    "sion",
    "siones",
    "logia",
    "logias",
)


def deaccent(s: str) -> str:
    return "".join(
        c for c in unicodedata.normalize("NFD", s) if unicodedata.category(c) != "Mn"
    )


def has_diacritic(s: str) -> bool:
    return bool(
        re.search(
            r"[\u00e1\u00e9\u00ed\u00f3\u00fa\u00fc\u00f1"
            r"\u00c1\u00c9\u00cd\u00d3\u00da\u00dc\u00d1]",
            s,
        )
    )


def hunspell_lemma(raw: str) -> str:
    w = raw.strip()
    if not w or w[0].isdigit():
        return ""
    if "/" in w:
        w = w.split("/", 1)[0]
    return w.strip()


def load_reference_lemmas() -> set[str]:
    lemmas: set[str] = set()
    for name in ("es_GT.dic", "es_ES.dic"):
        path = EXT / name
        if not path.exists():
            continue
        for line in path.read_text(encoding="utf-8", errors="replace").splitlines():
            w = hunspell_lemma(line)
            if w and LETTER.fullmatch(w):
                lemmas.add(w)
    return lemmas


def accented_keys(lemmas: set[str]) -> set[str]:
    keys: set[str] = set()
    for w in lemmas:
        if has_diacritic(w):
            keys.add(deaccent(w).casefold())
    return keys


def is_always_bad_unaccented(w: str) -> bool:
    if has_diacritic(w):
        return False
    low = w.casefold()
    return any(low.endswith(suf) for suf in ALWAYS_BAD_UNACCENTED_ENDINGS)


def filter_orthography_errors(words: set[str]) -> tuple[set[str], dict[str, int]]:
    """Drop Spanish spelling errors only; keep English loanword forms."""
    ref = load_reference_lemmas()
    ref_acc_keys = accented_keys(ref)
    bag_acc_keys = accented_keys(words)
    forbidden_unaccented_keys = ref_acc_keys | bag_acc_keys

    kept: set[str] = set()
    stats = {
        "input": len(words),
        "drop_unaccented_vs_accented": 0,
        "drop_bad_ending": 0,
        "kept": 0,
    }

    for w in words:
        if not has_diacritic(w):
            key = deaccent(w).casefold()
            if key in forbidden_unaccented_keys:
                stats["drop_unaccented_vs_accented"] += 1
                continue
            if is_always_bad_unaccented(w):
                stats["drop_bad_ending"] += 1
                continue
        kept.add(w)

    stats["kept"] = len(kept)
    return kept, stats
