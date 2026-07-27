# -*- coding: utf-8 -*-
"""Expand psychology lexicon. Orthography-first."""
from __future__ import annotations

import sys
from pathlib import Path

BASE = Path(__file__).resolve().parent
ROOT = BASE.parent
sys.path.insert(0, str(ROOT / "_shared"))
sys.path.insert(0, str(BASE))

from expand_engine import (  # noqa: E402
    COMMON_PREFIXES,
    finalize,
    gender_number,
    harvest_reference,
    morph_combo,
    tokens_from_escaped_block,
    valid_word,
    write_expanded,
)

OUT = BASE / "source" / "external" / "expanded_psi.txt"
EXT = BASE / "source" / "external"

ROOTS = [
    "psic", "psicolog", "psicometr", "psicoanalisi", "psicoanal",
    "psicodinam", "psicopat", "psicoterap", "psicofarma", "psicofisic",
    "psicosoci", "psicofisiolog", "psicoling", "psicoeduc",
    "cognit", "cognic", "metacognit", "precognit", "cognoscitiv",
    "neuro", "neuropsic", "neurocienci", "neurocognit", "neurobiolog",
    "neurofisiolog", "neuroanato", "neurodesarroll", "neuropatolog",
    "neurotransm", "neurotransmis", "neuromodul", "neuroplast",
    "sinap", "sinaptic", "cortical", "subcortical", "cerebral",
    "cerebeloso", "cerebelar", "hipocampal", "amigdalin", "prefrontal",
    "terap", "terapeutic", "terapeuta", "terapista", "psicoterapi",
    "cognitiv", "comportamental", "conduct", "humanist", "gestaltic",
    "sistemica", "narrat", "familiar", "grupal", "individual",
    "trastorn", "transtorn", "sindr", "sindrom", "sintom",
    "sintomatolog", "nosolog", "nosografic", "diagnostic",
    "depres", "depresiv", "distim", "ciclotim", "bipolar",
    "maniac", "hipoman", "eutim", "melancol", "estacional",
    "ansiedad", "ansios", "angust", "panic", "fobic",
    "agorafob", "claustrofob", "social", "especific", "generaliz",
    "obsesiv", "compulsiv", "rumiac", "intrusiv",
    "esquiz", "esquizofreni", "esquizotip", "esquizoafect",
    "psicotico", "psicosi", "delirant", "deliri", "alucinacion",
    "alucinatori", "paranoide", "catatonic", "desorgani",
    "autism", "autist", "asperger", "espectro", "neurodiversidad",
    "hiperactividad", "hiperactiv", "atencion", "atencional",
    "concentracion", "inatent", "impulsiv", "impulsividad",
    "personalidad", "caracter", "temperament", "narcis", "narcisist",
    "border", "borderline", "limit", "antisocial", "evitativ",
    "dependent", "histrion", "obsesiv", "paranoide", "esquizoid",
    "traum", "traumatic", "estres", "postraum", "agud", "cronic",
    "estressor", "estresan", "cortisol", "adrenal", "amigdala",
    "resilienc", "resilient", "afrontamient", "adaptacion", "adaptativ",
    "emocional", "emocion", "afectiv", "afecto", "sentimient",
    "anhedoni", "disfor", "alextimi", "alexitim", "autorregulacion",
    "inteligenci", "cognitiv", "emocional", "social", "fluida",
    "cristaliz", "cocient", "intelectual", "superdotacion",
    "aprendiz", "aprendizaj", "memoria", "memor", "mnemotecni",
    "consolid", "recuperacion", "recuerdo", "olvido", "amnesi",
    "percepcion", "perceptiv", "atencion", "atencional", "concentracion",
    "ejecutiv", "ejecutividad", "planificacion", "inhibicion",
    "flexibilidad", "monitoriz", "memoria", "trabajo",
    "lenguaj", "linguistic", "afasi", "dislexia", "dislalici",
    "tartamud", "fluidez", "fonolog", "semantic", "sintactic",
    "mindfulness", "atencion", "plena", "meditacion", "meditat",
    "conscienci", "conscient", "inconscient", "preconscient",
    "subconscient", "supra", "hipervigilanci", "disociac",
    "DSM", "CIE", "manual", "diagnostico", "estadistic",
    "clasificacion", "criterio", "categori", "dimensional",
    "TDAH", "TEA", "TOC", "TAG", "TLP", "TAB", "TEPT",
    "TDM", "TCA", "TND", "TDA", "TGD", "TPP",
    "serotonin", "serotoner", "dopamin", "dopaminer",
    "noradrenal", "adrenal", "GABA", "glutamat", "acetilcolin",
    "endorf", "oxitoc", "vasopres", "melatonin", "cortisol",
    "antidepr", "antipsicot", "ansiolit", "estabiliz", "humor",
    "benzodiazep", "ISRS", "IRSN", "tricic", "IMAO",
    "conduct", "comportament", "conductual", "conductismo",
    "condicionamient", "operant", "clasic", "refuerzo", "castigo",
    "extincion", "habituacion", "sensibiliz", "contracondicionamient",
    "desarrollo", "desarroll", "evolutiv", "madurac", "ontogenes",
    "infanci", "ninez", "adolescenci", "adultez", "senescenci",
    "apego", "vinculo", "vinculacion", "separacion", "abandon",
    "piaget", "piagetian", "vygotsky", "vygotskyano", "erikson",
    "freud", "freudian", "jung", "jungian", "adler", "adlerian",
    "evaluacion", "evaluat", "test", "prueba", "bater",
    "psicomet", "validez", "fiabilidad", "confiabilidad", "estandar",
    "norma", "percentil", "puntuacion", "baremacion", "calibracion",
    "clinica", "clinic", "salud", "mental", "bienestar",
    "psicopatolog", "anormal", "patolog", "intervencion", "prevencion",
    "promocion", "educativ", "escolar", "orientacion", "vocacional",
    "organizacion", "laboral", "trabajo", "recursos", "humanos",
    "social", "comunitari", "ambiental", "forense", "juridic",
    "deporte", "deportiv", "alto", "rendimiento", "motivacion",
    "sexualidad", "sexual", "identidad", "genero", "orientacion",
    "adiccion", "adicto", "sustanci", "alcohol", "droga",
    "dependenci", "abstinenci", "toleranci", "sindrome", "abstinencia",
    "suicid", "suicida", "autolesion", "parasuicid", "ideacion",
    "violenci", "maltrat", "abuso", "negligencia", "victimizacion",
    "duelo", "perdida", "afliccion", "luto", "elaboracion",
    "crisis", "urgenci", "emergenci", "intervencion", "contencion",
]

