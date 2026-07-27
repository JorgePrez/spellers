# -*- coding: utf-8 -*-
"""Curated psychology lexicon (ASCII + unicode_escape)."""
from __future__ import annotations

import codecs

B_GENERAL = r"""
psicolog\u00eda psicologia psicol\u00f3gico psicol\u00f3gica psicol\u00f3gicos
psicol\u00f3gicas psic\u00f3logo psicologa psic\u00f3loga psic\u00f3logos
psicologas psic\u00f3logas psiquiatr\u00eda psiquiatria psiquiatra psiquiatras
mental mentales salud mental bienestar psicol\u00f3gico
investigaci\u00f3n investigacion psicol\u00f3gica metodolog\u00eda metodologia
teor\u00eda teor\u00edas te\u00f3rico te\u00f3rica enfoque enfoques
paradigma paradigmas modelo modelos constructo constructos
"""

B_CLINICO = r"""
cl\u00ednico cl\u00ednica cl\u00ednicos cl\u00ednicas cl\u00ednicamente
psicopatolog\u00eda psicopatologia psicopatol\u00f3gico psicopatol\u00f3gica
diagn\u00f3stico diagnostico diagn\u00f3sticos diagnosticos
DSM CIE clasificaci\u00f3n clasificacion nosol\u00f3gica nosologica
trastorno trastornos s\u00edndrome sindrome s\u00edndromes sindromes
s\u00edntoma sintoma s\u00edntomas sintomas sintomatolog\u00eda sintomatologia
comorbilidad comorbilidades cronicidad agudeza episodio episodios
remisi\u00f3n remision reca\u00edda recaida reca\u00eddas recaidas
esquizofrenia esquizofr\u00e9nico esquizofr\u00e9nica bipolar man\u00edaco maniaca
hipoman\u00edaco hipomaniaca depresi\u00f3n depresion distimia
ansiedad fobia fobias p\u00e1nico panico agorafobia fobia social
obsesi\u00f3n obsesion compulsi\u00f3n compulsion TOC
TEPT trauma traum\u00e1tico traumatica disociaci\u00f3n disociacion
personalidad borderline narcisista antisocial evitativo dependiente
histri\u00f3nico histrionico autismo autista TEA TDAH
hiperactividad inatenci\u00f3n inatencion impulsividad
dislexia discalculia disgraf\u00eda disgrafia
anorexia bulimia trastorno alimentario
adicci\u00f3n adiccion dependencia sustancias abstinencia tolerancia
alucinaci\u00f3n alucinacion delirio paranoia psicosis catatonia
demencia Alzheimer Parkinson epilepsia
"""

B_COGNITIVO = r"""
cognitivo cognitiva cognitivos cognitivas cognici\u00f3n cognicion
procesamiento informaci\u00f3n informacion atenci\u00f3n atencion
memoria percepci\u00f3n percepcion pensamiento razonamiento
inteligencia creatividad metacognici\u00f3n metacognicion
funciones ejecutivas inhibici\u00f3n inhibicion control cognitivo
aprendizaje condicionamiento refuerzo castigo
modelado observacional esquema esquemas
sesgo sesgos heur\u00edstica heuristica
"""

B_DESARROLLO = r"""
desarrollo evolutivo evolutiva infancia adolescencia adultez vejez
gerontolog\u00eda gerontologia psicolog\u00eda del desarrollo
maduraci\u00f3n maduracion crianza apego vinculaci\u00f3n vinculacion
socializaci\u00f3n socializacion identidad g\u00e9nero genero
autoconcepto autoestima autorregulaci\u00f3n autorregulacion
"""

B_SOCIAL = r"""
psicolog\u00eda social grupal comunitaria comunitario
actitud actitudes creencia creencias estereotipo estereotipos
prejuicio prejuicios discriminaci\u00f3n discriminacion
conformidad obediencia influencia social
agresi\u00f3n agresion prosocial altruismo
grupo grupos liderazgo cohesi\u00f3n cohesion
din\u00e1mica dinamica grupal roles normas
"""

