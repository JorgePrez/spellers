# -*- coding: utf-8 -*-
"""Generate UA architecture Hunspell dictionary (UTF-8). Orthography-first."""
from __future__ import annotations

import re
from pathlib import Path

from gen_lexicon import all_tokens  # type: ignore
from gen_morph import morph_tokens, simple_plurals  # type: ignore
from ortho_priority import filter_orthography_errors  # type: ignore

BASE = Path(__file__).resolve().parent
OUT = BASE / "ua_arq_GT.dic"
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
    "clear",
}

ARQ_MARKERS = (
    "arquitect",
    "urban",
    "planimetr",
    "axonometr",
    "construcc",
    "edifici",
    "estructura",
    "ciment",
    "fundaci",
    "columna",
    "viga",
    "losa",
    "muro",
    "fachada",
    "cubierta",
    "techumbre",
    "instalac",
    "fontaner",
    "sanitari",
    "electr",
    "iluminac",
    "ventilac",
    "climatizac",
    "calefacc",
    "diseño",
    "disen",
    "plano",
    "proyecto",
    "maqueta",
    "modelo",
    "render",
    "modelad",
    "CAD",
    "BIM",
    "topograf",
    "terren",
    "nivel",
    "cotas",
    "escala",
    "dimensi",
    "medid",
    "metrad",
    "presupuest",
    "cubicac",
    "especificac",
    "normat",
    "reglament",
    "codigo",
    "construcc",
    "sismorresist",
    "sismo",
    "sismico",
    "seism",
    "estructural",
    "resistenc",
    "carga",
    "esfuerzo",
    "moment",
    "flexion",
    "compresi",
    "traccion",
    "cortant",
    "torsion",
    "hormigon",
    "concreto",
    "acero",
    "madera",
    "ladrillo",
    "block",
    "mampost",
    "ceram",
    "vidri",
    "alumin",
    "materiale",
    "acabado",
    "revestim",
    "piso",
    "cielo",
    "carpinter",
    "herreri",
    "cerraj",
    "ebanist",
    "paisaj",
    "jardin",
    "area",
    "verde",
    "espacio",
    "publico",
    "plaza",
    "parque",
    "zonif",
    "uso",
    "suelo",
    "ordenamient",
    "territorial",
    "patrimon",
    "histor",
    "restaurac",
    "conservac",
    "rehabilit",
    "intervencion",
    "COPRAQ",
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
    """Harvest architecture terms from es_GT/es_ES using ARQ_MARKERS."""
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
            if any(m in low for m in ARQ_MARKERS):
                kept.add(w)
    return kept


def load_expanded() -> set[str]:
    """Load expanded_arq.txt if exists."""
    expanded_file = EXT / "expanded_arq.txt"
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
        "arquitectura",
        "urbanismo",
        "planimetr\u00eda",
        "axonometr\u00eda",
        "construcci\u00f3n",
        "COPRAQ",
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
    print(
        "Filtro ortografia: "
        f"in={stats['input']} kept={stats['kept']} "
        f"drop_sin_tilde={stats['drop_unaccented_vs_accented']} "
        f"drop_sufijo={stats['drop_bad_medical_ending']} "
        f"drop_en={stats['drop_english']}"
    )

    for w in load_fp_seeds():
        bag.add(w)
    # Seeds must not reintroduce missing-tilde forms (exequatur, etc.)
    bag, stats2 = filter_orthography_errors(bag)
    print(
        "Filtro ortografia post-seeds: "
        f"in={stats2['input']} kept={stats2['kept']} "
        f"drop_sin_tilde={stats2['drop_unaccented_vs_accented']}"
    )
    words = sorted(bag, key=lambda s: (s.casefold(), s))
    dump = SRC / "ua_arq_lexicon_full.txt"
    dump.write_text(
        "# Lexico arquitectura UA (generado)\n" + "\n".join(words) + "\n",
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
        "arquitectura",
        "urbanismo",
        "planimetr\u00eda",
        "axonometr\u00eda",
        "construcci\u00f3n",
        "estructura",
        "instalaci\u00f3n",
        "cimentaci\u00f3n",
        "sismorresistente",
        "COPRAQ",
        "arquitectonico",
        "construccion",
        "tecnico",
    ]
    s = set(words)
    for c in checks:
        print(f"  {c}: {c in s}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
