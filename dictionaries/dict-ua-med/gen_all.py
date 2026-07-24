# -*- coding: utf-8 -*-
"""Generate complete UA medical Hunspell dictionary (UTF-8). Pure ASCII source."""
from __future__ import annotations

import codecs
import re
from pathlib import Path

BASE = Path(__file__).resolve().parent
OUT = BASE / "ua_med_GT.dic"
SRC = BASE / "source"
SRC.mkdir(parents=True, exist_ok=True)

# Incluye cedilla/dieresis ocasionales (Behcet, Sjogren)
LETTER = re.compile(
    r"^[A-Za-z\u00c1\u00c9\u00cd\u00d3\u00da\u00dc\u00d1"
    r"\u00e1\u00e9\u00ed\u00f3\u00fa\u00fc\u00f1"
    r"\u00c7\u00e7\u00d6\u00f6]+"
    r"(?:-[A-Za-z\u00c1\u00c9\u00cd\u00d3\u00da\u00dc\u00d1"
    r"\u00e1\u00e9\u00ed\u00f3\u00fa\u00fc\u00f1"
    r"\u00c7\u00e7\u00d6\u00f6]+)?$"
)
JUNK = ("hipercardio", "hipernefro")

FIXES = {
    "efisema": "enfisema",
    "hab\u00edtualizaci\u00f3n": None,
    "protein\u00faria": "proteinuria",
    "olig\u00faria": "oliguria",
    "cachexia": "caquexia",
    "hordeolum": "orzuelo",
    "pemphigus": "p\u00e9nfigo",
    "torsion": "torsi\u00f3n",
    "tetanos": "t\u00e9tanos",
    "tetanoespasmo": "t\u00e9tanos",
    "fistula": "f\u00edstula",
    "vertigo": "v\u00e9rtigo",
    "angulo": "\u00e1ngulo",
    "pneumaturia": "neumaturia",
    "pneumoconiosis": "neumoconiosis",
    "transplantaci\u00f3n": "trasplantaci\u00f3n",
    "hepatoesplenomegal\u00eda": "hepatoesplenomegalia",
    "bronquiectas\u00eda": "bronquiectasia",
}


def dec(s: str) -> str:
    return codecs.decode(s, "unicode_escape")


def valid(w: str) -> bool:
    w = w.strip()
    if not w or not (2 <= len(w) <= 60):
        return False
    if any(c.isdigit() for c in w) or " " in w or "\t" in w:
        return False
    if not LETTER.fullmatch(w):
        return False
    low = w.lower()
    if any(low.startswith(p) for p in JUNK):
        return False
    return True


def tokens(escaped_block: str) -> list[str]:
    return [t for t in dec(escaped_block).split() if t]


# ---------------------------------------------------------------------------
# Lexicon blocks (unicode_escape). Keep ASCII-only in this file.
# ---------------------------------------------------------------------------

