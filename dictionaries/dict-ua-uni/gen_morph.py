# -*- coding: utf-8 -*-
"""University academic morphology (accented Spanish suffixes)."""
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
    "universit", "academ", "facult", "escuel", "departament", "carrer",
    "program", "pensum", "curricular", "credit", "asignatur", "curs",
    "semestr", "cuatrimestr", "trimestr", "licenciatur", "maestr",
    "doctor", "posgrad", "postgrad", "pregrad", "grad", "titul",
    "diplom", "certific", "especializ", "prerrequis", "requis", "correquis",
    "electiv", "optativ", "obligatori", "ordinari", "extraordinari",
    "act", "rubric", "portafoli", "investig", "tes", "tesin", "asesor",
    "catedratic", "claustr", "decan", "vicerrector", "rector",
    "acredit", "evalu", "calific", "matricul", "inscrip", "admis",
    "egres", "gradu", "titul", "aprendizaj", "competenc", "result",
    "indic", "planific", "objet", "conten", "sesion", "tall", "laboratori",
    "practic", "comunitari", "extension", "vincul", "proyecc", "modal",
    "presencial", "virtual", "hibrid", "cohort", "gener", "bec", "escolar",
    "convalid", "revalid", "homolog", "intercambi", "movil", "internacionaliz",
    "extracurricular", "curricular", "reglament", "normativ", "directr",
    "politic", "lineamient", "procediment", "resolut", "acuerd", "consej",
    "comit", "coordin", "direct", "secretari", "bibliotec", "repositori",
    "campus", "sed", "aul", "auditori", "horari", "calendari",
]

SUFFIXES = [
    dec(s)
    for s in (
        "o", "a", "os", "as", "al", "ales", "ario", "aria", "arios", "arias",
        "ivo", "iva", "ivos", "ivas", "ico", "ica", "icos", "icas",
        "ista", "istas", "ismo", "ismos", "idad", "idades",
        "aci\\u00f3n", "aciones", "encia", "encias", "ente", "entes",
        "ura", "uras",
    )
]

EXTRA = [
    dec(s)
    for s in (
        "universitario", "universitaria", "universitarios", "universitarias",
        "acad\\u00e9mico", "acad\\u00e9mica", "acad\\u00e9micos", "acad\\u00e9micas",
        "cr\\u00e9dito", "cr\\u00e9ditos", "matr\\u00edcula", "matr\\u00edculas",
        "licenciatura", "maestr\\u00eda", "doctorado", "posgrado", "pregrado",
        "prerrequisito", "electiva", "ordinaria", "extraordinaria",
        "r\\u00fabrica", "portafolio", "investigaci\\u00f3n", "tesis", "tesina",
        "catedr\\u00e1tico", "catedr\\u00e1tica", "vicerrector", "acreditaci\\u00f3n",
        "evaluaci\\u00f3n", "graduaci\\u00f3n", "titulaci\\u00f3n",
        "syllabus", "silabus", "PAES", "UFM",
    )
]

PREFIXES = (
    "a", "an", "anti", "co", "contra", "des", "extra", "in", "inter",
    "multi", "pre", "post", "re", "sub", "super", "semi", "pluri", "uni", "bi",
)


def morph_tokens() -> set[str]:
    out: set[str] = set(EXTRA)
    for root, suf in product(ROOTS, SUFFIXES):
        w = root + suf
        if 4 <= len(w) <= 40 and LETTER.fullmatch(w):
            out.add(w)
    bases = [
        dec("aci\\u00f3n"), "idad", "ismo", "ista", "ario", "aria",
        "ivo", "iva", "al", "ico", "ica", "ente", "ura",
    ]
    for pre, base in product(PREFIXES, bases):
        w = pre + base
        if 5 <= len(w) <= 40 and LETTER.fullmatch(w):
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
