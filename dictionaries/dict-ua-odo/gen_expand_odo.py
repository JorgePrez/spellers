# -*- coding: utf-8 -*-
"""Expand odontology lexicon toward ~40k real/clinical lemmas. ASCII-safe."""
from __future__ import annotations

import codecs
import re
from itertools import product
from pathlib import Path

BASE = Path(__file__).resolve().parent
SRC = BASE / "source"
EXT = SRC / "external"
OUT = EXT / "expanded_odo.txt"
OUT_SEED = SRC / "user_examples_odo.txt"
MED_FREQ = BASE.parent / "dict-ua-med" / "source" / "external" / "freq_list.txt"
MED_DIC = BASE.parent / "dict-ua-med" / "ua_med_GT.dic"
AGENT = Path(
    r"C:\Users\IT-14\.cursor\projects\c-Users-IT-14-Documents-syllabus-syllabus-analisis-actual-spellers-main\agent-tools"
)

LETTER = re.compile(
    r"^[A-Za-z\u00c1\u00c9\u00cd\u00d3\u00da\u00dc\u00d1"
    r"\u00e1\u00e9\u00ed\u00f3\u00fa\u00fc\u00f1]+$"
)


def dec(s: str) -> str:
    return codecs.decode(s, "unicode_escape")


USER_SEED = [
    dec(s)
    for s in (
        "amelog\\u00e9nesis",
        "dentinog\\u00e9nesis",
        "cementog\\u00e9nesis",
        "desmineralizaci\\u00f3n",
        "remineralizaci\\u00f3n",
        "alveoloplastia",
        "mixoma",
        "cementoblastoma",
        "radiolucidez",
        "semiajustable",
        "pulpectom\\u00eda",
        "ameloblasto",
        "ameloblastos",
        "odontoblasto",
        "odontoblastos",
        "cementoblasto",
        "cementoblastos",
        "ameloblastoma",
        "ameloblastomas",
        "queratoquiste",
        "queratoquistes",
        "odontog\\u00e9nico",
        "odontog\\u00e9nica",
        "conductometr\\u00eda",
        "furcaci\\u00f3n",
        "biotipo",
        "periodontal",
        "odontectom\\u00eda",
        "osteotom\\u00eda",
        "osteointegraci\\u00f3n",
        "torque",
        "inserci\\u00f3n",
        "l\\u00e1mina",
        "papila",
        "esmalte",
        "microscop\\u00eda",
        "microestructura",
    )
]

SEED_RAW = r"""
amelogenesis amelog\u00e9nesis dentinogenesis dentinog\u00e9nesis
cementogenesis cementog\u00e9nesis odontogenesis odontog\u00e9nesis
desmineralizacion desmineralizaci\u00f3n remineralizacion remineralizaci\u00f3n
alveoloplastia alveoloplastias odontectomia odontectom\u00eda
mixoma mixomas odontogenico odontog\u00e9nico odontogenica odontog\u00e9nica
cementoblastoma cementoblastomas radiolucidez radiolucido radiol\u00facido
radiolucida radiol\u00facida radiopacidad radiopaco radiopaca
semiajustable articulator articulado
pulpectomia pulpectom\u00eda pulpotomia pulpotom\u00eda
ameloblasto ameloblastos odontoblasto odontoblastos
cementoblasto cementoblastos cementocito cementocitos
ameloblastoma queratoquiste odontogenico
conductometria conductometr\u00eda periodontitis apical
furcacion furcaci\u00f3n biotipo periodontal
osteotomia osteotom\u00eda osteointegracion osteointegraci\u00f3n
lamina dental l\u00e1mina organo del esmalte \u00f3rgano
papila dental papilas
adamantino adamantina adamantinoma
esmalte dentina cemento periodonto
limite amelodentinario amelocementario mucogingival
LAC LAD
camara pulpar c\u00e1mara
rizalisis riz\u00e1lisis rizogenesis rizog\u00e9nesis
apexificacion apexificaci\u00f3n apexogenesis apexog\u00e9nesis
revascularizacion revascularizaci\u00f3n
hiperdoncia hipodoncia oligodoncia anodoncia
supernumerario impactacion impactaci\u00f3n inclusion inclusi\u00f3n
pericoronaritis alveolitis
abfraccion abfracci\u00f3n abrasion abrasi\u00f3n atricion atrici\u00f3n
erosion erosi\u00f3n
fluorosis hipoplasia hipomineralizacion hipomineralizaci\u00f3n
MIH
bruxismo bruxomania bruxoman\u00eda
halitosis xerostomia xerostom\u00eda
candidiasis leucoplasia eritroplasia
liquen plano afta aftas herpes
ranula r\u00e1nula mucocele
sialolitiasis parotiditis
ATM articulacion temporomandibular
condilo c\u00f3ndilo menisco
trismus trismo
celulitis facial
osteomielitis
granuloma periapical quiste radicular dentigero dent\u00edgero
odontoma complexo compuesto
fibroma ossificante
"""

