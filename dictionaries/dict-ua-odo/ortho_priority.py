# -*- coding: utf-8 -*-
"""Orthography-first filter for ua_med dictionary. ASCII-safe source.

Rule: never keep a form that is a Spanish spelling error (esp. missing tilde).
If a form could be a misspelling of a correct Spanish/medical lemma, drop it.
es_GT / es_ES cover general Spanish; bag covers medical accented siblings.
"""
from __future__ import annotations

import re
import unicodedata
from pathlib import Path

EXT = Path(__file__).resolve().parent / "source" / "external"

LETTER = re.compile(
    r"^[A-Za-z\u00c1\u00c9\u00cd\u00d3\u00da\u00dc\u00d1"
    r"\u00e1\u00e9\u00ed\u00f3\u00fa\u00fc\u00f1"
    r"\u00c7\u00e7\u00d6\u00f6]+"
    r"(?:-[A-Za-z\u00c1\u00c9\u00cd\u00d3\u00da\u00dc\u00d1"
    r"\u00e1\u00e9\u00ed\u00f3\u00fa\u00fc\u00f1"
    r"\u00c7\u00e7\u00d6\u00f6]+)?$"
)

# Productive Spanish/medical endings that require an acute accent.
# Keeping the unaccented form would white-list real orthography errors.
ALWAYS_BAD_UNACCENTED_ENDINGS = (
    "cion",
    "ciones",
    "sion",
    "siones",
    "logia",
    "logias",
    "grafia",
    "grafias",
    "scopia",
    "scopias",
    "tomia",
    "tomias",
    "ectomia",
    "ectomias",
    "patia",
    "patias",
    "dinamica",
    "dinamico",
    "dinamicas",
    "dinamicos",
    "cinetica",
    "cinetico",
    "cineticas",
    "cineticos",
    "grafica",
    "grafico",
    "graficas",
    "graficos",
    "terapeutica",
    "terapeutico",
)

ENG_ENDS = (
    "ing",
    "tion",
    "tions",
    "ness",
    "ment",
    "ments",
    "ship",
    "ships",
    # note: do NOT ban Spanish -able/-ible (semiajustable, ajustable)
    "ally",
    "ized",
    "ised",
    "izing",
    "ising",
    "ology",
    "opathies",
)

# Wrong accent on -plastia (correct is unaccented -plastia)
BAD_ACCENTED_ENDINGS = (
    "plast\u00eda",
    "plast\u00edas",
)

ENG_WORDS = {
    "the",
    "and",
    "for",
    "with",
    "from",
    "that",
    "this",
    "were",
    "was",
    "are",
    "been",
    "being",
    "have",
    "has",
    "had",
    "will",
    "would",
    "could",
    "should",
    "vaccine",
    "airway",
    "agenda",
    "extraction",
    "interaction",
    "interpretation",
    "intervention",
    "microsimulation",
    "substituting",
    "anticipating",
    "anticoagulation",
    "abscessos",
}


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
    """Strip Hunspell flags: palabra/ABC -> palabra."""
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
    """deaccent(casefold) keys for lemmas that carry a Spanish diacritic."""
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


def is_bad_accented_form(w: str) -> bool:
    low = w.casefold()
    return any(low.endswith(suf) for suf in BAD_ACCENTED_ENDINGS)


def looks_english(w: str) -> bool:
    low = w.casefold()
    if low in ENG_WORDS:
        return True
    if any(low.endswith(e) for e in ENG_ENDS):
        return True
    return False


def filter_orthography_errors(words: set[str]) -> tuple[set[str], dict[str, int]]:
    """Drop forms that would mask Spanish spelling mistakes.

    Priority: orthography > medical vocabulary.
    """
    ref = load_reference_lemmas()
    ref_acc_keys = accented_keys(ref)

    stats = {
        "input": len(words),
        "drop_unaccented_vs_accented": 0,
        "drop_bad_medical_ending": 0,
        "drop_english": 0,
        "kept": 0,
    }

    # Pass 1: drop english + known-bad accented endings (e.g. -plastía)
    interim: set[str] = set()
    for w in words:
        if looks_english(w):
            stats["drop_english"] += 1
            continue
        if is_bad_accented_form(w):
            stats["drop_bad_medical_ending"] += 1
            continue
        interim.add(w)

    # Build accent keys only from remaining words (avoid poisoning with -plastía)
    forbidden_unaccented_keys = ref_acc_keys | accented_keys(interim)

    kept: set[str] = set()
    for w in interim:
        if not has_diacritic(w):
            key = deaccent(w).casefold()
            if key in forbidden_unaccented_keys:
                stats["drop_unaccented_vs_accented"] += 1
                continue
            if is_always_bad_unaccented(w):
                stats["drop_bad_medical_ending"] += 1
                continue
        kept.add(w)

    stats["kept"] = len(kept)
    return kept, stats
