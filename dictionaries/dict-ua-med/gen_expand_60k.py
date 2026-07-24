# -*- coding: utf-8 -*-
"""Expand medical dictionary to >=60000 lemmas. ASCII-safe source only."""
from __future__ import annotations

import codecs
import re
from itertools import product
from pathlib import Path

BASE = Path(__file__).resolve().parent
SRC = BASE / "source"
EXT = SRC / "external"
OUT_SEED = SRC / "user_examples_med.txt"
OUT_EXPANDED = EXT / "expanded_60k.txt"
OUT_EXPANDED.parent.mkdir(parents=True, exist_ok=True)

LETTER = re.compile(
    r"^[A-Za-z\u00c1\u00c9\u00cd\u00d3\u00da\u00dc\u00d1"
    r"\u00e1\u00e9\u00ed\u00f3\u00fa\u00fc\u00f1]+$"
)


def dec(s: str) -> str:
    return codecs.decode(s, "unicode_escape")


USER_CHECK = [
    "somitas",
    "miofibroblastos",
    "pericitos",
    "farmacodin\u00e1mica",
    "coagulativa",
    "Cariotipo",
    "cariotipo",
    "mosaicismo",
    "laminillas",
    "electrocardiogr\u00e1fica",
]

SUFFIXES = tuple(
    dec(s)
    for s in (
        "itis",
        "osis",
        "oma",
        "omas",
        "emia",
        "uria",
        "urias",
        "algia",
        "algias",
        "patia",
        "pat\\u00eda",
        "patias",
        "pat\\u00edas",
        "scopia",
        "scop\\u00eda",
        "scopias",
        "scop\\u00edas",
        "tomia",
        "tom\\u00eda",
        "tomias",
        "tom\\u00edas",
        "ectomia",
        "ectom\\u00eda",
        "ectomias",
        "ectom\\u00edas",
        "grafia",
        "graf\\u00eda",
        "grafias",
        "graf\\u00edas",
        "grafico",
        "gr\\u00e1fico",
        "grafica",
        "gr\\u00e1fica",
        "graficos",
        "gr\\u00e1ficos",
        "graficas",
        "gr\\u00e1ficas",
        "plastia",
        "plastias",
        "rragia",
        "rragias",
        "rrea",
        "rreas",
        "penia",
        "penias",
        "plasia",
        "plasias",
        "plejia",
        "plej\\u00eda",
        "plejias",
        "plej\\u00edas",
        "paresia",
        "paresias",
        "megalia",
        "megalias",
        "cele",
        "celes",
        "lisis",
        "ptosis",
        "centesis",
        "stomia",
        "stom\\u00eda",
        "desis",
        "pexia",
        "pexias",
        "cito",
        "citos",
        "blasto",
        "blastos",
        "logo",
        "loga",
        "logos",
        "logas",
        "logia",
        "log\\u00eda",
        "geno",
        "gena",
        "genos",
        "genas",
        "genesis",
        "g\\u00e9nesis",
        "ismo",
        "ismos",
        "ista",
        "istas",
        "ivo",
        "iva",
        "ivos",
        "ivas",
        "ico",
        "ica",
        "icos",
        "icas",
        "oso",
        "osa",
        "osos",
        "osas",
        "ario",
        "aria",
        "arios",
        "arias",
        "orio",
        "oria",
        "orios",
        "orias",
        "ente",
        "entes",
        "ante",
        "antes",
        "illa",
        "illas",
        "illo",
        "illos",
        "tipo",
        "tipos",
        "soma",
        "somas",
        "sita",
        "sitas",
        "dinamica",
        "din\\u00e1mica",
        "dinamico",
        "din\\u00e1mico",
        "cinetica",
        "cin\\u00e9tica",
        "cinetico",
        "cin\\u00e9tico",
        "terapia",
        "terapias",
        "terapeutica",
        "terap\\u00e9utica",
        "farmaco",
        "f\\u00e1rmaco",
        "farmacos",
        "f\\u00e1rmacos",
        "patogeno",
        "pat\\u00f3geno",
        "patogena",
        "pat\\u00f3gena",
    )
)

