# -*- coding: utf-8 -*-
"""Expand economics/finance/accounting lexicon (seeds + morph + es_* harvest). Orthography-first."""
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

OUT = BASE / "source" / "external" / "expanded_eco.txt"
EXT = BASE / "source" / "external"

ROOTS = [
    "economic", "macroeconomic", "microeconomic", "econometr",
    "financier", "contabil", "fiscal", "monetar", "bursatil",
    "bancari", "creditici", "tributari", "presupuest", "comercial",
    "export", "import", "arancelari", "cambiari", "inflacion",
    "deflacion", "recesion", "crecimiento", "desemple", "productiv",
    "inversion", "capitaliz", "rentabil", "liquidez", "solvenci",
    "apalanc", "endeud", "depreci", "amortiz", "auditor",
    "patrimoni", "oligopol", "monopol", "duopol", "external",
    "elastic", "keynes", "marshall", "walras", "cobb", "douglas",
    "islm", "phillips", "taylor", "balanza", "pagos", "tipo",
    "cambio", "reserva", "internacional", "FMI", "BM", "BID",
    "BANGUAT", "mercad", "ofert", "demand", "precio", "cost",
    "ingres", "egres", "gast", "ahorro", "consum", "utilid",
    "gananci", "perdid", "benefici", "margin", "interes",
    "divisa", "moneda", "devaluacion", "revaluacion", "paridad",
    "competitiv", "ventaj", "comparativ", "absolut", "comerci",
    "import", "export", "balanz", "deficit", "superavit",
    "proteccion", "librecambis", "arancel", "cuota", "subsidio",
    "dumping", "salvaguard", "barreras", "comerciales",
    "inelastic", "sustitut", "complement", "bien", "servici",
    "mercaderi", "capital", "trabajo", "tierra", "empresari",
    "factor", "producci", "distribuc", "intercambi", "asignacion",
    "eficienci", "equidad", "optim", "pareto", "equilibri",
    "estabil", "volatil", "fluctuacion", "ciclo", "expansion",
    "contraccion", "estancamient", "estanflacion", "hiperinflacion",
    "desemplead", "subemple", "pleno", "empleo", "ocupacion",
    "desocupacion", "tasa", "actividad", "participation",
    "salari", "sueld", "remuneration", "ingresal", "nominal",
    "presupuestar", "planific", "proyecto", "programacion",
    "evalua", "rentabilid", "viabilid", "factibilid",
    "flujo", "caja", "efectiv", "tesoreri", "circulant",
    "corriente", "activo", "pasivo", "capital", "neto",
    "balance", "estado", "resultad", "situacion", "financier",
    "patrimonial", "cuenta", "perdi", "gananc",
    "mayor", "diario", "libro", "asient", "partid",
    "debe", "haber", "cargo", "abono", "saldo",
    "inventari", "existenci", "mercaderi", "almacen",
    "valuacion", "valoracion", "tasacion", "precio",
    "adquisicion", "venta", "compra", "transaccion",
    "operacion", "registro", "contable", "asiento",
    "conciliacion", "bancar", "ajuste", "provision",
    "depreciacion", "amortizacion", "deterioro", "baja",
    "revaluacion", "desvalorizacion", "plusvali",
    "impuest", "renta", "valor", "agregad", "IVA",
    "ISR", "retencion", "percepcion", "declaracion",
    "contribuyent", "sujeto", "pasivo", "hecho", "generador",
    "base", "imponible", "tarifa", "alicuota", "exencion",
    "deduccion", "credito", "tributari", "fiscaliz",
    "SAT", "SUNAT", "AFIP", "SII", "DIAN",
    "evasion", "elusion", "planificacion", "tributari",
    "paraiso", "fiscal", "offshore", "precios", "transferenci",
    "auditoria", "dictamen", "revision", "examen",
    "verificacion", "inspeccion", "control", "interno",
    "externo", "independient", "financier", "operacion",
    "cumplimient", "forense", "forensic", "gubernament",
    "NIIF", "NIC", "GAAP", "normas", "internacionales",
    "principios", "contables", "aceptad", "generalment",
    "revelacion", "notas", "estados", "financieros",
    "consolidacion", "combinacion", "negocios", "fusion",
    "adquisicion", "participacion", "control", "influencia",
    "significativ", "subsidiaria", "asociada", "coligad",
    "matriz", "holding", "grupo", "empresarial",
    "riesgo", "incertidum", "contingencia", "probabilid",
    "exposicion", "cobertur", "hedge", "derivad",
    "futuro", "opcion", "swap", "forward", "warrant",
    "arbitraje", "especulacion", "inversion", "portafoli",
    "diversificacion", "concentracion", "correlacion",
    "beta", "alfa", "sigma", "varianza", "covarianza",
    "sharpe", "treynor", "jensen", "sortino",
    "volatilid", "riesgo", "sistematic", "especific",
    "mercad", "capital", "valores", "bolsa", "bursatil",
    "accion", "bono", "obligacion", "debenture",
    "papeles", "comerciales", "certificad", "deposit",
    "fondos", "mutuo", "inversion", "ETF", "fiduciario",
    "indice", "benchmark", "referencia", "SP", "dow",
    "nasdaq", "rendimient", "yield", "cupon", "descuento",
    "prima", "par", "valor", "nominal", "mercado",
    "cotizacion", "precio", "cierre", "apertura",
    "maximo", "minimo", "promedio", "ponderado",
    "volumen", "operaciones", "liquidez", "profundid",
    "spread", "diferencial", "margen", "comision",
    "corredor", "broker", "dealer", "market", "maker",
    "custodi", "depositari", "clearing", "compensacion",
    "liquidacion", "settlement", "entrega", "recepcion",
    "calificador", "rating", "grado", "inversion",
    "especulativ", "AAA", "BBB", "junk", "basura",
    "credito", "prestamo", "financiamiento", "endeudamient",
    "apalancamient", "leverage", "debt", "equity",
    "garantia", "colateral", "hipoteca", "prenda",
    "aval", "fianza", "cauciones", "respaldo",
    "moratoria", "incumplimient", "default", "impago",
    "reestructuracion", "refinanciacion", "renegociacion",
    "quiebra", "bancarrota", "insolvencia", "concurs",
    "acreedores", "liquidacion", "cesion", "pagos",
    "presupuesto", "proyeccion", "prevision", "estimacion",
    "pronostic", "forecast", "budget", "meta",
    "objetivo", "indicador", "KPI", "metrica",
    "desempe\u00f1", "performance", "eficiencia", "eficacia",
    "efectivid", "productivid", "competitivid",
    "benchmarking", "mejores", "practicas", "best",
    "estrategi", "tactic", "operativ", "planificacion",
    "organizacion", "direccion", "control", "gestion",
    "administracion", "gerenci", "management", "liderazg",
    "corporativ", "empresarial", "negocio", "comercio",
    "industri", "sector", "ramo", "actividad",
    "PIB", "PNB", "ingreso", "nacional", "per", "capita",
    "cuentas", "nacionales", "agregados", "macroeconomicos",
]

