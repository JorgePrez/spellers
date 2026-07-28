# -*- coding: utf-8 -*-
"""Expand law lexicon (DPEJ-informed seeds + morph + es_* harvest). Orthography-first."""
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

OUT = BASE / "source" / "external" / "expanded_der.txt"
EXT = BASE / "source" / "external"

ROOTS = [
    "jurid", "legal", "legisl", "judicial", "jurisdic", "jurisconsult",
    "proces", "procediment", "penal", "civil", "mercantil", "laboral",
    "administrativ", "constitucional", "contract", "obligacion", "obligatori",
    "demand", "sentenc", "apelac", "casacion", "amparo", "embarg",
    "notific", "citacion", "emplaz", "prescrip", "usucap", "caduc",
    "hered", "testament", "suces", "legat", "albace", "donat",
    "matrimon", "divorci", "filiac", "adopcion", "tutel", "curatel",
    "indemniz", "resarc", "compens", "da\u00f1", "perjuic",
    "delit", "delict", "criminal", "culpab", "imputab", "antijurid",
    "tipicidad", "antijuridicidad", "culpabilidad",
    "cohech", "peculad", "prevaric", "malvers", "extorsion", "estafa",
    "usurp", "feminicid", "homicid", "parricid", "fratricid", "infanticid",
    "secuest", "extradic", "expropi", "licitac", "adjudic",
    "recurs", "cautelar", "notarial", "registral", "fideicomis",
    "usufruct", "hipotec", "prendari", "anticretic",
    "arbitraj", "concili", "mediacion", "transaccion",
    "diplom", "tratad", "ratific", "jurisprud",
    "fiscal", "procurad", "magistr", "juzgad", "tribunal",
    "querell", "denunci", "imputad", "procesad", "conden", "absolv",
    "prision", "libertad", "condena", "pena", "sancion",
    "nulidad", "anulab", "rescind", "resolutor", "resolutiv",
    "simulat", "vici", "consent", "capacidad", "legitim",
    "posesion", "propiedad", "dominio", "servidumbr", "superficie",
    "comunidad", "copropiedad", "condomin", "medianer",
    "arrend", "comodat", "mutu", "deposit", "secuest", "fianza",
    "garant", "aval", "endoso", "protest", "letra", "pagar",
    "cheque", "titulo", "valor", "accionari", "societari",
    "quiebra", "concurs", "insolvenci", "reorganiz", "liquidacion",
    "trabajador", "patronal", "sindical", "huelg", "despido",
    "indemnizatori", "preaviso", "reinstal", "reenganche",
    "tributari", "impositiv", "fiscaliz", "evas", "elusion",
    "aduaner", "arancelari", "contraband",
    "ambiental", "urbanistic", "agrari", "mineri", "energet",
    "consumidor", "competenci", "antitrust", "monopoli",
    "propiedad", "intelectual", "industrial", "patente", "marca",
    "derech", "autor", "copyright", "licenc",
    "internacional", "humanitari", "refugiad", "asilo", "extradicion",
    "genocid", "crimen", "lesa", "tortur", "desaparicion",
    "constitucionalidad", "inconstitucional", "convencionalidad",
    "supremacia", "bloque", "reforma", "enmienda", "constituyente",
    "habeas", "exequátur", "affidavit", "litis", "litisconsor",
    "litispend", "conexidad", "prejudicial", "competenc",
    "declinator", "inhibitor", "recus", "excus", "terceria",
    "allanamient", "reconvencion", "memorial", "alegat",
    "testifical", "documental", "pericial", "indiciar",
    "confesion", "inspeccion", "edicto", "cedula",
    "medida", "interdicto", "posesori", "restitutori",
    "ejecucion", "remate", "subasta", "lanzamient", "desahuc",
    "ministerio", "fiscalia", "defensoria", "procuraduria",
    "juez", "jueza", "magistrado", "magistrada", "secretario",
    "actuacion", "expediente", "autos", "folio", "proveido",
    "resolucion", "auto", "decreto", "acuerdo", "providencia",
    "amnistia", "indult", "conmutacion", "rehabilitacion",
    "antecedent", "prontuari", "reincidencia", "habitualidad",
    "tentativa", "frustrad", "consumad",
    "autoria", "induccion", "instigacion", "cooperacion",
    "encubr", "receptacion", "eximent", "atenuant", "agravant",
    "dolo", "culpa", "preterintencion", "imprudenc", "negligenc",
    "legitima", "defensa", "estado", "necesidad", "obediencia",
    "error", "tipo", "prohibicion", "vencible", "invencible",
    "causacion", "nexo", "causal", "imputacion", "objetiv",
    "subjetiv", "antijuridic", "punibil",
    "persona", "juridica", "fisica", "capacidad", "representacion",
    "mandat", "procuracion", "poder", "apoderad",
    "sociedad", "anonima", "limitada", "colectiva", "comandita",
    "cooperativa", "asociacion", "fundacion", "ONG",
    "acto", "juridico", "negocio", "declaracion", "voluntad",
    "forma", "solemnidad", "escritur", "publicidad", "inscripcion",
    "registro", "civil", "mercantil", "propiedad", "inmueble",
    "catastro", "folio", "real", "matricula",
    "bien", "mueble", "inmueble", "fungible", "consumible",
    "principal", "accesorio", "futuro", "presente",
    "obligacion", "natural", "civil", "solidari", "mancomun",
    "divisible", "indivisible", "alternativa", "facultativa",
    "condici", "plazo", "modo", "termino",
    "pago", "cumplimiento", "incumplimiento", "mora", "dacion",
    "novacion", "compensacion", "confusion", "remision", "perdida",
    "imposibilidad", "caso", "fortuito", "fuerza", "mayor",
    "clausula", "penal", "resolutoria", "adjudicacion",
    "pacto", "comisorio", "retroventa", "reserva", "dominio",
    "opcion", "preferent", "tanteo", "retracto",
    "compravent", "permut", "donacion", "mutuo", "prestamo",
    "cuenta", "corriente", "apertura", "credito", "descuento",
    "factoring", "leasing", "renting", "franchising",
    "seguro", "poliza", "siniestro", "indemnizacion", "reaseguro",
    "transporte", "flete", "conocimiento", "embarque",
    "agencia", "comision", "corretaje", "mediacion", "consignacion",
    "edicion", "publicacion", "obra", "audiovisual",
    "marca", "nombre", "comercial", "rotulo", "slogan",
    "patente", "modelo", "utilidad",
    "diseno", "industrial", "secreto", "empresarial",
    "competencia", "desleal", "publicidad", "enganosa",
    "proteccion", "datos", "privacidad", "habeas", "data",
    "ciberdelit", "ciberseguridad", "phishing", "hacking",
    "familia", "parentesc", "afinidad", "consanguinidad",
    "alimentos", "pension", "guard", "custodia", "visitas",
    "patria", "potestad", "emancipacion", "mayoria", "edad",
    "menor", "incapaz", "interdiccion", "inhabilitacion",
    "sucesion", "intestada", "testada", "legitima", "mejora",
    "libre", "disposicion", "colacion", "particion", "adjudicacion",
    "inventario", "avaluo", "tasacion", "albacea", "contadors",
    "partidor", "heredero", "legatario", "acreedor", "hereditari",
    "trabajo", "empleo", "contrato", "individual", "colectivo",
    "jornada", "horario", "salario", "aguinaldo", "bonificacion",
    "vacacion", "licencia", "permiso", "incapacidad", "maternidad",
    "paternidad", "acoso", "laboral", "mobbing", "discriminacion",
    "igualdad", "genero", "acoso", "sexual",
    "sindicato", "federacion", "confederacion", "huelga", "paro",
    "lockout", "convenio", "colectivo", "negociacion",
    "inspeccion", "trabajo", "sancion", "administrativa",
    "seguridad", "social", "IGSS", "cotizacion", "aportacion",
    "pension", "jubilacion", "invalidez", "sobrevivencia",
    "administracion", "publica", "acto", "administrativo",
    "potestad", "reglamentaria", "sancionadora",
    "recurso", "revocatoria", "reposicion", "apelacion",
    "silencio", "administrativo", "positivo", "negativo",
    "expropiacion", "utilidad", "publica", "ocupacion",
    "concesion", "licencia", "permiso", "autorizacion",
    "contratacion", "publica", "licitacion", "adjudicacion",
    "contratista", "subcontratista", "garantia", "cumplimiento",
    "responsabilidad", "patrimonial", "Estado",
    "servidor", "publico", "funcionario", "empleado",
    "destitucion", "suspension", "inhabilitacion",
    "municipio", "alcaldia", "concejo", "corporacion",
    "ordenanza", "reglamento", "acuerdo",
    "tributo", "impuesto", "tasa", "contribucion", "arbitrio",
    "base", "imponible", "alicuota", "exencion", "deduccion",
    "retencion", "percepcion", "declaracion", "jurada",
    "fiscalizacion", "determinacion", "liquidacion", "cobro",
    "ejecutivo", "apremio", "embargo", "remate",
    "contencioso", "administrativo", "proceso",
    "amparo", "constitucional", "exhibicion", "personal",
    "habeas", "corpus", "data", "inconstitucionalidad",
    "consulta", "facultativa", "opiniones", "consultivas",
    "corte", "constitucionalidad", "suprema", "justicia",
    "camara", "apelaciones", "tribunal", "sentencia",
    "jurisprudencia", "doctrina", "legal", "precedente",
    "vinculante", "obiter", "dictum", "ratio", "decidendi",
    "interpretacion", "analogia", "equidad", "principios",
    "generales", "derecho", "costumbre", "usos",
    "fuente", "formal", "material", "jerarquia", "normativa",
    "ley", "decreto", "reglamento", "acuerdo", "gubernativo",
    "codigo", "civil", "penal", "procesal", "comercio",
    "trabajo", "tributario", "municipal",
    "constitucion", "politica", "republica", "Guatemala",
    "organo", "judicial", "legislativo", "ejecutivo",
    "ministerio", "publico", "procuraduria", "derechos", "humanos",
    "PDH", "MP", "OJ", "CC", "CSJ", "TSE", "SAT", "IGSS",
    "notario", "notaria", "protocolo", "escritura", "publica",
    "acta", "notarial", "legalizacion", "autenticacion",
    "fe", "publica", "registro", "publico",
    "tradicion", "romana", "common", "law", "continental",
    "ius", "cogens", "erga", "omnes", "pacta", "sunt", "servanda",
    "non", "bis", "in", "idem", "favor", "rei", "pro", "homine",
    "nemo", "tenetur", "judex", "ultra", "petita",
    "reformatio", "in", "peius", "peyorativa",
]

