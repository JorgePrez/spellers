# -*- coding: utf-8 -*-
"""Orthography-first filter for UA dictionaries (ASCII-safe source).

Rule: never keep a form that is a Spanish spelling error (esp. missing tilde).
If a form could be a misspelling of a correct Spanish lemma, drop it.

Improvements vs early versions:
- Expand /G /GS gender-number for safe adjective endings (-ico/-iva/-ogo…)
  so metodológico/GS also forbids metodologica.
- Always-bad productive endings include -logico/-logica (unaccented).
- Do NOT treat -ciones/-siones as always-bad (many plurals are correct).
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

# Productive endings that require an acute accent (singular / adj patterns).
# Avoid bare -ciones/-siones: elecciones, etc. are correct.
ALWAYS_BAD_UNACCENTED_ENDINGS = (
    "cion",
    "sion",
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
    "logica",
    "logico",
    "logicas",
    "logicos",
    "nomica",
    "nomico",
    "nomicas",
    "nomicos",
    "metrica",
    "metrico",
    "metricas",
    "metricos",
    "terapeutica",
    "terapeutico",
)

# Exact lemmas that must never be whitelisted without accent.
ALWAYS_BAD_EXACT = {
    "algebra",
    "algebras",
    "exequatur",
    "analisis",
}

# Adjective-like endings safe to expand for gender/number (/G /GS).
# Intentionally excludes -ogo/-oga (diálogo/catálogo) to avoid killing verbs
# dialogo/cataloga that are valid without accent.
_SAFE_ADJ_END = (
    "ico",
    "ica",
    "ivo",
    "iva",
    "oso",
    "osa",
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
    "ally",
    "ized",
    "ised",
    "izing",
    "ising",
    "ology",
    "opathies",
)
# Note: do NOT treat -able/-ible as English — productive in Spanish (accionable).


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


def hunspell_lemma(raw: str) -> tuple[str, str]:
    """Strip Hunspell flags: palabra/ABC -> (palabra, flags)."""
    w = raw.strip()
    if not w or w[0].isdigit():
        return "", ""
    flags = ""
    if "/" in w:
        w, flags = w.split("/", 1)
    return w.strip(), flags.strip()


def _safe_adj_stem_forms(w: str) -> set[str]:
    """Gender/number variants for accented adjectives (-ico/-iva/-ogo…)."""
    forms = {w}
    if not has_diacritic(w):
        return forms
    low = w.casefold()
    if not any(low.endswith(suf) for suf in _SAFE_ADJ_END):
        return forms
    if w.endswith("o"):
        stem = w[:-1]
        forms.update({stem + "a", stem + "os", stem + "as"})
    elif w.endswith("a"):
        stem = w[:-1]
        forms.update({stem + "o", stem + "os", stem + "as"})
    return forms


def load_reference_lemmas() -> set[str]:
    lemmas: set[str] = set()
    for name in ("es_GT.dic", "es_ES.dic"):
        path = EXT / name
        if not path.exists():
            continue
        for line in path.read_text(encoding="utf-8", errors="replace").splitlines():
            w, flags = hunspell_lemma(line)
            if not w or not LETTER.fullmatch(w):
                continue
            lemmas.add(w)
            # Expand /G /GS only for safe adjective patterns (metodológico → …ógica)
            if has_diacritic(w) and ("G" in flags):
                lemmas |= _safe_adj_stem_forms(w)
    return lemmas


def accented_keys(lemmas: set[str]) -> set[str]:
    """deaccent(casefold) keys for lemmas that carry a Spanish diacritic."""
    keys: set[str] = set()
    for w in lemmas:
        if has_diacritic(w):
            keys.add(deaccent(w).casefold())
            for form in _safe_adj_stem_forms(w):
                if has_diacritic(form):
                    keys.add(deaccent(form).casefold())
    return keys


def is_always_bad_unaccented(w: str) -> bool:
    if has_diacritic(w):
        return False
    low = w.casefold()
    if low in ALWAYS_BAD_EXACT:
        return True
    return any(low.endswith(suf) for suf in ALWAYS_BAD_UNACCENTED_ENDINGS)


def looks_english(w: str) -> bool:
    low = w.casefold()
    if low in ENG_WORDS:
        return True
    if any(low.endswith(e) for e in ENG_ENDS):
        return True
    return False


def filter_orthography_errors(words: set[str]) -> tuple[set[str], dict[str, int]]:
    """Drop forms that would mask Spanish spelling mistakes.

    Priority: orthography > domain vocabulary.
    """
    ref = load_reference_lemmas()
    ref_acc_keys = accented_keys(ref)
    bag_acc_keys = accented_keys(words)
    forbidden_unaccented_keys = ref_acc_keys | bag_acc_keys

    kept: set[str] = set()
    stats = {
        "input": len(words),
        "drop_unaccented_vs_accented": 0,
        "drop_bad_medical_ending": 0,
        "drop_english": 0,
        "kept": 0,
    }

    for w in words:
        if looks_english(w):
            stats["drop_english"] += 1
            continue
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
