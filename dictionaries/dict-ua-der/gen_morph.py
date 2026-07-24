# -*- coding: utf-8 -*-
"""Legal morphology (accented Spanish suffixes only)."""
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
    "jurid",
    "legal",
    "legisl",
    "judicial",
    "jurisdicc",
    "proces",
    "procediment",
    "penal",
    "civil",
    "mercantil",
    "laboral",
    "administrativ",
    "constitucional",
    "contractual",
    "obligatori",
    "contract",
    "demand",
    "sentenc",
    "resolutori",
    "apelatori",
    "casacion",
    "ampar",
    "embarg",
    "ejecutori",
    "notificatori",
    "citatori",
    "emplaz",
    "prescript",
    "usucap",
    "heredit",
    "testament",
    "sucesori",
    "matrimoni",
    "divorci",
    "filiatori",
    "adoptiv",
    "indemnizatori",
    "resarcitori",
    "delictiv",
    "criminal",
    "culpabil",
    "imputabil",
    "antijuridic",
    "tipic",
    "culpos",
    "imprudent",
    "negligent",
    "prevaric",
    "malvers",
    "extors",
    "estaf",
    "usurp",
    "feminicid",
    "homicid",
    "parricid",
    "secuest",
    "extradic",
    "expropiatori",
    "concesionari",
    "licitatori",
    "adjudicatari",
    "impugnatori",
    "cautelar",
    "procesal",
    "sustantiv",
    "normativ",
    "reglamentari",
    "estatutari",
    "promulgatori",
    "derogatori",
    "notarial",
    "registral",
    "societari",
    "concursal",
    "hipotecari",
    "prendari",
    "fiduciari",
    "fideicomis",
    "arrendatic",
    "posesori",
    "reivindicatori",
    "interdictal",
    "arbitr",
    "conciliatori",
    "mediatori",
    "diplomat",
    "consular",
    "internacional",
    "supranacional",
    "comunitari",
    "convencional",
    "tratad",
    "ratificatori",
    "penalist",
    "civilist",
    "constitucionalist",
    "administrativist",
    "laboralist",
    "mercantilist",
    "internacionalist",
    "jurisprudencial",
]

SUFFIXES = [
    dec(s)
    for s in (
        "o",
        "a",
        "os",
        "as",
        "al",
        "ales",
        "ario",
        "aria",
        "arios",
        "arias",
        "ivo",
        "iva",
        "ivos",
        "ivas",
        "ico",
        "ica",
        "icos",
        "icas",
        "ista",
        "istas",
        "ismo",
        "ismos",
        "idad",
        "idades",
        "aci\\u00f3n",
        "encia",
        "encias",
        "ente",
        "entes",
        "ura",
        "uras",
        "orio",
        "oria",
    )
]

EXTRA = [
    dec(s)
    for s in (
        "jur\\u00eddico",
        "jur\\u00eddica",
        "jur\\u00eddicos",
        "jur\\u00eddicas",
        "procesal",
        "procesales",
        "constitucional",
        "constitucionales",
        "administrativo",
        "administrativa",
        "mercantil",
        "mercantiles",
        "laboral",
        "laborales",
        "notarial",
        "notariales",
        "registral",
        "registrales",
        "cautelar",
        "cautelares",
        "inconstitucional",
        "inconstitucionales",
        "antijur\\u00eddico",
        "antijur\\u00eddica",
        "inimputable",
        "inimputables",
        "reincidente",
        "reincidentes",
        "demandante",
        "demandantes",
        "demandado",
        "demandada",
        "querellante",
        "imputado",
        "procesado",
        "condenado",
        "absuelto",
        "exequatur",
        "fideicomiso",
        "fideicomisario",
        "usufructo",
        "usufructuario",
        "usucapi\\u00f3n",
        "prescripci\\u00f3n",
        "jurisprudencia",
        "jurisprudencial",
        "jurisprudenciales",
        "habeas",
        "corpus",
        "affidavit",
        "erga",
        "omnes",
    )
]

PREFIXES = (
    "a",
    "an",
    "anti",
    "auto",
    "co",
    "contra",
    "des",
    "extra",
    "in",
    "inter",
    "intra",
    "multi",
    "pre",
    "post",
    "re",
    "sub",
    "super",
    "trans",
    "ultra",
    "infra",
    "supra",
    "semi",
    "pluri",
    "uni",
    "bi",
)


def morph_tokens() -> set[str]:
    out: set[str] = set(EXTRA)
    for root, suf in product(ROOTS, SUFFIXES):
        w = root + suf
        if 5 <= len(w) <= 40 and LETTER.fullmatch(w):
            out.add(w)
    bases = [
        dec("aci\\u00f3n"),
        "idad",
        "ismo",
        "ista",
        "ario",
        "aria",
        "ivo",
        "iva",
        "al",
        "ico",
        "ica",
        "ente",
        "ura",
    ]
    for pre, base in product(PREFIXES, bases):
        w = pre + base
        if 6 <= len(w) <= 40 and LETTER.fullmatch(w):
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
        elif low.endswith(("al", "ar", "or", "en")):
            out.add(w + "es")
    return out