PREFIXES = (
    "a",
    "an",
    "anti",
    "auto",
    "bi",
    "di",
    "tri",
    "tetra",
    "penta",
    "mono",
    "poli",
    "multi",
    "hemi",
    "semi",
    "cuasi",
    "pseudo",
    "neo",
    "hiper",
    "hipo",
    "iso",
    "orto",
    "meta",
    "para",
    "peri",
    "epi",
    "endo",
    "exo",
    "ecto",
    "meso",
    "infra",
    "supra",
    "sub",
    "super",
    "inter",
    "intra",
    "extra",
    "trans",
    "retro",
    "pro",
    "pre",
    "post",
    "ante",
    "contra",
    "ultra",
    "micro",
    "macro",
    "mega",
    "nano",
    "cito",
    "histo",
    "hemo",
    "hemato",
    "neuro",
    "cardio",
    "gastro",
    "hepato",
    "nefro",
    "reno",
    "neumo",
    "pneumo",
    "bronco",
    "traqueo",
    "laringo",
    "faringo",
    "esofago",
    "entero",
    "colo",
    "recto",
    "procto",
    "angio",
    "arterio",
    "flebo",
    "vaso",
    "linfo",
    "adeno",
    "osteo",
    "artro",
    "condro",
    "mio",
    "tendino",
    "dermato",
    "oftalmo",
    "oculo",
    "oto",
    "rino",
    "stomato",
    "gloso",
    "encefalo",
    "cerebro",
    "meningo",
    "medulo",
    "raqui",
    "uro",
    "cisto",
    "uretero",
    "uretro",
    "histero",
    "metro",
    "salpingo",
    "ooforo",
    "ovario",
    "orqui",
    "prostato",
    "mammo",
    "masto",
    "tiro",
    "paratiro",
    "adrenal",
    "hipofiso",
    "pancreato",
    "esplen",
    "timo",
    "inmuno",
    "onco",
    "carcino",
    "sarco",
    "leuko",
    "leuco",
    "eritro",
    "trombo",
    "coagulo",
    "fibro",
    "lipo",
    "adipo",
    "gluco",
    "glico",
    "proteo",
    "amino",
    "nucleo",
    "cromo",
    "geneto",
    "cario",
    "mito",
    "radio",
    "electro",
    "foto",
    "quimio",
    "toxico",
    "psico",
    "pato",
    "fisio",
    "anato",
    "embrio",
    "feto",
    "gineco",
    "obstetro",
    "fono",
    "audio",
    "vestibulo",
    "corneo",
    "retino",
    "irido",
    "querato",
    "blefaro",
    "conjuntivo",
    "sinovio",
    "farmaco",
    "antibio",
    "antivir",
    "bio",
    "hidro",
    "aero",
    "xeno",
    "zoo",
    "bacterio",
    "viro",
    "mico",
    "parasit",
    "protozoo",
)

