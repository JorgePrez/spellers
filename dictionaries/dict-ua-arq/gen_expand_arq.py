# -*- coding: utf-8 -*-
"""Expand architecture/urbanism/construction/heritage lexicon (seeds + morph + es_* + wiki harvest). Orthography-first."""
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

OUT = BASE / "source" / "external" / "expanded_arq.txt"
EXT = BASE / "source" / "external"

ROOTS = [
    "arquitect", "arquitec", "urban", "urbanis", "construc",
    "edifici", "edificacion", "estructur", "cimentacion", "fundacion",
    "pilotaj", "zapata", "losa", "columna", "viga", "muro",
    "tabique", "mampost", "hormigon", "concreto", "armad",
    "refuerzo", "acero", "varilla", "malla", "estribo",
    "encofrad", "cimbra", "dovela", "dintel", "jamba",
    "alfeizar", "voladizo", "balcon", "terraza", "azotea",
    "cubierta", "tejado", "techo", "cielo", "raso", "falso",
    "lucernari", "claraboya", "tragaluz", "ventanal",
    "ventana", "puerta", "porton", "cancela", "acceso",
    "zaguán", "vestibulo", "hall", "atrio", "portico",
    "peristilo", "columnata", "arqueria", "arcada",
    "arco", "arquivolta", "dovela", "clave", "imposta",
    "jamba", "moldura", "cornisa", "friso", "arquitrabe",
    "capitel", "fuste", "basa", "pedestal", "plinto",
    "entablamento", "frontón", "timpano", "mención",
    "ábside", "absidal", "crucero", "transepto", "nave",
    "bóveda", "cúpula", "cupular", "domo", "tambor",
    "linterna", "nervio", "nervadura", "luneto", "pechina",
    "trompa", "arbotante", "contrafuerte", "estribo",
    "escalera", "escalinata", "grada", "peldaño", "huella",
    "contrahuella", "descanso", "barandal", "pasamano",
    "rampla", "pendiente", "accesibilidad", "universal",
    "diseño", "proyección", "planimetría", "topografía",
    "levantamiento", "relevamiento", "planta", "alzado",
    "sección", "corte", "fachada", "perspectiva",
    "axonometría", "isométrica", "escala", "acotación",
    "escantillón", "modulación", "módulo", "proporción",
    "áureo", "fibonacci", "simetría", "asimetría",
    "ritmo", "composición", "equilibrio", "armonía",
    "jerarquía", "focal", "énfasis", "contraste",
    "textura", "color", "iluminación", "natural",
    "artificial", "cenital", "lateral", "difusa",
    "directa", "indirecta", "sombra", "penumbra",
    "ventilación", "renovación", "aire", "cruzada",
    "mecánica", "extracción", "inyección", "climatización",
    "calefacción", "refrigeración", "aire", "acondicionado",
    "térmica", "aislamiento", "aislante", "térmico",
    "acústico", "impermeabilización", "impermeable",
    "hidrofugación", "humedad", "capilar", "ascendente",
    "condensación", "filtración", "goteo", "escorrentía",
    "drenaje", "desagüe", "alcantarillado", "pluvial",
    "sanitario", "residual", "cloaca", "sumidero",
    "instalación", "eléctrica", "hidráulica", "sanitaria",
    "gas", "mecánica", "especial", "domótica",
    "automatización", "inteligente", "smart", "building",
    "sostenible", "sustentable", "ecológico", "verde",
    "bioclimático", "bioclimática", "pasivo", "activo",
    "renovable", "fotovoltaico", "solar", "térmica",
    "geotérmica", "eólico", "biomasa", "reciclaje",
    "reutilización", "eficiencia", "energética", "certificación",
    "LEED", "BREEAM", "EDGE", "carbono", "neutro",
    "huella", "ecológica", "análisis", "ciclo", "vida",
    "materiales", "local", "regional", "reciclado",
    "madera", "laminada", "contrachapado", "OSB", "MDF",
    "bambú", "adobe", "tapial", "quincha", "bahareque",
    "piedra", "cantera", "mármol", "granito", "pizarra",
    "ladrillo", "bloque", "tabique", "cerámica", "azulejo",
    "baldosa", "loza", "porcelanato", "gres", "terracota",
    "vidrio", "cristal", "templado", "laminado", "doble",
    "triple", "bajo", "emisivo", "control", "solar",
    "metal", "aluminio", "cobre", "bronce", "latón",
    "acero", "inoxidable", "cortén", "galvanizado",
    "pintura", "revestimiento", "acabado", "estuco",
    "yeso", "enlucido", "repello", "cernido", "alisado",
    "impermeabilizante", "sellador", "juntas", "silicona",
    "poliuretano", "epóxico", "resina", "polímero",
    "membrana", "geotextil", "geomembrana", "bituminoso",
    "asfáltico", "alquitrán", "brea", "impermeabilizante",
    "urbanismo", "ordenamiento", "territorial", "zonificación",
    "uso", "suelo", "densidad", "edificabilidad",
    "coeficiente", "ocupación", "construcción", "altura",
    "retiro", "aislamiento", "frontal", "lateral", "posterior",
    "alineación", "municipal", "vialidad", "circulación",
    "tránsito", "peatonal", "vehicular", "ciclovía",
    "acera", "banqueta", "vereda", "calzada", "carril",
    "bulevar", "avenida", "calle", "pasaje", "callején",
    "plaza", "parque", "jardín", "área", "verde",
    "espacio", "público", "equipamiento", "urbano",
    "mobiliario", "señalización", "semáforo", "luminaria",
    "alumbrado", "público", "papelera", "banca", "fuente",
    "monumento", "escultura", "mural", "arte", "urbano",
    "graffiti", "intervención", "activación", "espacio",
    "regeneración", "revitalización", "rehabilitación",
    "restauración", "conservación", "patrimonio", "cultural",
    "histórico", "inmueble", "protegido", "catalogado",
    "monumental", "arqueológico", "colonial", "prehispánico",
    "vernáculo", "tradicional", "popular", "autóctono",
    "sismorresistente", "antisísmico", "sísmica", "resistencia",
    "ductilidad", "rigidez", "amortiguamiento", "disipación",
    "energía", "aislamiento", "base", "péndulo", "invertido",
    "amortiguador", "fricción", "viscoso", "elastomérico",
    "normativa", "código", "construcción", "reglamento",
    "especificación", "técnica", "norma", "NEC", "ACI",
    "ASTM", "ISO", "AGIES", "COPRAQ", "COGUANOR",
    "superintendencia", "catastro", "registro", "propiedad",
    "escritura", "título", "dominio", "posesión",
    "usufructo", "servidumbre", "paso", "vista",
    "licencia", "construcción", "permiso", "aprobación",
    "autorización", "supervisión", "inspección", "fiscal",
    "recepción", "definitiva", "provisional", "entrega",
    "contratista", "constructor", "maestro", "obra",
    "albañil", "carpintero", "herrero", "plomero",
    "electricista", "pintor", "yesero", "soldador",
    "topógrafo", "nivelador", "estadalero", "cadenaero",
    "peón", "ayudante", "oficial", "aprendiz",
    "presupuesto", "cotización", "estimación", "costo",
    "precio", "unitario", "cuantificación", "metraje",
    "volumen", "obra", "partida", "renglón", "especificación",
    "cronograma", "programa", "planificación", "ruta",
    "crítica", "CPM", "PERT", "Gantt", "diagrama",
    "avance", "físico", "financiero", "flujo", "caja",
    "dirección", "administración", "gerencia", "coordinación",
    "control", "calidad", "seguridad", "salud", "ocupacional",
    "riesgo", "accidente", "prevención", "protección",
    "EPP", "equipo", "individual", "colectiva",
    "señalización", "delimitación", "barreras", "andamio",
    "encofrado", "apuntalamiento", "excavación", "demolición",
    "residuo", "escombro", "desperdicio", "gestión",
    "movimiento", "tierra", "corte", "relleno", "compactación",
    "nivelación", "terracería", "explanación", "talud",
]

