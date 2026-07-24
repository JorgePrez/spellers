# -*- coding: utf-8 -*-
"""Curated law lexicon blocks (ASCII + unicode_escape)."""
from __future__ import annotations

import codecs

B_GENERAL = r"""
derecho jur\u00eddico jur\u00eddica jur\u00eddicos jur\u00eddicas
jurista juristas abogado abogada abogados abogadas
notario notaria notarios notarias escribano escribanos
procurador procuradora fiscal fiscales
juez jueza jueces magistrado magistrada magistrados
tribunal tribunales corte cortes juzgado juzgados
sala salas audiencia audiencias
legislaci\u00f3n legislacion norma normas normativo normativa
ley leyes decreto decretos reglamento reglamentos
ordenanza ordenanzas acuerdo acuerdos resoluci\u00f3n resolucion
sentencia sentencias auto autos fallos fallo
jurisprudencia doctrina precedentes
constituci\u00f3n constitucion constitucional constitucionales
c\u00f3digo codigo c\u00f3digos codigos
art\u00edculo articulo art\u00edculos articulos inciso incisos
p\u00e1rrafo parrafo numeral numerales
vigencia vigencia derogado derogada derogaci\u00f3n derogacion
promulgaci\u00f3n promulgacion publicaci\u00f3n publicacion
vacatio legis
pre\u00e1mbulo preambulo exposici\u00f3n de motivos
"""

B_CIVIL = r"""
derecho civil patrimonial familiar sucesorio
persona f\u00edsica fisica jur\u00eddica juridica
capacidad de obrar incapacidad emancipaci\u00f3n emancipacion
domicilio vecindad nacionalidad
acto jur\u00eddico negocio jur\u00eddico
consentimiento objeto causa forma
nulidad anulabilidad inexistencia
simulaci\u00f3n simulacion dolo culpa lesi\u00f3n lesion
vicios del consentimiento error violencia intimidaci\u00f3n intimidacion
contrato contratos contractual contractuales
obligaci\u00f3n obligacion obligaciones
deudor acreedor prestaci\u00f3n prestacion
cumplimiento incumplimiento mora
resoluci\u00f3n resolucion rescisi\u00f3n reseision
cesi\u00f3n cesion novaci\u00f3n novacion
compensaci\u00f3n compensacion confusi\u00f3n confusion
remisi\u00f3n remision daci\u00f3n dacion en pago
compraventa arrendamiento comodato
mutuo pr\u00e9stamo prestamo hipoteca prenda
fianza mandato sociedad civil
donaci\u00f3n donacion herencia legado legatario
testamento intestada intestada intestada
sucesi\u00f3n sucesion hereditaria herederos
albacea partici\u00f3n particion colaci\u00f3n colacion
usufructo nuda propiedad servidumbre
propiedad posesi\u00f3n posesion tenencia
reivindicatoria reivindicatorio
prescripci\u00f3n prescripcion adquisitiva usucapi\u00f3n usucapion
matrimonio divorcio separaci\u00f3n separacion
r\u00e9gimen regimen patrimonial sociedad conyugal
patria potestad tutela curatela
adopci\u00f3n adopcion alimentos
filiaci\u00f3n filiacion reconocimiento
da\u00f1os danos perjuicios responsabilidad civil
indemnizaci\u00f3n indemnizacion
"""

B_PENAL = r"""
derecho penal criminal criminolog\u00eda criminologia
delito delitos falta faltas infracci\u00f3n infraccion
tipicidad antijuridicidad culpabilidad
dolo culpa imprudencia negligencia
tentativa consumaci\u00f3n consumacion frustraci\u00f3n frustracion
autor coautor c\u00f3mplice complice encubridor
autor\u00eda autoria participaci\u00f3n participacion
pena penas sanci\u00f3n sancion
prisi\u00f3n prision multa comiso decomiso
inhabilitaci\u00f3n inhabilitacion
eximente atenuante agravante
leg\u00edtima legitima defensa estado de necesidad
inimputabilidad inimputable
homicidio asesinato parricidio feminicidio
lesiones amenazas coacciones
robo hurto estafa apropiaci\u00f3n apropiacion indebida
extorsi\u00f3n extorsion lavado de dinero
narcotr\u00e1fico narcotrafico
violaci\u00f3n violacion abuso sexual
secuestro plagio
prevaricaci\u00f3n prevaricacion cohecho peculado
malversaci\u00f3n malversacion
corrupci\u00f3n corrupcion
contrabando contrabandista
delito culposo doloso continuado permanente
concurso ideal real de delitos
reincidencia reincidente
prescripci\u00f3n de la acci\u00f3n penal
"""

B_PROCESAL = r"""
derecho procesal procedimiento procedimental
demanda contestaci\u00f3n contestacion
rebeld\u00eda rebeldia
prueba pruebas testifical documental pericial
confesi\u00f3n confesion inspecci\u00f3n inspeccion ocular
alegatos conclusiones
audiencia preliminar vista p\u00fablica publica
notificaci\u00f3n notificacion emplazamiento citaci\u00f3n citacion
emplazado citado
medida cautelar embargo secuestro judicial
interdicto interdictos
apelaci\u00f3n apelacion casaci\u00f3n casacion
amparo revisi\u00f3n revision
recurso recursos reposici\u00f3n reposicion
queja nulidad de actuaciones
cosa juzgada litispendencia conexidad
competencia territorial objetiva funcional
jurisdicci\u00f3n jurisdiccion
parte actora demandada demandante demandado
tercero coadyuvante
ejecuci\u00f3n ejecucion forzosa
sentencia firme ejecutoriada
procesalista procesalistas
juicio ordinario sumario verbal
proceso penal acusatorio adversativo
imputado acusado procesado condenado absuelto
querella querellante denuncia denunciante
ministerio p\u00fablico publico fiscal\u00eda fiscalia
defensa t\u00e9cnica tecnica
prisi\u00f3n preventiva
alternativa al proceso criterio de oportunidad
mediaci\u00f3n mediacion conciliaci\u00f3n conciliacion
arbitraje \u00e1rbitro arbitro laudo
"""

