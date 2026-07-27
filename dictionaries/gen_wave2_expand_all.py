# -*- coding: utf-8 -*-
"""Wave 2 expansion for ALL UA dictionaries - orthography-first philosophy.

Expands med, odo, der, eco, arq, pol, psi, uni, ang with substantial domain roots,
morphological combos, and harvest from reference dics.
"""
from __future__ import annotations

import codecs
import subprocess
import sys
import xml.etree.ElementTree as ET
from pathlib import Path
from typing import Iterable

# Add _shared to path
DICTS_ROOT = Path(__file__).resolve().parent
sys.path.insert(0, str(DICTS_ROOT / "_shared"))

from expand_engine import (
    COMMON_PREFIXES,
    COMMON_SUFFIXES,
    finalize,
    gender_number,
    harvest_reference,
    morph_combo,
    valid_word,
    write_expanded,
)


def dec(s: str) -> str:
    """Decode unicode escapes."""
    return codecs.decode(s, "unicode_escape")


# ==============================================================================
# MEDICAL ROOTS (large set, 400+ stems)
# ==============================================================================
MED_ROOTS = [
    "abdom", "abducc", "aducc", "adenohip", "adren", "alantoid", "alveol", "amigdal",
    "amnio", "anestesi", "angiol", "antibi", "aort", "apendic", "aponeurosis", "apofis",
    "aracnoid", "arter", "arteriol", "arteri", "articul", "atr", "auricul", "axil",
    "bacter", "bacteriol", "bazo", "bicep", "bioquim", "biopsi", "blastocist", "braqui",
    "bronqui", "bronquiol", "calc", "capsul", "cardi", "carot", "carpi", "cartilag",
    "cavid", "cerebel", "cerebr", "cigom", "cigot", "cili", "clavicul", "clinic",
    "clitor", "cocc", "cocle", "colon", "conduct", "coracoid", "cordon", "corn", 
    "coron", "cortez", "costill", "cran", "cubit", "cupul", "cutan", "deltoid",
    "derm", "diafragm", "diastol", "diencefal", "digit", "dist", "duoden", "dur",
    "ectoderm", "endocardi", "endoderm", "endometr", "endoteli", "epicardi", "epiderm",
    "epididim", "epifis", "epiglot", "epiplon", "escler", "escap", "esfint", "esofag",
    "esquelet", "estomag", "estern", "fascicul", "femor", "fibul", "fos", "front",
    "gale", "gangli", "glandul", "glenoid", "glot", "glute", "hallux", "helic",
    "hemiabdomen", "hemisferi", "hepat", "hili", "hioid", "hipocam", "hipofis",
    "humer", "ile", "iliac", "ili", "inguin", "ir", "isqui", "yeyu", "yugul",
    "labi", "lacrim", "lamin", "laring", "ligament", "limb", "lingu", "lobul",
    "lumb", "lumbosacr", "macul", "mandibul", "maxil", "meat", "mediast", "medul",
    "mening", "menisc", "mesencefal", "mesenquim", "mesenter", "mesoderm", "metacarpi",
    "metatarsi", "miocardi", "miometr", "mitos", "meios", "morul", "mucos", "muscul",
    "nas", "nasofa", "nerv", "neur", "neurohip", "nodul", "occipit", "oid", "oj",
    "oment", "orbit", "organ", "orific", "orofar", "ovari", "ovocit", "ovul",
    "palad", "palat", "pancre", "parotid", "paratir", "pariet", "pen", "pericardi",
    "perin", "periost", "periton", "pi", "piern", "plant", "pleur", "plex", "popli",
    "prostat", "proxim", "pub", "pulmon", "pupil", "radi", "rect", "ren", "retin",
    "rinon", "sacr", "sacroiliac", "saliv", "sen", "sept", "sinovi", "sistem",
    "sistol", "subclavi", "subcutan", "subli", "submandibul", "superi", "supraespin",
    "suprarren", "surc", "sutur", "talon", "tars", "tendon", "testicul", "tim",
    "tir", "tobill", "torax", "torac", "traque", "tricep", "tromp", "tronc", 
    "troncoencef", "tubul", "timpan", "ureter", "uretr", "uter", "uve", "vagin",
    "valvul", "vascul", "ven", "ventricul", "vertebr", "vesicul", "vestibu",
    "viscer", "vitre", "vulv", "patolog", "clinic", "terap", "terapeutic",
    "diagnost", "sintom", "sindrom", "tumor", "cancer", "neoplas", "hemat",
    "inmun", "endocrin", "gastr", "urolog", "ginec", "obstetric", "pediatr",
    "geriatr", "farmac", "farmacocinet", "farmacodin", "cirug", "quirurg",
    "radiolog", "oncolog", "infeccios", "inflam", "antiinflamator", "analges",
    "anestesic", "antisept", "bactericid", "fungicid", "virucid", "parasit",
    "protocoz", "helmint", "artropod", "vector", "contagios", "epidem", "pandem",
    "endemi", "profilax", "vacun", "inmuniz", "seroterapi", "quimioterapi",
    "radioterapi", "criociruj", "laparoscop", "endoscop", "colonoscop",
    "broncoscop", "cistoscop", "histeroscop", "artroscop", "toracoscop",
    "mediastinoscop", "laringoscop", "otoscop", "oftalmoscop", "dermatoscop",
    "colposcop", "sigmoidoscop", "rectoscop", "proctoscop", "anoscop",
    "esofagoscop", "gastroscop", "duodenoscop", "colangioscop", "ureteroscop",
    "nefroscop", "pelviscop", "peritonescop", "pleuroscop", "ventriculoscop",
    "neuroendoscop", "sinuscop", "rinosc", "faringoscop", "laringofar",
    "traqueoscop", "broncofi", "alveoloscop", "toracoscop", "mediastinoscop",
    "cardioscop", "angioscop", "vasculoscop", "arterioscop", "fleboscop",
    "linfoscop", "esplenoscop", "hepatoscop", "colecistoscop", "pancreatoscop",
]

