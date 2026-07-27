# -*- coding: utf-8 -*-
"""Curated architecture lexicon blocks (ASCII + unicode_escape)."""
from __future__ import annotations

import codecs

B_GENERAL = r"""
arquitectura arquitecto arquitecta arquitectos arquitectas
urbanismo urbanista urbanistas
dise\u00f1o dise\u00f1os proyecto proyectos
obra obras construcci\u00f3n construcciones
edificio edificios edificaci\u00f3n
estructura estructuras instalaci\u00f3n instalaciones
material materiales
representaci\u00f3n planimetr\u00eda axonometr\u00eda
perspectiva maqueta maquetas
normativa normativas reglamento reglamentos
"""

B_HISTORIA = r"""
historia arquitectura
estilo estilos arquitect\u00f3nico arquitect\u00f3nicos
cl\u00e1sico cl\u00e1sica clasicismo
griego romano bizantino
rom\u00e1nico g\u00f3tico g\u00f3tica
renacimiento barroco barroca
neocl\u00e1sico neocl\u00e1sica
modernismo art nouveau
racionalismo funcionalismo
organicismo brutalismo
postmodernismo deconstructivismo
contempor\u00e1neo contempor\u00e1nea
colonial neocolonial
mud\u00e9jar
vern\u00e1culo vernacular
patrimonio patrimonial
monumento monumentos
restauraci\u00f3n rehabilitaci\u00f3n
"""

B_MATERIALES = r"""
material materiales construcci\u00f3n
concreto hormig\u00f3n cemento
acero hierro aluminio
madera maderas laminada
ladrillo ladrillos bloque bloques
piedra piedras m\u00e1rmol granito
vidrio cristal
cer\u00e1mica cer\u00e1micas
yeso estuco
mortero morteros
asfalto pavimento pavimentos
teja tejas
impermeabilizaci\u00f3n aislante aislantes
poliuretano poliestireno
fibra fibra vidrio carbono
pl\u00e1stico pl\u00e1sticos
composite composites
adhesivo adhesivos
anclaje anclajes
"""

B_ESTRUCTURA = r"""
estructura estructural estructurales
estructuraci\u00f3n c\u00e1lculo estructural
viga vigas columna columnas
pilar pilares muro muros
losas losa losa aligerada
cimentaci\u00f3n cimiento cimientos
zapata zapatas pilote pilotes
cadena cadenas cadenas carga
arco arcos b\u00f3veda b\u00f3vedas
c\u00fabiertas cubierta cubiertas
marco marcos p\u00f3rtico p\u00f3rticos
p\u00e9rgola celos\u00eda celos\u00edas
contraviento rigidizaci\u00f3n
flecha deformaci\u00f3n esfuerzo esfuerzos
tensi\u00f3n compresi\u00f3n corte
momento momento flector
carga cargas sobrecarga
sismo s\u00edsmico s\u00edsmica
resistencia resistencia sismorresistente
n\u00facleo n\u00facleos rigidez
"""

B_INSTALACIONES = r"""
instalaci\u00f3n instalaciones
instalaci\u00f3n el\u00e9ctrica hidr\u00e1ulica
instalaci\u00f3n sanitaria
instalaci\u00f3n climatizaci\u00f3n
instalaci\u00f3n gas
plomer\u00eda fontaner\u00eda
electricidad cableado
tablero tableros el\u00e9ctricos
tomacorriente tomacorrientes
iluminaci\u00f3n luminaria luminarias
ventilaci\u00f3n extracci\u00f3n
aire acondicionado
calefacci\u00f3n caldera calderas
bomba bombas tuber\u00eda tuber\u00edas
desag\u00fce desag\u00fces
tanque tanques cisterna cisternas
hidrante hidrantes
incendio protecci\u00f3n incendios
detecci\u00f3n humo
"""