B_CONST = r"""
derecho constitucional constitucionalismo
estado de derecho democracia
soberan\u00eda soberania pueblo
poderes del estado legislativo ejecutivo judicial
separaci\u00f3n de poderes
supremac\u00eda supremacia constitucional
control de constitucionalidad
inconstitucionalidad inconstitucional
amparo constitucional
habeas corpus habeas data
derechos fundamentales humanos
garant\u00edas garantias constitucionales
debido proceso igualdad libertad
libertad de expresi\u00f3n expresion
libertad de locomoci\u00f3n locomocion
propiedad privada
tutela judicial efectiva
bloque de constitucionalidad
reforma constitucional
asamblea nacional constituyente
corte de constitucionalidad
"""

B_ADMIN = r"""
derecho administrativo administraci\u00f3n administracion p\u00fablica publica
acto administrativo potestad
discrecionalidad reglada
recurso administrativo
silencio administrativo
expropiaci\u00f3n expropiacion
concesi\u00f3n concesion licencia autorizaci\u00f3n autorizacion
permiso permisos
contrataci\u00f3n contratacion administrativa
licitaci\u00f3n licitacion p\u00fablica publica
adjudicaci\u00f3n adjudicacion
servicio p\u00fablico publico
funcionario funcionaria servidor p\u00fablico
responsabilidad patrimonial del estado
contencioso administrativo
"""

B_MERCANTIL = r"""
derecho mercantil comercial societario
sociedad an\u00f3nima anonima responsabilidad limitada
accionista acciones capital social
junta general consejo de administraci\u00f3n
administrador gerentes
quiebra insolvencia concurso de acreedores
t\u00edtulo titulo valor cheque letra de cambio pagar\u00e9 pagare
endoso aval
propiedad industrial marca patente
competencia desleal monopolio
contrato mercantil compraventa mercantil
transporte seguro fianza mercantil
"""

B_LABORAL = r"""
derecho laboral trabajo
contrato de trabajo patrono trabajador
salario prestaci\u00f3n prestacion laboral
jornada horas extras
despido injustificado indemnizaci\u00f3n indemnizacion
sindicato huelga negociaci\u00f3n negociacion colectiva
convenio colectivo
inspecci\u00f3n inspeccion de trabajo
seguridad social
"""

B_INTERNACIONAL = r"""
derecho internacional p\u00fablico publico privado
tratado tratados convenio convenios
ratificaci\u00f3n ratificacion adhesi\u00f3n adhesion
reserva reservas
costumbre internacional
sujeto de derecho internacional
soberan\u00eda soberania territorial
inmunidad diplom\u00e1tica diplomatica
extradici\u00f3n extradicion
asilo refugio
organizaci\u00f3n internacional
corte internacional de justicia
derechos humanos
lex mercatoria
"""

B_LATIN = r"""
aequitas animus bonus malus
culpa lata leve levisima
dolo eventual directo
erga omnes inter partes
ex lege ex contractu ex delicto
ex nunc ex tunc
ex officio ex parte
habeas corpus data
in dubio pro reo pro operario
ipso iure ipso facto
iuris tantum iuris et de iure
ius cogens gentium
lex specialis posterior
locus standi
modus operandi vivendi
mutatis mutandis
ne bis in idem
non bis in idem
nullum crimen sine lege
nulla poena sine lege
pacta sunt servanda
prima facie
ratio decidendi legis
res iudicata judicata
sine die qua non
stricto sensu lato sensu
ultra vires petita
uti possidetis
ad litem hoc
amicus curiae
bona fide fides
de facto iure
iure gestionis imperii
onus probandi
reformatio in peius
restitutio in integrum
sub iudice judice
versus
affidavit
exequatur
fideicomiso fideicomisario
"""

B_GT = r"""
Organismo Judicial Corte Suprema de Justicia
Corte de Constitucionalidad
Ministerio P\u00fablico Publico
Procuradur\u00eda Procuraduria General de la Naci\u00f3n Nacion
Procuradur\u00eda de los Derechos Humanos
Congreso de la Rep\u00fablica Republica
C\u00f3digo Penal Civil Procesal Penal Procesal Civil
y Mercantil de Trabajo
Ley de Amparo Exhibici\u00f3n Exhibicion Personal
Constitucionalidad
C\u00f3digo Procesal Penal
C\u00f3digo Procesal Civil y Mercantil
C\u00f3digo de Trabajo
C\u00f3digo Municipal
Ley del Organismo Judicial
Ley de lo Contencioso Administrativo
Inacif Instituto Nacional de Ciencias Forenses
PNC Polic\u00eda Policia Nacional Civil
SAT Superintendencia de Administraci\u00f3n Administracion Tributaria
Registro Mercantil General de la Propiedad
"""

BLOCKS = (
    B_GENERAL,
    B_CIVIL,
    B_PENAL,
    B_PROCESAL,
    B_CONST,
    B_ADMIN,
    B_MERCANTIL,
    B_LABORAL,
    B_INTERNACIONAL,
    B_LATIN,
    B_GT,
)


def tokens(escaped_block: str) -> list[str]:
    return [t for t in codecs.decode(escaped_block, "unicode_escape").split() if t]


def all_block_tokens() -> list[str]:
    out: list[str] = []
    for b in BLOCKS:
        out.extend(tokens(b))
    return out