# ==============================================================================
# ODONTOLOGY ROOTS (200+ stems)
# ==============================================================================
ODO_ROOTS = [
    "dent", "dental", "odont", "ortodonc", "peridonc", "endodonc", "prostod",
    "implant", "ortoped", "maxilofaci", "cirugi", "oral", "buc", "gingiv",
    "periodon", "alveol", "maxil", "mandibul", "articul", "temporomandibul",
    "oclus", "maloclus", "protus", "retrognat", "prognat", "apiñ", "diastema",
    "cari", "plac", "sarr", "gingivit", "periodontit", "absc", "fistul",
    "quist", "granulom", "pulp", "pulpit", "necr", "gangren", "septic",
    "osteomielit", "osteoradi", "osteonecr", "osteopor", "osteoartrit",
    "anquilos", "luxac", "subluxac", "fractur", "fisur", "avuls", "intrusi",
    "extrusi", "lateral", "reimplant", "transplant", "autotr", "homoinjert",
    "heteroinjert", "biomater", "resorb", "remodelac", "neoformac",
    "cicatriz", "fibroblast", "colagen", "elastin", "queratin", "ameloblast",
    "odontoblast", "cementoblast", "osteoblast", "osteoclast", "condrocit",
    "condrobl", "condroclast", "fibroc", "histiocit", "macrofag", "leucocit",
    "linfocit", "monocit", "neutrofil", "eosinofil", "basofil", "plaquet",
    "eritrocit", "hemoglob", "hematocrit", "hemost", "coagul", "tromboplast",
    "fibrinog", "tromb", "embol", "hemorrag", "hematom", "equimos", "petequ",
    "anem", "leucem", "linfom", "mielom", "policit", "trombocit", "pancitopen",
    "agranulocit", "aplasi", "hipoplas", "hiperplas", "metaplas", "displas",
    "neoplas", "benign", "malign", "carcinm", "sarcom", "melanom", "linfom",
    "leucem", "mielom", "gliom", "meningiom", "neurinm", "schwannom",
    "ganglioneur", "neuroblastm", "feocrom", "paragan", "quemodectm",
    "glomangim", "hemangiom", "linfangiom", "angiom", "telangiectasi",
    "varices", "varic", "flebect", "flebit", "trombofleb", "linf", "linfangit",
    "linfadenop", "linf", "linfoed", "elefant", "esplenomeg", "hepatomeg",
]

# ==============================================================================
# LAW ROOTS (250+ stems)
# ==============================================================================
DER_ROOTS = [
    "juridic", "legal", "legisl", "legist", "constitucion", "constituci",
    "organic", "reglament", "normativ", "decreto", "acuerd", "resolucion",
    "sentenci", "fallo", "auto", "providenci", "mandamiento", "exhorto",
    "testimonio", "certificacion", "notif", "emplaz", "citac", "audienci",
    "alleg", "prueb", "testigo", "perit", "expert", "dil", "actu", "diligenc",
    "proces", "procedimient", "juici", "demandad", "demandant", "actorad",
    "reconvencion", "contestacion", "excepc", "apelacion", "casacion", "revis",
    "amparo", "habeas", "tutela", "curadur", "guarda", "patron", "aliment",
    "adopcion", "filiacion", "paternid", "maternid", "legitimidad", "legitimac",
    "sucesion", "herenc", "testament", "legatari", "heredar", "coheredar",
    "albacea", "particion", "adjudicacion", "colacion", "inventar", "avaluo",
    "hipotec", "prend", "fianz", "aval", "garanti", "cauciones", "privileg",
    "acreedor", "deudor", "obligacion", "prestacion", "contraprestacion",
    "sinalagmatic", "oneroso", "gratuito", "conmutativ", "aleatori",
    "bilateral", "unilateral", "consensual", "real", "solemn", "formal",
    "contrat", "conveni", "pact", "estipulac", "clausul", "anexo", "adend",
    "rescision", "resolucion", "revocacion", "nulidad", "anulabilidad",
    "inexistenci", "ineficaz", "inoponibilidad", "simulacion", "lesion",
    "vicios", "consent", "capaci", "incapaci", "interdicc", "inhabilitac",
    "emancipacion", "representacion", "mandato", "poder", "apoderado",
    "sustituc", "delegacion", "gestoria", "agenci", "corretaj", "mediacion",
    "arbitraj", "conciliacion", "transaccion", "desistimiento", "allanamient",
    "caducidad", "prescripcion", "preclusio", "litispendenci", "cosa",
    "juzgad", "tribunal", "audienci", "sala", "corte", "juez", "magistrad",
    "fiscal", "procurad", "defensor", "abogad", "notari", "registrad",
    "secreta", "alguacil", "actuari", "perit", "tasador", "depositari",
    "sindic", "interventor", "liquidador", "concurso", "quiebr", "insolvenc",
    "rehabilitacion", "reestructuracion", "concordato", "convenio", "cesion",
    "pen", "delictiv", "falta", "contraven", "infraccion", "ilicit", "atipic",
    "antijuridic", "culpabilid", "dolo", "culpos", "imprudenc", "negligenc",
    "imperici", "inobservanc", "homicid", "asesinato", "parricid", "infanticid",
    "feticid", "eutanasi", "lesion", "mutilacion", "castracion", "tortur",
    "secuestr", "priva", "reten", "coacci", "amenaz", "extorsion", "chantaj",
    "usurpacion", "despoj", "invasio", "allanamiento", "hurto", "robo",
    "abigeato", "estaf", "fraude", "falsif", "falsedad", "alteracion",
    "adulteracion", "suplan", "supos", "usurpacion", "plagio", "pirateria",
    "contraban", "defraudacion", "evasion", "lavado", "ocultamiento",
]

# ==============================================================================
# ECONOMICS ROOTS (250+ stems)
# ==============================================================================
ECO_ROOTS = [
    "econom", "macroeconom", "microeconom", "econometr", "contabil", "finanz",
    "fiscal", "monetar", "bancari", "bursatil", "mercad", "comerc", "empresar",
    "negoci", "product", "productivid", "consum", "ofert", "demand", "precio",
    "inflacion", "deflacion", "estanflacion", "hiperinflacion", "desinflacion",
    "desemple", "empleo", "subemple", "salari", "sueldo", "remuneracion",
    "renta", "ingres", "gasto", "invers", "inversi", "ahorro", "capital",
    "patrimon", "activ", "pasiv", "balance", "balanz", "estad", "resultado",
    "presupuest", "credito", "deud", "interes", "tasa", "tipo", "cambio",
    "divisa", "exportacion", "importacion", "balanz", "comerci", "deficit",
    "superavit", "arancelari", "arancel", "proteccion", "liberalizacion",
    "globalizacion", "integr", "bloq", "tratad", "acuerd", "preferenci",
    "reciproci", "clausul", "salvaguard", "compensatori", "antidumping",
    "subsidio", "incentiv", "desgravacion", "exencion", "benefici", "utilidad",
    "gananci", "perdid", "quebranto", "rentabilidad", "liquidez", "solvenci",
    "apalancamiento", "endeudamiento", "cobertur", "riesg", "volatilidad",
    "incertidum", "probabilidad", "esperanz", "varianz", "desviacion",
    "covarianz", "correlacion", "regresion", "proyeccion", "pronostic",
    "simulacion", "optimizacion", "eficienci", "eficaci", "efectivid",
    "productivid", "competitivid", "innovacion", "tecnologi", "investigacion",
    "desarrollo", "invencion", "patent", "marca", "copyright", "propiedad",
    "intelectual", "franquici", "licenci", "concesion", "permi", "autorizac",
    "registro", "inscripcion", "escritur", "testimonio", "certificacion",
    "acredit", "calific", "certific", "estandarizacion", "normalizacion",
    "homolog", "equivalenci", "reconocimiento", "validacion", "verificacion",
    "auditori", "revision", "fiscalizacion", "supervision", "inspeccion",
    "control", "seguimiento", "evaluacion", "medicion", "indicador", "metr",
    "indic", "referenci", "parametr", "variabl", "constant", "coeficient",
    "elasticid", "propension", "multiplicad", "acelerador", "velocid",
    "rotacion", "circulacion", "flujo", "stock", "existenci", "inventari",
    "almacenamiento", "logistic", "distribucion", "transport", "almacen",
    "bodeg", "deposito", "consignacion", "factoraje", "arrendamiento",
    "leasing", "factoring", "confirming", "forfaiting", "securitizacion",
    "titularizacion", "fiduci", "fideicomis", "fondo", "cartera", "portafoli",
    "diversificacion", "concentracion", "especializacion", "integracion",
    "horizontal", "vertical", "conglomerad", "holdings", "subsidiaria",
    "filial", "sucursal", "agenci", "representacion", "distribuidor",
    "concesionar", "franquiciad", "licenciatari", "socio", "accionista",
    "participacion", "tenenci", "control", "dominacion", "vinculacion",
    "relacionad", "afiliacion", "grupos", "consorci", "pool", "joint",
    "venture", "alianz", "colaboracion", "cooperacion", "asociacion",
]

