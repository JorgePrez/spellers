# -*- coding: utf-8 -*-
"""Additional lexicon + simple plural expansion helpers."""

MORE_BLOCKS = []

MORE_BLOCKS.append(r"""
afecciones s\u00edntomas antibioticos antibioticos
abscesos adenomas alergias aneurismas anemias angiomas
anticoagulantes antidepresivos antiinflamatorios arritmias
arterias articulaciones biopsias bronquios bronquiolos
c\u00e1lculos c\u00e1nceres carcinomas cardiopat\u00edas cataratas
cicatrices convulsiones dolores edemas embolias enfisemas
enzimas eritrocitos fracturas ganglios gl\u00e1ndulas hematomas
hemorragias hernias infecciones inyecciones leucemias
ligamentos linfomas luxaciones met\u00e1stasis migra\u00f1as
m\u00fasculos neoplasias nervios neumon\u00edas n\u00f3dulos
\u00f3rganos ovarios pacientes plaquetas procedimientos
pr\u00f3tesis ri\u00f1ones senos s\u00edndromes s\u00edntomas
tendones test\u00edculos tratamientos trombosis tumores
\u00falceras v\u00e1lvulas venas v\u00e9rtebras v\u00edsceras
vitaminas v\u00f3mitos antibi\u00f3ticos antihistam\u00ednicos
broncodilatadores corticoides diur\u00e9ticos inmunosupresores
quimioter\u00e1picos sedantes trombol\u00edticos vasodilatadores
vasopresores
""")

