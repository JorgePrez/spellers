# -*- coding: utf-8 -*-
"""Expand academic university lexicon (Spanish only). Orthography-first."""
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

OUT = BASE / "source" / "external" / "expanded_uni.txt"
EXT = BASE / "source" / "external"

ROOTS = [
    "universit", "universitari", "interuniversitari", "pluriuniversitari",
    "facultad", "facultativ", "interfacultad", "multifacultad",
    "licenciat", "licenciatur", "licenciad", "prelicenciat",
    "maestr", "maestria", "magist", "masterand", "postmaestr",
    "doctorad", "doctoral", "doctor", "doctorando", "postdoctor",
    "grado", "gradua", "graduacion", "pregrad", "postgrad",
    "semestr", "semestral", "bimestr", "trimestr", "cuatrimestr",
    "credit", "creditici", "creditable", "acredit",
    "acreditacion", "acreditad", "reacreditacion", "desacreditacion",
    "pensum", "pensa", "plan", "estudios", "curricular",
    "curriculum", "curricul", "extracurricular", "cocurricular",
    "prerrequis", "correquisit", "requisito", "prerrequisito",
    "rubric", "rubrica", "evaluacion", "evaluativ", "evaluat",
    "catedr", "catedrat", "catedralic", "subcatedrat",
    "profesor", "profesion", "profesoral", "profesorado", "coprofesor",
    "docent", "docenci", "docentil", "codocenci",
    "titularidad", "titular", "interinidad", "interin", "suplen",
    "adjunt", "auxiliar", "asistent", "asociado", "emerito",
    "egresad", "egreso", "egresar", "preegres", "reegres",
    "matricul", "matriculacion", "matriculant", "rematricul",
    "inscripcion", "inscrit", "reinscripcion", "preinscripcion",
    "admision", "admisionari", "admitido", "readmision", "inadmision",
    "examen", "examinacion", "examinad", "reexamen", "preexamen",
    "evaluacion", "evaluat", "evaluativ", "reevaluacion",
    "calificacion", "calificat", "recalificacion", "descalific",
    "aprobacion", "aprobad", "reprobacion", "reprobad",
    "investigacion", "investig", "investigador", "coinvestig",
    "tesis", "tesist", "tesario", "tesina", "protesis",
    "defensa", "defensor", "defensiv", "predefensa",
    "syllabus", "silab", "silabo", "programatic", "program",
    "metodolog", "metodologic", "metod", "metodist",
    "pedagogic", "pedagog", "didact", "didactic", "andragog",
    "academico", "academ", "academia", "interacademic", "extraacademic",
    "escolar", "escolaridad", "escolariz", "desescolariz",
    "estudiant", "estudiantil", "estudios", "coestudiant",
    "alumn", "alumnad", "coalumno", "exalumn",
    "discent", "discipul", "disciplinar", "interdisciplinar",
    "multidisciplinar", "transdisciplinar", "intradisciplinar",
    "bachiller", "bachillerat", "prebachiller", "posbachiller",
    "carrer", "profesion", "profesional", "profesionalizacion",
    "titulacion", "titulad", "titular", "retitulacion",
    "diploma", "diplom", "diplomacion", "diplomad",
    "certificacion", "certificad", "certific", "recertificacion",
    "homologacion", "homolog", "homologad", "rehomologacion",
    "convalidacion", "convalid", "convalidacion", "reconvalidacion",
    "equivalenci", "equivalent", "equiparable", "equiparacion",
    "rector", "rectorad", "rectoria", "vicerrector", "prorrector",
    "decano", "decanato", "decanal", "vicedecano", "prodecano",
    "secretari", "secretaria", "vicesecretari", "prosecretario",
    "coordinacion", "coordinador", "coordinad", "cocoordinador",
    "departament", "departamental", "interdepartamental",
    "institucion", "institucional", "interinstitucional",
    "extension", "extensionist", "extensionismo",
    "vinculacion", "vincul", "vinculant", "desvincul",
    "proyecto", "proyect", "proyeccion", "proyectist",
    "innovacion", "innov", "innovador", "coinnovacion",
    "educacion", "educat", "educativ", "educacional", "coeducacion",
    "formacion", "formativ", "formador", "reformacion",
    "capacitacion", "capacit", "capacitad", "recapacitacion",
    "competenci", "competent", "competitiv", "incompetenci",
    "habilidad", "habil", "habilitacion", "rehabilitacion",
    "destrez", "perici", "expertici", "experto", "experticia",
    "conocimient", "cognoscitiv", "epistem", "epistemolog",
    "autonomi", "autonom", "autonomist", "heteronom",
    "colegiad", "colegiacion", "colegial", "colegiatur",
    "practicant", "practic", "practicum", "prepratic",
    "pasanti", "pasantia", "cotutor", "tutor", "tutoria",
    "asesor", "asesori", "asesoriado", "coasesor",
    "orient", "orientacion", "orientador", "reorientacion",
    "consejeri", "consej", "aconsejar", "aconsejad",
    "especializacion", "especializ", "especialist", "reespecializ",
    "actualizacion", "actualiz", "actualizad", "desactualiz",
    "perfeccionamient", "perfeccion", "perfeccionad",
    "aprendiz", "aprendizaj", "coaprendiz", "desaprendiz",
    "ensenanz", "ensen", "ensenante", "coensenanza",
    "lectiv", "leccion", "lector", "lectorad", "lectorado",
    "asignatur", "asignacion", "asignad", "reasignacion",
    "modul", "modular", "modulacion", "modulad",
    "unidad", "unitari", "subunidad", "pluriunidad",
    "sesion", "sesional", "sesionad", "sesionari",
    "claustro", "claustral", "claustralidad", "interclaustral",
    "consejo", "consejeri", "consejil", "aconsej",
    "comision", "comisionado", "comisionad", "subcomision",
    "comite", "comit", "subcomite", "intercomit",
    "junta", "juntad", "juntador", "rejuntar",
    "asamblea", "asambleari", "asambleist", "asambleario",
    "colegio", "colegial", "colegiacion", "intercolegio",
    "asociacion", "asociativ", "asociad", "coasociacion",
    "gremio", "gremial", "gremialism", "intergremial",
    "PAES", "PAA", "UFM", "URL", "USAC", "UVG", "Galileo",
    "CUN", "CUNOC", "CUNSURORI", "CUNORI",
    "Guatemala", "guatemaltec", "centroamerican",
]