# Clean roots: remove accidental bad tokens and short noise
ROOTS = sorted({r for r in ROOTS if len(r) >= 3 and " " not in r})

MARKERS = (
    "economic", "financier", "contabil", "fiscal", "monetar",
    "bancari", "tributari", "presupuest", "comercial",
    "export", "import", "inflacion", "deflacion", "recesion",
    "productiv", "inversion", "rentabil", "liquidez",
    "depreci", "amortiz", "oligopol", "monopol",
    "balanza", "cambio", "mercad", "precio", "cost",
    "ingres", "gast", "ahorro", "consum", "gananci",
    "perdid", "margin", "interes", "divisa", "moneda",
    "devaluacion", "competitiv", "deficit", "superavit",
    "arancel", "subsidio", "dumping", "elastic",
    "equilibri", "volatil", "ciclo", "expansion",
    "estancamient", "desemple", "salari", "sueld",
    "flujo", "caja", "activo", "pasivo", "balance",
    "estado", "resultad", "mayor", "diario", "asient",
    "debe", "haber", "inventari", "valuacion",
    "impuest", "renta", "IVA", "ISR", "contribuyent",
    "evasion", "elusion", "auditoria", "dictamen",
    "NIIF", "NIC", "GAAP", "consolidacion", "fusion",
    "subsidiaria", "matriz", "holding", "riesgo",
    "derivad", "futuro", "opcion", "swap",
    "portafoli", "diversificacion", "bolsa", "bursatil",
    "accion", "bono", "obligacion", "fondos",
    "indice", "rendimient", "yield", "cotizacion",
    "broker", "rating", "credito", "prestamo",
    "apalancamient", "garantia", "hipoteca", "default",
    "quiebra", "insolvencia", "presupuesto", "proyeccion",
    "KPI", "eficiencia", "productivid", "gestion",
    "PIB", "PNB", "agregados", "macro", "micro",
)