B_URBANISMO = r"""
urbanismo urban\u00edstico urban\u00edstica
urbanizaci\u00f3n urbanizaciones
planificaci\u00f3n territorial
ordenamiento territorial
zona zonas urbanas rurales
\u00e1rea \u00e1reas verdes
parque parques plazas
peatonal peatonalizaci\u00f3n
vial vialidad calle calles
avenida avenidas boulevard
manzana manzanas lote lotes
\u00edndice \u00edndices construcci\u00f3n
\u00edndice \u00edndice ocupaci\u00f3n suelo
\u00edndice \u00edndice utilizaci\u00f3n
\u00edndice \u00edndice habitabilidad
\u00edndice \u00edndice densidad
\u00edndice \u00edndice permeabilidad
\u00edndice \u00edndice vegetaci\u00f3n
retiro retiros
franja franjas protecci\u00f3n
\u00e1rea \u00e1rea influencia
\u00e1rea \u00e1rea conservaci\u00f3n
\u00e1rea \u00e1rea protecci\u00f3n
\u00e1rea \u00e1rea restringida
\u00e1rea \u00e1rea libre
"""

B_REPRESENTACION = r"""
representaci\u00f3n gr\u00e1fica
planimetr\u00eda planimetr\u00edas
axonometr\u00eda axonometr\u00edas
isometr\u00eda isometr\u00edas
perspectiva perspectivas
alzado alzados secci\u00f3n secciones
planta plantas
corte cortes detalle detalles
escala escalas gr\u00e1ficas
acotaci\u00f3n acotaciones
simbolizaci\u00f3n leyenda leyendas
plano planos dibujo dibujos
croquis boceto bocetos
render renders renderizado
modelado tridimensional
BIM CAD
maqueta maquetas maquetismo
"""

B_CONSTRUCCION = r"""
construcci\u00f3n construcciones
obra obras cante cante obra
contratista contratistas
supervisi\u00f3n fiscalizaci\u00f3n
presupuesto presupuestos
licitaci\u00f3n licitaciones
pliego pliegos
cronograma cronogramas
metrado metrados
excavaci\u00f3n excavaciones
relleno compactaci\u00f3n
encofrado encofrados
apuntalamiento
colocaci\u00f3n armado armado acero
vaciado vaciado concreto
curado concreto
impermeabilizaci\u00f3n
acabado acabados
pintura pinturas
revestimiento revestimientos
tabiquer\u00eda tabique tabiques
cielo cielo falso
piso pisos loseta losetas
"""

B_NORMATIVA = r"""
normativa normativas reglamento reglamentos
c\u00f3digo c\u00f3digos construcci\u00f3n
norma normas t\u00e9cnicas
especificaci\u00f3n especificaciones
ac\u00e1pite ac\u00e1pites
cap\u00edtulo cap\u00edtulos
art\u00edculo art\u00edculos
inciso incisos
cumplimiento incumplimiento
permiso permisos licencia licencias
uso suelo
\u00edndice \u00edndices urban\u00edsticos
retiro retiros
\u00e1rea \u00e1rea libre
\u00e1rea \u00e1rea verde
accesibilidad accesible
rampa rampas barandilla barandillas
\u00e1rea \u00e1rea parqueo
estacionamiento estacionamientos
evacuaci\u00f3n rutas evacuaci\u00f3n
sismo s\u00edsmico resistencia
"""

B_DISENO = r"""
dise\u00f1o arquitect\u00f3nico
concepto conceptos parti
programa programas arquitect\u00f3nicos
funcionalidad funcional
circulaci\u00f3n circulaciones
ventilaci\u00f3n natural iluminaci\u00f3n natural
orientaci\u00f3n solar sombra
microclima bioclim\u00e1tico bioclim\u00e1tica
sustentabilidad sustentable
eficiencia energ\u00e9tica
certificaci\u00f3n LEED
huella huella carbono
reciclaje reutilizaci\u00f3n
espacio espacios volumen vol\u00famenes
proporci\u00f3n proporciones escala
ritmo ritmos textura texturas
materialidad
"""

B_GT = r"""
Guatemala guatemalteco guatemalteca
Municipalidad Municipalidades
Ministerio Comunicaciones Infraestructura Vivienda
Reglamento Reglamento Construcci\u00f3n
Plan Plan Ordenamiento Territorial
CONAP
INSIVUMEH
INTA
COPRAQ
Colegio Profesional Arquitectos Guatemala
Antigua Guatemala
"""

B_ELEMENTOS = r"""
fachada fachadas
muro muros tabique tabiques
ventana ventanas puerta puertas
vanos vano
balc\u00f3n balcones terraza terrazas
escalera escaleras rampa rampas
barandilla barandillas pasamanos
cielo cielo falso
mamposter\u00eda mamposter\u00edas
enlucido enlucidos
cornisa cornisas
front\u00f3n frontones
arco arcos columnata columnatas
atrio atrios patio patios
galer\u00eda galer\u00edas corredor corredores
"""