ROOTS = [
    "odonto", "odont", "dento", "dent", "gingivo", "gingiv",
    "periodonto", "periodont", "endodonto", "endodont",
    "ortodonto", "ortodont", "prostodonto", "prostodont",
    "maxilo", "maxil", "mandibulo", "mandibul",
    "alveolo", "alveol", "pulpo", "pulp", "radiculo", "radicul",
    "apico", "apic", "corono", "coron", "ocluso", "oclus",
    "vestibulo", "vestibul", "linguo", "lingu", "palato", "palat",
    "mesio", "disto", "bucco", "labio", "incisiv", "canin",
    "premolar", "molar", "implanto", "implant",
    "cemento", "cement", "amel", "amelo", "dentino", "dentin",
    "esmalte", "esmal", "cario", "cari",
    "radiogr", "radio", "cefalom", "ortopantom", "tomograf",
    "fluor", "fluorur", "sellant", "amalgam", "composit",
    "resin", "ceram", "zirconi", "titan", "alginat", "silicon",
    "anestesi", "articain", "lidocain", "mepivacain",
    "exodon", "odontect", "apicect", "gingivect", "gingivoplast",
    "alveoloplast", "frenect", "osteointegr", "osteotom",
    "protes", "corona", "carilla", "bracket", "alineador",
    "retenedor", "ferul", "brux", "halit", "xerostom",
    "pericoronar", "alveolitis", "maloclus", "condil", "maseter",
    "parotid", "submandibul", "sublingu", "saliv", "buco",
    "craneofaci", "maxilofaci", "odontopediatr", "periodontolog",
    "implantolog", "gerodontolog", "estomat", "estomatognat",
    "mucogingiv", "mucos", "papil", "furc", "bols",
    "rasp", "alis", "curet", "tartrect", "profilax",
    "obtur", "instrument", "irrig", "gutaperch",
    "blanque", "fluorac", "sellad", "restaur",
    "rebas", "encajon", "enmufl", "tallad", "prepar",
    "biotip", "conductometr", "localiz", "apex",
    "queratoquist", "odontom", "mixom", "fibrom",
    "cementoblastom", "ameloblastom", "adamantin",
    "radioluc", "radiopac", "periapical", "interproximal",
    "supragingiv", "subgingiv", "transeptal",
    "sial", "sialolit", "ranul", "mucocele",
    "avuls", "lux", "intrus", "extrus", "reimplant",
    "feruliz", "esplinaj", "trauma", "fractur",
    "sinus", "bucosinusal", "elevacion",
    "injerto", "membrana", "regener", "colgaj",
    "anquil", "agenes", "hipodon", "hiperdon", "oligodon",
    "supernumer", "impact", "reten", "erup",
    "oclus", "articul", "cefalometr", "expans",
    "disyunt", "miniimpl", "anclaj",
    "estomatolog", "gnatolog", "oclusolog", "radiolog", "patolog",
    "histolog", "citolog", "microbiolog", "farmacolog", "anestesiolog",
    "cirug", "quirurg", "traumatolog", "oncolog", "hematolog",
    "inmunolog", "toxicolog", "epidemiolog", "biomaterial",
    "osteoconduct", "osteoinduct", "osteoblast", "osteoclast", "osteocit",
    "periost", "endost", "trabecul", "zigomat", "pterigoid",
    "digastric", "milohio", "genihio", "geniogloso", "hiogloso",
    "estilogloso", "palatogloso", "amigdal", "faring", "laring",
    "epiglot", "uvul", "menton", "infraorbit", "supraorbit",
    "tartrect", "profilax", "obtur", "irrig", "gutaperch",
    "blanque", "sellad", "restaur", "rebas", "tallad", "prepar",
    "odontect", "apicect", "gingivect", "gingivoplast",
    "frenect", "pulpect", "pulpot", "semiajust",
    "desbrid", "curetaj", "raspado", "alisado", "sondaj",
    "rebasado", "rebordes", "munon",
    "pilar", "cofre", "cofia", "inlay", "onlay", "overlay",
    "composite", "ionomer",
    "hipoclor", "clorhex",
    "gutapercha", "conos", "limas",
    "bisel", "cavosuperfic", "retencion", "resistenc",
    "grabado", "adhes", "fotopolimer",
    "pulido", "acabado", "equilibr",
    "miorelaj", "descarga", "interoclus",
    "centric", "protrus", "laterotrus", "mediotrus",
    "resalte", "sobremord", "apin", "diastem",
]