# Clean roots: remove accidental bad tokens and short noise
ROOTS = sorted({r for r in ROOTS if len(r) >= 3 and " " not in r})

MARKERS = (
    "arquitect", "urban", "construc", "edifici", "estructur",
    "cimentacion", "columna", "viga", "muro", "concreto",
    "acero", "refuerzo", "encofrad", "voladizo", "cubierta",
    "fachada", "ventana", "puerta", "arco", "boveda",
    "cupula", "escalera", "rampa", "diseño", "planimetria",
    "topografia", "planta", "alzado", "seccion", "perspectiva",
    "axonometria", "isometric", "modulo", "proporcion",
    "simetria", "iluminacion", "ventilacion", "climatizacion",
    "aislamiento", "termico", "acustico", "impermeabilizacion",
    "drenaje", "instalacion", "electrica", "hidraulica",
    "domotica", "sostenible", "bioclimatic", "renovable",
    "fotovoltaic", "solar", "reciclaje", "eficiencia",
    "madera", "adobe", "piedra", "ladrillo", "vidrio",
    "ceramica", "azulejo", "baldosa", "metal", "aluminio",
    "pintura", "revestimiento", "yeso", "estuco",
    "urbanismo", "ordenamiento", "zonificacion", "suelo",
    "densidad", "vialidad", "peatonal", "ciclovia",
    "acera", "plaza", "parque", "espacio", "publico",
    "equipamiento", "mobiliario", "luminaria", "alumbrado",
    "regeneracion", "revitalizacion", "rehabilitacion",
    "restauracion", "conservacion", "patrimonio", "historico",
    "colonial", "prehispanico", "vernaculo", "sismorresistente",
    "antisismico", "sismica", "ductilidad", "amortiguamiento",
    "normativa", "codigo", "reglamento", "NEC", "ACI",
    "AGIES", "COPRAQ", "catastro", "licencia", "permiso",
    "supervision", "inspeccion", "contratista", "constructor",
    "albañil", "presupuesto", "cotizacion", "cronograma",
    "planificacion", "CPM", "PERT", "Gantt",
    "administracion", "calidad", "seguridad", "riesgo",
    "excavacion", "demolicion", "residuo", "escombro",
)

