# -*- coding: utf-8 -*-
"""Expand political science lexicon. Orthography-first."""
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

OUT = BASE / "source" / "external" / "expanded_pol.txt"
EXT = BASE / "source" / "external"

ROOTS = [
    "polit", "politolog", "democr", "democratiz", "autocraci", "tecnocraci",
    "buroc", "burocrati", "elect", "electoral", "eleccionari", "sufrag",
    "referend", "plebiscit", "partid", "partidari", "pluripartid", "bipartid",
    "gobiern", "gubernament", "gobernabilidad", "gobernanz",
    "diplom", "diplomat", "geopolit", "geoestrategic", "realpolitik",
    "soberan", "soberanist", "parlament", "parlamentari", "congres",
    "legislat", "legislativ", "legislador", "diputad", "senad",
    "ejecutiv", "ministerial", "secretar", "subsecretar", "vicepresid",
    "presid", "presidencial", "presidencialism", "parlamentarism",
    "multilateral", "bilateral", "unilateral", "transnacional", "supranacional",
    "hegemón", "hegemonic", "contrahegemon", "hegemonist",
    "migr", "migratori", "inmigr", "emigr", "refugi", "refug", "asilad",
    "ciudadan", "ciudadaniz", "nacionaliz", "desnacionaliz", "expatri",
    "autoritar", "autoritarism", "totalitar", "totalitarism", "fascist",
    "dictad", "dictator", "dictatorial", "tirani", "tiran", "autocrat",
    "revoluc", "revolucionari", "insurrecci", "rebelion", "golpist",
    "clientel", "clientelar", "patronazg", "nepot", "caciquism", "caudill",
    "popul", "populist", "demagog", "demagogic", "populism",
    "neoliberal", "neoliberalism", "keynes", "keynesian", "monetar",
    "socialdem", "socialdemocrat", "socialdemocraci", "progresist",
    "conservador", "conservadur", "liberal", "liberalism", "libertari",
    "izquierd", "derechist", "centrist", "centrism",
    "transparen", "transparenci", "rendicion", "rendicionist",
    "corrupcion", "corrupto", "anticorrupcion", "soborno", "cohecho",
    "impunidad", "kleptocr", "kleptocraci", "patrimonialismo",
    "estado", "estatal", "nacional", "nacionalist", "supraestatal",
    "federacion", "federal", "federalist", "confederacion", "autonomi",
    "descentraliz", "centralizacion", "municipal", "municipaliz",
    "region", "regional", "interregional", "microrregion",
    "parti", "apartidi", "multipartidi", "oposicion", "opositor",
    "coalicion", "alianz", "bloqu", "frente", "movimient",
    "ideolog", "ideologic", "dogmat", "pragmat", "doctrinar",
    "poder", "empoder", "poderos", "omnipotent", "oligarqu",
    "separatist", "secesion", "secesionist", "independentist",
    "imperio", "imperial", "imperialista", "colonial", "colonialismo",
    "descoloniz", "poscolonial", "neocolonial", "anticolonial",
    "tratado", "tratadist", "convenci", "protocolo", "acuerdo",
    "alianza", "pactad", "negociad", "concertacion", "consenso",
    "veto", "vetar", "referendum", "iniciativ", "proporcional",
    "mayoritari", "represent", "representativ", "proporcionalidad",
    "elector", "electoral", "voto", "votant", "votacion", "escrutini",
    "cabildo", "consulta", "consultiv", "deliberat", "deliberativ",
    "participacion", "participativ", "abstencion", "abstencionismo",
    "militanc", "militantism", "activism", "actvist", "moviliz",
    "protest", "protestari", "manifest", "manifiest", "concentracion",
    "sindicat", "sindical", "gremial", "corporativ", "corporativism",
    "lobby", "lobbyst", "cabildeo", "grupo", "presion",
    "opinion", "publicist", "propagand", "propagandist", "mediatico",
    "discurso", "discursiv", "retorica", "retoric", "oratoria",
    "lider", "liderazgo", "liderismo", "caudillism", "mesianismo",
    "carisma", "carismatic", "populachero", "demagogo",
    "estrateg", "estrategic", "tactico", "tacticismo", "politiqueo",
    "campana", "campanero", "electoralismo", "proselitist",
    "sondeo", "encuest", "estadistic", "demografic", "votolog",
    "escano", "curul", "banca", "bancad", "legislatur",
    "quorum", "sesion", "plenari", "comision", "dictamen",
    "proyecto", "iniciativ", "proponent", "reformador", "enmiend",
    "fiscal", "fiscaliz", "contralor", "auditoria", "supervision",
    "tribunal", "magistr", "judicatur", "jurisdic", "competenci",
    "justici", "justiciabil", "enjuici", "impugn", "apelacion",
    "referendum", "plebiscit", "comicios", "jornada", "urna",
    "boleta", "papeleta", "candidat", "candid", "postul",
    "nominacion", "primari", "eleccionari", "eleccion",
    "derecho", "derechos", "humanos", "libertad", "libertades",
    "garantias", "garantist", "civil", "civico", "civismo",
    "seguridad", "segurid", "defensa", "defensiv", "militar",
    "armad", "fuerzas", "armadas", "ejercito", "policial",
    "paz", "pacif", "pacifist", "belico", "conflict", "guerra",
    "reconciliacion", "posconflict", "transicion", "transicional",
    "justicia", "transicional", "verdad", "reparacion", "memoria",
    "derecha", "izquierda", "centro", "extremismo", "extrem",
    "radical", "radicalizacion", "moderad", "centrista",
    "religion", "religios", "laicismo", "laicidad", "secular",
    "teocra", "teocracia", "clericalismo", "anticlerical",
    "nacion", "nacionalismo", "nacionalist", "patriot", "patriotismo",
    "cosmopolit", "internacionalismo", "internacionalista",
    "ONU", "OEA", "UNESCO", "ACNUR", "FMI", "BM", "BID",
    "OTAN", "CEPAL", "SICA", "PARLACEN", "CELAC",
    "Guatemala", "guatemaltec", "centroamerican", "latinoamerican",
    "TSE", "CICIG", "MP", "PDH", "CC", "Congreso",
]

