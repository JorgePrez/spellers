# -*- coding: utf-8 -*-
"""Expanded law lexicon (~12k-20k lemmas). ASCII + unicode_escape only."""
from __future__ import annotations

import codecs
import re
from itertools import product

LETTER = re.compile(
    r"^[A-Za-z\u00c1\u00c9\u00cd\u00d3\u00da\u00dc\u00d1"
    r"\u00e1\u00e9\u00ed\u00f3\u00fa\u00fc\u00f1"
    r"\u00c7\u00e7\u00d6\u00f6]+$"
)


def dec(s: str) -> str:
    return codecs.decode(s, "unicode_escape")


B_CIVIL_EXPAND = r"""
usufructuario nudo propietario superficiario
anticresista arrendatario arrendador comodatario
mutuario depositario secuestre fiduciario
albacea testamentario heredero legatario donatario
c\u00f3nyuge conyuge concubino concubina
r\u00e9gimen de bienes separaci\u00f3n separacion
sociedad conyugal participaci\u00f3n participacion
patria potestad guarda tutela curatela
reconocimiento filiatorio impugnaci\u00f3n impugnacion
alimentos pension alimenticia
da\u00f1o emergente lucro cesante da\u00f1o moral
responsabilidad extracontractual subjetiva objetiva
cl\u00e1usula resolutoria resolutiva resolutoria
pacto de retroventa retroventa
enajenaci\u00f3n enajenacion gravamen
accesi\u00f3n accession uni\u00f3n union
confusi\u00f3n confusion adjunci\u00f3n adjuncion
especie genero cuerpo cierto cosa determinada
obligaci\u00f3n de dar hacer no hacer
mora autom\u00e1tica automatica mora ex re
exceptio non adimpleti contractus
exceptio non numeratae pecuniae
caducidad prescripci\u00f3n extintiva
nulidad relativa absoluta
simulaci\u00f3n absoluta relativa
error substancia error in negotio
lesi\u00f3n enorme lesion enorme
"""

B_PENAL_EXPAND = r"""
delito doloso culposo preterintencional
concurso ideal real medial impropio
iter criminis tentativa frustrada
autor\u00eda intelectual material
inductor instigador cooperador necesario
encubridor receptador
eximente plena incompleta
atenuante anal\u00f3gica analogica
agravante calificada privilegiada
pena privativa libertad accesoria
conmutaci\u00f3n conmutacion indulto amnist\u00eda amnistia
rehabilitaci\u00f3n rehabilitacion
antecedentes penales prontuario
medida seguridad internamiento
libertad vigilada
feminicidio parricidio fratricidio infanticidio
sicariato sicario mercenario
trata personas explotaci\u00f3n explotacion
pornograf\u00eda infantil pornografia
violencia intrafamiliar dom\u00e9stica domestica
falsedad ideol\u00f3gica material ideologica
uso documento falso
usurpaci\u00f3n funciones usurpacion
asociaci\u00f3n il\u00edcita ilicita
terrorismo genocidio lesa humanidad
cr\u00edmenes guerra crimen guerra
desaparici\u00f3n forzada desaparicion
tortura tratos crueles inhumanos
lavado activos blanqueo capitales
narcotr\u00e1fico microtr\u00e1fico microtrafico
"""

B_PROC_EXPAND = r"""
demandante demandado actor reo
litisconsorte necesario facultativo
acumulaci\u00f3n objetiva subjetiva acumulacion
prejudicialidad perentoria
cosa juzgada formal material
litispendencia conexidad prejudicialidad
competencia ratione materiae loci personae
declinatoria inhibitoria
excusa recusaci\u00f3n recusacion
tercer\u00eda terceria
allanamiento demanda reconvenci\u00f3n reconvencion
diligencias preparatorias prueba anticipada
memorial escrito alegato conclusiones
audiencia conciliaci\u00f3n conciliacion
prueba testifical documental pericial indiciaria
confesi\u00f3n confesion judicial extrajudicial
inspecci\u00f3n inspeccion ocular judicial
notificaci\u00f3n personal edicto c\u00e9dula cedula
emplazamiento citaci\u00f3n citacion
medida cautelar embargo secuestro
interdicto posesorio restitutorio
apelaci\u00f3n apelacion casaci\u00f3n casacion
amparo revisi\u00f3n revision
recurso reposici\u00f3n reposicion queja
nulidad actuaciones
sentencia firme ejecutoriada
ejecuci\u00f3n forzosa ejecucion
remate subasta adjudicaci\u00f3n adjudicacion
lanzamiento desahucio
querella querellante denunciante
imputado acusado procesado
ministerio p\u00fablico publico fiscal\u00eda fiscalia
prisi\u00f3n preventiva prision
criterio oportunidad suspensi\u00f3n suspension
"""