B_PAISAJE = r"""
paisaje paisajes paisajismo paisajista
jard\u00edn jardines jardiner\u00eda
vegetaci\u00f3n arborizaci\u00f3n
topograf\u00eda topogr\u00e1fico topogr\u00e1fica
curva curvas nivel
cota cotas altimetr\u00eda
talud taludes terrapl\u00e9n terrapl\u00e9n
desnivel desniveles
pendiente pendientes
drenaje drenajes
captaci\u00f3n aguas lluvia
biojard\u00edn biojardines
"""

B_SUSTENTABLE = r"""
sustentabilidad sustentable
eficiencia eficiencia energ\u00e9tica
certificaci\u00f3n certificaciones LEED
huella huella carbono
huella huella h\u00eddrica
reciclaje reutilizaci\u00f3n
bioclim\u00e1tico bioclim\u00e1tica
pasiva pasivo pasiva
ventilaci\u00f3n ventilaci\u00f3n cruzada
iluminaci\u00f3n iluminaci\u00f3n natural
orientaci\u00f3n orientaci\u00f3n solar
sombra sombras protecci\u00f3n solar
microclima microclimas
aislamiento aislamiento t\u00e9rmico ac\u00fastico
"""

B_EXTRA = r"""
anteproyecto anteproyectos
proyecto proyectos ejecutivo
memoria memoria descriptiva
memoria memoria c\u00e1lculo
plano planos ubicaci\u00f3n
plano planos conjunto
plano planos arquitect\u00f3nicos
plano planos estructurales
plano planos instalaciones
especificaci\u00f3n especificaciones t\u00e9cnicas
partida partidas presupuesto
apu an\u00e1lisis precios unitarios
rendimiento rendimientos jornal
jornal jornales
mano mano obra
equipo equipos maquinaria
subcontrato subcontratos
fianza fianzas cumplimiento
anticipo anticipos
amortizaci\u00f3n amortizaciones
liquidaci\u00f3n liquidaciones obra
recepci\u00f3n recepciones provisional definitiva
punch list
habilitaci\u00f3n habilitaciones
tr\u00e1mite tr\u00e1mites municipales
uso uso suelo
zonificaci\u00f3n zonificaciones
\u00edndice \u00edndice construcci\u00f3n
\u00edndice \u00edndice ocupaci\u00f3n
\u00edndice \u00edndice utilizaci\u00f3n
\u00edndice \u00edndice habitabilidad
\u00edndice \u00edndice densidad
\u00edndice \u00edndice permeabilidad
\u00edndice \u00edndice vegetaci\u00f3n
retiro retiros laterales frontales
franja franjas protecci\u00f3n
\u00e1rea \u00e1rea libre
\u00e1rea \u00e1rea verde
\u00e1rea \u00e1rea parqueo
estacionamiento estacionamientos
rampa rampas acceso
accesibilidad accesible universal
ancho ancho pasillos
altura altura libre
escalera escaleras evacuaci\u00f3n
salida salidas emergencia
extintor extintores
hidrante hidrantes
detecci\u00f3n detecci\u00f3n humo
alarma alarmas incendio
sistema sistema rociadores
resistencia resistencia fuego
compartimentaci\u00f3n compartimentaciones
"""


B_FORCE_UA = r"""
voladizo voladizos arquitrabe arquitrabes dintel dinteles hastial hastiales contrafuerte contrafuertes cornisa cornisas front\u00f3n frontones
"""

BLOCKS = (B_FORCE_UA, 
    B_GENERAL,
    B_HISTORIA,
    B_MATERIALES,
    B_ESTRUCTURA,
    B_INSTALACIONES,
    B_URBANISMO,
    B_REPRESENTACION,
    B_CONSTRUCCION,
    B_NORMATIVA,
    B_DISENO,
    B_GT,
    B_ELEMENTOS,
    B_PAISAJE,
    B_SUSTENTABLE,
    B_EXTRA,
)


def tokens(escaped_block: str) -> list[str]:
    return [t for t in codecs.decode(escaped_block, "unicode_escape").split() if t]


def all_tokens() -> list[str]:
    out: list[str] = []
    for b in BLOCKS:
        out.extend(tokens(b))
    return out