# Clean roots: remove accidental bad tokens and short noise
ROOTS = sorted({r for r in ROOTS if len(r) >= 3 and " " not in r})

MARKERS = (
    "jurid", "legal", "legisl", "judicial", "jurisdic", "proces",
    "penal", "civil", "mercantil", "laboral", "administrativ",
    "constitucional", "contract", "obligaci", "demand", "sentenc",
    "apelac", "casaci", "amparo", "embarg", "notific", "citaci",
    "emplaz", "prescrip", "usucap", "heredi", "testament", "suces",
    "matrimon", "divorci", "filiac", "adopci", "indemniz", "delito",
    "delict", "criminal", "culpab", "imputab", "antijurid", "tipicidad",
    "cohech", "peculad", "prevaric", "malvers", "extorsi", "estafa",
    "usurp", "feminicid", "homicid", "parricid", "secuest", "extradic",
    "expropi", "licitac", "adjudic", "recurs", "cautelar", "notarial",
    "registral", "fideicomis", "usufruct", "hipotec", "prend",
    "arbitr", "concili", "mediaci", "diplom", "tratado", "ratific",
    "jurisprud", "habeas", "exequátur", "fiscal", "procurad",
    "magistr", "juzgado", "tribunal", "querell", "denunci",
    "litis", "nulidad", "sentencia", "proceso", "juicio",
    "abogad", "notari", "escrib", "protocol", "codigo",
    "decreto", "reglamento", "ordenanza", "constituc",
    "demandant", "demandad", "imputad", "procesad", "conden",
    "absolv", "apelant", "recurrent", "agraviad",
    "embarg", "remate", "subasta", "desahuc", "lanzamient",
    "tutel", "curatel", "alimentos", "pension", "custodia",
    "patria", "potestad", "testador", "heredero", "legatari",
    "albacea", "donatari", "usufructuari", "nudo",
    "arrendatari", "arrendador", "comodatari", "fiador",
    "avalist", "endosant", "tenedor", "librador", "librado",
    "accionist", "societari", "quiebra", "concurs", "insolv",
    "trabajador", "empleador", "patron", "sindic", "huelga",
    "despido", "indemniz", "salario", "jornada",
    "tribut", "impuest", "fiscaliz", "evas", "elusion",
    "aduan", "arancel", "contraband", "SAT",
    "expropi", "concesion", "licencia", "autorizacion",
    "servidor", "funcionari", "municipal", "alcald",
    "amnistia", "indult", "reincidencia", "tentativa",
    "dolo", "culpa", "imprudenc", "negligenc",
    "legitima", "defensa", "antijuridic", "punibil",
    "propiedad", "intelectual", "patente", "marca",
    "competencia", "desleal", "consumidor",
    "internacional", "humanitari", "refugiad", "asilo",
    "genocid", "tortur", "desaparicion", "trata",
    "constitucionalidad", "inconstitucional", "convencionalidad",
    "supremacia", "constituyente", "jurisprudencial",
)