# Clean roots: remove accidental bad tokens and short noise
ROOTS = sorted({r for r in ROOTS if len(r) >= 3 and " " not in r})

MARKERS = (
    "psic", "cognit", "neuro", "terap", "trastorn", "depres",
    "ansiedad", "ansios", "esquiz", "psicot", "psicosi",
    "autism", "hiperactiv", "atencion", "personalidad",
    "traum", "estres", "resilienc", "emocional", "afectiv",
    "inteligenci", "aprendiz", "memoria", "percepcion",
    "ejecutiv", "lenguaj", "mindfulness", "conscienc",
    "DSM", "CIE", "TDAH", "TEA", "TOC", "TAG", "TLP",
    "serotonin", "dopamin", "neurotransm", "antidepr",
    "antipsicot", "conduct", "comportam", "condicion",
    "desarrollo", "evolut", "apego", "vinculo",
    "evaluacion", "psicomet", "clinica", "mental",
    "psicopatolog", "intervencion", "adiccion", "suicid",
    "violenci", "duelo", "crisis",
)

SEED = r"""
psicología psicopatología neurotransmisor resiliencia psicometría
cognitivo cognición neurociencia terapia psicoterapia
depresión ansiedad esquizofrenia autismo trastorno
TDAH TEA TOC TAG TLP TAB TEPT TDM DSM CIE
personalidad emoción afectivo inteligencia memoria
aprendizaje percepción atención ejecutivo lenguaje
mindfulness consciencia inconsciente conductismo
desarrollo evolutivo apego Piaget Vygotsky Freud
evaluación test clínica salud mental bienestar
adicción suicidio violencia duelo crisis
serotonina dopamina GABA antidepresivo ansiolítico
"""

FORCE = [
    "psicología", "psicopatología", "neurotransmisor", "resiliencia",
    "psicometría", "cognitivo", "cognición", "terapia",
    "depresión", "ansiedad", "esquizofrenia", "autismo",
    "DSM", "TDAH", "TEA", "TOC", "TAG", "TLP", "TAB", "TEPT",
    "emoción", "atención", "percepción", "consciencia",
    "adicción", "ansiolítico",
]

DENY = {
    "clinico", "depresion", "tecnico", "imagenes", "ansiedad",
    "cognicion", "atencion", "percepcion", "emocion", "adicion",
    "clear", "airway", "vaccine", "joint", "venture",
}

BASES = [
    "psicológico", "psicológica", "cognitivo", "cognitiva",
    "neurológico", "neurológica", "terapéutico", "terapéutica",
    "clínico", "clínica", "emocional", "afectivo", "afectiva",
    "conductual", "comportamental", "evolutivo", "evolutiva",
    "mental", "patológico", "patológica",
]


def main() -> int:
    bag: set[str] = set()
    bag |= tokens_from_escaped_block(SEED)
    bag |= morph_combo(ROOTS, bases=BASES, prefixes=COMMON_PREFIXES)
    bag |= harvest_reference(EXT, MARKERS)
    bag |= gender_number(bag, title_if=lambda w: any(m in w.casefold() for m in ("psic", "cognit", "neuro", "terap")))
    bag = {w for w in bag if valid_word(w)}
    bag, stats = finalize(bag, BASE, force_keep=FORCE, deny=DENY)
    words = write_expanded(OUT, bag, "expanded_psi UA (generado; ortografia prioritaria)")
    print(f"expanded_psi: {len(words)}  stats={stats}")
    for c in ("psicología", "cognitivo", "depresión", "clinico", "tecnico", "depresion"):
        print(f"  {c}: {c in bag}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
