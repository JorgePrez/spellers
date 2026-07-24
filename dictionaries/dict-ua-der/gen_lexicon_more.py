# -*- coding: utf-8 -*-
"""Extra law lemmas. ASCII-safe unicode_escape only."""
from __future__ import annotations

import codecs

B_MORE_PROC = r"""
escritos memoriales memorial
providencia providencias
edicto edictos
c\u00e9dula de notificaci\u00f3n
traslado de la demanda
audiencia de conciliaci\u00f3n
prueba anticipada
diligencias preparatorias
allanamiento a la demanda
reconvenci\u00f3n
acumulaci\u00f3n de procesos
excusa recusaci\u00f3n
inhibitoria declinatoria
tercer\u00eda
embargo precautorio definitivo
remate adjudicaci\u00f3n
lanzamiento desahucio
medidas de seguridad
libertad provisional cauci\u00f3n
fianza carcelaria
indulto amnist\u00eda conmutaci\u00f3n
rehabilitaci\u00f3n
antecedentes penales
certificaci\u00f3n de carencia
fe p\u00fablica
protocolizaci\u00f3n
escritura p\u00fablica
instrumento p\u00fablico
testimonio notarial
legalizaci\u00f3n apostilla
"""

B_MORE_CIVIL = r"""
bienes muebles inmuebles semovientes
patrimonio caudal hereditario
masa hereditaria
colaci\u00f3n hereditaria
mejora leg\u00edtima
desheredaci\u00f3n
aceptaci\u00f3n de herencia
repudiaci\u00f3n
petici\u00f3n de herencia
acci\u00f3n reivindicatoria
acci\u00f3n confesoria negatoria
deslinde amojonamiento
accesi\u00f3n ocupaci\u00f3n
hallazgo tesoro
comunidad de bienes
copropiedad condominio
propiedad horizontal
hipoteca naval a\u00e9rea
anticresis
arrendamiento financiero
franquicia
contrato de adhesi\u00f3n
cl\u00e1usulas abusivas
"""

B_MORE_PENAL = r"""
feminicidio parricidio fratricidio
infanticidio aborto
sicariato sicario
trata de personas
explotaci\u00f3n sexual
pornograf\u00eda infantil
violencia contra la mujer
violencia intrafamiliar
delitos contra el patrimonio
delitos contra la fe p\u00fablica
falsedad ideol\u00f3gica material
uso de documento falso
usurpaci\u00f3n de funciones
asociaci\u00f3n il\u00edcita
terrorismo
genocidio cr\u00edmenes de lesa humanidad
cr\u00edmenes de guerra
desaparici\u00f3n forzada
tortura tratos crueles
"""

B_INST = r"""
Organismo Judicial
Corte Suprema
Sala de Apelaciones
Juzgado de Primera Instancia
Juzgado de Paz
Tribunal de Sentencia
Tribunal de Mayor Riesgo
Corte de Constitucionalidad
Ministerio P\u00fablico
Fiscal\u00eda General
Fiscal\u00eda Distrital
Procuradur\u00eda General
Procuradur\u00eda de Derechos Humanos
Congreso de la Rep\u00fablica
Presidencia de la Rep\u00fablica
Ministerio de Gobernaci\u00f3n
Ministerio de Relaciones Exteriores
Canciller\u00eda
Registro Civil
Registro de la Propiedad
Registro Mercantil
Inacif
PNC
SAT
Contralor\u00eda General de Cuentas
Tribunal Supremo Electoral
"""

MORE_BLOCKS = (B_MORE_PROC, B_MORE_CIVIL, B_MORE_PENAL, B_INST)


def more_tokens() -> list[str]:
    out: list[str] = []
    for b in MORE_BLOCKS:
        out.extend(codecs.decode(b, "unicode_escape").split())
    return out