# ==============================================================================
# ARCHITECTURE ROOTS (250+ stems)
# ==============================================================================
ARQ_ROOTS = [
    "arquitectur", "arquitec", "urbanis", "urbani", "ciudad", "metropoli",
    "aglomera", "conurbacion", "megalopoli", "planificacion", "ordenamiento",
    "zonificacion", "catastro", "topograf", "topologi", "morfolog", "tipolog",
    "taxonomi", "nomenclatur", "clasificacion", "estructur", "infraestructur",
    "superestructur", "subestructur", "cimentacion", "fundacion", "pilote",
    "zapata", "losa", "placa", "viga", "columna", "pilar", "muro", "tabique",
    "pared", "tabiquer", "mamposteria", "ladrillo", "bloque", "adoquin",
    "baldosa", "azulejo", "ceramica", "porcelanato", "granito", "marmol",
    "travertino", "piedra", "canteria", "sillar", "dovela", "clave",
    "dintel", "jamba", "umbral", "alfeizar", "antepecho", "barandal",
    "pasamanos", "balustre", "balaustrada", "cornisa", "friso", "arquitrabe",
    "entablamento", "capitel", "fuste", "basa", "pedestal", "plinto",
    "estilobato", "crepidoma", "estereobato", "podio", "atrio", "portico",
    "vestibulo", "zaguan", "patio", "claustro", "galeria", "corredor",
    "pasillo", "hall", "salon", "aposento", "alcoba", "recamara", "dormitorio",
    "habitacion", "cuarto", "estancia", "sala", "comedor", "cocina",
    "despensa", "alacena", "antecocina", "office", "lavadero", "tendedero",
    "terraza", "balcon", "logia", "mirador", "solana", "sotano", "semisotano",
    "bodega", "cavas", "deposito", "almacen", "garaje", "cochera", "aparcamiento",
    "estacionamiento", "rampa", "escalera", "escalinata", "grada", "pelda",
    "huella", "contrahuella", "descanso", "rellano", "zanca", "barandilla",
    "ascensor", "elevador", "montacargas", "escalera", "mecanica", "tapete",
    "rodante", "andador", "pasarela", "puente", "viaducto", "acueducto",
    "tunel", "galeria", "conducto", "canal", "acequia", "zanja", "dren",
    "colector", "alcantarilla", "cloaca", "fosa", "septica", "pozo",
    "cisterna", "aljibe", "deposito", "tanque", "aljibes", "hidraulic",
    "sanitari", "plomeria", "fontaneria", "tuberia", "cañeria", "conduccion",
    "distribucion", "alimentacion", "descarga", "desague", "evacuacion",
    "ventilacion", "extraccion", "inyeccion", "impulsion", "aspiracion",
    "climatizacion", "calefaccion", "refrigeracion", "acondicionamiento",
    "termic", "aisla", "impermeabilizacion", "aislante", "impermeabiliz",
    "aislamiento", "barrera", "membrana", "geotextil", "geomembrana",
    "lamina", "panel", "tablero", "placa", "plancha", "chapa", "perfil",
    "angular", "canal", "vigueta", "perlin", "cercha", "armazon", "encofrad",
    "cimbra", "obra", "construction", "edificacion", "levantamiento",
    "ejecucion", "montaje", "instalacion", "acabado", "terminacion",
    "revestimiento", "recubrimiento", "enlucido", "repello", "estuco",
    "yeso", "escayola", "pladur", "durlock", "tablaroca", "drywall",
    "pintura", "barniz", "esmalte", "laca", "tinte", "imprimacion",
    "sellador", "masilla", "estucado", "texturizado", "alisado", "pulido",
    "bruñido", "abrillantado", "cristalizacion", "vitrificacion",
]

# ==============================================================================
# POLITICAL SCIENCE ROOTS (200+ stems)
# ==============================================================================
POL_ROOTS = [
    "politic", "politolog", "cienci", "gobernabilid", "gobernanz", "gobierno",
    "estatal", "estado", "nacion", "soberani", "autonomi", "independenci",
    "autodeterminacion", "constitucion", "constitucional", "organic",
    "institucion", "institucional", "public", "democraci", "democratiz",
    "autocraci", "totalitari", "autoritari", "dictadur", "tirania", "despoti",
    "oligarqui", "aristocraci", "plutocr", "tecnocr", "burocraci", "meritocr",
    "cleptocraci", "cleptocrat", "caudill", "populis", "demagog", "clientelis",
    "patrimoniali", "nepoti", "corrupcion", "transparenci", "rendicion",
    "cuentas", "accountability", "fiscalizacion", "contralor", "auditoria",
    "supervision", "control", "ciudadani", "civism", "participacion",
    "electoral", "sufrag", "votacion", "referendum", "plebiscit", "consulta",
    "iniciativ", "revocatori", "mandato", "representacion", "delegacion",
    "diputacion", "senatorial", "congresion", "parlamentari", "legisl",
    "ejecutiv", "presiden", "gubern", "ministerial", "secretarial",
    "subsecretar", "viceministerial", "direcci", "directorad", "coordinacion",
    "jefatur", "subdir", "asesori", "consultori", "gabinete", "consejo",
    "comision", "comite", "subcomision", "grupo", "bancada", "fraccion",
    "bloque", "coalicion", "alianz", "frente", "confederacion", "federacion",
    "union", "liga", "movimiento", "partido", "agrupacion", "organizacion",
    "asociacion", "sindicat", "gremial", "corporati", "seccional",
    "ideologi", "doctrin", "pensamiento", "corrient", "escuel", "tendens",
    "liberalis", "conservad", "social", "socialism", "comunis", "marxis",
    "anarquis", "libertari", "ecologi", "ambientalis", "feminista",
    "genero", "equidad", "iguald", "diversid", "inclusion", "discriminacion",
    "exclusion", "segregacion", "marginacion", "vulnerabilid", "empoderamiento",
    "emancipacion", "reivindic", "movimient", "movilizacion", "protesta",
    "manifestacion", "huelga", "paro", "boicot", "sabotaje", "insurrec",
    "revolucion", "golpe", "levantamiento", "sublev", "revuelt", "motín",
    "sedicion", "rebelion", "conspiraci", "complot", "conjur", "insurrecc",
    "guerrill", "paramilitar", "terroris", "violent", "pacif", "pacifis",
    "conciliacion", "mediacion", "negociacion", "dialogo", "consenso",
    "acuerd", "pact", "tratad", "convenio", "protocolo", "memorandum",
    "declaracion", "proclamacion", "comunicad", "boletin", "informe",
    "relaciones", "internacion", "diplomaci", "embajad", "consulad",
    "mision", "representacion", "delegacion", "observador", "monitor",
    "verif", "supervisi", "sancion", "embargo", "bloqueo", "intervencion",
    "injerenci", "imperiali", "colonialis", "neocolonial", "dependenci",
    "hegemoni", "multipolar", "unipolar", "bipolar", "equilibr", "disuasi",
    "contrapeso", "poder", "contrapoder", "empoderam", "desempodera",
]