SUFFIXES = [
    dec(s)
    for s in (
        "itis", "osis", "oma", "omas", "algia", "algias",
        "pat\\u00eda", "pat\\u00edas",
        "scop\\u00eda", "scop\\u00edas",
        "tom\\u00eda", "tom\\u00edas",
        "ectom\\u00eda", "ectom\\u00edas",
        "plastia", "plastias",
        "graf\\u00eda", "graf\\u00edas",
        "gr\\u00e1fica", "gr\\u00e1fico", "gr\\u00e1ficas", "gr\\u00e1ficos",
        "metr\\u00eda", "metr\\u00edas",
        "log\\u00eda", "log\\u00edas",
        "g\\u00e9nesis",
        "blasto", "blastos", "cito", "citos",
        "logo", "loga", "logos", "logas",
        "ico", "ica", "icos", "icas",
        "ivo", "iva", "ivos", "ivas",
        "al", "ales", "ar", "ares",
        "ario", "aria", "arios", "arias",
        "ismo", "ismos", "ista", "istas",
        "aci\\u00f3n", "aciones",
        "encia", "encias", "ente", "entes",
        "ura", "uras", "ina", "inas",
        "oso", "osa", "osos", "osas",
        "ez", "eza", "idez",
    )
]

PREFIXES = (
    "a", "an", "anti", "auto", "bi", "hemi", "hiper", "hipo",
    "infra", "inter", "intra", "extra", "peri", "pre", "post",
    "sub", "supra", "trans", "endo", "exo", "neo", "pseudo",
    "micro", "macro", "multi", "poli", "semi", "ultra",
    "des", "re", "co", "contra", "super", "meso", "ecto",
)

BASES = [
    dec(s)
    for s in (
        "pat\\u00eda", "log\\u00eda", "tom\\u00eda", "ectom\\u00eda",
        "graf\\u00eda", "metr\\u00eda", "g\\u00e9nesis", "plastia",
        "itis", "osis", "oma", "algia", "ismo", "ista",
        "al", "ico", "ica", "ivo", "iva", "ente", "ura",
        "dental", "gingival", "apical", "radicular", "pulpar",
        "oclusal", "periodontal", "implante", "corona",
        "od\\u00f3ntico", "od\\u00f3ntica",
    )
]

DENTAL_SUB = (
    "odont", "dent", "gingiv", "periodont", "endodont", "ortodont",
    "prostodont", "maxil", "mandib", "alveol", "pulp", "radicul",
    "apical", "oclus", "vestibul", "lingual", "palatin", "mesial",
    "distal", "incisal", "molar", "canin", "premolar", "incisiv",
    "implan", "exodon", "caries", "cariad", "amalgam", "composit",
    "gutaper", "bracket", "ortopantom", "cefalom", "fluor",
    "brux", "halit", "xerostom", "afta", "leucoplas", "candidias",
    "pericoronar", "tartrect", "frenect", "apicect", "gingivect",
    "alveoloplast", "osteointegr", "carilla", "incrust", "obturac",
    "conducto", "buco", "saliv", "parotid", "condil", "maseter",
    "odontogram", "bitewing", "panoram", "cbct", "amel", "cement",
    "esmalte", "dentina", "furc", "biotip", "queratoquist",
    "radioluc", "radiopac", "pulpect", "pulpot", "odontect",
    "mixom", "sial", "ranul", "mucocele", "avuls", "reimplant",
    "estomat", "mucogingiv", "adamantin", "rizal", "apexif",
    "apexog", "desmineral", "remineral", "hipomineral",
    "supernumer", "hipodon", "hiperdon", "anodon", "oligodon",
    "abfrac", "atrisi", "fluoros", "sellant", "profilax",
    "torque", "osteotom", "colgaj", "injerto", "regener",
    "articul", "cefalometr", "miniimpl", "alineador", "retenedor",
)


def looks_dental(w: str) -> bool:
    low = w.casefold()
    return any(m in low for m in DENTAL_SUB)