SEED = r"""
amparo casaci\u00f3n apelaci\u00f3n fideicomiso usufructo
habeas corpus exequatur litis litisconsorcio litispendencia
jurisprudencia juridicidad antijuridicidad tipicidad
feminicidio parricidio sicariato trata personas
prescripci\u00f3n usucapi\u00f3n caducidad nulidad
embargo secuestro interdicto desahucio lanzamiento
notario notaria protocolo escritura p\u00fablica
ministerio p\u00fablico fiscal\u00eda procuradur\u00eda
corte constitucionalidad supremacia convencionalidad
debido proceso tutela judicial efectiva
cosa juzgada litispendencia prejudicialidad
recurso reposici\u00f3n queja revisi\u00f3n
prisi\u00f3n preventiva criterio oportunidad
patria potestad alimentos pensi\u00f3n alimenticia
da\u00f1o emergente lucro cesante da\u00f1o moral
responsabilidad extracontractual contractual
acta notarial legalizaci\u00f3n autenticaci\u00f3n
c\u00f3digo civil penal procesal comercio trabajo
constituci\u00f3n pol\u00edtica rep\u00fablica Guatemala
\u00f3rgano judicial legislativo ejecutivo
PDH MP OJ CC CSJ TSE SAT IGSS
"""

FORCE = [
    "amparo", "casaci\u00f3n", "apelaci\u00f3n", "fideicomiso", "usufructo",
    "habeas", "exequátur", "litis", "litisconsorcio", "litispendencia",
    "jurisprudencia", "feminicidio", "prescripci\u00f3n", "usucapi\u00f3n",
    "embargo", "notario", "fiscal\u00eda", "constitucionalidad",
    "Guatemala", "Constituci\u00f3n", "PDH", "MP", "OJ", "CC", "CSJ", "TSE", "SAT",
]