MORE_BLOCKS.append(r"""
abdomenoscop\u00eda ablactaci\u00f3n ablaci\u00f3n aborto abortos
abrasiones abrasiones absceso abscessus absortivo acalasia
acantoma acarbosa accesorio accidente accidentes acebutolol
aceitunas acelular acetazolamida acetona acetonemia
acetonuria acetabulum acetabular acetabuloplastia
aciclovir acidemia acidificaci\u00f3n acid\u00f3tico
aclorhidria acinesia acin\u00e9tico acneiforme acolia
acomodaci\u00f3n acomodativo acromioclavicular acr\u00f3mion
act\u00ednica act\u00ednico actinomicosis ac\u00fastico
ac\u00fastica acuidad acupuntura adamantinoma adenoide
adenoides adenoidectom\u00eda adenoma adenomatoso
adenopat\u00eda adenopat\u00edas adenosina adenosina
adherencia adherencias adhesi\u00f3n adhesiolisis
adiadococinesia adipocito adipocitos adipocitoquina
adiposo adiposa adyuvancia aerobiosis aerofagia aerosol
aerosoles afagia afasia af\u00e1sico afasia af\u00e9resis
afibrinogenemia aflatoxina aflujo afonia afrenta
agalactia agammaglobulinemia agar agarosa agenda
agenesia agen\u00e9sico agentaje agglutination aglutinaci\u00f3n
aglutinina aglutininas agnathia agnosia agonista
agonistas agorafobia agranulocito agranulocitos
agregaci\u00f3n agreg\u00f3metro agresividad agresivo
aguda agudo agudos agudas agudeza aguja agujas
airway aix aix aix ala alas alactasia alambrado
alantoico albahaca albendazol albinismo albino
albug\u00ednea alb\u00famina albuminuria albuterol alcalemia
alcalinizaci\u00f3n alcalino alcalinos alcalosis
alcal\u00f3tico alcances alcantarillado alcohol
alcoh\u00f3lico alcoh\u00f3lica alcoholismo aldeh\u00eddo
aldosterona aldosteronismo alejamiento alelo alelos
alendronato alerta alertas alfabloqueador
alfafetoprote\u00edna alfainterfer\u00f3n alfametildopa
algalia algias algodonosa algoritmo algoritmos
alianza alienaci\u00f3n alimentaci\u00f3n alimento
alimentos alisinasa alitretino\u00edna alivio alivios
almacenamiento alojamiento alopecia alopecia
alotrasplante alprazolam alprostadil alta altas
alteraci\u00f3n alteraciones alternancia alternativa
alternativas altitud altura alturas alveolar
alv\u00e9olo alv\u00e9olos alzh\u00e9imer amantadina
amaurosis ambulatorio ambulatoria ambulatorios
ameba amebas amebiasis amebicida amebicidas
ameloblastoma amenaza amenazas amenorrea amiba
amigdala am\u00edgdala am\u00edgdalas amigdalectom\u00eda
amigdalitis amikacina amiloide amiloidosis
amilasa amilorida amino\u00e1cido amino\u00e1cidos
aminofilina aminogluc\u00f3sido aminogluc\u00f3sidos
aminotransferasa amiodarona amitriptilina amnesia
amniocentesis amnios amnioscopia amni\u00f3tico
amni\u00f3tica amoebicida amoxicilina ampolla
ampollas ampolloso amputaci\u00f3n amputaciones
amputado amputada amygdalina an\u00e1bolico
anabolismo an\u00e1lisis an\u00e1lisis an\u00e1logo
an\u00e1logos anafilaxia anafil\u00e1ctico
anafil\u00e1ctica an\u00e1geno anal anales
analg\u00e9sia analg\u00e9sico analg\u00e9sicos
analista anamnesis anastomosis an\u00e1tomo
anatomopatolog\u00eda anatomopatol\u00f3gico
anca anclas ancla ancrod andamiaje andamiajes
androblastoma androgenizaci\u00f3n andr\u00f3geno
andr\u00f3genos androlog\u00eda andropausia
anecd\u00f3tico anectina anemia anemias an\u00e9mico
an\u00e9mica anencefalia anesthesiolog\u00eda
aneurisma aneurismas aneurism\u00e1tico
aneurism\u00e1tica anexectom\u00eda anexo anexos
anexial anexiales anfetamina anfetaminas
anfibio anfibios anflog\u00edstico anflog\u00edstica
anfolito anfolitos anfotericina \u00e1ngel
angiitis angina anginal angioedema
angiog\u00e9nesis angiograf\u00eda angiograma
angiomas angiomatosis angiopat\u00eda
angioplastia angiosarcoma angiotensina
angiotomograf\u00eda \u00e1ngulo \u00e1ngulos
anhidrasa anhidrosis anhidro anhidros
anilina anilinas animal animales animaci\u00f3n
anisocitosis anisocoria anisometrop\u00eda
anisotrop\u00eda ankilostomiasis anociaci\u00f3n
anomal\u00eda anomal\u00edas an\u00f3malo an\u00f3mala
anomia an\u00f3mico anopia anopsia anorexia
anor\u00e9xico anor\u00e9xica anosmia anosognosia
anotia anovulaci\u00f3n anovulatorio anoxia
an\u00f3xico an\u00f3xica anquilosis anquilosante
antagonista antagonistas ant\u00e1cido ant\u00e1cidos
antag\u00f3nico antebrazo antecedentes anteversi\u00f3n
anti\u00e1cido anti\u00e1cidos antiagregante
antiagregantes antial\u00e9rgico antial\u00e9rgicos
antian\u00e9mico antian\u00e9micos antianginoso
antianginosos antiarr\u00edtmico antiarr\u00edtmicos
antibacteriano antibacterianos antibiograma
antibi\u00f3tico antibi\u00f3ticos anticolin\u00e9rgico
anticolin\u00e9rgicos anticoagulaci\u00f3n
anticoagulante anticoagulantes anticoncepci\u00f3n
anticonceptivo anticonceptivos anticonvulsivante
anticonvulsivantes antidepresivo antidepresivos
antidiab\u00e9tico antidiab\u00e9ticos antidiarreico
antidiarreicos antidiur\u00e9tico antidiur\u00e9ticos
ant\u00eddoto ant\u00eddotos antiem\u00e9tico antiem\u00e9ticos
antiepil\u00e9ptico antiepil\u00e9pticos antif\u00fangico
antif\u00fangicos ant\u00edgeno ant\u00edgenos
antihistam\u00ednico antihistam\u00ednicos
antihipertensivo antihipertensivos
antiinflamatorio antiinflamatorios
antimal\u00e1rico antimal\u00e1ricos antimetabolito
antimetabolitos antimicrobiano antimicrobianos
antimic\u00f3tico antimic\u00f3ticos antimit\u00f3tico
antimit\u00f3ticos antineopl\u00e1sico
antineopl\u00e1sicos antiparasitario
antiparasitarios antiplaquetario
antiplaquetarios antiprotozoario
antiprotozoarios antipruriginoso
antipruriginosos antipir\u00e9tico antipir\u00e9ticos
antipsic\u00f3tico antipsic\u00f3ticos antirretroviral
antirretrovirales antis\u00e9ptico antis\u00e9pticos
antitiroideo antitiroideos antitromb\u00f3tico
antitromb\u00f3ticos antituberculoso
antituberculosos antit\u00fasigeno antit\u00fasigenos
antitus\u00edgeno antitus\u00edgenos antiviral
antivirales antiv\u00edrico antiv\u00edricos
antracosis \u00e1ntrax antral antro antropometr\u00eda
antroscopia anuria an\u00farico an\u00farica
anxiol\u00edtico anxiol\u00edticos aorta aortas
a\u00f3rtico a\u00f3rtica aortitis aortograf\u00eda
aortoplastia apat\u00eda ap\u00e1tico ap\u00e1tica
apendicectom\u00eda apendicitis ap\u00e9ndice
ap\u00e9ndices apertura aperturas apertura
\u00e1pex \u00e1pices \u00e1pex aphonia aplasia
apl\u00e1sico apl\u00e1sica apnea apneas
apn\u00e9ico apn\u00e9ica apocrino apocrina
apofisis ap\u00f3fisis aponeurosis apoptosis
apopt\u00f3tico apopt\u00f3tica aparato aparatos
aparato apparato apariencia apariencias
aparici\u00f3n apariciones apariencia
aparente aparentes apartamento aperitivo
apertura apertura apertura apertura
\u00e1pex \u00e1pex
""")