# Clean roots: remove accidental bad tokens and short noise
ROOTS = sorted({r for r in ROOTS if len(r) >= 3 and " " not in r})

MARKERS = (
    "polit", "democr", "elect", "sufrag", "referend", "partid",
    "gobiern", "gobernanz", "diplom", "geopolit", "soberan",
    "parlament", "multilateral", "hegemon", "migr", "refug",
    "autoritar", "totalitar", "dictad", "revoluc", "clientel",
    "popul", "neoliberal", "socialdem", "transparen", "corrupcion",
    "estado", "federal", "municipal", "coalicion", "ideolog",
    "poder", "oligarqu", "secesion", "imperi", "colonial",
    "tratado", "alianza", "veto", "represent", "elector",
    "voto", "cabildo", "participacion", "militanc", "activism",
    "protest", "sindic", "lobby", "opinion", "propagand",
    "discurso", "lider", "carisma", "estrateg", "campana",
    "sondeo", "escano", "quorum", "sesion", "comision",
    "proyecto", "fiscal", "tribunal", "justici", "referendum",
    "candidat", "nominacion", "eleccion", "derech", "human",
    "libertad", "garantia", "civil", "seguridad", "defensa",
    "militar", "paz", "pacif", "conflict", "guerra",
    "reconciliacion", "transicion", "justicia", "memoria",
    "derecha", "izquierda", "centro", "extremism", "radical",
    "religion", "laicismo", "secular", "teocra", "nacion",
    "nacionalismo", "patriot", "cosmopolit", "ONU", "OEA",
    "TSE", "CICIG", "Guatemala", "centroamerican",
)

SEED = r"""
política geopolítica soberanía democracia gobernanza diplomacia
ONU OEA TSE Guatemala CICIG parlamento multilateral hegemonía
referéndum sufragio autoritarismo totalitarismo dictadura
revolución clientelismo populismo neoliberalismo socialdemocracia
transparencia corrupción migración refugiado ciudadanía
elección electoral presidencial legislativo ejecutivo
coalición ideología oligarquía secesión imperialismo
tratado alianza representación participación activismo
protesta sindical lobby opinión propaganda discurso
liderazgo carisma estrategia campaña sondeo legislatura
tribunal justicia derechos humanos libertades garantías
seguridad defensa militar paz conflicto guerra
reconciliación transición memoria radical centrista
nacionalismo patriotismo cosmopolita latinoamericano
"""

FORCE = [
    "política", "geopolítica", "soberanía", "democracia", "gobernanza",
    "diplomacia", "ONU", "OEA", "TSE", "Guatemala", "CICIG",
    "referéndum", "hegemonía", "migración", "ciudadanía",
    "elección", "coalición", "ideología", "oligarquía",
    "secesión", "opinión", "estrategia", "legislatura",
    "crédito", "rúbrica", "catedrático",
]

DENY = {
    "politico", "politica", "eleccion", "tecnico", "imagenes",
    "migracion", "ciudadania", "ideologia", "opinion",
    "estrategia", "revolucion", "corrupcion", "participacion",
    "clear", "airway", "vaccine", "joint", "venture",
}

BASES = [
    "político", "política", "democrático", "democrática",
    "electoral", "gubernamental", "diplomático", "diplomática",
    "geopolítico", "geopolítica", "parlamentario", "parlamentaria",
    "legislativo", "legislativa", "ejecutivo", "ejecutiva",
    "multilateral", "bilateral", "unilateral", "transnacional",
    "hegemónico", "hegemónica", "migratorio", "migratoria",
    "autoritario", "autoritaria", "totalitario", "totalitaria",
    "revolucionario", "revolucionaria", "populista",
    "neoliberal", "socialdemócrata", "corrupto", "corrupta",
]


def main() -> int:
    bag: set[str] = set()
    bag |= tokens_from_escaped_block(SEED)
    bag |= morph_combo(ROOTS, bases=BASES, prefixes=COMMON_PREFIXES)
    bag |= harvest_reference(EXT, MARKERS)
    bag |= gender_number(bag, title_if=lambda w: any(m in w.casefold() for m in ("polít", "democr", "gobiern", "parlament")))
    bag = {w for w in bag if valid_word(w)}
    bag, stats = finalize(bag, BASE, force_keep=FORCE, deny=DENY)
    words = write_expanded(OUT, bag, "expanded_pol UA (generado; ortografia prioritaria)")
    print(f"expanded_pol: {len(words)}  stats={stats}")
    for c in ("política", "geopolítica", "democracia", "politico", "eleccion", "tecnico"):
        print(f"  {c}: {c in bag}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
