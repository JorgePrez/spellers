# -*- coding: utf-8 -*-
"""Additional university academic lemmas. ASCII + unicode_escape."""
from __future__ import annotations

import codecs
import re
from itertools import product

LETTER = re.compile(
    r"^[A-Za-z\u00c1\u00c9\u00cd\u00d3\u00da\u00dc\u00d1"
    r"\u00e1\u00e9\u00ed\u00f3\u00fa\u00fc\u00f1]+$"
)


def dec(s: str) -> str:
    return codecs.decode(s, "unicode_escape")


B_STUDENT = r"""
estudiante estudiantes alumno alumna alumnos alumnas
becario becaria becarios becarias
monitor monitores monitora monitoras
tutor tutora tutores tutoras tutor\u00eda tutoria
orientaci\u00f3n orientacion vocacional
trayectoria trayectorias perfil perfiles
portafolio acad\u00e9mico academico
"""

B_EVAL = r"""
examen examenes parcial parciales final finales
recuperaci\u00f3n recuperacion recuperaciones
supletorio supletorios
calificaci\u00f3n calificacion calificaciones
escala escala evaluativa
r\u00fabrica anal\u00edtica rubrica analitica
r\u00fabrica hol\u00edstica rubrica holistica
autoevaluaci\u00f3n autoevaluacion coevaluaci\u00f3n coevaluacion
heteroevaluaci\u00f3n heteroevaluacion
retroalimentaci\u00f3n retroalimentacion
"""

B_RESEARCH = r"""
metodolog\u00eda metodologia metodolog\u00edas metodologias
marco te\u00f3rico teorico marco conceptual
hip\u00f3tesis hipotesis variable variables
muestra muestras poblaci\u00f3n poblacion
muestreo encuesta encuestas entrevista entrevistas
triangulaci\u00f3n triangulacion
revisi\u00f3n sistem\u00e1tica revision sistematica
art\u00edculo articulo cient\u00edfico cientifico
publicaci\u00f3n publicacion indexada
"""

B_DEGREE = r"""
bachillerato bachilleratos
tecnico tecnica tecnicos tecnicas
profesorado profesorados
posdoctorado posdoctorados
especialidad especialidades
concentraci\u00f3n concentracion
\u00e1rea area \u00e1reas areas
sub\u00e1rea subarea sub\u00e1reas subareas
"""

B_SCHEDULE = r"""
per\u00edodo periodo per\u00edodos periodos
bloque bloques m\u00f3dulo modulo m\u00f3dulos modulos
unidad did\u00e1ctica unidad didactica
secuencia secuencias secuenciaci\u00f3n secuenciacion
cronograma cronogramas
"""

MORE_BLOCKS = (B_STUDENT, B_EVAL, B_RESEARCH, B_DEGREE, B_SCHEDULE)

ROOTS = [
    "estudiant", "alumn", "becari", "monitor", "tutor", "orient",
    "trayectori", "examen", "parcial", "final", "recuper", "supletori",
    "calific", "escal", "autoevalu", "coevalu", "heteroevalu",
    "retroaliment", "metodolog", "marco", "hipotes", "variabl", "muestr",
    "poblaci", "muestre", "encuest", "entrevist", "triangul",
    "revision", "sistematic", "articul", "cientific", "public",
    "index", "bachillerat", "profesorad", "posdoctor", "especial",
    "concentr", "subarea", "period", "bloqu", "modul", "didactic",
    "secuenc", "cronogram", "portafoli", "vocacional", "academ",
    "curricular", "pedagog", "didact", "formativ", "sumativ",
    "diagnostic", "progres", "transversal", "fundament", "electiv",
    "optativ", "obligatori", "practic", "teor", "experimental",
    "cualitativ", "cuantitativ", "mixt", "longitudinal", "transversal",
    "observ", "particip", "etnograf", "fenomenolog", "hermeneut",
    "estadistic", "descript", "inferencial", "correl", "regres",
    "valid", "confiabil", "triangul", "muestre", "signific",
    "conclusion", "discusion", "introducc", "bibliograf", "anex",
    "apendic", "glosari", "indic", "competenc", "habilidad", "destrez",
    "conocimient", "actitud", "valor", "axiolog", "epistemolog",
    "ontolog", "paradig", "enfoqu", "perspect", "model", "teor",
    "concept", "construct", "operacional", "instrument", "escal",
    "rubric", "portafoli", "evidenc", "product", "result", "impact",
    "transfer", "aplic", "innov", "emprend", "empresarial",
    "interdisciplin", "multidisciplin", "transdisciplin",
    "colabor", "cooper", "autonom", "autoregul", "metacogn",
    "reflex", "crit", "analit", "sintet", "creativ", "comunic",
    "present", "argument", "debate", "foro", "seminari", "conferenc",
    "simposi", "congres", "ponenc", "poster", "expos", "demostr",
    "laboratori", "campo", "clinic", "servicio", "comunitari",
    "extension", "proyecc", "social", "vincul", "internacionaliz",
    "movil", "intercambi", "convalid", "revalid", "homolog",
    "acredit", "certific", "aval", "reconoc", "calidad",
    "mejor", "continu", "acredit", "evalu", "autoevalu",
    "planific", "gestion", "administr", "financ", "presupuest",
    "bec", "escolar", "matricul", "inscrip", "admis", "selecc",
    "ingres", "egres", "gradu", "titul", "colacion", "promoc",
    "distinc", "honor", "merit", "excelenc", "liderazg",
]

SUFFIXES = [
    dec(s)
    for s in (
        "o", "a", "os", "as", "al", "ales", "ario", "aria", "arios", "arias",
        "ivo", "iva", "ivos", "ivas", "ico", "ica", "icos", "icas",
        "ista", "istas", "ismo", "ismos", "idad", "idades",
        "aci\\u00f3n", "aciones", "encia", "encias", "ente", "entes",
        "ura", "uras",
    )
]


def block_tokens() -> set[str]:
    out: set[str] = set()
    for b in MORE_BLOCKS:
        for t in dec(b).split():
            if t:
                out.add(t)
    return out


def morph_more() -> set[str]:
    out: set[str] = set()
    for root, suf in product(ROOTS, SUFFIXES):
        w = root + suf
        if 4 <= len(w) <= 40 and LETTER.fullmatch(w):
            out.add(w)
    return out


def more_tokens() -> list[str]:
    bag = block_tokens() | morph_more()
    return sorted(bag)