B_CONST_EXPAND = r"""
control constitucionalidad abstracto concreto
inconstitucionalidad general particular
supremac\u00eda constitucional supremacia
bloque constitucionalidad convencionalidad
reforma constitucional enmienda
asamblea constituyente
corte constitucionalidad
habeas corpus habeas data
amparo constitucional exhibici\u00f3n exhibicion personal
debido proceso legal tutela judicial efectiva
doble instancia
prohibici\u00f3n reforma peyorativa
presunci\u00f3n inocencia presuncion
nemo tenetur nemo judex
audi alteram partem
principio legalidad reserva ley
proporcionalidad razonabilidad
igualdad ante ley
seguridad jur\u00eddica juridica
irretroactividad favor libertatis
favor rei pro homine pro persona
"""

B_ADMIN_EXPAND = r"""
acto administrativo discrecional reglado
potestad sancionadora reglamentaria
recurso administrativo revocatoria apelaci\u00f3n apelacion
silencio administrativo positivo negativo
expropiaci\u00f3n expropiacion utilidad p\u00fablica publica
concesi\u00f3n concesion licencia permiso autorizaci\u00f3n autorizacion
contrataci\u00f3n contratacion administrativa
licitaci\u00f3n licitacion p\u00fablica publica
adjudicaci\u00f3n adjudicacion directa
servicio p\u00fablico publico
funcionario servidor p\u00fablico publico
responsabilidad patrimonial estado
contencioso administrativo
"""

B_MERC_EXPAND = r"""
sociedad an\u00f3nima anonima responsabilidad limitada
accionista acciones capital social
junta general consejo administraci\u00f3n administracion
administrador gerente apoderado
quiebra insolvencia concurso acreedores
t\u00edtulo valor cheque letra cambio pagar\u00e9 pagare
endoso aval protesto
propiedad industrial marca patente
competencia desleal monopolio oligopolio
contrato mercantil transporte seguro
cuenta corriente mercantil
oferta p\u00fablica adquisici\u00f3n opa
fusi\u00f3n fusion adquisici\u00f3n adquisicion escisi\u00f3n escision
transformaci\u00f3n societaria transformacion
disoluci\u00f3n liquidaci\u00f3n disolucion liquidacion
sindicatura s\u00edndico sindico
masa concursal acreedores privilegiados quirografarios
"""

B_LAB_EXPAND = r"""
contrato trabajo patrono trabajador
salario prestaci\u00f3n prestacion laboral
jornada horas extras
despido injustificado indemnizaci\u00f3n indemnizacion
sindicato huelga negociaci\u00f3n negociacion colectiva
convenio colectivo
inspecci\u00f3n inspeccion trabajo
seguridad social
"""

B_INT_EXPAND = r"""
derecho internacional p\u00fablico publico privado
tratado convenio ratificaci\u00f3n ratificacion
adhesi\u00f3n adhesion reserva
costumbre internacional
soberan\u00eda territorial soberania
inmunidad diplom\u00e1tica diplomatica
extradici\u00f3n extradicion
asilo refugio
organizaci\u00f3n internacional organizacion
corte internacional justicia
lex mercatoria
"""

B_LATIN_EXPAND = r"""
aequitas animus bonus malus
culpa lata leve levisima
dolo eventual directo
erga omnes inter partes
ex lege ex contractu ex delicto
ex nunc ex tunc ex officio ex parte
habeas corpus data
in dubio pro reo pro operario
ipso iure ipso facto
iuris tantum iuris de iure
ius cogens gentium
lex specialis posterior
locus standi modus operandi vivendi
mutatis mutandis ne bis idem
non bis idem
nullum crimen sine lege
nulla poena sine lege
pacta sunt servanda prima facie
ratio decidendi legis
res iudicata judicata
sine die qua non
stricto sensu lato sensu
ultra vires petita
uti possidetis ad litem hoc
amicus curiae bona fide fides
de facto iure iure gestionis imperii
onus probandi reformatio peius
restitutio integrum sub iudice judice
versus affidavit exequatur
fideicomiso fideicomisario
stare decisis obiter dictum
mandamus certiorari writ subpoena
estoppel common equity
"""

