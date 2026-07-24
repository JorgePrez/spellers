# -*- coding: utf-8 -*-
"""Curated odontology lexicon blocks (ASCII + unicode_escape)."""
from __future__ import annotations

import codecs

# Anatomia oral / craneofacial
B_ANATOMIA = r"""
odontolog\u00eda odontol\u00f3gica odontol\u00f3gico odontol\u00f3gicos odontol\u00f3gicas
odontologo odont\u00f3logo odontologa odont\u00f3loga odontologos odont\u00f3logos
odontologas odont\u00f3logas
diente dientes dentario dentaria dentarios dentarias
dentici\u00f3n denticion denticiones
corona coronas coronario coronal coronales
cuello cervicales ra\u00edz raices ra\u00edces radicular radiculares
\u00e1pice apice \u00e1pices apices apical apicales
c\u00e1mara camara pulpar pulpares pulpa pulpas
conducto conductos radicular endod\u00f3ntico endodontico
esmalte dentina cemento cementario cementarios
periodonto periodontal periodontales periodoncio
ligamento periodontal alveolo alv\u00e9olo alveolos alv\u00e9olos
alveolar alveolares proceso alveolar
enc\u00eda encia enc\u00edas encias gingival gingivales
gingiva mucosa mucosas bucal bucales oral orales
vest\u00edbulo vestibulo vestibular vestibulares
labial labiales lingual linguales palatino palatina
palatinos palatinas paladar
mesial mesiales distal distales oclusal oclusales
incisal incisales proximal proximales interproximal
interproximales facial faciales
incisivo incisivos canino caninos premolar premolares
molar molares cordal cordales
terceros molares muela muelas
maxilar maxilares maxila mand\u00edbula mandibula mandibular
mandibulares c\u00f3ndilo condilo c\u00f3ndilos condilos
c\u00f3ndilo mandibular ATM articulaci\u00f3n temporomandibular
fosa glenoides c\u00e1psula capsula menisco disco articular
m\u00fasculo temporal masetero pterigoideo dig\u00e1strico
hueso hioides ment\u00f3n menton mentoniano
foramen mentoniano infraorbitario mandibular
seno maxilar senos paranasales
gl\u00e1ndula par\u00f3tida submandibular sublingual
saliva salival salivales ducto de Stenon Wharton
nervio trig\u00e9mino dentario inferior alveolar
lingual bucal mentoniano
arco dental arcada arcadas oclusi\u00f3n oclusion
maloclusi\u00f3n maloclusion
"""

B_PATOLOGIA = r"""
caries cariado cariados cariada cariadas
cavitaci\u00f3n cavitacion cavidad cavitaria
pulpitis irreversible reversible hiperemia pulpar
necrosis pulpar gangrena pulpar
absceso abscesos periapical periapicales
periodontitis apical agudo cr\u00f3nico
granuloma periapical quiste radicular
quiste dent\u00edgero dentigero odontog\u00e9nico odontogenico
ameloblastoma odontoma cementoma
gingivitis periodontitis periodontosis
bolsa periodontal bolsas periodontales
sarro c\u00e1lculo calculo dental c\u00e1lculos calculos
placa bacteriana biofilm
recesi\u00f3n recesion gingival
movilidad dental traum\u00e1tica traumatica
halitosis xerostom\u00eda xerostomia
hipersensibilidad dentinaria
atrisi\u00f3n atrision abrasi\u00f3n abrasion
erosi\u00f3n erosion dental abfracci\u00f3n abfraccion
bruxismo brux\u00f3mano bruxomano
apnea obstrucci\u00f3n v\u00eda a\u00e9rea
candidiasis oral leucoplasia eritroplasia
liquen plano oral \u00falcera ulcera aftosa aftas
herpes labial herpesvirus
celulitis facial osteomielitis
pericoronaritis alveolitis seca alveolitis
anquilosis dental hipodoncia hiperdoncia
agenesia dental supernumerario
impactaci\u00f3n impactacion dental retenido retenidos
inclusi\u00f3n inclusion dentaria
fisura labio paladar hendido
malformaci\u00f3n malformacion craneofacial
"""

B_PROCEDIMIENTOS = r"""
odontolog\u00eda preventiva operatoria restauradora
profilaxis tartrectom\u00eda tartrectomia
raspado alisado radicular
curetaje gingival periodontal
endodoncia endod\u00f3ntica endodontica
tratamiento de conductos obturaci\u00f3n obturacion
instrumentaci\u00f3n instrumentacion biomec\u00e1nica biomecanica
limpieza conformaci\u00f3n conformacion
gutapercha cemento sellador
retratamiento endod\u00f3ntico apicectom\u00eda apicectomia
exodoncia extracci\u00f3n extraccion dental
exodoncia simple quir\u00fargica quirurgica
alveoloplastia alveoloplast\u00eda
gingivectom\u00eda gingivectomia gingivoplastia
frenectom\u00eda frenectomia
implante implantes osteointegraci\u00f3n osteointegracion
implantolog\u00eda implantologia
elevaci\u00f3n de seno injerto \u00f3seo oseo
regeneraci\u00f3n regeneracion \u00f3sea osea guiada
pr\u00f3tesis protesis fija removible
corona unitaria puente parcial total
pr\u00f3tesis completa parcial esquel\u00e9tica esqueletica
sobre dentadura sobredentadura
pr\u00f3tesis provisional
incrustaci\u00f3n incrustacion inlay onlay overlay
carilla carillas veneer
composite resina amalgama ion\u00f3mero ionomero
de vidrio cementaci\u00f3n cementacion
ortodoncia brackets alineadores retenedores
aparato aparato fijo removible
expansor disyuntor
cirug\u00eda cirugia oral maxilofacial
osteotom\u00eda osteotomia Le Fort sagital
distracci\u00f3n distraction \u00f3sea
biopsia escisi\u00f3n escision
sutura puntos hemostasia
anestesia local infiltrativa troncular
bloqueo nervioso articaina lidoca\u00edna lidocaina
mepivaca\u00edna mepivacaina bupivaca\u00edna bupivacaina
epinefrina vasoconstrictor
radiograf\u00eda radiografia periapical bitewing
panor\u00e1mica panoramica ortopantomograf\u00eda ortopantomografia
tomograf\u00eda tomografia cone beam CBCT
cefalometr\u00eda cefalometria
blanqueamiento dental fluoraci\u00f3n fluoracion
sellante sellantes fisuras
"""