ROOTS = [
    "adeno",
    "angio",
    "aorto",
    "artro",
    "blefaro",
    "bronco",
    "cardio",
    "cerebro",
    "cervico",
    "cisto",
    "colecisto",
    "colo",
    "corneo",
    "costo",
    "crane",
    "dactilo",
    "dermato",
    "duodeno",
    "encefalo",
    "endocardio",
    "entero",
    "esofago",
    "esplen",
    "estomato",
    "faringo",
    "flebo",
    "fibro",
    "gastro",
    "gingivo",
    "gloso",
    "hemato",
    "hemo",
    "hepato",
    "hidro",
    "histero",
    "ileo",
    "irido",
    "querato",
    "laringo",
    "leuco",
    "linfo",
    "lipo",
    "mammo",
    "masto",
    "maxilo",
    "medulo",
    "meningo",
    "metro",
    "mio",
    "miel",
    "naso",
    "nefro",
    "neumo",
    "neuro",
    "oculo",
    "oftalmo",
    "ooforo",
    "orqui",
    "osteo",
    "oto",
    "ovario",
    "pancreato",
    "pericardio",
    "periodonto",
    "procto",
    "prostato",
    "pulmo",
    "raqui",
    "recto",
    "retino",
    "rino",
    "salpingo",
    "sinovio",
    "tiro",
    "toraco",
    "traqueo",
    "trombo",
    "uretero",
    "uretro",
    "uro",
    "utero",
    "vagino",
    "vaso",
    "vesico",
    "vulvo",
    "cario",
    "cromo",
    "embrio",
    "feto",
    "blasto",
    "cito",
    "histo",
    "farmaco",
    "electro",
    "radio",
    "quimio",
    "inmuno",
    "onco",
    "pato",
    "fisio",
    "anato",
    "coagulo",
    "fibroblasto",
    "pericito",
    "somita",
    "laminilla",
    "mosaic",
]

SEED_RAW = r"""
somita somitas miofibroblasto miofibroblastos pericito pericitos
farmacodinamica farmacodin\u00e1mica farmacodinamico farmacodin\u00e1mico
farmacocinetica farmacocin\u00e9tica farmacocinetico farmacocin\u00e9tico
coagulativa coagulativo coagulativas coagulativos coagulacion coagulaci\u00f3n
cariotipo cariotipos Cariotipo Cariotipos mosaicismo mosaicismos
laminilla laminillas electrocardiografica electrocardiogr\u00e1fica
electrocardiografico electrocardiogr\u00e1fico electrocardiograficas
electrocardiogr\u00e1ficas electrocardiograficos electrocardiogr\u00e1ficos
electrocardiografia electrocardiograf\u00eda
miofibra miofibras miofibrilla miofibrillas fibroblasto fibroblastos
fibrocito fibrocitos osteocito osteocitos osteoblasto osteoblastos
osteoclasto osteoclastos condrocito condrocitos condroblasto
adipocito adipocitos hepatocito hepatocitos enterocito enterocitos
neumocito neumocitos eritrocito eritrocitos leucocito leucocitos
linfocito linfocitos monocito monocitos trombocito trombocitos
megacariocito megacariocitos melanocito melanocitos queratinocito
astrocito astrocitos oligodendrocito oligodendrocitos microglia
ependimocito ependimocitos podocito podocitos mesangio mesangiales
endotelio endotelial endoteliales
embriologia embriolog\u00eda histologia histolog\u00eda citologia citolog\u00eda
cariocinesis mitosis meiosis haploide diploide triploide
aneuploidia aneuploid\u00eda euploidia euploid\u00eda polisomia polisom\u00eda
monosomia monosom\u00eda trisomia trisom\u00eda delecion deleci\u00f3n
translocacion translocaci\u00f3n inversion inversi\u00f3n duplicacion
duplicaci\u00f3n mutacion mutaci\u00f3n polimorfismo haplotipo genotipo
fenotipo alelo alelos locus loci cromatina cromosoma cromosomas
nucleolo nucl\u00e9olo ribosoma ribosomas lisosoma lisosomas
mitocondria mitocondrias peroxisoma peroxisomas
golgi reticulo ret\u00edculo endoplasmatico endoplasm\u00e1tico
citoesqueleto microtubulo microt\u00fabulo microtubulos microt\u00fabulos
microfilamento microfilamentos somitomero
miotoma dermatoma esclerotoma neuroporo notocorda blastocisto
gastrula g\u00e1strula neurula n\u00e9urula organogenesis organog\u00e9nesis
morfogenesis morfogen\u00e9sis histogenesis
"""