# Clean roots: remove accidental bad tokens and short noise
ROOTS = sorted({r for r in ROOTS if len(r) >= 3 and " " not in r})

MARKERS = (
    "universit", "facultad", "licenciat", "maestr", "doctorad",
    "semestr", "credit", "acredit", "pensum", "curricular",
    "prerrequis", "requisit", "rubric", "catedr", "profesor",
    "docent", "titular", "adjunt", "auxiliar", "egresad",
    "matricul", "inscripcion", "admision", "examen", "evaluacion",
    "calificacion", "aprobacion", "investigacion", "tesis",
    "syllabus", "silab", "metodolog", "pedagogic", "didactic",
    "academico", "escolar", "estudiant", "alumn", "discent",
    "disciplinar", "bachiller", "carrer", "titulacion",
    "diploma", "certificacion", "homolog", "convalid",
    "rector", "decano", "secretari", "coordinacion",
    "departament", "institucion", "extension", "vinculacion",
    "proyecto", "innovacion", "educacion", "formacion",
    "capacitacion", "competenci", "habilidad", "destrez",
    "conocimient", "autonomi", "colegiad", "practicant",
    "pasanti", "tutor", "asesor", "orient", "especializ",
    "actualizacion", "aprendiz", "ensenan", "lectiv",
    "asignatur", "modul", "unidad", "sesion", "claustro",
    "consejo", "comision", "comite", "junta", "asamblea",
    "colegio", "asociacion", "gremio", "PAES", "UFM",
)

SEED = r"""
pensum crédito rúbrica sílabus semestre tesario catedrático
PAES UFM URL USAC UVG Galileo CUN Guatemala
facultad licenciatura maestría doctorado egresado matricula
admisión evaluación investigación tesis syllabus
metodología pedagogía didáctica académico escolar
disciplinar profesión titulación diploma certificación
homologación convalidación rector decano secretaría
coordinación departamento institución extensión proyecto
innovación educación formación capacitación competencia
habilidad destreza conocimiento autonomía colegiado
práctica pasantía tutoría asesoría orientación
especialización actualización aprendizaje enseñanza
asignatura módulo unidad sesión claustro consejo
comisión comité junta asamblea colegio asociación
"""

FORCE = [
    "pensum", "crédito", "rúbrica", "sílabus", "semestre",
    "tesario", "catedrático", "PAES", "UFM", "URL", "USAC",
    "UVG", "Galileo", "CUN", "Guatemala",
    "facultad", "licenciatura", "maestría", "doctorado",
    "matrícula", "admisión", "evaluación", "investigación",
    "metodología", "pedagogía", "didáctica", "académico",
    "titulación", "homologación", "convalidación",
    "coordinación", "innovación", "educación", "formación",
    "capacitación", "tutoría", "asesoría", "orientación",
    "especialización", "actualización", "enseñanza",
    "comisión",
]

DENY = {
    "credito", "rubrica", "silabus", "tesario", "catedratico",
    "matricula", "admision", "evaluacion", "investigacion",
    "metodologia", "pedagogia", "didactica", "academico",
    "titulacion", "homologacion", "convalidacion",
    "coordinacion", "innovacion", "educacion", "formacion",
    "capacitacion", "tutoria", "asesoria", "orientacion",
    "especializacion", "actualizacion", "ensenanza",
    "comision", "tecnico", "imagenes",
    "clear", "airway", "vaccine", "joint", "venture",
}

BASES = [
    "universitario", "universitaria", "académico", "académica",
    "escolar", "estudiantil", "profesional", "educativo",
    "educativa", "pedagógico", "pedagógica", "didáctico",
    "didáctica", "metodológico", "metodológica", "curricular",
    "disciplinar", "interdisciplinar", "multidisciplinar",
    "institucional", "departamental", "facultativo", "facultativa",
]


def main() -> int:
    bag: set[str] = set()
    bag |= tokens_from_escaped_block(SEED)
    bag |= morph_combo(ROOTS, bases=BASES, prefixes=COMMON_PREFIXES)
    bag |= harvest_reference(EXT, MARKERS)
    bag |= gender_number(bag, title_if=lambda w: any(m in w.casefold() for m in ("universit", "facultad", "académ", "profesor")))
    bag = {w for w in bag if valid_word(w)}
    bag, stats = finalize(bag, BASE, force_keep=FORCE, deny=DENY)
    words = write_expanded(OUT, bag, "expanded_uni UA (generado; ortografia prioritaria)")
    print(f"expanded_uni: {len(words)}  stats={stats}")
    for c in ("pensum", "crédito", "sílabus", "credito", "tecnico", "imagenes"):
        print(f"  {c}: {c in bag}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