B_MATERIALES = r"""
amalgama amalgamaci\u00f3n amalgamacion
composite composites fotopolimerizable
resina resinas acr\u00edlica acrilica
ion\u00f3mero ionomero de vidrio
hidr\u00f3xido hidroxido de calcio
MTA mineral tri\u00f3xido trioxido agregado
gutapercha conos de papel
EDTA hipoclorito de sodio
clorhexidina irrigante irrigaci\u00f3n irrigacion
cemento provisional fosfato de zinc
carboxilato ion\u00f3mero resinoso
porcelana cer\u00e1mica ceramica feldesp\u00e1tica feldespatica
zirconio zirconia disilicato de litio
titanio aleaci\u00f3n aleacion cromo cobalto
n\u00edquel niquel cromo oro
alginato hidrocoloide irreversible
silicona polivinilsiloxano pol\u00edetero polietero
yeso piedra tipo III IV
cera de modelar cera utility
algod\u00f3n algodon gasa
dique de goma clamps grapas
matriz porta matriz cu\u00f1a cuna
fresas diamantadas carburo
turbina micromotor contra\u00e1ngulo contraangulo
pieza de mano
"""

B_ESPECIALIDADES = r"""
odontolog\u00eda general pedi\u00e1trica pediatrica
odontopediatr\u00eda odontopediatria
periodoncia periodontolog\u00eda periodontologia
endodoncia endodoncista endodoncistas
prostodoncia pr\u00f3tesis protesis dental
prostodoncista
ortodoncia ortodoncista ortodoncistas
cirug\u00eda oral maxilofacial
cirujano maxilofacial
implantolog\u00eda implantologia oral
odontolog\u00eda est\u00e9tica estetica
odontolog\u00eda preventiva comunitaria
salud p\u00fablica dental
radiolog\u00eda radiologia oral
patolog\u00eda patologia oral
medicina oral
odontolog\u00eda forense
gerodontolog\u00eda gerodontologia
"""

B_CLINICA = r"""
anamnesis historia cl\u00ednica clinica odontogr\u00e1fica odontografica
exploraci\u00f3n exploracion intraoral extraoral
diagn\u00f3stico diagnostico diferencial
pron\u00f3stico pronostico plan de tratamiento
consentimiento informado
odontograma odontograma
notaci\u00f3n notacion FDI Universal Palmer
c\u00f3digo codigo dental
urgencia dental dolor odontalgia
hipersensibilidad
apertura bucal trismus trismo
desviaci\u00f3n desviacion mandibular
chasquido crepitaci\u00f3n crepitacion ATM
ferula f\u00e9rula oclusal estabilizaci\u00f3n estabilizacion
interconsulta remisi\u00f3n remision
bioseguridad asepsia antisepsia
esterilizaci\u00f3n esterilizacion autoclave
EPP guantes mascarilla bata
barreras protectoras
"""

B_EXTRA = r"""
odontog\u00e9nesis odontogenesis ameloblastos odontoblastos
cementoblastos fibroblastos periodontales
membrana de Hertwig
fol\u00edculo foliculo dental saco dentario
erupci\u00f3n erupcion dental riz\u00f3lisis rizolisis
rizog\u00e9nesis rizogenesis
apexificaci\u00f3n apexificacion apexog\u00e9nesis apexogenesis
revascularizaci\u00f3n revascularizacion pulpar
biomateriales regenerativos
gu\u00eda guia quir\u00fargica quirurgica digital
esc\u00e1ner escaner intraoral CAD CAM
impresi\u00f3n impresion digital
flujo digital odontol\u00f3gico
articulador semiajustable
arco facial
registro interoclusal
dimensi\u00f3n vertical
relaci\u00f3n c\u00e9ntrica
gu\u00eda anterior canina
interferencia oclusal
contacto prematuro
"""


BLOCKS = (
    B_ANATOMIA,
    B_PATOLOGIA,
    B_PROCEDIMIENTOS,
    B_MATERIALES,
    B_ESPECIALIDADES,
    B_CLINICA,
    B_EXTRA,
)


def tokens(escaped_block: str) -> list[str]:
    return [t for t in codecs.decode(escaped_block, "unicode_escape").split() if t]


def all_block_tokens() -> list[str]:
    out: list[str] = []
    for b in BLOCKS:
        out.extend(tokens(b))
    return out