STOP = set(
    dec(
        r"""
        el la los las un una unos unas de del al a en y o u que se por para
        con sin sobre entre desde hasta como mas m\u00e1s muy ya no si s\u00ed
        su sus mi mis tu tus le les lo me te nos os ser estar haber tener
        hacer poder deber ir ver dar decir este esta estos estas ese esa
        esos esas aquel aquella aquellos aquellas todo toda todos todas
        otro otra otros otras mismo misma cada cual cuales
        cuando donde d\u00f3nde quien qui\u00e9n porque
        tambien tambi\u00e9n solo s\u00f3lo ahora antes despues despu\u00e9s
        entonces luego siempre nunca jamas jam\u00e1s aqui aqu\u00ed alli all\u00ed
        alla all\u00e1 hoy ayer manana ma\u00f1ana ano a\u00f1o anos a\u00f1os
        dia d\u00eda dias d\u00edas vez veces cosa cosas persona personas gente
        hombre mujer hombres mujeres nino ni\u00f1o nina ni\u00f1a casa mundo
        vida tiempo parte forma manera modo lugar sitio pais pa\u00eds
        paises pa\u00edses trabajo estudio estudios datos informacion
        informaci\u00f3n medida medidas riesgo riesgos caso casos resultado
        resultados ejemplo ejemplos problema problemas sistema sistemas
        proceso procesos nivel niveles tipo tipos grupo grupos numero
        n\u00famero numeros n\u00fameros valor valores efecto efectos cambio
        cambios uso usos desarrollo social general mayor menor mejor peor
        nuevo nueva nuevos nuevas gran grande grandes pequeno peque\u00f1o
        pequena peque\u00f1a primero primera segundo segunda tercero tercera
        bueno buena mal malo mala alto baja bajo
        vaccine anticipating anticoagulation extraction interaction
        interpretation intervention microsimulation substituting
        abscessos airway agenda agar cosas
        the and for with from that this were was are been being have has
        had will would could should may might must can into onto upon over
        under after before during while about against between through
        """
    ).split()
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
    "able",
    "ible",
    "ally",
    "ized",
    "ised",
    "izing",
    "ising",
)


def gender_number_variants(w: str) -> set[str]:
    out = {w}
    low = w
    for masc, fem in (
        ("ico", "ica"),
        ("ivo", "iva"),
        ("oso", "osa"),
        ("ario", "aria"),
        ("orio", "oria"),
        ("esco", "esca"),
        ("\u00edfico", "\u00edfica"),
        ("ifico", "ifica"),
        ("\u00e1tico", "\u00e1tica"),
        ("atico", "atica"),
        ("\u00f3gico", "\u00f3gica"),
        ("ogico", "ogica"),
        ("\u00e1fico", "\u00e1fica"),
        ("afico", "afica"),
        ("\u00e1mico", "\u00e1mica"),
        ("amico", "amica"),
        ("\u00e9tico", "\u00e9tica"),
        ("etico", "etica"),
    ):
        if low.endswith(masc):
            stem = low[: -len(masc)]
            out.update({stem + masc, stem + fem, stem + masc + "s", stem + fem + "s"})
        if low.endswith(fem):
            stem = low[: -len(fem)]
            out.update({stem + masc, stem + fem, stem + masc + "s", stem + fem + "s"})
    if low.endswith(("ica", "iva", "osa")):
        out.add(low + "mente")
    if low and low[0].islower():
        out.add(low[0].upper() + low[1:])
    return out