SEED = r"""
econ\u00f3mico econ\u00f3mica macroeconom\u00eda microeconom\u00eda
econometr\u00eda financiero contabilidad fiscal monetario
burs\u00e1til bancario crediticio tributario presupuesto
inflaci\u00f3n deflaci\u00f3n recesi\u00f3n desempleo
inversi\u00f3n capitalizaci\u00f3n rentabilidad liquidez
solvencia apalancamiento endeudamiento depreciaci\u00f3n
amortizaci\u00f3n patrimonio oligopolio monopolio duopolio
elasticidad balanza cambio divisa devaluaci\u00f3n
competitividad d\u00e9ficit super\u00e1vit arancel
mercader\u00eda producci\u00f3n distribuci\u00f3n
eficiencia \u00f3ptimo equilibrio estabilidad
hiperinflaci\u00f3n desempleado subempleo ocupaci\u00f3n
participaci\u00f3n salario planificaci\u00f3n evaluaci\u00f3n
viabilidad factibilidad tesorer\u00eda p\u00e9rdida
p\u00e9rdidas ganancia asiento conciliaci\u00f3n
provisi\u00f3n revaluaci\u00f3n desvalorizaci\u00f3n
plusval\u00eda impuesto alicuota exenci\u00f3n deducci\u00f3n
cr\u00e9dito fiscalizaci\u00f3n SAT evasi\u00f3n elusi\u00f3n
para\u00edso auditoria dict\u00e1men verificaci\u00f3n
inspecci\u00f3n revelaci\u00f3n consolidaci\u00f3n fusi\u00f3n
adquisici\u00f3n subsidiaria asociada coligada
incertidumbre probabilidad exposici\u00f3n cobertura
especulaci\u00f3n diversificaci\u00f3n concentraci\u00f3n
correlaci\u00f3n volatilidad sistem\u00e1tico espec\u00edfico
obligaci\u00f3n fiduciario \u00edndice rendimiento cup\u00f3n
m\u00e1ximo m\u00ednimo operaciones comisi\u00f3n
calificadora especulativo garant\u00eda incumplimiento
reestructuraci\u00f3n refinanciaci\u00f3n renegociaci\u00f3n
cesi\u00f3n proyecci\u00f3n previsi\u00f3n estimaci\u00f3n
pron\u00f3stico m\u00e9trica desempe\u00f1o estrategia
t\u00e1ctica organizaci\u00f3n direcci\u00f3n gesti\u00f3n
administraci\u00f3n gerencia liderazgo corporativo
per c\u00e1pita nacionales macroecon\u00f3micos
BANGUAT FMI BM BID IVA ISR NIIF NIC GAAP KPI PIB PNB
"""