SEED = r"""
arquitectura arquitect\u00f3nico urbana urbanismo construcci\u00f3n
edifici edificaci\u00f3n estructura cimentaci\u00f3n fundaci\u00f3n
hormig\u00f3n concreto columna viga muro mampostería
voladizo balc\u00f3n terraza azotea cubierta tejado
lucernario claraboya ventana puerta vest\u00edbulo atrio
p\u00f3rtico columnata arquer\u00eda arco b\u00f3veda c\u00fapula
arquitrabe capitel front\u00f3n t\u00edmpano \u00e1bside crucero
escalera peld\u00e1\u00f1o barandal rampa accesibilidad
dise\u00f1o planimetr\u00eda topograf\u00eda levantamiento
planta alzado secci\u00f3n fachada perspectiva
axonometr\u00eda isom\u00e9trica acotaci\u00f3n modulaci\u00f3n
m\u00f3dulo proporci\u00f3n \u00e1ureo simetr\u00eda asimetr\u00eda
composici\u00f3n jerarqu\u00eda \u00e9nfasis iluminaci\u00f3n
ventilaci\u00f3n renovaci\u00f3n climatizaci\u00f3n
calefacci\u00f3n refrigeraci\u00f3n t\u00e9rmica aislamiento
ac\u00fastico impermeabilizaci\u00f3n hidrofugaci\u00f3n
condensaci\u00f3n filtraci\u00f3n drenaje desag\u00fce
alcantarillado instalaci\u00f3n el\u00e9ctrica hidr\u00e1ulica
dom\u00f3tica automatizaci\u00f3n sostenible sustentable
ecol\u00f3gico bioclim\u00e1tico bioclim\u00e1tica renovable
fotovoltaico e\u00f3lico eficiencia energ\u00e9tica
certificaci\u00f3n LEED BREEAM an\u00e1lisis ciclo vida
cer\u00e1mica baldosa porcelanato vidrio l\u00e1mina
ordenamiento territorial zonificaci\u00f3n uso suelo
densidad edificabilidad ocupaci\u00f3n construcci\u00f3n
alineaci\u00f3n vialidad circulaci\u00f3n tr\u00e1nsito
peatonal ciclov\u00eda acera parque jard\u00edn \u00e1rea verde
espacio p\u00fablico equipamiento mobiliario se\u00f1alizaci\u00f3n
sem\u00e1foro luminaria regeneraci\u00f3n revitalizaci\u00f3n
rehabilitaci\u00f3n restauraci\u00f3n conservaci\u00f3n patrimonio
hist\u00f3rico colonial prehisp\u00e1nico vern\u00e1culo
sismorresistente antis\u00edsmico s\u00edsmica ductilidad
amortiguamiento disipaci\u00f3n energ\u00eda p\u00e9ndulo
normativa c\u00f3digo construcci\u00f3n reglamento
especificaci\u00f3n t\u00e9cnica NEC ACI ASTM AGIES COPRAQ
superintendencia catastro escritura t\u00edtulo dominio
licencia construcci\u00f3n supervisi\u00f3n inspecci\u00f3n
contratista alba\u00f1il carpintero top\u00f3grafo
presupuesto cotizaci\u00f3n estimaci\u00f3n metraje
cronograma planificaci\u00f3n CPM PERT Gantt
administraci\u00f3n gerencia coordinaci\u00f3n
control calidad seguridad prevenci\u00f3n protecci\u00f3n
se\u00f1alizaci\u00f3n andamio excavaci\u00f3n demolici\u00f3n
residuo escombro nivelaci\u00f3n compactaci\u00f3n
"""