def morph_combo() -> set[str]:
    out: set[str] = set()
    for root, suf in product(ROOTS, SUFFIXES):
        w = root + suf
        if 5 <= len(w) <= 42 and LETTER.fullmatch(w):
            out.add(w)
    for pre, base in product(PREFIXES, BASES):
        w = pre + base
        if 6 <= len(w) <= 42 and LETTER.fullmatch(w):
            out.add(w)
    for pre, root in product(
        ("peri", "endo", "orto", "supra", "infra", "inter", "intra", "sub", "trans", "neo"),
        (
            "dental", "gingival", "apical", "radicular", "pulpar", "oclusal",
            "periodontal", dec("od\\u00f3ntico"), dec("od\\u00f3ntica"),
            "coronario", "coronaria", "alveolar", "implante",
        ),
    ):
        w = pre + root
        if LETTER.fullmatch(w):
            out.add(w)
    return out


def gender_number(w: str) -> set[str]:
    out = {w}
    low = w
    pairs = (
        ("ico", "ica"), ("ivo", "iva"), ("oso", "osa"),
        ("ario", "aria"), ("orio", "oria"),
        ("\\u00f3gico", "\\u00f3gica"), ("ogico", "ogica"),
        ("\\u00e1fico", "\\u00e1fica"), ("afico", "afica"),
        ("\\u00e9tico", "\\u00e9tica"), ("etico", "etica"),
        ("\\u00e1tico", "\\u00e1tica"), ("atico", "atica"),
    )
    for masc, fem in pairs:
        m, f = dec(masc) if "\\" in masc else masc, dec(fem) if "\\" in fem else fem
        if low.endswith(m):
            stem = low[: -len(m)]
            out.update({stem + m, stem + f, stem + m + "s", stem + f + "s"})
        if low.endswith(f):
            stem = low[: -len(f)]
            out.update({stem + m, stem + f, stem + m + "s", stem + f + "s"})
    if low and low[0].islower() and looks_dental(low):
        out.add(low[0].upper() + low[1:])
    return out


def from_freq() -> set[str]:
    kept: set[str] = set()
    if not MED_FREQ.exists():
        return kept
    oral = (
        "oral",
        "buca",
        "maxil",
        "mandib",
        "diente",
        "pieza",
        "sarro",
        "placa",
        "mordida",
        "labial",
        "lingual",
        "paladar",
        "atm",
        "saliv",
        "parotid",
        "trigemin",
        "facial",
        "craneal",
        "encía",
        "encia",
        "sonrisa",
    )
    for line in MED_FREQ.read_text(encoding="utf-8").splitlines():
        parts = line.split()
        if not parts:
            continue
        w = parts[0]
        if not LETTER.fullmatch(w) or not (3 <= len(w) <= 45):
            continue
        low = w.casefold()
        if looks_dental(w) or any(o in low for o in oral):
            kept.add(w)
    return kept


def from_med_clinical() -> set[str]:
    """Clinical morphology shared with dental syllabus (from ortho-clean med dic)."""
    kept: set[str] = set()
    if not MED_DIC.exists():
        return kept
    ends = (
        "itis",
        "osis",
        "oma",
        "omas",
        "algia",
        "emia",
        "uria",
        "plasia",
        "plastia",
        dec("pat\\u00eda"),
        dec("tom\\u00eda"),
        dec("ectom\\u00eda"),
        dec("scop\\u00eda"),
        dec("graf\\u00eda"),
        dec("log\\u00eda"),
        dec("metr\\u00eda"),
        dec("g\\u00e9nesis"),
        dec("aci\\u00f3n"),
        "blasto",
        "blastos",
        "cito",
        "citos",
        "terapia",
        "antibio",
        dec("antibi\\u00f3tico"),
        dec("antibi\\u00f3tica"),
        "analges",
        dec("analg\\u00e9sico"),
        "anestesi",
        "sutura",
        "biopsia",
        "infeccion",
        dec("infecci\\u00f3n"),
        "inflamacion",
        dec("inflamaci\\u00f3n"),
    )
    for line in MED_DIC.read_text(encoding="utf-8").splitlines()[1:]:
        w = line.strip()
        if not LETTER.fullmatch(w) or not (5 <= len(w) <= 40):
            continue
        low = w.casefold()
        if any(low.endswith(e) if not e.startswith("anti") and "\\" not in e else low.endswith(e) for e in ends):
            # simpler:
            pass
        if any(low.endswith(e) for e in ends if len(e) >= 3):
            kept.add(w)
        elif any(e in low for e in ("anestesi", "analges", "antibio", "sutur", "biops")):
            kept.add(w)
    return kept


