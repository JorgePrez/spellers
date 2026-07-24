# -*- coding: utf-8 -*-
"""Dental morphology expansion (accented Spanish suffixes only)."""
from __future__ import annotations

import codecs
import re
from itertools import product

LETTER = re.compile(
    r"^[A-Za-z\u00c1\u00c9\u00cd\u00d3\u00da\u00dc\u00d1"
    r"\u00e1\u00e9\u00ed\u00f3\u00fa\u00fc\u00f1]+$"
)


def dec(s: str) -> str:
    return codecs.decode(s, "unicode_escape")


ROOTS = [
    "odonto",
    "dent",
    "dento",
    "gingivo",
    "gingiv",
    "periodonto",
    "periodont",
    "endodonto",
    "endodont",
    "ortodonto",
    "ortodont",
    "prostodonto",
    "prostodont",
    "maxilo",
    "mandibulo",
    "alveolo",
    "pulpo",
    "radiculo",
    "apico",
    "corono",
    "ocluso",
    "vestibulo",
    "linguo",
    "palato",
    "mesio",
    "disto",
    "bucco",
    "labio",
    "implanto",
    "cemento",
    "cario",
    "radiogr",
    "cefalom",
    "ortopantom",
    "fluor",
    "amalgam",
    "composit",
    "resin",
    "ceram",
    "zirconi",
    "titan",
    "alginat",
    "silicon",
    "anestesi",
    "exodon",
    "apicect",
    "gingivect",
    "gingivoplast",
    "alveoloplast",
    "frenect",
    "osteointegr",
    "protes",
    "corona",
    "carilla",
    "bracket",
    "alineador",
    "retenedor",
    "ferul",
    "brux",
    "halit",
    "pericoronar",
    "alveolitis",
    "maloclus",
    "condil",
    "maseter",
    "parotid",
    "submandibul",
    "sublingu",
    "saliv",
    "buco",
    "craneofaci",
    "maxilofaci",
    "odontopediatr",
    "periodontolog",
    "implantolog",
    "gerodontolog",
]

SUFFIXES = [
    dec(s)
    for s in (
        "itis",
        "osis",
        "oma",
        "algia",
        "pat\\u00eda",
        "scop\\u00eda",
        "tom\\u00eda",
        "ectom\\u00eda",
        "plastia",
        "graf\\u00eda",
        "gr\\u00e1fica",
        "gr\\u00e1fico",
        "log\\u00eda",
        "logo",
        "loga",
        "logos",
        "logas",
        "ico",
        "ica",
        "icos",
        "icas",
        "ivo",
        "iva",
        "al",
        "ales",
        "ar",
        "ares",
        "ario",
        "aria",
        "ismo",
        "ismos",
        "ista",
        "istas",
        "aci\\u00f3n",
        "encia",
        "ente",
        "entes",
        "ura",
        "uras",
    )
]

EXTRA = [
    dec(s)
    for s in (
        "odont\\u00f3logo",
        "odont\\u00f3loga",
        "odont\\u00f3logos",
        "odont\\u00f3logas",
        "odontol\\u00f3gico",
        "odontol\\u00f3gica",
        "odontol\\u00f3gicos",
        "odontol\\u00f3gicas",
        "endod\\u00f3ntico",
        "endod\\u00f3ntica",
        "endod\\u00f3nticos",
        "endod\\u00f3nticas",
        "periodontal",
        "periodontales",
        "gingival",
        "gingivales",
        "oclusal",
        "oclusales",
        "radicular",
        "radiculares",
        "apical",
        "apicales",
        "mesial",
        "mesiales",
        "distal",
        "distales",
        "vestibular",
        "vestibulares",
        "lingual",
        "linguales",
        "palatino",
        "palatina",
        "palatinos",
        "palatinas",
        "incisal",
        "incisales",
        "interproximal",
        "interproximales",
        "maxilar",
        "maxilares",
        "mandibular",
        "mandibulares",
        "alveolar",
        "alveolares",
        "pulpar",
        "pulpares",
        "coronario",
        "coronaria",
        "temporomandibular",
        "craneofacial",
        "maxilofacial",
        "osteointegraci\\u00f3n",
        "ortopantomograf\\u00eda",
        "cefalometr\\u00eda",
        "odontopediatr\\u00eda",
        "periodontolog\\u00eda",
        "implantolog\\u00eda",
        "gerodontolog\\u00eda",
        "apicectom\\u00eda",
        "gingivectom\\u00eda",
        "frenectom\\u00eda",
        "alveoloplastia",
        "tartrectom\\u00eda",
    )
]

PREFIXES = (
    "a",
    "an",
    "anti",
    "auto",
    "bi",
    "hemi",
    "hiper",
    "hipo",
    "infra",
    "inter",
    "intra",
    "extra",
    "peri",
    "pre",
    "post",
    "sub",
    "supra",
    "trans",
    "endo",
    "exo",
    "neo",
    "pseudo",
    "micro",
    "macro",
    "multi",
    "poli",
    "semi",
    "ultra",
)


def morph_tokens() -> set[str]:
    out: set[str] = set(EXTRA)
    for root, suf in product(ROOTS, SUFFIXES):
        w = root + suf
        if 5 <= len(w) <= 40 and LETTER.fullmatch(w):
            out.add(w)
    bases = [
        dec("pat\\u00eda"),
        dec("log\\u00eda"),
        dec("tom\\u00eda"),
        dec("ectom\\u00eda"),
        dec("graf\\u00eda"),
        "itis",
        "osis",
        "oma",
        "algia",
        "plastia",
        "ismo",
        "ista",
        "al",
        "ico",
        "ica",
    ]
    for pre, base in product(PREFIXES, bases):
        w = pre + base
        if 6 <= len(w) <= 40 and LETTER.fullmatch(w):
            out.add(w)
    for pre, root in product(
        ("peri", "endo", "orto", "supra", "infra", "inter", "intra", "sub"),
        (
            "dental",
            "gingival",
            "apical",
            "radicular",
            dec("od\\u00f3ntico"),
            dec("od\\u00f3ntica"),
        ),
    ):
        w = pre + root
        if LETTER.fullmatch(w):
            out.add(w)
    return out


def simple_plurals(words: set[str]) -> set[str]:
    out: set[str] = set()
    for w in words:
        if len(w) < 4 or len(w) > 35:
            continue
        low = w.casefold()
        if low.endswith(("s", "x")):
            continue
        if low.endswith(("a", "e", "i", "o", "u")):
            out.add(w + "s")
        elif low.endswith(("al", "ar", "or")):
            out.add(w + "es")
    return out