FORCE = [
    "voladizo", "arquitrabe", "planimetr\u00eda", "axonometr\u00eda",
    "bioclim\u00e1tico", "bioclim\u00e1tica", "sismorresistente",
    "COPRAQ", "arquitectura", "urbanismo", "construcci\u00f3n",
    "cimentaci\u00f3n", "fundaci\u00f3n", "edificaci\u00f3n",
    "mampostería", "balc\u00f3n", "vest\u00edbulo", "p\u00f3rtico",
    "arquer\u00eda", "b\u00f3veda", "c\u00fapula", "front\u00f3n",
    "t\u00edmpano", "\u00e1bside", "peld\u00e1\u00f1o", "dise\u00f1o",
    "topograf\u00eda", "levantamiento", "secci\u00f3n", "isom\u00e9trica",
    "acotaci\u00f3n", "modulaci\u00f3n", "m\u00f3dulo", "proporci\u00f3n",
    "\u00e1ureo", "simetr\u00eda", "asimetr\u00eda", "composici\u00f3n",
    "jerarqu\u00eda", "\u00e9nfasis", "iluminaci\u00f3n", "ventilaci\u00f3n",
    "renovaci\u00f3n", "climatizaci\u00f3n", "calefacci\u00f3n",
    "refrigeraci\u00f3n", "t\u00e9rmica", "ac\u00fastico",
    "impermeabilizaci\u00f3n", "hidrofugaci\u00f3n", "condensaci\u00f3n",
    "filtraci\u00f3n", "desag\u00fce", "instalaci\u00f3n", "el\u00e9ctrica",
    "hidr\u00e1ulica", "dom\u00f3tica", "automatizaci\u00f3n",
    "ecol\u00f3gico", "e\u00f3lico", "energ\u00e9tica", "certificaci\u00f3n",
    "an\u00e1lisis", "cer\u00e1mica", "zonificaci\u00f3n", "alineaci\u00f3n",
    "circulaci\u00f3n", "tr\u00e1nsito", "ciclov\u00eda", "jard\u00edn",
    "\u00e1rea", "p\u00fablico", "se\u00f1alizaci\u00f3n", "sem\u00e1foro",
    "regeneraci\u00f3n", "revitalizaci\u00f3n", "rehabilitaci\u00f3n",
    "restauraci\u00f3n", "conservaci\u00f3n", "hist\u00f3rico",
    "prehisp\u00e1nico", "vern\u00e1culo", "antis\u00edsmico",
    "s\u00edsmica", "disipaci\u00f3n", "energ\u00eda", "p\u00e9ndulo",
    "c\u00f3digo", "especificaci\u00f3n", "t\u00e9cnica", "t\u00edtulo",
    "supervisi\u00f3n", "inspecci\u00f3n", "alba\u00f1il", "top\u00f3grafo",
    "cotizaci\u00f3n", "estimaci\u00f3n", "planificaci\u00f3n",
    "administraci\u00f3n", "coordinaci\u00f3n", "prevenci\u00f3n",
    "protecci\u00f3n", "excavaci\u00f3n", "demolici\u00f3n",
    "nivelaci\u00f3n", "compactaci\u00f3n",
    "LEED", "BREEAM", "NEC", "ACI", "ASTM", "AGIES", "COPRAQ",
    "CPM", "PERT", "Gantt",
]

