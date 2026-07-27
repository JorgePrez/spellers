# -*- coding: utf-8 -*-
"""Curated economics lexicon blocks (ASCII + unicode_escape)."""
from __future__ import annotations

import codecs

B_GENERAL = r"""
econom\u00eda economista economistas
ciencias econ\u00f3micas
microeconom\u00eda macroeconom\u00eda
econometr\u00eda finanzas contabilidad
comercio internacional
pol\u00edtica monetaria pol\u00edtica fiscal
banca mercados desarrollo
recurso recursos escasez oportunidad
utilidad preferencia consumidor consumidores
productor productores oferta demanda
equilibrio desequilibrio mercado mercados
precio precios costo costos
ingreso ingresos renta rentas
capital trabajo tierra
factor factores producci\u00f3n
bien bienes servicio servicios
valor agregado
elasticidad inelasticidad
sustituibilidad complementariedad
eficiencia ineficiencia
asignaci\u00f3n distribuci\u00f3n
bienestar social
externalidad externalidades
falla fallas mercado
informaci\u00f3n asim\u00e9trica
agente agentes agente econ\u00f3mico
racionalidad racional
expectativa expectativas
"""

B_MICRO = r"""
microeconom\u00eda microecon\u00f3mico microecon\u00f3mica
utilidad marginal
producto productos marginal
costo marginal costo promedio
costo fijo costo variable
econom\u00eda escala
rendimientos decrecientes
isoquanta isocosto
curva curvas indiferencia
presupuesto restricci\u00f3n presupuestaria
maximizaci\u00f3n minimizaci\u00f3n
monopolio monopolista monopsonio
oligopolio competencia perfecta
competencia imperfecta
duopolio cartel cartelizaci\u00f3n
barrera barreras entrada
poder poder mercado
discriminaci\u00f3n precios
elasticidad precio ingreso cruzada
excedente excedente consumidor
excedente productor
deadweight p\u00e9rdida peso muerto
bien bien p\u00fablico
bien bien privado
bien bien com\u00fan
free rider
se\u00f1alizaci\u00f3n screening
selecci\u00f3n adversa
riesgo moral
contrato contratos
principal agente
"""

B_MACRO = r"""
macroeconom\u00eda macroecon\u00f3mico macroecon\u00f3mica
producto producto interno bruto
PIB PNB
crecimiento econ\u00f3mico
ciclo ciclos econ\u00f3mico
recesi\u00f3n depresi\u00f3n
expansi\u00f3n auge
desempleo subempleo
inflaci\u00f3n deflaci\u00f3n
estanflaci\u00f3n hiperinflaci\u00f3n
tasa tasas inter\u00e9s
pol\u00edtica monetaria pol\u00edtica fiscal
pol\u00edtica cambiaria
multiplicador multiplicador fiscal
multiplicador monetario
curva curva Phillips
curva curva Laffer
curva curva rendimiento
oferta agregada demanda agregada
equilibrio general
modelo modelos macroecon\u00f3micos
keynesianismo monetarismo
austeridad est\u00edmulo fiscal
d\u00e9ficit super\u00e1vit
deuda deuda p\u00fablica
balanza pagos
tipo tipo cambio
paridad poder adquisitivo
"""

B_FINANZAS = r"""
finanzas financiero financiera financieros financieras
financiamiento financiar
inversi\u00f3n inversiones inversionista
inversionistas portafolio portafolios
diversificaci\u00f3n riesgo retorno
valor valor presente neto
tasa tasa descuento
tasa tasa interna retorno
flujo flujos efectivo
capital capitalizaci\u00f3n
accionista accionistas acci\u00f3n acciones
bono bonos obligaci\u00f3n obligaciones
renta renta fija renta variable
dividendo dividendos cup\u00f3n
mercado mercados capitales
mercado mercado primario secundario
bolsa bolsa valores
\u00edndice \u00edndices burs\u00e1tiles
derivado derivados
futuro futuros forward forwards
swap swaps opciones
cobertura hedge hedging
arbitraje especulaci\u00f3n
volatilidad liquidez solvencia
apalancamiento endeudamiento
ratio ratios financieros
liquidez solvencia rentabilidad
"""