B_ANATOMIA = r"""
abdomen abdominal abducci\u00f3n aducci\u00f3n adenohip\u00f3fisis adventicia
alantoides alv\u00e9olo alveolar alv\u00e9olos am\u00edgdala am\u00edgdalas amnios
ampolla antebrazo antitrago aorta a\u00f3rtica a\u00f3rtico ap\u00e9ndice
aponeurosis ap\u00f3fisis aracnoides arteria arterial arterias arter\u00edola
arteriolas articulaci\u00f3n articular articulaciones atlas atrio aur\u00edcula
aur\u00edculas auricular axila axilar b\u00edceps blastocisto braquial bronquio
bronquios bronquiolo bronquiolos bursa calc\u00e1neo c\u00e1psula capsular
cardias car\u00f3tida car\u00f3tidas carpiano cart\u00edlago cartilaginoso
cavidad cavidades cerebelo cerebeloso cerebro cigoma cigom\u00e1tico cigoto
cilio cilios clav\u00edcula cl\u00edtoris c\u00f3ccix c\u00f3clea colon
conducto conductos coracoides cord\u00f3n c\u00f3rnea coroides corona
coronaria coronario corteza costilla costillas cr\u00e1neo craneal cubital
c\u00fabito c\u00fapula cut\u00e1neo deltoides dermis diafragma diafragm\u00e1tico
di\u00e1stole dienc\u00e9falo d\u00edgito d\u00edgitos distal duodeno duodenal
dura duramadre ectodermo endocardio endodermo endometrio endotelio
epicardio epidermis epid\u00eddimo ep\u00edfisis epiglotis epipl\u00f3n
escler\u00f3tica esc\u00e1pula esf\u00ednter es\u00f3fago esof\u00e1gico
esqueleto est\u00f3mago estern\u00f3n fasc\u00edculo f\u00e9mur femoral
f\u00edbula fibular fosa frontal galea ganglio ganglios gl\u00e1ndula
gl\u00e1ndulas glenoideo glotis gl\u00fateo hallux h\u00e9lix hemiabdomen
hemisferio hep\u00e1tico h\u00edgado hilio hioides hipocampo hip\u00f3fisis
h\u00famero humeral \u00edleon il\u00edaco ilion \u00ednferior inguinal iris
isquion isqui\u00e1tico yeyuno yugular labio labios lacrimal l\u00e1mina
laringe lar\u00edngeo ligamento ligamentos limbo lengua lingual l\u00f3bulo
l\u00f3bulos lumbar lumbosacro m\u00e1cula mand\u00edbula mandibular maxilar
meato mediastino m\u00e9dula medular meninge meninges menisco mesenc\u00e9falo
mes\u00e9nquima mesenterio mesodermo metacarpiano metatarsiano miocardio
miometrio mitosis meiosis m\u00f3rula mucosa m\u00fasculo m\u00fasculos nasal
nasofaringe nervio nervios neural neuroeje neurohip\u00f3fisis n\u00f3dulo
n\u00f3dulos occipital o\u00eddo ojo ojos omento \u00f3rbita orbitario
\u00f3rgano \u00f3rganos orificio orofaringe ovario ovarios ovocito \u00f3vulo
paladar palatino p\u00e1ncreas pancre\u00e1tico par\u00f3tida paratiroides
parietal pene pericardio perin\u00e9 perineal periostio peritoneo
peritoneal pie pies piel pierna plantar pleura pleural plexo popl\u00edteo
pr\u00f3stata prost\u00e1tico proximal pubis pulmonar pupila radial radio
recto renal retina retiniano ri\u00f1\u00f3n ri\u00f1ones sacro sacroil\u00edaco
salival seno senos septo septal sinovial sistema sist\u00f3lico subclavia
subcut\u00e1neo sublingual submandibular superior supraespinoso
suprarrenal surco sutura tal\u00f3n tarso tarsiano tend\u00f3n tendones
tend\u00edneo test\u00edculo test\u00edculos timo t\u00edmico tiroides
tiroideo tobillo t\u00f3rax tor\u00e1cico tr\u00e1quea traqueal tr\u00edceps
trompa trompas tronco troncoencef\u00e1lico t\u00fabulo tubular t\u00edmpano
timp\u00e1nico ur\u00e9ter ur\u00e9teres uretra uretral \u00fatero uterino
\u00favea uveal vagina vaginal v\u00e1lvula v\u00e1lvulas vascular vena
venas venoso ventr\u00edculo ventriculares v\u00e9rtebra v\u00e9rtebras
vertebral ves\u00edcula vest\u00edbulo vestibular v\u00edscera v\u00edsceras
visceral v\u00edtreo vulva
"""

B_ESPECIALIDADES = r"""
Anatom\u00eda anatom\u00eda Anestesiolog\u00eda anestesiolog\u00eda Angiolog\u00eda
angiolog\u00eda Audiolog\u00eda audiolog\u00eda Bacteriolog\u00eda bacteriolog\u00eda
Bioqu\u00edmica bioqu\u00edmica Cardiolog\u00eda cardiolog\u00eda Cirug\u00eda
cirug\u00eda Citolog\u00eda citolog\u00eda Dermatolog\u00eda dermatolog\u00eda
Embriolog\u00eda embriolog\u00eda Endocrinolog\u00eda endocrinolog\u00eda
Epidemiolog\u00eda epidemiolog\u00eda Estomatolog\u00eda estomatolog\u00eda
Farmacolog\u00eda farmacolog\u00eda Fisiatr\u00eda fisiatr\u00eda Fisiolog\u00eda
fisiolog\u00eda Fisiopatolog\u00eda fisiopatolog\u00eda Flebolog\u00eda flebolog\u00eda
Gastroenterolog\u00eda gastroenterolog\u00eda Gen\u00e9tica gen\u00e9tica
Geriatr\u00eda geriatr\u00eda Ginecolog\u00eda ginecolog\u00eda Hematolog\u00eda
hematolog\u00eda Hepatolog\u00eda hepatolog\u00eda Histolog\u00eda histolog\u00eda
Infectolog\u00eda infectolog\u00eda Inmunolog\u00eda inmunolog\u00eda
Microbiolog\u00eda microbiolog\u00eda Nefrolog\u00eda nefrolog\u00eda Neonatolog\u00eda
neonatolog\u00eda Neumolog\u00eda neumolog\u00eda Neurocirug\u00eda neurocirug\u00eda
Neurolog\u00eda neurolog\u00eda Nutriolog\u00eda nutriolog\u00eda Obstetricia
obstetricia Odontolog\u00eda odontolog\u00eda Oftalmolog\u00eda oftalmolog\u00eda
Oncolog\u00eda oncolog\u00eda Ortopedia ortopedia Otorrinolaringolog\u00eda
otorrinolaringolog\u00eda Parasitolog\u00eda parasitolog\u00eda Patolog\u00eda
patolog\u00eda Pediatr\u00eda pediatr\u00eda Perinatolog\u00eda perinatolog\u00eda
Proctolog\u00eda proctolog\u00eda Psiquiatr\u00eda psiquiatr\u00eda Radiolog\u00eda
radiolog\u00eda Radioterapia radioterapia Reumatolog\u00eda reumatolog\u00eda
Toxicolog\u00eda toxicolog\u00eda Traumatolog\u00eda traumatolog\u00eda Urolog\u00eda
urolog\u00eda Virolog\u00eda virolog\u00eda Alergolog\u00eda alergolog\u00eda
Inmunopatolog\u00eda inmunopatolog\u00eda Hematooncolog\u00eda hematooncolog\u00eda
Neurofisiolog\u00eda neurofisiolog\u00eda Neurooftalmolog\u00eda neurooftalmolog\u00eda
Neuropsicolog\u00eda neuropsicolog\u00eda Neuropsiquiatr\u00eda neuropsiquiatr\u00eda
Neuropediatr\u00eda neuropediatr\u00eda Neuropatolog\u00eda neuropatolog\u00eda
Otoneurolog\u00eda otoneurolog\u00eda Fonoaudiolog\u00eda fonoaudiolog\u00eda
Logopedia logopedia Optometr\u00eda optometr\u00eda Ort\u00f3ptica ort\u00f3ptica
Podolog\u00eda podolog\u00eda Androlog\u00eda androlog\u00eda Mastolog\u00eda
mastolog\u00eda Senolog\u00eda senolog\u00eda Tanatolog\u00eda tanatolog\u00eda
Sexolog\u00eda sexolog\u00eda Imagenolog\u00eda imagenolog\u00eda Emergentolog\u00eda
emergentolog\u00eda Intensivismo intensivismo Medicina medicina Enfermer\u00eda
enfermer\u00eda Fisioterapia fisioterapia Kinesiolog\u00eda kinesiolog\u00eda
Rehabilitaci\u00f3n rehabilitaci\u00f3n Proped\u00e9utica proped\u00e9utica
Semiolog\u00eda semiolog\u00eda Nosolog\u00eda nosolog\u00eda Bio\u00e9tica bio\u00e9tica
Deontolog\u00eda deontolog\u00eda
"""