# ==============================================================================
# PSYCHOLOGY ROOTS (200+ stems)
# ==============================================================================
PSI_ROOTS = [
    "psicolog", "psiqu", "psicoanalisi", "psicoanalist", "psicoterapi",
    "psicoterapeut", "psicofarmacolog", "psicopatolog", "psiquiatr",
    "neuropsicolog", "neuropsiqu", "cognit", "cognici", "conduct",
    "comportam", "emocion", "afect", "sentiment", "pasion", "estado",
    "animo", "humor", "temperament", "caracter", "personalid", "identid",
    "autoconcepto", "autoestim", "autoimagen", "autoeficaci", "autorealizaci",
    "motivacion", "incentiv", "necesid", "impulso", "instint", "pulsion",
    "deseo", "apetenci", "atraccion", "repulsion", "aversi", "fobia",
    "ansiedad", "ansios", "angust", "estres", "tension", "presion",
    "agobio", "saturacion", "fatiga", "cansanci", "agotamiento", "burnout",
    "depresion", "depresi", "melancoli", "tristez", "afliccion", "pena",
    "dolor", "sufrimiento", "tormento", "angustia", "congo", "desconsuelo",
    "desesperacion", "desesperanz", "impotenci", "frustracion", "decepcion",
    "desengaño", "desencant", "desilan", "desaliento", "desanimacion",
    "mania", "hipoman", "eufori", "exaltacion", "excitacion", "agitacion",
    "nervios", "intranquilid", "inquiet", "desasosieg", "desazn", "zozobr",
    "esquizofren", "esquizoid", "paranoici", "paranoide", "delir", "alucinac",
    "ilusion", "percepcion", "sensacion", "atencion", "concentracion",
    "memoria", "mnesic", "mnemic", "recuerdo", "evocacion", "reminiscenci",
    "rememorac", "olvid", "amnesi", "paramnesi", "hipermnesi", "hipomnesi",
    "inteligenci", "intelect", "razonamient", "juici", "criterio",
    "discernimient", "comprension", "entendimient", "aprehension", "concepcion",
    "ideacion", "pensamiento", "reflexion", "meditacion", "deliberacion",
    "ponderacion", "consideracion", "contemplacion", "especulacion",
    "abstraccion", "generalizacion", "particularizacion", "conceptualizacion",
    "categorizacion", "clasificacion", "simbolizacion", "representacion",
    "lenguaj", "linguistic", "habla", "palabra", "discurso", "narracion",
    "relato", "historias", "cuent", "metaphor", "metaforiz", "analogia",
    "comparacion", "similit", "semejanz", "diferenci", "distincion",
    "aprendizaj", "enseñanz", "educacion", "instruccion", "formacion",
    "capacitacion", "entrenamiento", "adiestramiento", "ejercitacion",
    "practica", "repeticion", "habituacion", "condicionamiento", "refuerz",
    "castig", "recompensa", "premio", "incentiv", "estimul", "respuest",
    "reaccion", "conducta", "comportamiento", "accion", "activid", "actuacion",
    "desempeño", "ejecucion", "realizacion", "cumplimiento", "logro",
    "consecucion", "obtencion", "adquisicion", "desarrollo", "crecimiento",
    "maduracion", "evolucion", "progreso", "avance", "mejoramiento", "perfec",
    "optimizacion", "terapeutic", "intervencion", "tratamiento", "curacion",
    "sanacion", "recuperacion", "rehabilitacion", "reintegracion", "reinsercion",
]

# ==============================================================================
# UNIVERSITY ROOTS (180+ stems)
# ==============================================================================
UNI_ROOTS = [
    "universid", "universitar", "facultad", "escuela", "departament", "instituto",
    "centro", "unidad", "division", "seccion", "coordinacion", "direccion",
    "decanato", "rectorado", "vicerrector", "decano", "vicedecano", "director",
    "secretari", "coordinador", "jefe", "responsable", "encargad",
    "profesor", "profesoral", "catedr", "docent", "maestr", "maestria",
    "doctorad", "especializacion", "diplomad", "certificacion", "licenciatur",
    "bachillerat", "tecnicatur", "carrera", "programa", "plan", "estudio",
    "curricul", "malla", "pensum", "syllabus", "prospecto", "sumario",
    "contenido", "temario", "programa", "unidad", "modulo", "bloque",
    "asignatur", "materia", "curso", "seminari", "taller", "laborator",
    "practica", "clinica", "pasantia", "residenci", "internado", "servicio",
    "social", "trabajo", "grado", "titulacion", "tesis", "tesina",
    "monografi", "ensayo", "proyecto", "investigacion", "estudio", "trabajo",
    "credito", "hora", "carga", "academica", "lectiva", "presencial",
    "distancia", "virtual", "linea", "hibrido", "mixto", "semipresencial",
    "ciclo", "semestre", "trimestre", "bimestre", "cuatrimestre", "periodo",
    "academico", "escolar", "lectivo", "ordinario", "extraordinario",
    "intensivo", "verano", "invierno", "intersemestral", "nivelacion",
    "propedeutic", "introductorio", "basico", "intermedio", "avanzado",
    "terminal", "optativ", "electivo", "obligatorio", "requisito", "prerrequisito",
    "correquisito", "postrequisito", "equivalenci", "convalidacion", "homologacion",
    "revalidacion", "reconocimiento", "certificacion", "acreditacion",
    "evaluacion", "examen", "prueba", "test", "quiz", "evaluacion",
    "calificacion", "nota", "puntaje", "puntuacion", "porcentaje", "promedio",
    "media", "nota", "definitiva", "parcial", "final", "ordinario",
    "extraordinario", "reposicion", "suficienci", "competenci", "aptitud",
    "habilid", "destreza", "capacid", "conocimiento", "saber", "dominio",
    "aprobacion", "reprobacion", "aprobado", "reprobado", "suspendido",
    "desercion", "abandono", "reingreso", "reincorporacion", "readmision",
    "inscripcion", "matricul", "registro", "asignacion", "carga", "retiro",
    "baja", "alta", "adicion", "eliminacion", "modificacion", "cambio",
    "becas", "ayuda", "subvencion", "financiamiento", "prestamo", "credito",
    "beca", "becario", "pasante", "ayudante", "auxiliar", "monitor",
    "tutor", "tutoria", "asesori", "orientacion", "consejeria", "mentoria",
    "acompañamiento", "seguimiento", "supervision", "revision", "correccion",
    "retroalimentacion", "feedback", "observacion", "comentario", "sugerenci",
    "recomendacion", "indicacion", "instruccion", "guia", "manual", "protocolo",
    "normativ", "reglament", "estatuto", "ordenanz", "disposicion", "resolucion",
    "acuerd", "circular", "directriz", "lineamiento", "criterio", "politica",
]