# Clean-ish clinical expansion without too much garbage
MORE_BLOCKS.append(r"""
acetabuloplastia acomodativo acromioclavicular act\u00ednica
ac\u00fastico adenoidectom\u00eda adenosina adhesiolisis
adiadococinesia adipocito adipocitos aerosol aerosoles
afasia af\u00e9resis aglutinaci\u00f3n aglutinina aglutininas
agonista agonistas agranulocito alendronato alfafetoprote\u00edna
alopecia alprazolam amantadina amikacina amino\u00e1cido
amino\u00e1cidos aminofilina aminogluc\u00f3sido aminogluc\u00f3sidos
amiodarona amitriptilina amni\u00f3tico amni\u00f3tica
anabolismo anafil\u00e1ctico anafil\u00e1ctica an\u00e1lisis
an\u00e1logo an\u00e1logos anexectom\u00eda anexial anfetamina
angiog\u00e9nesis angiograma angiomatosis angiosarcoma
angiotensina anhidrosis anisocitosis anomal\u00eda anomal\u00edas
anosognosia anovulaci\u00f3n anovulatorio antagonista
antagonistas ant\u00e1cido ant\u00e1cidos antiagregante
antiagregantes antial\u00e9rgico antian\u00e9mico antianginoso
antibacteriano anticolin\u00e9rgico antidiab\u00e9tico
antidiarreico antidiur\u00e9tico antiepil\u00e9ptico
antif\u00fangico antimal\u00e1rico antimetabolito
antiplaquetario antiprotozoario antipruriginoso
antitromb\u00f3tico antituberculoso antitus\u00edgeno
antracosis antropometr\u00eda antroscopia anxiol\u00edtico
apat\u00eda apocrino apoptosis apopt\u00f3tico
aparato aparatos apertura aperturas
aripiprazol arterioplastia arteriotom\u00eda
articulaci\u00f3n articulares asistolia astenopia
astrocito astrocitos atenolol atomoxetina
atorvastatina atracurio atrio atrios
atropina auditory audici\u00f3n auditivo auditiva
aur\u00edcula aur\u00edculas autoanticuerpo autoanticuerpos
autoexploraci\u00f3n autoinjerto autoinjertos
autolisis aut\u00f3lisis autopsia autopsias
axilares axilas azoospermia azotemia aztreonam
bacilo bacilos bacteriemia bactericida
bacteriolog\u00eda bacteriost\u00e1tico bacteroides
bal\u00f3n balones barbit\u00farico barbit\u00faricos
bas\u00f3filo bas\u00f3filos benzodiacepina
benzodiacepinas betaagonista betaagonistas
betabloqueador betabloqueadores bicarbonato
bifosfonato bifosfonatos bilirrubinemia
bioequivalencia biomarcador biomarcadores
biopsia biopsias biotina bisoprolol
blefaroplastia bloqueador bloqueadores
bloqueo bloqueos bolo bolos bomba bombas
bradicardia bradilalia bradipnea broncoaspiraci\u00f3n
broncoalveolar broncoconstricci\u00f3n broncodilataci\u00f3n
broncoespasmo bronconeumon\u00eda broncopulmonar
broncorrea broncoscopia bronquiectasia
bronquiolo bronquiolos bronquios
bupivaca\u00edna bupropi\u00f3n bypass
calcemia calcificaci\u00f3n calcinosis calciuria
calostro canalizaci\u00f3n candidemia candidiasis
caquexia carcinog\u00e9nesis carcin\u00f3geno
carcinomatosis cardiodesfibrilaci\u00f3n
cardiog\u00e9nico cardiomegalia cardiomiopat\u00eda
cardioprotector cardiorrespiratorio
cardiot\u00f3xico cardiotoxicidad cardioversi\u00f3n
carot\u00eddea carot\u00eddeo carpiano carpianos
cat\u00e9ter cat\u00e9teres cateterismo
cateterizaci\u00f3n cauterizaci\u00f3n
cefalorraqu\u00eddeo cefalea cefaleas
celiotom\u00eda cemento cementos cerebroespinal
cerebrovasculares cervicobraquial ces\u00e1rea
ces\u00e1reas cetoacidosis cetonuria
cian\u00f3tico cian\u00f3tica cicloplej\u00eda
citoquina citoquinas citot\u00f3xico
citotoxicidad clamidia claudicaci\u00f3n
coagulabilidad coagulaci\u00f3n coagulopat\u00eda
colangiograf\u00eda colangiopancreatograf\u00eda
colecistectom\u00eda colelitiasis colesterol
coloides colostom\u00eda colposcopia
comorbilidad comorbilidades complacencia
conizaci\u00f3n contrapulsaci\u00f3n contrapulsador
cordocentesis corea coronariograf\u00eda
corticoterapia craneotom\u00eda crioterapia
crioablaci\u00f3n crioprecipitado cristaloides
cromosoma cromosomas curetaje
dacriocistitis dacrioestenosis dactiloscopia
debridamiento dehiscencia densitometr\u00eda
dermatoglifo desbridamiento descompresi\u00f3n
desfibrilaci\u00f3n desfibrilador deshidrataci\u00f3n
desnutrici\u00f3n diabetes dial\u00edtico diast\u00f3lico
digit\u00e1lico digit\u00e1licos discinesia discinesias
disecci\u00f3n dislipidemias disociaci\u00f3n
dispareunia displasias distocia distocias
divert\u00edculo divert\u00edculos Doppler
drenaje duodenitis ecocardiograma
electrocardiograma electroencefalograma
electromiograma electrofisiolog\u00eda
electrofisiol\u00f3gico embolizaci\u00f3n
embrionario emesis encefalitis
endarterectom\u00eda endoc\u00e1rdico endoscopia
endosc\u00f3pico endotelial enfermer\u00eda
enfermero enfermera enterorrafia
eosin\u00f3filo eosin\u00f3filos ependimoma
epidemiol\u00f3gico epididimitis epidural
epig\u00e1strico epilept\u00f3geno epistaxis
eritrocito eritrocitos eritropoyetina
escler\u00f3tica esfinterotom\u00eda
esofagogastroduodenoscopia esofagorrafia
esofagostom\u00eda esplenectom\u00eda
espondiloartropat\u00eda esputo esputos
esteatorrea estimulaci\u00f3n estradiol
estr\u00f3geno estr\u00f3genos euforia
euvolemia exanguinotransfusi\u00f3n
ex\u00e9resis exocrino exocrina
expectoraci\u00f3n expectorante extracorp\u00f3rea
extracci\u00f3n extracciones extrauterino
fasc\u00edculo fasciculaci\u00f3n fenotipo
fenotipos feocromocitoma fertilidad
fertilizaci\u00f3n fibrilaci\u00f3n fibrin\u00f3geno
fibrinolisis fibrobroncoscopia fibroma
fibromas fibromialgia fibrosis
flebitis flem\u00f3n fluoroscopia f\u00f3rceps
fototerapia fractura fracturas
galactorrea gammapat\u00eda ganglio
ganglios gasometr\u00eda gastrostom\u00eda
genoma gen\u00f3mica genotipo gestaci\u00f3n
gestacional glicemia glomerular
glomeruloesclerosis glucag\u00f3n
glucagonoma gluconeog\u00e9nesis glucosuria
gonadotropina gonadotropinas granulocito
granulocitos gripe hematemesis hemat\u00edes
hematopoyesis hematopoy\u00e9tico hemianopsia
hemicr\u00e1nea hemicuerpo hemocromatosis
hemodi\u00e1lisis hemodin\u00e1mico hemodin\u00e1mica
hemofilia hemoglobina hem\u00f3lisis
hemoperitoneo hemoptisis hemorragia
hemorr\u00e1gico hemostasia hemost\u00e1tico
heparina hepatitis hepatocarcinoma
hepatocito hepatomegalia hepatoesplenomegalia
hepatotoxicidad hernia herniorrafia
herpesvirus hidatidosis hidrocefalia
hidrocele hidronefrosis hidropes\u00eda
hidroterapia hiperacusia hiperactividad
hiperbilirrubinemia hipercalcemia
hipercapnia hipercolesterolemia
hiperglucemia hiperhidrosis hiperinsulinemia
hiperkalemia hiperlipidemia hipermetrop\u00eda
hipernatremia hiperparatiroidismo
hiperplasia hiperprolactinemia
hipertensi\u00f3n hipertermia hipertiroidismo
hipertrofia hiperuricemia hiperventilaci\u00f3n
hipervolemia hipoacusia hipocalcemia
hipocapnia hipoglucemia hipogonadismo
hipokalemia hipomagnesemia hiponatremia
hipoparatiroidismo hipopituitarismo
hipotensi\u00f3n hipotermia hipotiroidismo
hipovolemia hipoxemia hipoxia
histerectom\u00eda histeroscopia histiocitosis
histolog\u00eda histol\u00f3gico histoplasmosis
homeostasis homeost\u00e1tico hormonoterapia
hospitalizaci\u00f3n iatrog\u00e9nico iatrogenia
ictericia ictus ileostom\u00eda ile\u00edtis
imagenolog\u00eda inmunizaci\u00f3n
inmunocomprometido inmunodeficiencia
inmunoglobulina inmunosupresi\u00f3n
inmunoterapia inotropismo in\u00f3tropo
insuficiencia intensivo interconsulta
interconsultas intubaci\u00f3n intususcepci\u00f3n
isquemia queratina queratitis
queratoplastia labio legrado leiomioma
leishmaniasis leptospirosis leucemia
leucocitosis leucoencefalopat\u00eda
leucopenia leucorrea linfangitis
linfedema linfocitosis linfoma lipasa
litiasis lobectom\u00eda lordosis lumbalgia
lupus luxaci\u00f3n macroadenoma macrocitosis
malabsorci\u00f3n malformaci\u00f3n mamograf\u00eda
mastectom\u00eda mastitis mastoiditis
mediastinitis mediastino megacolon
melanoma melena meningitis meningioma
meningocele meningoencefalitis menopausia
menorragia metaplasia metotrexato
miastenia microalbuminuria microangiopat\u00eda
microcitosis microhematuria microlitiasis
midriasis mielitis mielodisplasia
mielofibrosis mielograma mieloma
mielomeningocele mielopat\u00eda migra\u00f1a
miocardiopat\u00eda miocarditis miopat\u00eda
monitorizaci\u00f3n morfolog\u00eda morfol\u00f3gico
mucormicosis mucositis mucoviscidosis
narcolepsia necrosectom\u00eda necrosis
neoplasia nefritis nefrocalcinosis
nefrolitiasis nefropat\u00eda nefrostom\u00eda
nefrot\u00f3xico nefrotoxicidad neumoconiosis
neumolog\u00eda neumon\u00eda neumonitis
neumoperitoneo neumot\u00f3rax neuralgia
neurinoma neuritis neuroblastoma
neurocirug\u00eda neurocisticercosis
neuroendocrino neurofibromatosis
neurol\u00e9ptico neuromielitis neuropat\u00eda
neurotransmisor neurotransmisores
neutropenia nicturia nosocomial nutrici\u00f3n
nutricional obesidad obst\u00e9trico
obstrucci\u00f3n oclusi\u00f3n odontolog\u00eda
oftalmolog\u00eda oftalmoplej\u00eda
oligoastrocitoma oligodendroglioma
oligospermia oliguria omfalitis
oncolog\u00eda onicomicosis onic\u00f3lisis
ooforectom\u00eda orquiepididimitis orquitis
ortopedia ortostatismo osteocondritis
osteodistrofia osteomalacia osteomielitis
osteopenia osteoporosis otitis
otorrinolaringolog\u00eda otosclerosis
oxigenoterapia oximetr\u00eda pancitopenia
pancreatitis pancreatoduodenectom\u00eda
panhipopituitarismo panuve\u00edtis
papiledema papiloma paracentesis
paraganglioma paraneopl\u00e1sico paraparesia
paraplej\u00eda parasitolog\u00eda parasitosis
par\u00e1lisis paresia parkinsonismo
parotidectom\u00eda parotiditis patolog\u00eda
patol\u00f3gico pediatr\u00eda peliosis
p\u00e9nfigo pericardiocentesis pericarditis
periodontitis periodontosis periostitis
peritonitis perin\u00e9 petequias
pielonefritis pieloplastia piomiositis
placenta placentaria plasmaf\u00e9resis
pl\u00e1stica pleocitosis pleurodesis
policitemia polidipsia polimialgia
polimiositis polineuropat\u00eda polipectom\u00eda
poliposis polisomnograf\u00eda polaquiuria
poliuria porfiria posoperatorio
preoperatorio preeclampsia prematuridad
priapismo proctolog\u00eda prolactinoma
proptosis prostatectom\u00eda prostatitis
proteinuria protocolo prurito psoriasis
psiquiatr\u00eda ptosis puerperio pulmonar
p\u00farpura quimioterapia quilot\u00f3rax
quir\u00f3fano quir\u00fargico rabdomi\u00f3lisis
rabdomiosarcoma radiculitis radiculopat\u00eda
radiolog\u00eda radioterapia reanimaci\u00f3n
rectocele reflujo regurgitaci\u00f3n
resecci\u00f3n resonancia retinopat\u00eda
reumatolog\u00eda rinitis rinofima
rinoplastia rinorrea rotaci\u00f3n r\u00fabrica
rub\u00e9ola sacralizaci\u00f3n salpingectom\u00eda
salpingitis sarcoidosis sarcoma sepsis
septicemia serolog\u00eda serositis
sialadenitis sialolitiasis sibilancias
sigmoidoscopia silicosis sinusitis
siringomielia soplo somatostatinoma
somatotropina subluxaci\u00f3n sudoraci\u00f3n
suprarrenalectom\u00eda s\u00edfilis s\u00edncope
s\u00edndrome talasemia tamponamiento
tanatolog\u00eda telangiectasia tendinitis
tenosinovitis teratoma terapia
terap\u00e9utica tetania t\u00e9tanos
timpanocentesis timpanometr\u00eda
timpanoplastia tinnitus tiroidectom\u00eda
tiroiditis tirotoxicosis tonsilectom\u00eda
tonsilitis toracentesis toracocentesis
toracotom\u00eda toxemia toxicolog\u00eda
toxoplasmosis trabeculectom\u00eda
traqueostom\u00eda trasplante traumatolog\u00eda
tromboange\u00edtis trombocitopenia
trombocitosis tromboembolia
tromboembolismo trombofilia
tromboflebitis tromb\u00f3lisis trombosis
tuberculosis tumoraci\u00f3n ultrasonido
ultrasonograf\u00eda ureterolitiasis
ureteroscopia uretritis uretrorragia
urolog\u00eda urticaria uve\u00edtis vaginitis
vaginosis valvulopat\u00eda valvuloplastia
varices varicocele vasculitis
vasoespasmo ventilaci\u00f3n ventilatorio
ventriculostom\u00eda vertebroplastia
v\u00e9rtigo vesiculitis virolog\u00eda
virilizaci\u00f3n vitrectom\u00eda vit\u00edligo
v\u00f3lvulo xerostom\u00eda zoonosis z\u00f3ster
""")


def simple_plurals(words: set[str]) -> set[str]:
    """Generate conservative Spanish plurals for short clinical nouns."""
    out: set[str] = set()
    skip_end = (
        "sis", "tis", "osis", "itis", "oma", "ema", "ura", "ion", "i\u00f3n",
        "ia", "\u00eda", "ez", "is", "us", "um",
    )
    for w in words:
        low = w.lower()
        if len(low) < 4 or len(low) > 28:
            continue
        if any(low.endswith(s) for s in skip_end):
            continue
        if low.endswith("z"):
            out.add(w[:-1] + "ces")
        elif low.endswith(("\u00e1", "\u00e9", "\u00ed", "\u00f3", "\u00fa")):
            out.add(w + "es")
        elif low.endswith(("n", "l", "r", "d", "j", "y")):
            out.add(w + "es")
        elif not low.endswith("s"):
            out.add(w + "s")
    return out