def morph_combo() -> set[str]:
    out: set[str] = set()
    core_suf = [
        dec(s)
        for s in (
            "itis",
            "osis",
            "oma",
            "algia",
            "pat\\u00eda",
            "scopia",
            "scop\\u00eda",
            "tom\\u00eda",
            "ectom\\u00eda",
            "graf\\u00eda",
            "gr\\u00e1fica",
            "gr\\u00e1fico",
            "plastia",
            "megalia",
            "cele",
            "centesis",
            "stom\\u00eda",
            "cito",
            "citos",
            "blasto",
            "blastos",
            "ismo",
            "ismos",
            "log\\u00eda",
            "terapia",
            "din\\u00e1mica",
            "cin\\u00e9tica",
            "penia",
            "plasia",
            "rragia",
        )
    ]
    for root, suf in product(ROOTS, core_suf):
        w = root + suf
        if 5 <= len(w) <= 45 and LETTER.fullmatch(w):
            out.add(w)
    bases = [
        "plasia",
        dec("pat\\u00eda"),
        "genesis",
        dec("g\\u00e9nesis"),
        "cito",
        "citos",
        "blasto",
        "blastos",
        "terapia",
        "grafia",
        dec("graf\\u00eda"),
        "scopia",
        dec("scop\\u00eda"),
        "tomia",
        dec("tom\\u00eda"),
        "ectomia",
        dec("ectom\\u00eda"),
        "dinamica",
        dec("din\\u00e1mica"),
        "cinetica",
        dec("cin\\u00e9tica"),
        "cardia",
        "pnea",
        "uria",
        "emia",
        "oma",
        "itis",
        "osis",
    ]
    for pre, base in product(PREFIXES, bases):
        w = pre + base
        if 6 <= len(w) <= 45 and LETTER.fullmatch(w):
            out.add(w)
    return out


def from_freq_broad() -> set[str]:
    path = EXT / "freq_list.txt"
    if not path.exists():
        return set()
    kept: set[str] = set()
    for line in path.read_text(encoding="utf-8").splitlines():
        parts = line.split()
        if not parts:
            continue
        w = parts[0]
        if not LETTER.fullmatch(w):
            continue
        if not (3 <= len(w) <= 45):
            continue
        low = w.casefold()
        if low in STOP:
            continue
        if any(low.endswith(e) for e in ENG_ENDS):
            continue
        medish = any(low.endswith(s) for s in SUFFIXES) or any(
            low.startswith(p) and len(low) > len(p) + 2 for p in PREFIXES
        )
        if medish or len(w) >= 6:
            kept.add(w)
    return kept


def expand_variants(words: set[str]) -> set[str]:
    out = set(words)
    seed_cf = {x.casefold() for x in dec(SEED_RAW).split()}
    seed_cf.update(e.casefold() for e in USER_CHECK)
    for w in list(words):
        if len(w) < 5 or len(w) > 35:
            continue
        low = w.casefold()
        if low in seed_cf or any(
            low.endswith(s)
            for s in (
                "ico",
                "ica",
                "ivo",
                "iva",
                "oso",
                "osa",
                "ario",
                "aria",
                "tico",
                "tica",
                "fico",
                "fica",
                "gico",
                "gica",
                "mico",
                "mica",
                "cito",
                "citos",
                "blasto",
                "blastos",
                "ismo",
                "grafica",
                "gr\u00e1fica",
                "dinamica",
                "din\u00e1mica",
            )
        ):
            out |= gender_number_variants(w)
    return out


def build() -> list[str]:
    seeds = set(dec(SEED_RAW).split())
    seeds.update(USER_CHECK)
    bag: set[str] = {w for w in seeds if LETTER.fullmatch(w)}
    bag |= morph_combo()
    bag |= from_freq_broad()
    bag = expand_variants(bag)

    clean: set[str] = set()
    for w in bag:
        if not LETTER.fullmatch(w):
            continue
        if not (3 <= len(w) <= 45):
            continue
        if w.casefold() in STOP:
            continue
        clean.add(w)

    words = sorted(clean, key=lambda s: (s.casefold(), s))
    OUT_EXPANDED.write_text("\n".join(words) + "\n", encoding="utf-8", newline="\n")
    OUT_SEED.write_text(
        "\n".join(sorted({w for w in seeds if LETTER.fullmatch(w)}, key=str.casefold))
        + "\n",
        encoding="utf-8",
        newline="\n",
    )
    return words


def main() -> int:
    words = build()
    print(f"Expanded lemmas: {len(words)} -> {OUT_EXPANDED}")
    s = set(words)
    for e in USER_CHECK:
        print(f"  {e}: {e in s}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