B_CONTABILIDAD = r"""
contabilidad contable contables
contador contadora contadores
libro libros contables
diario mayor balance
estado estados financieros
balance general situaci\u00f3n financiera
estado estado resultados
flujo flujo efectivo
patrimonio capital contable
activo activos pasivo pasivos
cuenta cuentas contables
asiento asientos partida partida doble
debe haber
depreciaci\u00f3n amortizaci\u00f3n
provisi\u00f3n provisiones
devengo devengado
acumulado acumulada
costo costo ventas
inventario inventarios existencias
cuentas cuentas cobrar pagar
activo activo corriente fijo
pasivo pasivo corriente largo plazo
utilidad p\u00e9rdida
margen margen bruto neto
auditor\u00eda auditor auditora
auditor\u00edas informe informes
normas normas contables
NIIF IFRS GAAP
"""

B_ECONOMETRIA = r"""
econometr\u00eda econom\u00e9trico econom\u00e9trica
econometrista econometristas
modelo modelos econom\u00e9tricos
regresi\u00f3n regresiones
lineal no lineal
m\u00ednimos cuadrados ordinarios
endogeneidad exogeneidad
variable variables dependiente independiente
end\u00f3gena ex\u00f3gena instrumental
instrumento instrumentos
heterocedasticidad autocorrelaci\u00f3n
multicolinealidad
sesgo consistencia eficiencia
estimador estimadores
m\u00e1xima verosimilitud
prueba pruebas hip\u00f3tesis
significancia estad\u00edstica
intervalo intervalos confianza
error error est\u00e1ndar
residuo residuos
serie series tiempo
panel datos
estacionariedad cointegraci\u00f3n
ARIMA VAR VECM
GARCH probit logit
"""

B_COMERCIO = r"""
comercio internacional
exportaci\u00f3n importaci\u00f3n
exportaciones importaciones
exportador importador
balanza balanza comercial
arancel aranceles proteccionismo
libre libre comercio
tratado tratados libre comercio
TLCAN CAFTA DR
integraci\u00f3n integraci\u00f3n econ\u00f3mica
uni\u00f3n aduanera
mercado mercado com\u00fan
ventaja ventajas comparativas
ventaja ventajas absolutas
competitividad
tipo tipo cambio
paridad paridad cambio
devaluaci\u00f3n revaluaci\u00f3n
apreciaci\u00f3n depreciaci\u00f3n
reserva reservas internacionales
d\u00e9ficit super\u00e1vit cuenta corriente
balanza balanza pagos
inversi\u00f3n extranjera directa
globalizaci\u00f3n
cadena cadenas valor
nearshoring offshoring
"""

B_POLITICA = r"""
pol\u00edtica pol\u00edticas econ\u00f3micas
pol\u00edtica pol\u00edtica monetaria
pol\u00edtica pol\u00edtica fiscal
pol\u00edtica pol\u00edtica cambiaria
pol\u00edtica pol\u00edtica comercial
pol\u00edtica pol\u00edtica industrial
pol\u00edtica pol\u00edtica social
banco banco central
tasa tasa referencia
tasa tasa inter\u00e9s
operaci\u00f3n operaciones mercado abierto
encaje encajes legal
reserva reserva fractional
inflaci\u00f3n objetivo
impuesto impuestos tributaci\u00f3n
tributo tributos
IVA ISR
gasto gasto p\u00fablico
presupuesto presupuestos
d\u00e9ficit fiscal super\u00e1vit fiscal
deuda deuda soberana
austeridad est\u00edmulo
reforma reformas fiscal
subsidio subsidios
transferencia transferencias
"""