B_GT_EXPAND = r"""
Organismo Judicial Corte Suprema Justicia
Sala Apelaciones
Juzgado Primera Instancia Juzgado Paz
Tribunal Sentencia Mayor Riesgo
Corte Constitucionalidad
Ministerio P\u00fablico Publico
Fiscal\u00eda General Fiscal\u00eda Distrital
Procuradur\u00eda General Procuradur\u00eda Derechos Humanos
Congreso Rep\u00fablica Republica
Presidencia Rep\u00fablica
Ministerio Gobernaci\u00f3n Gobernacion
Ministerio Relaciones Exteriores Canciller\u00eda Cancilleria
Registro Civil Propiedad Mercantil
Inacif PNC SAT
Contralor\u00eda General Cuentas
Tribunal Supremo Electoral
C\u00f3digo Penal Civil Procesal Mercantil Trabajo Municipal
Ley Amparo Exhibici\u00f3n Exhibicion Personal Constitucionalidad
C\u00f3digo Procesal Penal Civil Mercantil
Ley Organismo Judicial Contencioso Administrativo
"""

EXPAND_BLOCKS = (
    B_CIVIL_EXPAND,
    B_PENAL_EXPAND,
    B_PROC_EXPAND,
    B_CONST_EXPAND,
    B_ADMIN_EXPAND,
    B_MERC_EXPAND,
    B_LAB_EXPAND,
    B_INT_EXPAND,
    B_LATIN_EXPAND,
    B_GT_EXPAND,
)

ROOTS = [
    "jurid", "legal", "legisl", "judicial", "jurisdicc", "jurisprudencial",
    "proces", "procediment", "procesal", "penal", "civil", "mercantil",
    "laboral", "administrativ", "constitucional", "contractual", "contract",
    "obligatori", "oblig", "demand", "sentenc", "resolutori", "apelatori",
    "casacion", "casatori", "ampar", "embarg", "ejecutori", "notificatori",
    "citatori", "emplaz", "prescript", "usucap", "heredit", "testament",
    "sucesori", "matrimoni", "divorci", "filiatori", "adoptiv", "indemnizatori",
    "resarcitori", "delictiv", "criminal", "culpabil", "imputabil",
    "antijuridic", "tipic", "culpos", "imprudent", "negligent", "prevaric",
    "malvers", "extors", "estaf", "usurp", "feminicid", "homicid", "parricid",
    "secuest", "extradic", "expropiatori", "concesionari", "licitatori",
    "adjudicatari", "impugnatori", "cautelar", "sustantiv", "normativ",
    "reglamentari", "estatutari", "promulgatori", "derogatori", "notarial",
    "registral", "societari", "concursal", "hipotecari", "prendari",
    "fiduciari", "fideicomis", "arrendatic", "posesori", "reivindicatori",
    "interdictal", "arbitr", "conciliatori", "mediatori", "diplomat",
    "consular", "internacional", "supranacional", "comunitari", "convencional",
    "tratad", "ratificatori", "penalist", "civilist", "constitucionalist",
    "administrativist", "laboralist", "mercantilist", "internacionalist",
    "accionari", "endosatori", "avalist", "asegur", "reasegur", "coasegur",
    "sindicat", "patronal", "obrero", "salarial", "prestacion", "convencional",
    "contencios", "expropiatori", "concesion", "licitatori", "funcionari",
    "inconstitucional", "supremaci", "soberan", "garant", "convencional",
    "reformatori", "constituyent", "querell", "imput", "proces", "conden",
    "absuelt", "fiscal", "procurador", "magistr", "juzg", "tribunal",
    "litispend", "conex", "prejudicial", "competenc", "declinatori",
    "inhibitori", "recusatori", "tercer", "allanam", "reconvenc", "acumul",
    "testific", "pericial", "documental", "confesori", "inspecc", "alegatori",
    "notific", "citaci", "secuestro", "remate", "subast", "adjudic", "lanzam",
    "desahuc", "prision", "libertad", "indult", "amnisti", "rehabilit",
    "cohech", "peculad", "corrupc", "narcotraf", "violaci", "tortur",
    "genocid", "terror", "sicari", "trata", "pornografi", "violenci",
    "falsedad", "asociaci", "lavado", "blanqueo", "reincid", "eximent",
    "atenuant", "agravant", "complic", "encubridor", "inductor", "cooper",
    "autori", "tentativ", "consumaci", "frustraci", "continuad", "permanent",
    "nulidad", "anulabil", "simulaci", "consentim", "vici", "error", "lesion",
    "responsabil", "dano", "perjuici", "mora", "incumplim", "rescision",
    "novaci", "compensaci", "remision", "enajenaci", "gravam", "accesi",
    "confusion", "usufruct", "servidumb", "propiedad", "poses", "reivindic",
    "deslinde", "condomini", "copropiedad", "anticres", "comodat", "mutuari",
    "depositari", "mandatari", "fianz", "aval", "albace", "hereder",
    "legatari", "donatari", "albaceazgo", "particion", "colacion", "mejora",
    "desheredaci", "repudiaci", "aceptaci", "tutel", "curatel", "patria",
    "potestad", "aliment", "reconocim", "impugnaci", "matrimonial", "conyugal",
    "separaci", "patrimoni", "caudal", "masa", "bienes", "muebles", "inmuebles",
    "semovient", "hipotec", "prendari", "arrendam", "compravent", "donaci",
    "retrovent", "enajenaci", "clausul", "pacto", "arras", "senal",
    "franquici", "adhesi", "abusiv", "monopoli", "oligopoli", "desleal",
    "quiebr", "insolvenci", "liquidaci", "disoluci", "transformaci",
    "fusion", "escision", "accion", "capital", "junta", "consej", "gerent",
    "apoderad", "chequ", "letra", "pagare", "endos", "protest", "marca",
    "patent", "transport", "segur", "reasegur", "coasegur", "poliz",
    "siniestr", "debentur", "bono", "titul", "valores", "valore",
    "interpret", "hermeneut", "analogi", "equidad", "costumbr", "doctrin",
    "precedent", "ratio", "decidendi", "obiter", "dictum", "stare", "decisis",
    "mandamus", "certiorari", "subpoen", "estoppel", "equit", "writ",
    "affidavit", "exequátur", "habeas", "corpus", "erga", "omnes",
    "convencional", "control", "inconstitucional", "supremaci", "reformatio",
    "peius", "restitutio", "integrum", "onus", "probandi", "bona", "fide",
    "de", "facto", "iure", "ipso", "mutatis", "mutandis", "pacta", "servanda",
    "nullum", "crimen", "nulla", "poena", "ne", "bis", "idem", "in", "dubio",
    "pro", "reo", "operario", "homine", "persona", "libertatis", "favor",
    "nemo", "tenetur", "judex", "causa", "sua", "audi", "alteram", "partem",
    "principi", "legalidad", "reserv", "proporcional", "razonabil", "igualdad",
    "seguridad", "irretroactiv", "jerarqui", "normativ", "piramid", "kelsen",
    "fuente", "teleolog", "sistematic", "literal", "sustantiv", "adjetiv",
    "procesal", "sustantivo", "adjetivo", "sustantiva", "adjetiva",
]

