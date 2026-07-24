# -*- coding: utf-8 -*-
"""Extra odontology lemmas (instruments, clinical, materials). ASCII-safe."""
from __future__ import annotations

import codecs

B_INSTRUMENTAL = r"""
espejo bucal sonda exploradora sonda periodontal
excavador cucharilla tallador bru\u00f1idor brunidor
condensador portaamalgama portamatrix
pinzas de algod\u00f3n algodon
syringa jeringa carpule carpules
anest\u00e9sico anesthesico
fresa fresas diamante carburo tungsteno
piedra de Arkansas
copa de goma cepillo profil\u00e1ctico profilactico
ultrasonido cavitron scaler
limas endod\u00f3nticas endodonticas K H reamer
limas rotatorias niquel titanio
localizador apical motor endod\u00f3ntico endodontico
obturador System B gutapercha termopl\u00e1stica termoplastica
aislamiento dique de goma arcos Young
grapas clamps f\u00f3rceps forceps elevadores
botadores bistur\u00ed bisturi mango de Bard Parker
periostotomo sindesmotomo
legra cureta de Gracey McCall
lima \u00f3sea osea gubia
martillo de mallet osteotomo
motor de implantes torqu\u00edmetro torquimetro
kit quir\u00fargico quirurgico
articulador arco facial
"""

B_CLINICA2 = r"""
odontograma FDI Palmer Universal
cara oclusal vestibular lingual palatina mesial distal
clase Black I II III IV V VI
preparaci\u00f3n preparacion cavitaria
retenci\u00f3n retencion resistencia convergencia
bisel biseles cavosuperficial
aislamiento relativo absoluto
sellado dentinario adhesi\u00f3n adhesion
grabado \u00e1cido acido esmalte dentina
primer adhesivo bonding
fotopolimerizaci\u00f3n fotopolimerizacion
pulido acabado oclusal
ajuste oclusal equilibrado
dimensi\u00f3n vertical de oclusi\u00f3n
relaci\u00f3n c\u00e9ntrica
gu\u00eda canina funci\u00f3n de grupo
interferencia protrusiva laterotrusiva
bruxismo c\u00e9ntrico excentrico exc\u00e9ntrico
placa miorelajante f\u00e9rula de descarga
ferulizaci\u00f3n ferulizacion dental
esplinaje
reimplante dental avulsi\u00f3n avulsion
luxaci\u00f3n luxacion intrusi\u00f3n intrusion extrusi\u00f3n extrusion
fractura coronaria radicular corona ra\u00edz
fractura alveolar
trauma dentoalveolar
urgencias odontol\u00f3gicas odontologicas
dolor odontog\u00e9nico odontogenico
neuralgia del trig\u00e9mino
sinusitis odont\u00f3gena odontogena
comunicaci\u00f3n bucosinusal
"""

B_PROTESIS = r"""
pr\u00f3tesis fija unitaria parcial
corona metal porcelana metalocer\u00e1mica metaloceramica
corona libre de metal zirconio
disilicato de litio Emax
puente Maryland adhesivo
pilar mu\u00f1\u00f3n munon tallado
provisional de acr\u00edlico acrilico
impresi\u00f3n con alginato silicona
vac\u00edado vaciado modelo de trabajo
antagonista articulaci\u00f3n
montaje en articulador
encajonado enmuflado
pr\u00f3tesis total completa
bases de prueba rodillos de cera
prueba de dientes
rebordes alveolares
estabilidad retenci\u00f3n soporte
pr\u00f3tesis parcial removible
ganchos retenedores conectores mayores menores
descanso oclusal
sobredentadura overdenture
attachment atache
barra de Ackerman
pr\u00f3tesis sobre implantes
pilares de cicatrizaci\u00f3n cicatrizacion
transferencia de impresi\u00f3n
"""

B_ORTO = r"""
ortodoncia interceptiva correctiva
brackets met\u00e1licos metalicos cer\u00e1micos ceramicos
autoligado autoligables
arco de alambre nickel titanio acero
ligaduras el\u00e1sticas elasticas met\u00e1licas
bandas molares tubos
expansor Hyrax Haas
disyuntor maxilar
m\u00e1scara mask facial
anclaje esquel\u00e9tico esqueletico miniimplantes
mini tornillos TADS
alineadores invisibles clear aligners
retenedor Hawley Essix
contenci\u00f3n contencion ortod\u00f3ncica ortodoncica
api\u00f1amiento apinamiento diastema diastemas
sobremordida resalte overjet overbite
mordida abierta cruzada profunda
clase Angle I II III
cefalometr\u00eda de Ricketts Steiner
ANB SNA SNB
"""

B_PERIO = r"""
periodoncia b\u00e1sica basica avanzada
\u00edndice indice de placa gingival
profundidad de sondaje
nivel de inserci\u00f3n insercion cl\u00ednica clinica
sangrado al sondaje
furca furcaci\u00f3n furcacion
movilidad Miller
recesiones Cairo Miller
injerto de enc\u00eda libre conectivo
colgajo de Widman modificado
cirug\u00eda \u00f3sea osea resectiva
regeneraci\u00f3n tisular guiada
membrana reabsorbible no reabsorbible
injerto \u00f3seo aut\u00f3geno autogeno
al\u00f3geno alogeno xenoinjerto aloplo\u00e1stico aloplastico
factores de crecimiento PRP PRF
mantenimiento periodontal
"""

MORE_BLOCKS = (
    B_INSTRUMENTAL,
    B_CLINICA2,
    B_PROTESIS,
    B_ORTO,
    B_PERIO,
)


def more_tokens() -> list[str]:
    out: list[str] = []
    for b in MORE_BLOCKS:
        out.extend(codecs.decode(b, "unicode_escape").split())
    return out