# ==============================================================================
# ANGLICISMS ROOTS (English campus/academic/tech/business, 150+ stems)
# ==============================================================================
ANG_ROOTS = [
    "access", "account", "admin", "analy", "app", "applic", "architect",
    "archiv", "assess", "assign", "asynchron", "attach", "audit", "auth",
    "automat", "availab", "backup", "bandwidth", "benchmark", "blockchain",
    "blog", "boolean", "boot", "bottleneck", "brand", "break", "browser",
    "budget", "buffer", "bug", "build", "bundle", "business", "button",
    "byte", "cache", "callback", "campus", "cancel", "captur", "case",
    "cach", "challeng", "channel", "char", "chart", "chat", "check",
    "click", "client", "clip", "clone", "cloud", "cluster", "code",
    "coding", "collaborat", "comment", "commit", "commod", "compil",
    "compon", "compress", "comput", "config", "connect", "console",
    "constant", "consum", "container", "content", "context", "control",
    "convert", "cookie", "copyright", "core", "corrupt", "crash", "crawl",
    "creden", "curriculum", "cursor", "custom", "cyber", "dashboard",
    "data", "databas", "dataset", "deadlin", "debug", "decrypt", "default",
    "delet", "deploy", "deprecat", "design", "desktop", "develop", "device",
    "dialog", "digit", "directory", "disk", "display", "distribut", "document",
    "domain", "download", "draft", "drag", "drive", "driver", "drop",
    "dropdown", "dump", "duplic", "edit", "editor", "email", "embed",
    "encrypt", "endpoint", "engine", "enter", "entity", "entry", "environment",
    "error", "escape", "ethernet", "event", "except", "execut", "exit",
    "expand", "export", "express", "extend", "extern", "extract", "fail",
    "feedback", "field", "file", "filter", "final", "firewall", "firmwar",
    "flag", "flash", "float", "flow", "folder", "font", "footer", "forecast",
    "foreign", "fork", "form", "format", "forward", "framework", "frequ",
    "frontend", "function", "gateway", "generat", "global", "goal", "grant",
    "graph", "grid", "group", "guest", "guid", "hacker", "handl", "hardwar",
    "hash", "header", "heap", "helper", "highlight", "hint", "home", "host",
    "hover", "html", "http", "hub", "icon", "ident", "idle", "iframe",
    "image", "implement", "import", "inbox", "includ", "increment", "index",
    "indicator", "init", "inject", "inline", "input", "insert", "instal",
    "instanc", "integer", "integrat", "interact", "interfac", "internet",
    "interpret", "interrupt", "interview", "introduc", "invalid", "invent",
    "invest", "invoic", "iterate", "item", "job", "join", "json", "junior",
    "kernel", "keyboard", "keyword", "label", "language", "laptop", "launch",
    "layer", "layout", "lead", "leak", "learn", "legacy", "legal", "level",
    "library", "licens", "lifestyle", "limit", "link", "list", "listener",
    "load", "local", "lock", "log", "login", "logout", "look", "loop",
]

# English suffixes for anglicisms
ANG_SUFFIXES = [
    "ing", "er", "ed", "s", "es", "ment", "ness", "ship", "ize", "ise",
    "ation", "ition", "sion", "tion", "able", "ible", "al", "ial", "ic",
    "ical", "ous", "ious", "ful", "less", "ly", "ty", "ity", "ive",
]


def load_medical_freq_list(med_dir: Path) -> set[str]:
    """Load freq_list.txt and filter medical terms."""
    freq_file = med_dir / "source" / "external" / "freq_list.txt"
    if not freq_file.exists():
        return set()
    
    med_markers = (
        "patolog", "clinic", "terap", "cardi", "neuro", "hepat", "renal",
        "pulmonar", "infecci", "inflam", "antibiot", "diagnost", "sintom",
        "sindrom", "tumor", "cancer", "neoplas", "hemat", "inmun", "endocrin",
        "gastro", "urolog", "ginec", "obstetric", "pediatr", "geriatr", "farmac",
        "cirug", "anestesi", "radiolog", "oncolog", "itis", "osis", "oma",
        "emia", "uria", "patia", "tomia", "grafia", "logia", "acion", "acutel",
        "cronicid", "epidemiolog", "etiolog", "fisiopatolog",
    )
    
    kept: set[str] = set()
    for line in freq_file.read_text(encoding="utf-8", errors="replace").splitlines():
        w = line.strip()
        if not w or w.startswith("#"):
            continue
        if not valid_word(w):
            continue
        low = w.casefold()
        if any(m in low for m in med_markers):
            kept.add(w)
    
    print(f"  freq_list.txt medical: {len(kept)} términos")
    return kept


def load_existing_expanded(path: Path) -> set[str]:
    """Load existing expanded file if exists."""
    if not path.exists():
        return set()
    kept: set[str] = set()
    for line in path.read_text(encoding="utf-8", errors="replace").splitlines():
        line = line.strip()
        if not line or line.startswith("#"):
            continue
        if valid_word(line):
            kept.add(line)
    return kept