SUFFIXES = [
    dec(s)
    for s in (
        "o", "a", "os", "as", "al", "ales", "ario", "aria", "arios", "arias",
        "ivo", "iva", "ivos", "ivas", "ico", "ica", "icos", "icas",
        "ista", "istas", "ismo", "ismos", "idad", "idades",
        "aci\\u00f3n", "aciones", "encia", "encias", "ente", "entes",
        "ura", "uras", "orio", "oria", "orios", "orias",
        "able", "ables", "ible", "ibles", "mente",
    )
]

PREFIXES = (
    "a", "an", "anti", "auto", "co", "contra", "des", "extra", "in", "inter",
    "intra", "multi", "pre", "post", "re", "sub", "super", "trans", "ultra",
    "infra", "supra", "semi", "pluri", "uni", "bi", "tri", "cuasi", "pseudo",
)


def block_tokens() -> set[str]:
    out: set[str] = set()
    for b in EXPAND_BLOCKS:
        for t in dec(b).split():
            if t:
                out.add(t)
    return out


def morph_expand() -> set[str]:
    out: set[str] = set()
    for root, suf in product(ROOTS, SUFFIXES):
        w = root + suf
        if 4 <= len(w) <= 42 and LETTER.fullmatch(w):
            out.add(w)
    bases = [
        dec("aci\\u00f3n"), "idad", "ismo", "ista", "ario", "aria",
        "ivo", "iva", "al", "ico", "ica", "ente", "ura", "orio", "oria",
    ]
    for pre, base in product(PREFIXES, bases):
        w = pre + base
        if 5 <= len(w) <= 42 and LETTER.fullmatch(w):
            out.add(w)
    return out


def expand_tokens() -> list[str]:
    bag = block_tokens() | morph_expand()
    return sorted(bag)