DENY = {
    "airway", "agenda", "vaccine", "cosas", "hola", "joint", "venture",
    "leasing", "factoring", "clear", "aligners", "tecnico", "imagenes",
    "construccion", "cimentacion", "fundacion", "edificacion",
    "mamposteria", "balcon", "vestibulo", "portico", "arqueria",
    "boveda", "cupula", "fronton", "timpano", "abside",
    "peldaño", "diseño", "topografia", "seccion", "isometrica",
    "acotacion", "modulacion", "modulo", "proporcion",
    "aureo", "simetria", "asimetria", "composicion",
    "jerarquia", "enfasis", "iluminacion", "ventilacion",
    "renovacion", "climatizacion", "calefaccion",
    "refrigeracion", "termica", "acustico",
    "impermeabilizacion", "hidrofugacion", "condensacion",
    "filtracion", "desague", "instalacion", "electrica",
    "hidraulica", "domotica", "automatizacion",
    "ecologico", "eolico", "energetica", "certificacion",
    "analisis", "ceramica", "zonificacion", "alineacion",
    "circulacion", "transito", "ciclovia", "jardin",
    "area", "publico", "señalizacion", "semaforo",
    "regeneracion", "revitalizacion", "rehabilitacion",
    "restauracion", "conservacion", "historico",
    "prehispanico", "vernaculo", "antisisimico",
    "sismica", "disipacion", "energia", "pendulo",
    "codigo", "especificacion", "tecnica", "titulo",
    "supervision", "inspeccion", "albañil", "topografo",
    "cotizacion", "estimacion", "planificacion",
    "administracion", "coordinacion", "prevencion",
    "proteccion", "excavacion", "demolicion",
    "nivelacion", "compactacion",
}

BASES = [
    "arquitect\u00f3nico", "arquitect\u00f3nica", "urban\u00edstico", "urban\u00edstica",
    "estructural", "constructivo", "constructiva", "edilicio", "edilicia",
    "habitacional", "residencial", "comercial", "industrial",
    "patrimonial", "hist\u00f3rico", "hist\u00f3rica", "monumental",
    "sostenible", "sustentable", "bioclim\u00e1tico", "bioclim\u00e1tica",
]


def main() -> int:
    bag: set[str] = set()
    bag |= tokens_from_escaped_block(SEED)
    bag |= morph_combo(ROOTS, bases=BASES, prefixes=COMMON_PREFIXES)
    bag |= harvest_reference(EXT, MARKERS)
    
    # Load wiki terms if available
    wiki_file = EXT / "wiki_arq_terms.txt"
    if wiki_file.exists():
        for line in wiki_file.read_text(encoding="utf-8", errors="replace").splitlines():
            line = line.strip()
            if not line or line.startswith("#"):
                continue
            if valid_word(line):
                bag.add(line)
    
    bag |= gender_number(bag, title_if=lambda w: any(m in w.casefold() for m in ("arquitect", "urban", "construc", "edific")))
    bag = {w for w in bag if valid_word(w)}
    bag, stats = finalize(bag, BASE, force_keep=FORCE, deny=DENY)
    words = write_expanded(OUT, bag, "expanded_arq UA (generado; ortografia prioritaria)")
    print(f"expanded_arq: {len(words)}  stats={stats}")
    for c in ("voladizo", "arquitrabe", "planimetr\u00eda", "COPRAQ", "tecnico", "construccion"):
        print(f"  {c}: {c in bag}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
