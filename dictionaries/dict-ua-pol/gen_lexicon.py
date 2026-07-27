# -*- coding: utf-8 -*-
"""Curated political science / IR lexicon (ASCII + unicode_escape)."""
from __future__ import annotations

import codecs

B_GENERAL = r"""
ciencia pol\u00edtica politolog\u00eda politologia
pol\u00edtico pol\u00edtica pol\u00edticos pol\u00edticas
polit\u00f3logo politologa politologos politologas
teor\u00eda teor\u00edas te\u00f3rico te\u00f3rica te\u00f3ricos te\u00f3ricas
concepto conceptos conceptual conceptuales
paradigma paradigmas enfoque enfoques
modelo modelos marco marcos
an\u00e1lisis analisis pol\u00edtico
estudio estudios caso casos comparado comparada
investigaci\u00f3n investigacion emp\u00edrico emp\u00edrica
metodolog\u00eda metodologia cualitativo cuantitativo
variable variables hip\u00f3tesis hipotesis
operacionalizaci\u00f3n operacionalizacion indicador indicadores
muestreo muestra muestras encuesta encuestas
entrevista entrevistas etnograf\u00eda etnografia
revisi\u00f3n revision sistem\u00e1tica sistematica
bibliograf\u00eda bibliografia fuente fuentes primaria secundaria
"""

B_TEORIA = r"""
teor\u00eda pol\u00edtica cl\u00e1sica clasica contempor\u00e1nea contemporanea
democracia democracias democr\u00e1tico democr\u00e1tica democr\u00e1ticos
democr\u00e1ticas democratizaci\u00f3n democratizacion
liberal liberalismo neoliberal neoliberalismo
social socialismo socialdemocracia socialdem\u00f3crata
conservador conservadora conservadurismo
progresista progresismo radical radicalismo
populismo populista populistas
autoritarismo totalitarismo dictadura dictaduras
autocracia autocr\u00e1tico autocr\u00e1tica
oligarqu\u00eda oligarquia plutocracia
Estado estado estados naci\u00f3n nacion naciones
nacionalismo nacionalista nacionalistas
ciudadano ciudadana ciudadanos ciudadanas
ciudadan\u00eda ciudadania participaci\u00f3n participacion
representaci\u00f3n representacion representante representantes
legitimidad legitimaci\u00f3n legitimacion
soberan\u00eda soberania autoridad autoridades
poder poderes hegemon\u00eda hegemonia
contrapoder balance equilibrio
separaci\u00f3n separacion poderes
"""

B_PARTIDOS = r"""
partido partidos partidario partidaria partidarios
sistema sistemas partidario multipartidismo bipartidismo
elecci\u00f3n eleccion elecciones elector electoral electorales
elector electores electora electoras sufragio sufragios
voto votos votante votantes votaci\u00f3n votacion
urna urnas boleta boletas padr\u00f3n padron electoral
candidato candidata candidatos candidatas
campa\u00f1a campana campa\u00f1as campanas
propaganda spot spots mitin mitines
debate debates foro foros
coalici\u00f3n coalicion coaliciones
frente frentes alianza alianzas
oposici\u00f3n oposicion opositor opositora opositores
mayor\u00eda mayoria minor\u00eda minoria
bancada bancadas caucus
comisi\u00f3n comision comisiones pleno plenos
qu\u00f3rum quorum sesi\u00f3n sesion sesiones
moci\u00f3n mocion mociones
referendo referendos plebiscito plebiscitos
"""

B_GOBERNANZA = r"""
gobernanza governance gobernar gobierno gobiernos
gubernamental gubernamentales administraci\u00f3n administracion
pol\u00edtica p\u00fablica publica pol\u00edticas publicas
planificaci\u00f3n planificacion presupuesto presupuestos
reforma reformas pol\u00edtica fiscal pol\u00edtica monetaria
regulaci\u00f3n regulacion deregulaci\u00f3n deregulacion
privatizaci\u00f3n privatizacion nacionalizaci\u00f3n nacionalizacion
subsidiariedad subsidiaridad
burocracia burocr\u00e1tico burocr\u00e1tica burocr\u00e1ticos
funcionario funcionaria funcionarios servidor servidora
servidores p\u00fablico publica publicos publicas
transparencia accountability rendici\u00f3n rendicion cuentas
corrupci\u00f3n corrupcion clientelismo patronazgo
nepotismo cohecho soborno
"""

B_RRII = r"""
relaciones internacionales diplomacia diplom\u00e1tico diplom\u00e1tica
diplom\u00e1ticos diplom\u00e1ticas diplom\u00e1tico diplom\u00e1tica
canciller canciller\u00eda cancilleria embajada embajadas
embajador embajadora embajadores consul consulado consulados
c\u00f3nsul c\u00f3nsules misi\u00f3n mision misiones
protocolo protocolos acreditaci\u00f3n acreditacion
nota verbal nota diplom\u00e1tica
tratado tratados convenio convenios acuerdo acuerdos
ratificaci\u00f3n ratificacion adhesi\u00f3n adhesion reserva reservas
multilateral bilateral unilateral
integraci\u00f3n integracion regional global
globalizaci\u00f3n globalizacion interdependencia
cooperaci\u00f3n cooperacion competencia rivalidad
alianza alianzas bloque bloques
equilibrio poder balance poderes
realismo idealismo constructivismo
liberalismo institucionalismo neorrealismo
"""

B_ORGANISMOS = r"""
ONU OEA UE OTAN UNASUR CELAC CAFTA MERCOSUR ALBA
FMI BM BID Banco Mundial UNICEF UNESCO OMS ACNUR
CICR CIJ TPI OIT OMC GATT G20 G7 BRICS APEC ASEAN
SAARC AU OSCE OPEP IEA OCDE PNUD UNDP USAID DEA CIA
FBI NSA Pent\u00e1gono Kremlin
Asamblea General Consejo Seguridad Secretar\u00eda
Secretaria General Comit\u00e9 Comite Comision Comisi\u00f3n
Tribunal Corte Internacional Justicia
"""

