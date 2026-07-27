# -*- coding: utf-8 -*-
"""Generate UA psychology Hunspell dictionary (UTF-8). Orthography-first."""
from __future__ import annotations

import re
from pathlib import Path

from gen_lexicon import all_tokens  # type: ignore
from gen_morph import morph_tokens, simple_plurals  # type: ignore
from ortho_priority import filter_orthography_errors  # type: ignore

BASE = Path(__file__).resolve().parent
OUT = BASE / "ua_psi_GT.dic"
SRC = BASE / "source"
EXT = SRC / "external"
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
    "clear",
    "borderline",
    "grounded",
    "clinico",
    "clinica",
    "diagnostico",
    "depresion",
    "imagenes",
    "tecnico",
}

PSI_MARKERS = (
    "psic",
    "mental",
    "cognit",
    "conduct",
    "comport",
    "neuro",
    "clinic",
    "terap",
    "trastorn",
    "personal",
    "emocion",
    "afect",
    "ansiedad",
    "depres",
    "esquiz",
    "bipolar",
    "autis",
    "trauma",
    "fob",
    "obses",
    "compuls",
    "motiv",
    "aprendiz",
    "memori",
    "atencion",
    "percepc",
    "sensoper",
    "desarroll",
    "infant",
    "adolesc",
    "adult",
    "vejez",
    "social",
    "grupo",
    "comun",
    "organiz",
    "laboral",
    "industrial",
    "educativ",
    "escolar",
    "famil",
    "parej",
    "sexual",
    "gener",
    "ident",
    "autoestim",
    "resilien",
    "adapt",
    "estres",
    "coping",
    "afront",
    "evalu",
    "diagnost",
    "psicomet",
    "test",
    "escala",
    "inventari",
    "valid",
    "confiabil",
    "fiabil",
    "muestre",
    "estadist",
    "correl",
    "regres",
    "factor",
    "varian",
    "experimental",
    "longitud",
    "transversal",
    "observ",
    "encuest",
    "entrev",
    "cualit",
    "cuantit",
    "fenomen",
    "etnograf",
    "metaanal",
    "hipotes",
    "variable",
    "construct",
    "operacional",
    "sesg",
    "placebo",
    "aleator",
    "signific",
    "inferenc",
    "parametr",
    "sindrome",
    "sintom",
    "prognos",
    "etiol",
    "psicopat",
    "psicofarm",
    "antidepres",
    "ansiolit",
    "neurolept",
    "estabiliz",
    "psicoeduc",
    "rehabilit",
    "prevenc",
    "promoc",
    "salud",
    "bienestar",
    "mindful",
    "cognitivo",
    "conductual",
    "humanist",
    "sistemic",
    "gestalt",
    "psicoanal",
    "hipocamp",
    "amigdal",
    "corteza",
    "lobulo",
    "hemisfer",
    "sinaps",
    "neurotrans",
    "dopamin",
    "serotonin",
    "neuropsic",
    "plastic",
    "neurog",
    "TDAH",
    "TEA",
    "TOC",
    "TEPT",
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


def hunspell_lemma(raw: str) -> str:
    w = raw.strip()
    if not w or w[0].isdigit():
        return ""
    if "/" in w:
        w = w.split("/", 1)[0]
    return w.strip()


def from_reference_dic() -> set[str]:
    kept: set[str] = set()
    for name in ("es_GT.dic", "es_ES.dic"):
        path = EXT / name
        if not path.exists():
            continue
        for line in path.read_text(encoding="utf-8", errors="replace").splitlines()[1:]:
            w = hunspell_lemma(line)
            if not valid(w):
                continue
            low = w.casefold()
            if any(m in low for m in PSI_MARKERS):
                kept.add(w)
    return kept


def load_expanded() -> set[str]:
    """Load expanded_psi.txt if exists."""
    expanded_file = EXT / "expanded_psi.txt"
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
        "psicolog\u00eda",
        "psicometr\u00eda",
        "neuropsicolog\u00eda",
        "psicoterapia",
        "depresi\u00f3n",
        "ansiedad",
        "esquizofrenia",
        "personalidad",
        "inteligencia",
        "memoria",
        "percepci\u00f3n",
    }
    keyset = {k.casefold() for k in keys}
    out: set[str] = set()
    for w in words:
        if w.casefold() in keyset and w and w[0].islower():
            out.add(w[0].upper() + w[1:])
    return out


def collect() -> list[str]:
    bag: set[str] = set()
    deny_cf = {d.casefold() for d in DENY}

    for w in all_tokens():
        if valid(w) and w.casefold() not in deny_cf:
            bag.add(w)
    for w in morph_tokens():
        if valid(w) and w.casefold() not in deny_cf:
            bag.add(w)
    for w in from_reference_dic():
        if w.casefold() not in deny_cf:
            bag.add(w)
    for w in load_expanded():
        if w.casefold() not in deny_cf:
            bag.add(w)
    bag |= title_variants(bag)
    for w in simple_plurals(bag):
        if valid(w) and w.casefold() not in deny_cf:
            bag.add(w)

    bag, stats = filter_orthography_errors(bag)
    for w in (
        "psicolog\u00eda",
        "psicometr\u00eda",
        "neuropsicolog\u00eda",
        "psicoterapia",
        "cl\u00ednico",
        "cognitivo",
        "DSM",
        "CIE",
        "trastorno",
        "depresi\u00f3n",
        "ansiedad",
        "esquizofrenia",
        "TDAH",
        "TEA",
        "variable",
        "hip\u00f3tesis",
        "muestreo",
        "validaci\u00f3n",
        "confiabilidad",
    ):
        bag.add(w)
    print(
        "Filtro ortografia: "
        f"in={stats['input']} kept={stats['kept']} "
        f"drop_sin_tilde={stats['drop_unaccented_vs_accented']} "
        f"drop_sufijo={stats['drop_bad_medical_ending']} "
        f"drop_en={stats['drop_english']}"
    )

    words = sorted(bag, key=lambda s: (s.casefold(), s))
    dump = SRC / "ua_psi_lexicon_full.txt"
    dump.write_text(
        "# Lexico psicologia UA (generado)\n" + "\n".join(words) + "\n",
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
        "psicolog\u00eda",
        "psicometr\u00eda",
        "neuropsicolog\u00eda",
        "psicoterapia",
        "cl\u00ednico",
        "cognitivo",
        "DSM",
        "CIE",
        "trastorno",
        "depresi\u00f3n",
        "ansiedad",
        "esquizofrenia",
        "TDAH",
        "TEA",
        "variable",
        "hip\u00f3tesis",
        "muestreo",
        "validaci\u00f3n",
        "confiabilidad",
        "clinico",
        "depresion",
        "imagenes",
    ]
    s = set(words)
    for c in checks:
        print(f"  {c}: {c in s}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
