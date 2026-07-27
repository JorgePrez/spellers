# -*- coding: utf-8 -*-
"""Curated university academic lexicon (UFM-style). ASCII + unicode_escape."""
from __future__ import annotations

import codecs

B_CORE = r"""
universidad universidades universitario universitaria universitarios universitarias
acad\u00e9mico academico acad\u00e9mica academica acad\u00e9micos academicos
acad\u00e9micas academicas academia academias
facultad facultades escuela escuelas departamento departamentos
carrera carreras programa programas pensum pensums
plan estudios malla curricular
cr\u00e9dito credito cr\u00e9ditos creditos
asignatura asignaturas curso cursos materia materias
semestre semestres cuatrimestre cuatrimestre trimestre trimestres
ciclo ciclos nivel niveles
licenciatura licenciaturas maestr\u00eda maestria maestrias
doctorado doctorados posgrado posgrados postgrado postgrados
pregrado pregrados grado grados titulaci\u00f3n titulacion titulaciones
diplomado diplomados certificaci\u00f3n certificacion certificaciones
especializaci\u00f3n especializacion especializaciones
prerrequisito prerrequisitos requisito requisitos
correquisito correquisitos
electiva electivas optativa optativas
obligatoria obligatorias optativa optativas
ordinaria ordinarias extraordinaria extraordinarias
acta actas r\u00fabrica rubrica r\u00fabricas rubricas
portafolio portafolios eportfolio
investigaci\u00f3n investigacion investigaciones
tesis tesina tesinas tesista tesistas
asesor asesora asesores asesoras asesor\u00eda asesoria
catedr\u00e1tico catedratico catedr\u00e1tica catedratica catedr\u00e1ticos catedraticos
catedr\u00e1ticas catedraticas
claustro claustros decano decana decanos decanas
vicerrector vicerrectora vicerrector\u00eda vicerrectoria
rector rectora rector\u00eda rectoria
acreditaci\u00f3n acreditacion acreditaciones acreditada acreditado
evaluaci\u00f3n evaluacion evaluaciones calificaci\u00f3n calificacion
nota notas promedio promedios
PAES admisi\u00f3n admision matr\u00edcula matricula matriculaci\u00f3n matriculacion
inscripci\u00f3n inscripcion inscripciones
egresado egresada egresados egresadas graduando graduanda
graduaci\u00f3n graduacion graduaciones titulado titulada
"""

B_PEDAGOGY = r"""
aprendizaje aprendizajes competencia competencias
resultado resultados indicador indicadores
r\u00fabrica evaluaci\u00f3n rubrica evaluacion
syllabus silabus s\u00edlabus s\u00edlabo silabo s\u00edlabos silabos planificaci\u00f3n planificacion
objetivo objetivos meta metas
contenido contenidos unidad unidades modulo modulos
sesi\u00f3n sesion sesiones clase clases
taller talleres laboratorio laboratorios practicum practica practicas
servicio comunitario extensi\u00f3n extension
vinculaci\u00f3n vinculacion proyecci\u00f3n proyeccion social
modalidad modalidades presencial virtual h\u00edbrido hibrido hibrida
cohorte cohortes generaci\u00f3n generacion
beca becas escolaridad
convalidaci\u00f3n convalidacion revalidaci\u00f3n revalidacion homologaci\u00f3n homologacion
intercambio intercambios movilidad movilidades
internacionalizaci\u00f3n internacionalizacion
extracurricular extracurriculares curricular curriculares
"""

B_ADMIN = r"""
reglamento reglamentos normativa normativas
directriz directrices pol\u00edtica politica pol\u00edticas politicas
lineamiento lineamientos procedimiento procedimientos
resoluci\u00f3n resolucion resoluciones acuerdo acuerdos
consejo consejos comit\u00e9 comite comites
coordinaci\u00f3n coordinacion coordinador coordinadora
director directora directores directoras
secretar\u00eda secretaria secretario secretarios
biblioteca bibliotecas repositorio repositorios
campus sede sedes aula aulas auditorio auditorios
horario horarios calendario calendarios
"""

B_GT = r"""
Universidad Francisco Marroqu\u00edn UFM
Francisco Marroqu\u00edn Marroquin
"""

BLOCKS = (B_CORE, B_PEDAGOGY, B_ADMIN, B_GT)


def tokens(escaped_block: str) -> list[str]:
    return [t for t in codecs.decode(escaped_block, "unicode_escape").split() if t]


def all_block_tokens() -> list[str]:
    out: list[str] = []
    for b in BLOCKS:
        out.extend(tokens(b))
    return out