FORCE = [
    "econ\u00f3mico", "econ\u00f3mica", "macroeconom\u00eda", "microeconom\u00eda",
    "econometr\u00eda", "inflaci\u00f3n", "deflaci\u00f3n", "recesi\u00f3n",
    "inversi\u00f3n", "capitalizaci\u00f3n", "depreciaci\u00f3n", "amortizaci\u00f3n",
    "devaluaci\u00f3n", "d\u00e9ficit", "super\u00e1vit", "producci\u00f3n",
    "distribuci\u00f3n", "\u00f3ptimo", "participaci\u00f3n", "evaluaci\u00f3n",
    "tesorer\u00eda", "p\u00e9rdida", "p\u00e9rdidas", "conciliaci\u00f3n",
    "provisi\u00f3n", "revaluaci\u00f3n", "desvalorizaci\u00f3n", "plusval\u00eda",
    "al\u00edcuota", "exenci\u00f3n", "deducci\u00f3n", "cr\u00e9dito",
    "fiscalizaci\u00f3n", "evasi\u00f3n", "elusi\u00f3n", "para\u00edso",
    "dict\u00e1men", "verificaci\u00f3n", "inspecci\u00f3n", "revelaci\u00f3n",
    "consolidaci\u00f3n", "fusi\u00f3n", "adquisici\u00f3n", "probabilidad",
    "exposici\u00f3n", "especulaci\u00f3n", "diversificaci\u00f3n",
    "concentraci\u00f3n", "correlaci\u00f3n", "sistem\u00e1tico",
    "espec\u00edfico", "obligaci\u00f3n", "\u00edndice", "cup\u00f3n",
    "m\u00e1ximo", "m\u00ednimo", "comisi\u00f3n", "garant\u00eda",
    "reestructuraci\u00f3n", "refinanciaci\u00f3n", "renegociaci\u00f3n",
    "cesi\u00f3n", "proyecci\u00f3n", "previsi\u00f3n", "estimaci\u00f3n",
    "pron\u00f3stico", "m\u00e9trica", "estrategia", "t\u00e1ctica",
    "organizaci\u00f3n", "direcci\u00f3n", "gesti\u00f3n", "administraci\u00f3n",
    "per c\u00e1pita", "macroecon\u00f3micos",
    "BANGUAT", "FMI", "BM", "BID", "IVA", "ISR", "NIIF", "NIC", "GAAP", "KPI", "PIB", "PNB",
]

DENY = {
    "airway", "agenda", "vaccine", "cosas", "hola", "joint", "venture",
    "leasing", "factoring", "clear", "aligners", "tecnico", "imagenes",
    "economico", "inflacion", "recesion", "inversion", "produccion",
    "depreciacion", "amortizacion", "evaluacion", "perdida", "provision",
    "credito", "evasion", "elusion", "paraiso", "dictamen", "fusion",
    "especifico", "sistematico", "indice", "cupon", "maximo", "minimo",
    "comision", "garantia", "proyeccion", "prevision", "estimacion",
    "pronostico", "metrica", "estrategia", "tactica", "organizacion",
    "direccion", "gestion", "administracion",
}

BASES = [
    "econ\u00f3mico", "econ\u00f3mica", "financiero", "financiera",
    "fiscal", "monetario", "monetaria", "tributario", "tributaria",
    "comercial", "burs\u00e1til", "bancario", "bancaria",
    "contable", "presupuestario", "presupuestaria",
]



# FP_ROOTS_20260727
ROOTS = list(ROOTS) + [
    'isocuant',
    'isocost',
    'paret',
    'duration',
    'señoreaj',
    'seigniorag',
    'edgeworth',
    'walrasian',
    'marshallian',
    'indiferenci',
    'factori',
    'marginalist',
]


# FP_FORCE_20260727
FORCE = list(FORCE) + [
    'isocuanta',
    'isocuantas',
    'isocosto',
    'isocostos',
    'Pareto',
    'pareto',
    'paretiano',
    'paretiana',
    'paretianos',
    'paretianas',
    'duration',
    'durations',
    'señoreaje',
    'señoreajes',
    'seigniorage',
]

def main() -> int:
    bag: set[str] = set()
    bag |= tokens_from_escaped_block(SEED)
    bag |= morph_combo(ROOTS, bases=BASES, prefixes=COMMON_PREFIXES)
    bag |= harvest_reference(EXT, MARKERS)
    bag |= gender_number(bag, title_if=lambda w: any(m in w.casefold() for m in ("economic", "financier", "contabil", "fiscal")))
    bag = {w for w in bag if valid_word(w)}
    bag, stats = finalize(bag, BASE, force_keep=FORCE, deny=DENY)
    words = write_expanded(OUT, bag, "expanded_eco UA (generado; ortografia prioritaria)")
    print(f"expanded_eco: {len(words)}  stats={stats}")
    for c in ("econ\u00f3mico", "inflaci\u00f3n", "PIB", "tecnico", "imagenes", "economico"):
        print(f"  {c}: {c in bag}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