def from_med_dic() -> set[str]:
    kept: set[str] = set()
    if not MED_DIC.exists():
        return kept
    oral = (
        "oral",
        "buca",
        "facial",
        "saliv",
        "parotid",
        "trigemin",
        "crane",
        "maxil",
        "mandib",
        "dental",
        "diente",
        "enc",
        "ging",
        "pulp",
        "alveol",
        "odont",
        "atm",
        "implan",
        "anestesi",
        "antibi",
        "infecc",
        "inflam",
        "absces",
        "quiste",
        "tumor",
        "biops",
        "sutur",
        "fractur",
        "traum",
        "hemorrag",
        "isquem",
        "necros",
        "edem",
        "erit",
        "ulcer",
        "fistul",
        "sepsis",
        "antibio",
        "analges",
        "antiinflam",
    )
    for line in MED_DIC.read_text(encoding="utf-8").splitlines()[1:]:
        w = line.strip()
        if not LETTER.fullmatch(w):
            continue
        low = w.casefold()
        if looks_dental(w) or any(o in low for o in oral):
            kept.add(w)
    return kept


def from_glossary_dumps() -> set[str]:
    kept: set[str] = set()
    if not AGENT.exists():
        return kept
    for p in AGENT.glob("*.txt"):
        try:
            text = p.read_text(encoding="utf-8", errors="replace")
        except OSError:
            continue
        # only process likely dental dumps (size heuristic + keywords)
        if "odont" not in text.casefold() and "dental" not in text.casefold():
            continue
        for tok in re.findall(
            r"[A-Za-z\u00c1\u00c9\u00cd\u00d3\u00da\u00dc\u00d1"
            r"\u00e1\u00e9\u00ed\u00f3\u00fa\u00fc\u00f1]{3,40}",
            text,
        ):
            if looks_dental(tok) or tok.casefold() in {
                "caries", "esmalte", "dentina", "pulpa", "encía", "encia",
                "sarro", "brackets", "implante", "prótesis", "protesis",
                "fluor", "flúor", "oclusión", "oclusion",
            }:
                if LETTER.fullmatch(tok):
                    kept.add(tok)
    return kept


def build() -> list[str]:
    from ortho_priority import filter_orthography_errors  # type: ignore

    bag: set[str] = set()
    seeds = set(dec(SEED_RAW).split()) | set(USER_SEED)
    bag |= {w for w in seeds if LETTER.fullmatch(w)}
    bag |= morph_combo()
    bag |= from_freq()
    bag |= from_med_dic()
    bag |= from_med_clinical()
    bag |= from_glossary_dumps()

    # variants for dental-ish words
    extra: set[str] = set()
    for w in list(bag):
        if looks_dental(w) and 4 <= len(w) <= 35:
            extra |= gender_number(w)
    bag |= extra

    clean: set[str] = set()
    for w in bag:
        if LETTER.fullmatch(w) and 3 <= len(w) <= 45:
            clean.add(w)

    clean, stats = filter_orthography_errors(clean)
    # Force-keep curated clinical seeds (orthographically correct)
    for w in USER_SEED:
        if LETTER.fullmatch(w):
            clean.add(w)
    # Ensure classic -plastia forms without wrong accent
    for w in (
        "alveoloplastia",
        "gingivoplastia",
        "gingivoplastias",
        "alveoloplastias",
    ):
        clean.add(w)
        clean.discard(w[:-1] + "\u00eda")  # drop *plastía if present
    print(
        "Expand odo ortho: "
        f"in={stats['input']} kept={stats['kept']} "
        f"drop_sin_tilde={stats['drop_unaccented_vs_accented']} "
        f"drop_sufijo={stats['drop_bad_medical_ending']} "
        f"drop_en={stats['drop_english']}"
    )

    words = sorted(clean, key=lambda s: (s.casefold(), s))
    OUT.parent.mkdir(parents=True, exist_ok=True)
    OUT.write_text("\n".join(words) + "\n", encoding="utf-8", newline="\n")
    seed_ok, _ = filter_orthography_errors(set(USER_SEED))
    OUT_SEED.write_text(
        "\n".join(sorted(seed_ok, key=str.casefold)) + "\n",
        encoding="utf-8",
        newline="\n",
    )
    return words


def main() -> int:
    words = build()
    print(f"Expanded odo lemmas: {len(words)} -> {OUT}")
    s = set(words)
    for e in USER_SEED:
        print(f"  {e}: {e in s}")
    # odontectomia correct; misspelling should be absent
    print(f"  odonctectom\u00eda (typo): {'odonctectom\u00eda' in s}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