# Import remaining large blocks from sibling modules
from gen_lexicon_extra import EXTRA_BLOCKS  # type: ignore
from gen_lexicon_more import MORE_BLOCKS, simple_plurals  # type: ignore
from gen_lexicon_morph import all_extra_tokens  # type: ignore
from ortho_priority import filter_orthography_errors  # type: ignore

# Palabras no clinicas / ruido a excluir si aparecen
DENY = {
    "airway", "aix", "agar", "agarosa", "agenda", "agentaje",
    "agglutination", "agreed", "alambrado", "albahaca", "aceitunas",
    "alcantarillado", "alejamiento", "alianza", "alienacion",
    "alienaci\u00f3n", "almacenamiento", "alojamiento", "auditory",
    "apparato", "apartamento", "aperitivo", "afrenta", "anesthesiolog\u00eda",
    "abscessos", "anticipating", "anticoagulation", "extraction",
    "interaction", "interpretation", "intervention", "microsimulation",
    "pararfighting", "substituting", "vaccine", "cosas",
}

EXT_FILTERED = SRC / "external" / "freq_medical_filtered.txt"
EXT_EXPANDED = SRC / "external" / "expanded_60k.txt"
USER_SEED = SRC / "user_examples_med.txt"


def load_wordlist(path: Path) -> set[str]:
    words: set[str] = set()
    if not path.exists():
        return words
    for line in path.read_text(encoding="utf-8").splitlines():
        w = line.strip()
        if not w or w.startswith("#"):
            continue
        if w in FIXES:
            fixed = FIXES[w]
            if fixed:
                words.add(fixed)
            continue
        if valid(w):
            words.add(w)
    return words


def collect() -> list[str]:
    # Rebuild from sources only (idempotent; do not bootstrap from OUT).
    bag: set[str] = set()
    deny_cf = {d.casefold() for d in DENY}
    for block in (B_ANATOMIA, B_ESPECIALIDADES, *EXTRA_BLOCKS, *MORE_BLOCKS):
        for w in tokens(block):
            if valid(w) and w.casefold() not in deny_cf:
                bag.add(w)
    for w in all_extra_tokens():
        if valid(w) and w.casefold() not in deny_cf:
            bag.add(w)
    for path in (EXT_FILTERED, EXT_EXPANDED, USER_SEED):
        for w in load_wordlist(path):
            if w.casefold() not in deny_cf:
                bag.add(w)
    for w in simple_plurals(bag):
        if valid(w) and w.casefold() not in deny_cf:
            bag.add(w)

    # Orthography has priority over medical vocabulary.
    bag, ortho_stats = filter_orthography_errors(bag)
    print(
        "Filtro ortografia: "
        f"in={ortho_stats['input']} kept={ortho_stats['kept']} "
        f"drop_sin_tilde={ortho_stats['drop_unaccented_vs_accented']} "
        f"drop_sufijo={ortho_stats['drop_bad_medical_ending']} "
        f"drop_en={ortho_stats['drop_english']}"
    )

    all_words = sorted(bag, key=lambda s: (s.casefold(), s))
    dump = SRC / "ua_med_lexicon_full.txt"
    dump.write_text(
        "# Lexico medico UA completo (generado)\n" + "\n".join(all_words) + "\n",
        encoding="utf-8",
        newline="\n",
    )
    return all_words


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
    print(f"Bytes UTF-8: {OUT.stat().st_size}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