B_NEURO = r"""
neuropsicolog\u00eda neuropsicologia neuropsic\u00f3logo neuropsicologa
neuroanatom\u00eda neuroanatomia neurofisiolog\u00eda neurofisiologia
corteza cerebral hemisferio frontal parietal temporal occipital
cerebelo hipocampo am\u00edgdala amigdala t\u00e1lamo talamo
sinapsis neurotransmisor dopamina serotonina
neuroimagen neuroplasticidad neurodesarrollo neurodegeneraci\u00f3n
neurodegeneracion neurocognici\u00f3n neurocognicion
amnesia afasia agnosia apraxia heminegligencia
TCE traumatismo craneoencef\u00e1lico craneoencefalico
EEG ERP fMRI TAC PET SPECT DTI
"""

B_PSICometria = r"""
psicometr\u00eda psicometria psicometr\u00edas psicometrias
psicodiagn\u00f3stico psicodiagnostico evaluaci\u00f3n evaluacion
test tests prueba pruebas escala escalas inventario inventarios
cuestionario cuestionarios validez confiabilidad fiabilidad
consistencia interna reactivo reactividad sesgo sesgos
estandarizaci\u00f3n estandarizacion normas percentiles
"""

B_TERAPIA = r"""
psicoterapia psicoterapeuta terapia terapias
cognitivo conductual TCC humanista existencial gestalt
sist\u00e9mica sistemica psicoan\u00e1lisis psicoanalisis
integrativa breve focal grupal individual familiar
psicoeducaci\u00f3n psicoeducacion intervenci\u00f3n intervencion
rehabilitaci\u00f3n rehabilitacion prevenci\u00f3n prevencion
promoci\u00f3n promocion mindfulness relajaci\u00f3n relajacion
exposici\u00f3n exposicion desensibilizaci\u00f3n desensibilizacion
reestructuraci\u00f3n reestructuracion cognitiva asertividad
habilidades sociales entrenamiento
EMDR hipnosis biofeedback
"""

B_INVESTIG = r"""
variable variables dependiente independiente control
hip\u00f3tesis hipotesis nula alternativa significancia
correlaci\u00f3n correlacion regresi\u00f3n regresion varianza
desviaci\u00f3n desviacion est\u00e1ndar estandar
muestreo muestra muestras aleatorio aleatoria estratificado
encuesta encuestas entrevista entrevistas
cualitativo cuantitativo experimental observacional
longitudinal transversal cuasiexperimental correlacional
triangulaci\u00f3n triangulacion metaan\u00e1lisis metaanalisis
revisi\u00f3n revision sistem\u00e1tica sistematica
placebo control cegamiento aleatorizaci\u00f3n aleatorizacion
"""

B_APLICADA = r"""
psicolog\u00eda cl\u00ednica psicologia clinica educativa escolar
organizacional laboral industrial ocupacional
comunitaria forense deportiva ambiental
"""

B_FARMACO = r"""
psicofarmacolog\u00eda psicofarmacologia psicofarmacol\u00f3gico
antidepresivo antidepresivos ansiol\u00edtico ansioliticos
neur\u00f3leptico neuroleptico antipsic\u00f3tico antipsicotico
estabilizador humor psicoestimulante
"""

B_PSIcodynam = r"""
inconsciente consciente preconsciente ego ello superego
transferencia contratransferencia interpretaci\u00f3n interpretacion
asociaci\u00f3n libre mecanismo defensa represi\u00f3n represion
proyecci\u00f3n proyeccion racionalizaci\u00f3n racionalizacion
sublimaci\u00f3n sublimacion regresi\u00f3n regresion
"""

B_AUTORES = r"""
Freud Jung Adler Fromm Horney Sullivan Bowen Minuchin Satir
Perls May Frankl Piaget Vygotsky Erikson Kohlberg Maslow Rogers
Beck Ellis Skinner Bandura Milgram Asch Zimbardo Wernicke Broca
"""

BLOCKS = (
    B_GENERAL,
    B_CLINICO,
    B_COGNITIVO,
    B_DESARROLLO,
    B_SOCIAL,
    B_NEURO,
    B_PSICometria,
    B_TERAPIA,
    B_INVESTIG,
    B_APLICADA,
    B_FARMACO,
    B_PSIcodynam,
    B_AUTORES,
)


def tokens(escaped_block: str) -> list[str]:
    return [t for t in codecs.decode(escaped_block, "unicode_escape").split() if t]


def all_tokens() -> list[str]:
    out: list[str] = []
    for b in BLOCKS:
        out.extend(tokens(b))
    return out