B_BANCA = r"""
banca bancario bancaria bancarios bancarias
banco bancos banco central
entidad entidades financieras
intermediaci\u00f3n financiera
cr\u00e9dito cr\u00e9ditos pr\u00e9stamo pr\u00e9stamos
tasa tasa inter\u00e9s
inter\u00e9s intereses moratorio
garant\u00eda garant\u00edas colateral
hipoteca hipotecas
fideicomiso fideicomisos
dep\u00f3sito dep\u00f3sitos
captaci\u00f3n colocaci\u00f3n
liquidez solvencia
ratio ratio capital
Basilea Basilea III
regulaci\u00f3n prudencial
supervisi\u00f3n financiera
morosidad cartera cartera vencida
provisionamiento
microcr\u00e9dito microfinanzas
banca banca desarrollo
banca banca inversi\u00f3n
banca banca comercial
cajero cajeros autom\u00e1ticos
transferencia transferencias
"""

B_MERCADOS = r"""
mercado mercados financieros
mercado mercado laboral
mercado mercado bienes
mercado mercado acciones
mercado mercado bonos
mercado mercado divisas
mercado mercado futuros
eficiencia mercado
formaci\u00f3n precios
competencia competencia perfecta
oligopolio monopolio
concentraci\u00f3n mercado
\u00edndice \u00edndices
volatilidad liquidez
spread spreads
bid ask
subasta subastas
clearing liquidaci\u00f3n
custodia custodio
rating calificaci\u00f3n
riesgo riesgo sist\u00e9mico
riesgo riesgo pa\u00eds
riesgo riesgo cr\u00e9dito
riesgo riesgo mercado
"""

B_DESARROLLO = r"""
desarrollo econ\u00f3mico
subdesarrollo subdesarrollado
crecimiento sostenible
pobreza pobreza extrema
desigualdad Gini
indicador indicadores desarrollo
PIB per c\u00e1pita
\u00edndice \u00edndice desarrollo humano
IDH
millennium objetivos desarrollo
sostenibilidad sostenible
cooperaci\u00f3n internacional
ayuda ayuda desarrollo
microcr\u00e9dito microfinanzas
inclusi\u00f3n financiera
formalizaci\u00f3n informalidad
sector sector informal
productividad productividad total factores
capital capital humano
tecnolog\u00eda innovaci\u00f3n
"""

B_GT = r"""
Guatemala guatemalteco guatemalteca
quetzal quetzales
Banguat Banco Guatemala
SAT Superintendencia Administraci\u00f3n Tributaria
IGSS
Ministerio Finanzas
Congreso Rep\u00fablica
Banco Guatemala
Bolsa Valores Nacional
CNBS Comisi\u00f3n Nacional Bancos Seguros
Intendencia Verificaci\u00f3n Especial
IVA ISR
R\u00e9gimen Peque\u00f1o Contribuyente
factura factura especial
NIT
DPI
Patronato
INAB
PRONacom
"""

B_METODO = r"""
metodolog\u00eda metodolog\u00edas
investigaci\u00f3n cuantitativa cualitativa
hip\u00f3tesis variable variables
muestra poblaci\u00f3n
estad\u00edstica descriptiva inferencial
media mediana moda
varianza desviaci\u00f3n est\u00e1ndar
correlaci\u00f3n causalidad
tabla tablas din\u00e1mica
series series tiempo
panel datos
optimizaci\u00f3n programaci\u00f3n lineal
funci\u00f3n funci\u00f3n producci\u00f3n
funci\u00f3n funci\u00f3n costo
funci\u00f3n funci\u00f3n utilidad
derivada derivadas parcial
integral integrales
matriz matrices
vector vectores
"""

B_EMPRESA = r"""
empresa empresas empresarial empresariales
emprendimiento emprendedor emprendedora
gerencia gerencial gerentes
administraci\u00f3n administrativo administrativa
organizaci\u00f3n organizaciones
estructura estructuras organizacional
cadena cadena valor
log\u00edstica log\u00edstico log\u00edstica
cadena cadena suministro
inventario inventarios rotaci\u00f3n
cadena cadena producci\u00f3n
benchmarking outsourcing
franquicia franquicias
fusiones adquisiciones
due diligence
valuaci\u00f3n valuaciones
marca marcas branding
marketing mercadotecnia
segmentaci\u00f3n nicho nichos
propuesta propuesta valor
cadena cadena distribuci\u00f3n
margen margen contribuci\u00f3n
punto punto equilibrio
presupuesto presupuestos maestro
flujo flujo caja
capital capital trabajo
rotaci\u00f3n rotaci\u00f3n activos
"""

