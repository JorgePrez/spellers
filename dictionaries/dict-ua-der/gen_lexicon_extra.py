# -*- coding: utf-8 -*-
"""Additional law lemmas for broader coverage. ASCII-safe."""
from __future__ import annotations

import codecs

B1 = r"""
capacidad jur\u00eddica legitimaci\u00f3n activa pasiva
litisconsorcio necesario facultativo
acumulaci\u00f3n objetiva subjetiva
prejudicialidad cosa juzgada material formal
efectos erga omnes inter partes
medida provisional cautelar autosatisfactiva
tutela preventiva inhibitoria resarcitoria
da\u00f1o emergente lucro cesante
da\u00f1o moral extrapatrimonial
responsabilidad objetiva subjetiva solidaria
obligaci\u00f3n solidaria mancomunada divisible
indivisible alternativa facultativa
garant\u00eda personal real
hipoteca abierta cerrada
prenda con desplazamiento sin desplazamiento
fianza solidaria simple
aval bancario
pacto comisorio
cl\u00e1usula penal
arrhes se\u00f1al se\u00f1alamiento
arrendamiento urbano r\u00fastico rustico
comodato precario
dep\u00f3sito deposito irregular regular
secuestro convencional
agencia mediaci\u00f3n corretaje
edici\u00f3n edicion licencia de uso
franquicia mercantil
cuenta corriente mercantil
contrato de seguro p\u00f3liza poliza
siniestro indemnizable
reaseguro coaseguro
letra de cambio aceptaci\u00f3n aceptacion
protesto endoso en procuraci\u00f3n
pagar\u00e9 a la orden
cheque cruzado certificado
t\u00edtulo al portador nominativo
acci\u00f3n preferente ordinaria
obligaci\u00f3n convertible
debenture bono
oferta p\u00fablica de adquisici\u00f3n
opa
fusiones adquisiciones escisiones
transformaci\u00f3n societaria
disoluci\u00f3n liquidaci\u00f3n
sindicatura s\u00edndico sindico
masa concursal acreedores privilegiados
quirografarios
"""

B2 = r"""
tipicidad objetiva subjetiva
error de tipo de prohibici\u00f3n
error vencible invencible
eximentes incompletas
atenuantes gen\u00e9ricas especificas espec\u00edficas
agravantes espec\u00edficas
concurso medial
delito continuado permanente
iter criminis
actos preparatorios ejecutivos
desistimiento abandono
autor\u00eda mediata
inducci\u00f3n cooperaci\u00f3n necesaria
complicidad primaria secundaria
encubrimiento personal real
autoria
pena privativa de libertad
pena de multa
pena accesoria
sustituci\u00f3n de la pena
suspensi\u00f3n condicional
libertad condicional
beneficio penitenciario
redenci\u00f3n de penas
medidas de seguridad
internamiento
libertad vigilada
inhabilitaci\u00f3n absoluta especial
privaci\u00f3n del derecho de conducir
comiso
decomiso
responsabilidad civil derivada del delito
"""

B3 = r"""
debido proceso legal
tutela judicial efectiva
doble instancia
prohibici\u00f3n de reforma peyorativa
reformatio in peius
presunci\u00f3n de inocencia
in dubio pro reo
nemo tenetur
nemo judex in causa sua
audi alteram partem
principio de legalidad
reserva de ley
proporcionalidad
razonabilidad
igualdad ante la ley
seguridad jur\u00eddica
irretroactividad
favor libertatis
favor rei
pro homine
pro persona
convencionalidad
control de convencionalidad
bloque de constitucionalidad
supremac\u00eda constitucional
jerarqu\u00eda normativa
pir\u00e1mide piramide de Kelsen
fuente del derecho
costumbre jurisprudencial
principios generales del derecho
equidad
analog\u00eda analogia legis iuris
interpretaci\u00f3n literal sistem\u00e1tica teleol\u00f3gica
hermen\u00e9utica hermeneutica jur\u00eddica
"""

B4 = r"""
amparo provisional definitivo
exhibici\u00f3n personal
habeas corpus
habeas data
inconstitucionalidad general concreta
opini\u00f3n consultiva
conflicto de competencia
conflicto de jurisdicci\u00f3n
cuesti\u00f3n de inconstitucionalidad
acci\u00f3n de cumplimiento
acci\u00f3n popular
acci\u00f3n de clase
mandamus
injunction
certiorari
writ
subpoena
estoppel
common law
equity
stare decisis
ratio decidendi
obiter dictum
dicta
precedente vinculante persuasivo
"""

EXTRA_BLOCKS = (B1, B2, B3, B4)


def extra_tokens() -> list[str]:
    out: list[str] = []
    for b in EXTRA_BLOCKS:
        out.extend(codecs.decode(b, "unicode_escape").split())
    return out