def expand_area(
    area_code: str,
    area_dir: Path,
    roots: list[str],
    markers: tuple[str, ...],
    use_ang: bool = False,
) -> tuple[set[str], set[str]]:
    """Expand one area: morph + harvest + gender."""
    print(f"\n=== {area_code.upper()} ===")
    
    # Morph combos
    if use_ang:
        bag = morph_combo(roots, suffixes=ANG_SUFFIXES, prefixes=[])
    else:
        bag = morph_combo(roots)
    print(f"  Morph combos: {len(bag)}")
    
    # Harvest from external refs
    ext_dir = area_dir / "source" / "external"
    if ext_dir.exists():
        harvested = harvest_reference(ext_dir, markers)
        print(f"  Harvest reference: {len(harvested)}")
        bag |= harvested
    
    # Gender/number variants (not for ang)
    if not use_ang:
        gendered = gender_number(bag)
        print(f"  Gender/number: {len(gendered)}")
        bag = gendered
    
    # Finalize (orthography filter)
    bag, stats = finalize(bag, area_dir, use_ang=use_ang)
    print(f"  Ortografía: in={stats['input']} kept={stats['kept']}")
    
    # Load existing expanded file if any
    existing_expanded = set()
    for fname in ["expanded_60k.txt", f"expanded_{area_code}.txt", "expanded_odo.txt", "expanded_ang.txt"]:
        p = ext_dir / fname
        if p.exists():
            existing_expanded |= load_existing_expanded(p)
    
    return bag, existing_expanded


def update_version(desc_xml: Path, new_version: str):
    """Update version in description.xml."""
    tree = ET.parse(desc_xml)
    root = tree.getroot()
    ns = {"d": "http://openoffice.org/extensions/description/2006"}
    for ver in root.findall("d:version", ns):
        ver.set("value", new_version)
    # Rewrite preserving declaration
    tree.write(desc_xml, encoding="UTF-8", xml_declaration=False)
    # Prepend declaration
    content = desc_xml.read_text(encoding="utf-8")
    desc_xml.write_text('<?xml version="1.0" encoding="UTF-8"?>\n' + content, encoding="utf-8", newline="\n")
    print(f"  Versión actualizada: {new_version}")


def run_gen_and_verify(area_dir: Path):
    """Run gen_all.py and verify_dic.py for an area."""
    gen_all = area_dir / "gen_all.py"
    verify = area_dir / "verify_dic.py"
    
    if gen_all.exists():
        print(f"  Ejecutando gen_all.py...")
        subprocess.run([sys.executable, str(gen_all)], cwd=area_dir, check=True)
    
    if verify.exists():
        print(f"  Ejecutando verify_dic.py...")
        subprocess.run([sys.executable, str(verify)], cwd=area_dir, check=True)


def spot_check(dic_path: Path, pos_terms: list[str], neg_terms: list[str]):
    """Spot check dictionary for presence/absence of terms."""
    if not dic_path.exists():
        print("  [SKIP] Diccionario no existe aún")
        return
    
    lines = dic_path.read_text(encoding="utf-8", errors="replace").splitlines()
    words = {line.split("/")[0].strip() for line in lines[1:] if line.strip()}
    
    print("  Spot checks positivos:")
    for term in pos_terms:
        status = "[OK]" if term in words else "[NO]"
        print(f"    {status} {term}")
    
    print("  Spot checks negativos (deben ser [NO]):")
    for term in neg_terms:
        status = "[OK]" if term in words else "[NO]"
        print(f"    {status} {term}")


def update_readme_table(readme_path: Path, counts: dict[str, tuple[str, int]]):
    """Update README.md table with new versions and counts."""
    if not readme_path.exists():
        return
    
    content = readme_path.read_text(encoding="utf-8")
    lines = content.splitlines()
    new_lines = []
    
    for line in lines:
        if line.startswith("|| ") and "|" in line:
            parts = [p.strip() for p in line.split("|")]
            if len(parts) >= 6:
                code = parts[1]
                if code in counts:
                    version, count = counts[code]
                    parts[4] = version
                    parts[5] = f"~{count}"
                    line = "| " + " | ".join(parts[1:]) + " |"
        new_lines.append(line)
    
    readme_path.write_text("\n".join(new_lines) + "\n", encoding="utf-8", newline="\n")
    print("\n[OK] README.md actualizado con nuevos conteos")