B_GEOPOL = r"""
geopol\u00edtica geopolitico geopolitica geoestrat\u00e9gico geoestrategico
seguridad nacional internacional regional
defensa disuasi\u00f3n disuasion disuasivo disuasiva
conflicto conflictos guerra guerras paz
guerra fr\u00eda fria guerra h\u00edbrida hibrida
terrorismo terrorista terroristas extremismo extremista
radicalizaci\u00f3n radicalizacion insurgencia insurgente
guerrilla guerrilleros paramilitar paramilitares
inteligencia contrainteligencia espionaje
ciberseguridad ciberataque ciberataques
armamento nuclear convencional
proliferaci\u00f3n proliferacion desarme
sanci\u00f3n sancion sanciones embargo embargos
intervenci\u00f3n intervencion ocupaci\u00f3n ocupacion
anexi\u00f3n anexion secesi\u00f3n secesion independencia
frontera fronteras territorial territorio territorios
mar\u00edtimo maritimo a\u00e9reo aereo espacio espacial
"""

B_DDHH = r"""
derechos humanos fundamentales civiles pol\u00edticos
econ\u00f3micos economicos sociales culturales
colectivos individuales universales
Declaraci\u00f3n Declaracion Universal
Pacto Pactos Internacionales
Convenci\u00f3n Convencion convenciones
libertad libertades expresi\u00f3n expresion
asociaci\u00f3n asociacion reuni\u00f3n reunion
debido proceso igualdad equidad
no discriminaci\u00f3n discriminacion
g\u00e9nero genero interseccionalidad
vulnerabilidad empoderamiento inclusi\u00f3n inclusion
exclusi\u00f3n exclusion marginaci\u00f3n marginacion
refugiado refugiada refugiados asilo
migrante migrantes migraci\u00f3n migracion
desplazamiento forzado desaparici\u00f3n desaparicion
tortura tratos crueles degradantes
impunidad justicia transicional
reconciliaci\u00f3n reconciliacion memoria hist\u00f3rica historica
comisi\u00f3n verdad
"""

B_GT = r"""
Guatemala guatemalteco guatemalteca guatemaltecos
Congreso Rep\u00fablica Republica Presidencia
Ministerio Gobernaci\u00f3n Relaciones Exteriores
Canciller\u00eda Cancilleria TSE Tribunal Supremo Electoral
Corte Constitucionalidad Organismo Judicial
Ministerio P\u00fablico Fiscal\u00eda Fiscalia Procuradur\u00eda Procuraduria
Contralor\u00eda General Cuentas SAT
PNC Polic\u00eda Policia Nacional Civil
municipalidad municipalidades alcald\u00eda alcaldia
alcalde alcaldesa concejal concejala
diputado diputada senador senadora
departamento departamentos municipio municipios
Pet\u00e9n Peten Huehuetenango Quetzaltenango Escuintla
Alta Verapaz Baja Verapaz Izabal Chimaltenango
Sacatep\u00e9quez Sacatepequez Totonicap\u00e1n Totonicapan
Solol\u00e1 Solola Suchitep\u00e9quez Suchitepequez Retalhuleu
San Marcos Jutiapa Jalapa Santa Rosa Zacapa Chiquimula
El Progreso Quich\u00e9 Quiche
"""

B_IDEOLOGIA = r"""
ideolog\u00eda ideologia ideol\u00f3gico ideol\u00f3gica ideol\u00f3gicos
capitalismo socialismo comunismo marxismo
anarquismo fascismo nazismo
neoconservadurismo neoliberalismo keynesianismo
feminismo poscolonialismo ecologismo ambientalismo
indigenismo multiculturalismo interculturalismo
secularismo laicismo teocracia
"""

B_METODO = r"""
variable dependiente independiente control
hip\u00f3tesis nula alternativa significancia
correlaci\u00f3n correlacion regresi\u00f3n regresion
varianza desviaci\u00f3n desviacion est\u00e1ndar estandar
muestreo probabil\u00edstico probabilistico no probabil\u00edstico
estudio transversal longitudinal experimental
cuasiexperimental observacional comparativo
triangulaci\u00f3n triangulacion validez confiabilidad
fiabilidad sesgo controlado
"""

B_EXTRA = r"""
actor actor pol\u00edtico actores agente agentes
stakeholder stakeholders inter\u00e9s interes intereses
lobby presi\u00f3n presion grupo grupos presi\u00f3n
sociedad civil movimiento movimientos social sociales
sindicato sindicatos huelga huelgas protesta protestas
manifestaci\u00f3n manifestacion manifestaciones
medios comunicaci\u00f3n comunicacion periodismo periodista
editorial censura desinformaci\u00f3n desinformacion
fake news algoritmo algoritmos plataforma plataformas
red redes social sociales viral polarizaci\u00f3n polarizacion
soft power hard power smart power
"""

BLOCKS = (
    B_GENERAL,
    B_TEORIA,
    B_PARTIDOS,
    B_GOBERNANZA,
    B_RRII,
    B_ORGANISMOS,
    B_GEOPOL,
    B_DDHH,
    B_GT,
    B_IDEOLOGIA,
    B_METODO,
    B_EXTRA,
)


def tokens(escaped_block: str) -> list[str]:
    return [t for t in codecs.decode(escaped_block, "unicode_escape").split() if t]


def all_tokens() -> list[str]:
    out: list[str] = []
    for b in BLOCKS:
        out.extend(tokens(b))
    return out