B_ESTADISTICA = r"""
estad\u00edstica estad\u00edsticas estad\u00edstico estad\u00edstica
probabilidad probabilidades
distribuci\u00f3n distribuciones
normal binomial Poisson
muestra muestras muestreo
poblaci\u00f3n poblaciones
sesgo varianza covarianza
correlaci\u00f3n correlaciones
regresi\u00f3n regresiones
significancia significancia
intervalo intervalos confianza
contraste contrastes hip\u00f3tesis
valor valor cr\u00edtico
p-valor
chi cuadrado
t Student
F Snedecor
ANOVA
boxplot histograma histogramas
outlier outliers
tendencia tendencias central
dispersi\u00f3n asimetr\u00eda curtosis
"""

B_EXTRA = r"""
activo activo intangible tangible
pasivo pasivo contingente
patrimonio patrimonio neto
capital capital social
reserva reservas legal
utilidad utilidades retenidas
amortizaci\u00f3n amortizaciones
depreciaci\u00f3n depreciaciones
provision provisiones
devengo devengos
conciliaci\u00f3n conciliaciones
cierre cierres contables
auditor auditor\u00eda externa interna
dictamen dict\u00e1menes
estado estado situaci\u00f3n
estado estado cambios patrimonio
estado estado flujos
nota notas revelaci\u00f3n
consolidaci\u00f3n consolidaciones
subsidiaria subsidiarias
matriz matrices consolidadas
goodwill
EBITDA
ROE ROA ROI
WACC
beta alfa
Sharpe Treynor
VaR
mark-to-market
mark-to-model
subasta subastas primarias
colocaci\u00f3n colocaciones
subscripci\u00f3n subscripciones
underwriting
roadshow
prospecto prospectos emisi\u00f3n
emisi\u00f3n emisiones
titularizaci\u00f3n titularizaciones
factoring leasing
forfaiting
confirming
cartas cr\u00e9dito
standby
incoterms
FOB CIF EXW
balanza balanza pagos
cuenta cuenta corriente
cuenta cuenta capital
reservas reservas internacionales
tipo tipo cambio flotante fijo
bandas bandas cambiarias
intervenci\u00f3n intervenciones cambiarias
paridad paridad inter\u00e9s
curva curva rendimiento
bono bono soberano corporativo
cup\u00f3n cupones cero
duration convexidad
spread spread cr\u00e9dito soberano
rating calificaci\u00f3n riesgo
agencia agencias calificadoras
default impago
reestructuraci\u00f3n reestructuraciones
quiebra quiebras insolvencia
concurso concurso acreedores
liquidaci\u00f3n liquidaciones
activo activo l\u00edquido
pasivo pasivo exigible
ratio ratio liquidez
ratio ratio endeudamiento
ratio ratio cobertura
ratio ratio rotaci\u00f3n
margen margen operativo
EBIT
UAFIR
flujo flujo libre
descuento descuentos flujo
valor valor empresa
m\u00faltiplo m\u00faltiplos
PER
"""

BLOCKS = (
    B_GENERAL,
    B_MICRO,
    B_MACRO,
    B_FINANZAS,
    B_CONTABILIDAD,
    B_ECONOMETRIA,
    B_COMERCIO,
    B_POLITICA,
    B_BANCA,
    B_MERCADOS,
    B_DESARROLLO,
    B_GT,
    B_METODO,
    B_EMPRESA,
    B_ESTADISTICA,
    B_EXTRA,
)


def tokens(escaped_block: str) -> list[str]:
    return [t for t in codecs.decode(escaped_block, "unicode_escape").split() if t]


def all_tokens() -> list[str]:
    out: list[str] = []
    for b in BLOCKS:
        out.extend(tokens(b))
    return out