DENY = {
    "airway", "agenda", "vaccine", "cosas", "hola", "joint", "venture",
    "leasing", "factoring", "clear", "aligners", "tecnico", "imagenes",
    "juridico", "casacion", "prescripcion", "apelacion",
}

BASES = [
    "jur\u00eddico", "jur\u00eddica", "legal", "penal", "civil", "procesal",
    "constitucional", "administrativo", "administrativa", "laboral",
    "mercantil", "tributario", "tributaria", "notarial", "registral",
]



# FP_ROOTS_20260727
ROOTS = list(ROOTS) + [
    'subrog',
    'evicc',
    'preclus',
    'subsunc',
    'subsuntiv',
    'dispositivum',
    'progresividad',
    'principiolog',
    'ponderacion',
    'proporcionalidad',
    'dispositivo',
]


# FP_FORCE_20260727
FORCE = list(FORCE) + [
    'dispositivum',
    'subrogación',
    'subrogaciones',
    'subrogar',
    'subrogado',
    'subrogada',
    'subrogante',
    'subrogantes',
    'evicción',
    'evicciones',
    'preclusión',
    'preclusiones',
    'preclusivo',
    'preclusiva',
    'exequátur',
    'exequátur',
    'progresividad',
    'progresividades',
    'subsunción',
    'subsunciones',
    'subsuncionar',
    'subsunto',
    'subsunta',
]

def main() -> int:
    bag: set[str] = set()
    bag |= tokens_from_escaped_block(SEED)
    bag |= morph_combo(ROOTS, bases=BASES, prefixes=COMMON_PREFIXES)
    bag |= harvest_reference(EXT, MARKERS)
    bag |= gender_number(bag, title_if=lambda w: any(m in w.casefold() for m in ("jurid", "legal", "amparo", "proces")))
    bag = {w for w in bag if valid_word(w)}
    bag, stats = finalize(bag, BASE, force_keep=FORCE, deny=DENY)
    words = write_expanded(OUT, bag, "expanded_der UA (generado; ortografia prioritaria)")
    print(f"expanded_der: {len(words)}  stats={stats}")
    for c in ("amparo", "casaci\u00f3n", "litis", "tecnico", "imagenes", "juridico"):
        print(f"  {c}: {c in bag}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