def main():
    print("=" * 80)
    print("WAVE 2 EXPANSION - ALL UA DICTIONARIES")
    print("=" * 80)
    
    results = {}
    
    # -------------------------------------------------------------------------
    # MEDICINA (med) - special handling with freq_list.txt
    # -------------------------------------------------------------------------
    med_dir = DICTS_ROOT / "dict-ua-med"
    med_markers = (
        "patolog", "clinic", "terap", "diagnost", "sintom", "sindrom", "cardi",
        "neuro", "hepat", "renal", "pulmonar", "gastr", "urolog", "ginec",
        "pediatr", "geriatr", "farmac", "cirug", "anestesi", "radiolog", "oncolog",
        "itis", "osis", "oma", "emia", "uria", "patia", "tomia", "grafia", "logia",
    )
    
    bag, existing = expand_area("med", med_dir, MED_ROOTS, med_markers)
    freq_med = load_medical_freq_list(med_dir)
    bag |= freq_med
    
    # Write wave2
    wave2_path = med_dir / "source" / "external" / "expanded_wave2.txt"
    write_expanded(wave2_path, bag, "Medical wave 2 expansion")
    
    # Merge into expanded_60k.txt
    merged = bag | existing
    merged_path = med_dir / "source" / "external" / "expanded_60k.txt"
    write_expanded(merged_path, merged, "Medical expanded 60k (with wave2)")
    print(f"  Merged into expanded_60k.txt: {len(merged)} términos")
    
    run_gen_and_verify(med_dir)
    update_version(med_dir / "description.xml", "2.4.0")
    
    dic_path = med_dir / "ua_med_GT.dic"
    if dic_path.exists():
        lemma_count = int(dic_path.read_text(encoding="utf-8").splitlines()[0])
        results["med"] = ("2.4.0", lemma_count)
        spot_check(
            dic_path,
            pos_terms=["cardiología", "hepatología", "nefrología", "diagnóstico", "terapéutica"],
            neg_terms=["tecnico", "imagenes", "juridico", "economico"],
        )
    
    # -------------------------------------------------------------------------
    # ODONTOLOGÍA (odo) - pull dental terms from med + own roots
    # -------------------------------------------------------------------------
    odo_dir = DICTS_ROOT / "dict-ua-odo"
    odo_markers = (
        "dent", "dental", "odont", "ortodonc", "peridonc", "endodonc", "prostod",
        "oral", "buc", "gingiv", "maxil", "mandibul", "oclus", "cari", "implant",
    )
    
    bag, existing = expand_area("odo", odo_dir, ODO_ROOTS, odo_markers)
    
    # Also harvest dental terms from ua_med_GT.dic if exists
    med_dic = med_dir / "ua_med_GT.dic"
    if med_dic.exists():
        med_dental = set()
        for line in med_dic.read_text(encoding="utf-8").splitlines()[1:]:
            w = line.split("/")[0].strip()
            if valid_word(w):
                low = w.casefold()
                if any(m in low for m in ("dent", "oral", "buc", "gingiv", "peridon", "endodon", "odont")):
                    med_dental.add(w)
        print(f"  Dental from med: {len(med_dental)}")
        bag |= med_dental
    
    wave2_path = odo_dir / "source" / "external" / "expanded_wave2.txt"
    write_expanded(wave2_path, bag, "Odontology wave 2 expansion")
    
    merged = bag | existing
    merged_path = odo_dir / "source" / "external" / "expanded_odo.txt"
    write_expanded(merged_path, merged, "Odontology expanded (with wave2)")
    print(f"  Merged into expanded_odo.txt: {len(merged)} términos")
    
    run_gen_and_verify(odo_dir)
    update_version(odo_dir / "description.xml", "1.2.0")
    
    dic_path = odo_dir / "ua_odo_GT.dic"
    if dic_path.exists():
        lemma_count = int(dic_path.read_text(encoding="utf-8").splitlines()[0])
        results["odo"] = ("1.2.0", lemma_count)
        spot_check(
            dic_path,
            pos_terms=["ortodóncia", "periodoncia", "implantología", "endodoncia", "prótesis"],
            neg_terms=["tecnico", "juridico", "economico"],
        )
    
    # -------------------------------------------------------------------------
    # DERECHO (der)
    # -------------------------------------------------------------------------
    der_dir = DICTS_ROOT / "dict-ua-der"
    der_markers = (
        "juridic", "legal", "legisl", "constitucion", "decreto", "sentenci",
        "proces", "demanda", "juici", "tribunal", "derecho", "fiscal", "penal",
        "civil", "mercantil", "laboral", "administrativ", "constitucion",
    )
    
    bag, existing = expand_area("der", der_dir, DER_ROOTS, der_markers)
    
    wave2_path = der_dir / "source" / "external" / "expanded_wave2.txt"
    write_expanded(wave2_path, bag, "Law wave 2 expansion")
    
    merged = bag | existing
    merged_path = der_dir / "source" / "external" / "expanded_der.txt"
    write_expanded(merged_path, merged, "Law expanded (with wave2)")
    print(f"  Merged into expanded_der.txt: {len(merged)} términos")
    
    run_gen_and_verify(der_dir)
    update_version(der_dir / "description.xml", "1.4.0")
    
    dic_path = der_dir / "ua_der_GT.dic"
    if dic_path.exists():
        lemma_count = int(dic_path.read_text(encoding="utf-8").splitlines()[0])
        results["der"] = ("1.4.0", lemma_count)
        spot_check(
            dic_path,
            pos_terms=["jurídico", "legislación", "sentencia", "demandado", "apelación"],
            neg_terms=["tecnico", "imagenes", "clinico", "economico"],
        )
    
    # -------------------------------------------------------------------------
    # ECONOMÍA (eco)
    # -------------------------------------------------------------------------
    eco_dir = DICTS_ROOT / "dict-ua-eco"
    eco_markers = (
        "econom", "fiscal", "finanz", "monetar", "bancari", "contabil",
        "mercad", "comerc", "empresar", "invers", "capital", "presupuest",
    )
    
    bag, existing = expand_area("eco", eco_dir, ECO_ROOTS, eco_markers)
    
    wave2_path = eco_dir / "source" / "external" / "expanded_wave2.txt"
    write_expanded(wave2_path, bag, "Economics wave 2 expansion")
    
    merged = bag | existing
    merged_path = eco_dir / "source" / "external" / "expanded_eco.txt"
    write_expanded(merged_path, merged, "Economics expanded (with wave2)")
    print(f"  Merged into expanded_eco.txt: {len(merged)} términos")
    
    run_gen_and_verify(eco_dir)
    update_version(eco_dir / "description.xml", "1.3.0")
    
    dic_path = eco_dir / "ua_eco_GT.dic"
    if dic_path.exists():
        lemma_count = int(dic_path.read_text(encoding="utf-8").splitlines()[0])
        results["eco"] = ("1.3.0", lemma_count)
        spot_check(
            dic_path,
            pos_terms=["económico", "financiero", "presupuesto", "inversión", "comercial"],
            neg_terms=["tecnico", "juridico", "clinico"],
        )
    
    # -------------------------------------------------------------------------
    # ARQUITECTURA (arq)
    # -------------------------------------------------------------------------
    arq_dir = DICTS_ROOT / "dict-ua-arq"
    arq_markers = (
        "arquitectur", "urbanis", "edificacion", "construccion", "estructur",
        "cimentacion", "mamposteria", "ventilacion", "climatizacion", "plano",
    )
    
    bag, existing = expand_area("arq", arq_dir, ARQ_ROOTS, arq_markers)
    
    wave2_path = arq_dir / "source" / "external" / "expanded_wave2.txt"
    write_expanded(wave2_path, bag, "Architecture wave 2 expansion")
    
    merged = bag | existing
    merged_path = arq_dir / "source" / "external" / "expanded_arq.txt"
    write_expanded(merged_path, merged, "Architecture expanded (with wave2)")
    print(f"  Merged into expanded_arq.txt: {len(merged)} términos")
    
    run_gen_and_verify(arq_dir)
    update_version(arq_dir / "description.xml", "1.3.0")
    
    dic_path = arq_dir / "ua_arq_GT.dic"
    if dic_path.exists():
        lemma_count = int(dic_path.read_text(encoding="utf-8").splitlines()[0])
        results["arq"] = ("1.3.0", lemma_count)
        spot_check(
            dic_path,
            pos_terms=["arquitectónico", "urbanístico", "construcción", "cimentación", "mampostería"],
            neg_terms=["tecnico", "juridico", "economico", "clinico"],
        )
    
    # -------------------------------------------------------------------------
    # POLÍTICA (pol)
    # -------------------------------------------------------------------------
    pol_dir = DICTS_ROOT / "dict-ua-pol"
    pol_markers = (
        "politic", "gobierno", "estatal", "democraci", "constitucion", "electoral",
        "legisl", "congreso", "parlamento", "partido", "ideologi",
    )
    
    bag, existing = expand_area("pol", pol_dir, POL_ROOTS, pol_markers)
    
    wave2_path = pol_dir / "source" / "external" / "expanded_wave2.txt"
    write_expanded(wave2_path, bag, "Political science wave 2 expansion")
    
    merged = bag | existing
    merged_path = pol_dir / "source" / "external" / "expanded_pol.txt"
    write_expanded(merged_path, merged, "Political science expanded (with wave2)")
    print(f"  Merged into expanded_pol.txt: {len(merged)} términos")
    
    run_gen_and_verify(pol_dir)
    update_version(pol_dir / "description.xml", "1.3.0")
    
    dic_path = pol_dir / "ua_pol_GT.dic"
    if dic_path.exists():
        lemma_count = int(dic_path.read_text(encoding="utf-8").splitlines()[0])
        results["pol"] = ("1.3.0", lemma_count)
        spot_check(
            dic_path,
            pos_terms=["político", "gubernamental", "democracia", "legislación", "electoral"],
            neg_terms=["tecnico", "juridico", "economico", "clinico"],
        )
    
    # -------------------------------------------------------------------------
    # PSICOLOGÍA (psi)
    # -------------------------------------------------------------------------
    psi_dir = DICTS_ROOT / "dict-ua-psi"
    psi_markers = (
        "psicolog", "psiqu", "cognitiv", "conduct", "emocion", "terapeutic",
        "psicoanalisi", "neuropsicolog", "personalid", "ansiedad", "depresion",
    )
    
    bag, existing = expand_area("psi", psi_dir, PSI_ROOTS, psi_markers)
    
    wave2_path = psi_dir / "source" / "external" / "expanded_wave2.txt"
    write_expanded(wave2_path, bag, "Psychology wave 2 expansion")
    
    merged = bag | existing
    merged_path = psi_dir / "source" / "external" / "expanded_psi.txt"
    write_expanded(merged_path, merged, "Psychology expanded (with wave2)")
    print(f"  Merged into expanded_psi.txt: {len(merged)} términos")
    
    run_gen_and_verify(psi_dir)
    update_version(psi_dir / "description.xml", "1.3.0")
    
    dic_path = psi_dir / "ua_psi_GT.dic"
    if dic_path.exists():
        lemma_count = int(dic_path.read_text(encoding="utf-8").splitlines()[0])
        results["psi"] = ("1.3.0", lemma_count)
        spot_check(
            dic_path,
            pos_terms=["psicológico", "cognitivo", "conductual", "emocional", "terapéutico"],
            neg_terms=["tecnico", "juridico", "economico", "clinico"],
        )
    
    # -------------------------------------------------------------------------
    # UNIVERSITARIO (uni)
    # -------------------------------------------------------------------------
    uni_dir = DICTS_ROOT / "dict-ua-uni"
    uni_markers = (
        "universid", "facultad", "licenciatur", "maestr", "doctorad", "syllabus",
        "pensum", "credito", "semestre", "academico", "curricul",
    )
    
    bag, existing = expand_area("uni", uni_dir, UNI_ROOTS, uni_markers)
    
    wave2_path = uni_dir / "source" / "external" / "expanded_wave2.txt"
    write_expanded(wave2_path, bag, "University wave 2 expansion")
    
    merged = bag | existing
    merged_path = uni_dir / "source" / "external" / "expanded_uni.txt"
    write_expanded(merged_path, merged, "University expanded (with wave2)")
    print(f"  Merged into expanded_uni.txt: {len(merged)} términos")
    
    # Patch gen_all.py if needed to load expanded files
    gen_all_py = uni_dir / "gen_all.py"
    if gen_all_py.exists():
        content = gen_all_py.read_text(encoding="utf-8")
        if "expanded_uni.txt" not in content and "expanded_wave2.txt" not in content:
            # Need to patch - add loading of expanded files
            print("  [PATCH] Actualizando uni/gen_all.py para cargar expanded files")
            # Insert after load_expanded() function
            insert_marker = "def load_expanded():"
            if insert_marker not in content:
                insert_marker = "def collect():"
            
            if insert_marker in content:
                # Add loading in collect function
                old_collect = "for w in load_expanded():"
                new_collect = """for w in load_expanded():
        if w.casefold() not in deny_cf:
            bag.add(w)
    # Wave 2 expansion
    wave2_path = EXT / "expanded_wave2.txt"
    if wave2_path.exists():
        for line in wave2_path.read_text(encoding="utf-8").splitlines():
            w = line.strip()
            if w and not w.startswith("#") and valid(w) and w.casefold() not in deny_cf:
                bag.add(w)
    for w in load_expanded():"""
                
                if old_collect in content and "expanded_wave2" not in content:
                    content = content.replace(old_collect, new_collect, 1)
                    gen_all_py.write_text(content, encoding="utf-8", newline="\n")
                    print("  [OK] gen_all.py parcheado")
    
    run_gen_and_verify(uni_dir)
    update_version(uni_dir / "description.xml", "1.3.0")
    
    dic_path = uni_dir / "ua_uni_GT.dic"
    if dic_path.exists():
        lemma_count = int(dic_path.read_text(encoding="utf-8").splitlines()[0])
        results["uni"] = ("1.3.0", lemma_count)
        spot_check(
            dic_path,
            pos_terms=["universitario", "licenciatura", "maestría", "syllabus", "académico"],
            neg_terms=["tecnico", "juridico", "economico", "clinico"],
        )
    
    # -------------------------------------------------------------------------
    # ANGLICISMOS (ang)
    # -------------------------------------------------------------------------
    ang_dir = DICTS_ROOT / "dict-ua-ang"
    ang_markers = (
        "software", "hardware", "internet", "email", "online", "offline",
        "database", "network", "server", "client", "browser", "application",
    )
    
    bag, existing = expand_area("ang", ang_dir, ANG_ROOTS, ang_markers, use_ang=True)
    
    wave2_path = ang_dir / "source" / "external" / "expanded_wave2.txt"
    write_expanded(wave2_path, bag, "Anglicisms wave 2 expansion")
    
    merged = bag | existing
    merged_path = ang_dir / "source" / "external" / "expanded_ang.txt"
    write_expanded(merged_path, merged, "Anglicisms expanded (with wave2)")
    print(f"  Merged into expanded_ang.txt: {len(merged)} términos")
    
    run_gen_and_verify(ang_dir)
    update_version(ang_dir / "description.xml", "1.3.0")
    
    dic_path = ang_dir / "ua_ang_GT.dic"
    if dic_path.exists():
        lemma_count = int(dic_path.read_text(encoding="utf-8").splitlines()[0])
        results["ang"] = ("1.3.0", lemma_count)
        spot_check(
            dic_path,
            pos_terms=["software", "hardware", "online", "database", "framework"],
            neg_terms=["tecnico", "juridico", "economico"],
        )
    
    # -------------------------------------------------------------------------
    # Update README
    # -------------------------------------------------------------------------
    readme = DICTS_ROOT / "README.md"
    update_readme_table(readme, results)
    
    # -------------------------------------------------------------------------
    # Summary
    # -------------------------------------------------------------------------
    print("\n" + "=" * 80)
    print("RESUMEN FINAL")
    print("=" * 80)
    for code in ["med", "odo", "der", "eco", "arq", "pol", "psi", "uni", "ang"]:
        if code in results:
            version, count = results[code]
            print(f"  {code:4s} | v{version} | {count:7,d} lemas")
    
    print("\n[OK] Wave 2 expansion completada para todos los diccionarios UA")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
