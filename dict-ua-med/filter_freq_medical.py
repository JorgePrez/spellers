# -*- coding: utf-8 -*-
"""Filter companion freq_list for likely Spanish medical lemmas. ASCII-safe source."""
from __future__ import annotations

import codecs
import re
from pathlib import Path

EXT = Path(__file__).resolve().parent / "source" / "external" / "freq_list.txt"
OUT = Path(__file__).resolve().parent / "source" / "external" / "freq_medical_filtered.txt"

SUFFIXES = tuple(
    codecs.decode(s, "unicode_escape")
    for s in (
        "itis", "osis", "oma", "emia", "uria", "algia", "odinia",
        "patia", "pat\\u00eda", "scopia", "scop\\u00eda", "tomia", "tom\\u00eda",
        "ectomia", "ectom\\u00eda", "grafia", "graf\\u00eda", "plastia",
        "rragia", "rrea", "penia", "plasia", "plejia", "plej\\u00eda",
        "paresia", "megalia", "cele", "lisis", "ptosis", "centesis",
        "stomia", "stom\\u00eda", "pexia", "desis", "fon\\u00eda", "fonia",
        "opsia", "acusia", "geusia", "osmia", "fagia", "pnea", "cardia",
        "terapia", "farmaco", "f\\u00e1rmaco",
    )
)
PREFIXES = (
    "hiper", "hipo", "endo", "exo", "peri", "para", "epi", "sub",
    "supra", "infra", "inter", "intra", "extra", "hemi", "mono",
    "poli", "multi", "micro", "macro", "mega", "neo", "pseudo",
    "anti", "auto", "cito", "histo", "hemo", "hemato", "neuro",
    "cardio", "gastro", "hepato", "nefro", "pneumo", "neumo",
    "osteo", "artro", "dermato", "oftalmo", "oto", "rino",
    "laringo", "bronco", "colo", "recto", "uro", "gineco",
    "andro", "onco", "radio", "quimio", "inmuno", "psico", "patolo",
)

EXTRA_RAW = r"""
paciente pacientes diagnostico diagnost\u00f3stico diagnostico
pronostico pron\u00f3stico sintoma s\u00edntoma sintomas s\u00edntomas
signo signos clinico cl\u00ednico clinica cl\u00ednica patologia patolog\u00eda
fisiologia fisiolog\u00eda anatomia anatom\u00eda farmacologia farmacolog\u00eda
cirugia cirug\u00eda quirurgico quir\u00fargico anestesia analgesia
analgesico analg\u00e9sico antibiotico antibi\u00f3tico antibioticos antibi\u00f3ticos
virus bacteria bacterias hongo hongos parasito par\u00e1sito
infeccion infecci\u00f3n inflamacion inflamaci\u00f3n tumor tumores
cancer c\u00e1ncer metastasis met\u00e1stasis biopsia histologia histolog\u00eda
citologia citolog\u00eda hemograma orina sangre plasma suero
anticuerpo anticuerpos antigeno ant\u00edgeno vacuna vacunas
vacunacion vacunaci\u00f3n dosis tratamiento terapia terapias
protocolo protocolos urgencia urgencias uci hospital hospitalario
ambulatorio consulta anamnesis exploracion exploraci\u00f3n
auscultacion auscultaci\u00f3n palpacion palpaci\u00f3n percusion percusi\u00f3n
radiografia radiograf\u00eda tomografia tomograf\u00eda resonancia
ecografia ecograf\u00eda endoscopia endoscop\u00eda colonoscopia broncoscopia
laparoscopia cateter cat\u00e9ter sonda intubacion intubaci\u00f3n
ventilacion ventilaci\u00f3n oxigeno ox\u00edgeno trasplante protesis pr\u00f3tesis
sutura incision incisi\u00f3n drenaje hemorragia trombo embolia
isquemia infarto ictus coma shock sepsis fiebre dolor edema eritema
ulcera \u00falcera fractura luxacion luxaci\u00f3n herida cicatriz
embarazo parto cesarea ces\u00e1rea neonato pediatria pediatr\u00eda
geriatria geriatr\u00eda psiquiatria psiquiatr\u00eda neurologia neurolog\u00eda
cardiologia cardiolog\u00eda neumologia neumolog\u00eda gastroenterologia
gastroenterolog\u00eda nefrologia nefrolog\u00eda urologia urolog\u00eda
ginecologia ginecolog\u00eda obstetricia oftalmologia oftalmolog\u00eda
dermatologia dermatolog\u00eda oncologia oncolog\u00eda hematologia
hematolog\u00eda endocrinologia endocrinolog\u00eda reumatologia
reumatolog\u00eda infectologia infectolog\u00eda radiologia radiolog\u00eda
anatomopatologia anatomopatolog\u00eda microbiologia microbiolog\u00eda
inmunologia inmunolog\u00eda toxicologia toxicolog\u00eda epidemiologia
epidemiolog\u00eda salud enfermedad enfermedades sindrome s\u00edndrome
cronico cr\u00f3nico aguda agudo benigno maligno metastasico metast\u00e1sico
idiopatico idiop\u00e1tico iatrogenico iatrog\u00e9nico nosocomial
comunitaria asintomatico asintom\u00e1tico sintomatico sintom\u00e1tico
bilateral unilateral proximal distal medial lateral anterior
posterior superior inferior ventral dorsal coronal sagital axial
transversal remdesivir hidroxicloroquina favipiravir confinamiento
cuarentena coronavirus pandemia
"""

EXTRA_KEEP = set(codecs.decode(EXTRA_RAW, "unicode_escape").split())

LETTER = re.compile(
    r"^[A-Za-z\u00c1\u00c9\u00cd\u00d3\u00da\u00dc\u00d1"
    r"\u00e1\u00e9\u00ed\u00f3\u00fa\u00fc\u00f1"
    r"\u00c7\u00e7\u00d6\u00f6]+$"
)

DENY = {
    "estudio", "estudios", "datos", "personas", "casos", "caso",
    "riesgo", "medidas", "cita", "cuadro", "libres", "significativo",
    "contraste", "instituciones", "asociado", "sociedades", "rx",
    "vaccine", "cosas",
}


def looks_medical(w: str) -> bool:
    low = w.lower()
    if low in DENY:
        return False
    if low in EXTRA_KEEP or w in EXTRA_KEEP:
        return True
    if any(low.endswith(s) for s in SUFFIXES):
        return True
    if any(low.startswith(p) and len(low) > len(p) + 2 for p in PREFIXES):
        return True
    return False


def main() -> int:
    if not EXT.exists():
        print("Missing", EXT)
        return 1
    kept: set[str] = set()
    for line in EXT.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line:
            continue
        w = line.split()[0]
        if "-" in w or "." in w or any(c.isdigit() for c in w):
            continue
        if not LETTER.fullmatch(w):
            continue
        if 3 <= len(w) <= 40 and looks_medical(w):
            kept.add(w)

    covid = EXT.parent / "covid-19_terms.txt"
    if covid.exists():
        for line in covid.read_text(encoding="utf-8").splitlines():
            w = line.strip()
            if LETTER.fullmatch(w) and 3 <= len(w) <= 40:
                kept.add(w)

    words = sorted(kept, key=lambda s: (s.casefold(), s))
    OUT.write_text("\n".join(words) + "\n", encoding="utf-8", newline="\n")
    print(f"Filtered medical lemmas: {len(words)} -> {OUT}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
