# -*- coding: utf-8 -*-
"""Generate attested-style Spanish medical morphology + specialty expansions."""
from __future__ import annotations

import codecs
from itertools import product

# Combining roots (Spanish medical) that commonly accept disease/procedure suffixes
ROOTS = [
    "adeno", "angio", "aorto", "artro", "blefaro", "bronco", "cardio",
    "cerebro", "cervico", "cisto", "colecisto", "colo", "corneo",
    "costo", "crane", "craneal", "dactilo", "dermato", "duodeno",
    "encefalo", "endocardio", "entero", "esofago", "esplen", "estomato",
    "faringo", "flebo", "gastro", "gingivo", "gloso", "hemato", "hemo",
    "hepato", "hidro", "histero", "ileo", "irido", "querato", "laringo",
    "leuco", "linfo", "lipo", "mammo", "masto", "maxilo", "medulo",
    "meningo", "metro", "mio", "miel", "naso", "nefro", "neumo",
    "neuro", "oculo", "oftalmo", "ooforo", "orqui", "osteo", "oto",
    "ovario", "pancreato", "pericardio", "periodonto", "piel", "procto",
    "prostato", "pulmo", "raqui", "recto", "renal", "retino", "rino",
    "salpingo", "sangui", "sinovio", "tiro", "toraco", "traqueo",
    "trombo", "uretero", "uretro", "uro", "utero", "vagino", "vaso",
    "vesico", "vulvo",
]

# Productive suffixes (unicode-escaped where needed)
SUFF_RAW = [
    "itis", "osis", "oma", "emia", "uria", "algia",
    "pat\\u00eda", "scopia", "scop\\u00eda", "tom\\u00eda", "ectom\\u00eda",
    "graf\\u00eda", "plastia", "rragia", "rrea", "penia", "plasia",
    "plej\\u00eda", "paresia", "megalia", "cele", "lisis", "ptosis",
    "centesis", "stom\\u00eda", "desis", "pexia",
]
SUFFIXES = [codecs.decode(s, "unicode_escape") for s in SUFF_RAW]

# Only generate root+suffix for combinations that are typically attested.
# Restrict high-noise suffixes to a smaller root set.
SAFE_FULL = set(
    codecs.decode(s, "unicode_escape")
    for s in (
        "itis", "osis", "oma", "algia", "pat\u00eda", "scopia", "scop\u00eda",
        "tom\u00eda", "ectom\u00eda", "graf\u00eda", "plastia", "megalia",
        "cele", "centesis", "stom\u00eda",
    )
)
SAFE_LIMITED = set(
    codecs.decode(s, "unicode_escape")
    for s in (
        "emia", "uria", "rragia", "rrea", "penia", "plasia", "plej\u00eda",
        "paresia", "lisis", "ptosis", "desis", "pexia",
    )
)
LIMITED_ROOTS = {
    "cardio", "gastro", "hepato", "nefro", "neuro", "hemato", "hemo",
    "neumo", "osteo", "artro", "dermato", "encefalo", "meningo",
    "mio", "angio", "linfo", "leuco", "trombo", "uro", "cisto",
    "histero", "colo", "entero", "bronco", "oftalmo", "oto", "rino",
}


def morph_terms() -> set[str]:
    out: set[str] = set()
    for root, suf in product(ROOTS, SUFFIXES):
        if suf in SAFE_LIMITED and root not in LIMITED_ROOTS:
            continue
        if suf not in SAFE_FULL and suf not in SAFE_LIMITED:
            continue
        w = root + suf
        if 5 <= len(w) <= 40:
            out.add(w)
    return out


# Large curated extras (escaped)
EXTRA_BLOCKS: list[str] = []

EXTRA_BLOCKS.append(r"""
abdomenoscop\u00eda ablactaci\u00f3n ablaci\u00f3n ablaci\u00f3n aborto abortos
abrasiones absceso absortivo acalasia acantoma acarbosa
aciclovir acidemia acidificaci\u00f3n acid\u00f3tico aclorhidria acinesia
acneiforme acolia acomodaci\u00f3n actinomicosis acuidad adamantinoma
adenoide adenoides adenosina adhesi\u00f3n adiadococinesia adipocito
adipocitos adiposo adyuvancia aerobiosis aerofagia aerosol aerosoles
afagia afasia afibrinogenemia aflatoxina agenesia agglutininas
aglutinaci\u00f3n aglutinina agonista agonistas agranulocito
agregaci\u00f3n agudeza aguja agujas alactasia albendazol albinismo
albug\u00ednea albuminuria albuterol alcalemia alcalinizaci\u00f3n
alcalino alcoholismo aldeh\u00eddo aldosterona alelo alelos
alendronato alfabloqueador alfafetoprote\u00edna alfainterfer\u00f3n
algalia alitretino\u00edna alopecia alprazolam alprostadil
alteraci\u00f3n altitud alveolar alv\u00e9olo amantadina amaurosis
ambulatorio ameba amebas amebiasis amebicida ameloblastoma
amenorrea amiba am\u00edgdala amigdalectom\u00eda amigdalitis amikacina
amiloide amiloidosis amilasa amilorida amino\u00e1cido amino\u00e1cidos
aminofilina aminogluc\u00f3sido aminogluc\u00f3sidos amiodarona
amitriptilina amnesia amniocentesis amnios amnioscopia amni\u00f3tico
amoebicida amoxicilina ampolla ampollas amputaci\u00f3n amputado
anabolismo anafilaxia anafil\u00e1ctico an\u00e1lisis an\u00e1logo
anastomosis anatomopatolog\u00eda androblastoma andr\u00f3geno
androlog\u00eda andropausia anectina anemia an\u00e9mico anencefalia
aneurisma aneurism\u00e1tico anexectom\u00eda anexo anexial anfetamina
angiitis angina angioedema angiog\u00e9nesis angiograf\u00eda angiograma
angioma angiomatosis angiopat\u00eda angioplastia angiosarcoma
angiotensina angiotensi\u00f3n anhidrasa anhidrosis anilina
anisocitosis anisocoria anisometrop\u00eda anomal\u00eda anomia anopia
anopsia anorexia anosmia anosognosia anotia anovulaci\u00f3n
anovulatorio anoxia anquilosis anquilosante antagonista ant\u00e1cido
antiagregante antial\u00e9rgico antian\u00e9mico antianginoso
antibacteriano antibiograma antibi\u00f3tico anticolin\u00e9rgico
anticoagulaci\u00f3n anticoagulante anticoncepci\u00f3n anticonceptivo
anticonvulsivante antidepresivo antidiab\u00e9tico antidiarreico
antidiur\u00e9tico ant\u00eddoto antiem\u00e9tico antiepil\u00e9ptico
antif\u00fangico ant\u00edgeno antihistam\u00ednico antihipertensivo
antiinflamatorio antimal\u00e1rico antimetabolito antimicrobiano
antimic\u00f3tico antimit\u00f3tico antineopl\u00e1sico antiparasitario
antiplaquetario antiprotozoario antipruriginoso antipir\u00e9tico
antipsic\u00f3tico antirretroviral antis\u00e9ptico antitiroideo
antitromb\u00f3tico antituberculoso antitus\u00edgeno antiviral
antracosis \u00e1ntrax antroscopia anuria aortitis aortograf\u00eda
aortoplastia apat\u00eda apendicectom\u00eda apendicitis ap\u00e9ndice
apertura aplasia apnea apocrino ap\u00f3fisis aponeurosis apoptosis
aparato aripiprazol arteriopat\u00eda arterioplastia arteriotom\u00eda
articulaci\u00f3n asistolia astenopia astrocito atenolol atomoxetina
atorvastatina atracurio atrio atropina audici\u00f3n auditivo
aur\u00edcula autoanticuerpo autoexploraci\u00f3n autoinjerto
aut\u00f3lisis autopsia azoospermia azotemia aztreonam
""")

EXTRA_BLOCKS.append(r"""
bacilo bacteriemia bactericida bacteriolog\u00eda bacteriost\u00e1tico
bacteroides bal\u00f3n barbit\u00farico bas\u00f3filo benzodiacepina
betaagonista betabloqueador bicarbonato bifosfonato bilirrubinemia
bioequivalencia biomarcador biopsia biotina bisoprolol
blefaroplastia bloqueador bloqueo bolo bomba bradicardia bradilalia
bradipnea broncoaspiraci\u00f3n broncoalveolar broncoconstricci\u00f3n
broncodilataci\u00f3n broncoespasmo bronconeumon\u00eda broncopulmonar
broncorrea broncoscopia bronquiectasia bronquiolo bronquitis
bupivaca\u00edna bupropi\u00f3n bypass calcemia calcificaci\u00f3n
calcinosis calciuria calostro canalizaci\u00f3n candidemia candidiasis
caquexia carcinog\u00e9nesis carcin\u00f3geno carcinomatosis
cardiodesfibrilaci\u00f3n cardiog\u00e9nico cardiomegalia
cardiomiopat\u00eda cardioprotector cardiorrespiratorio cardiot\u00f3xico
cardiotoxicidad cardioversi\u00f3n carot\u00eddea carpiano cat\u00e9ter
cateterismo cateterizaci\u00f3n cauterizaci\u00f3n cefalorraqu\u00eddeo
cefalea celiotom\u00eda cemento cerebroespinal cerebrovascular
cervicobraquial ces\u00e1rea cetoacidosis cetonuria cian\u00f3tico
cicloplej\u00eda citoquina citot\u00f3xico citotoxicidad clamidia
claudicaci\u00f3n coagulabilidad coagulaci\u00f3n coagulopat\u00eda
colangiograf\u00eda colangiopancreatograf\u00eda colecistectom\u00eda
colelitiasis colesterol coloides colostom\u00eda colposcopia
comorbilidad complacencia conizaci\u00f3n contrapulsaci\u00f3n
contrapulsador cordocentesis corea coronariograf\u00eda
corticoterapia craneotom\u00eda crioterapia crioablaci\u00f3n
crioprecipitado cristaloides cromosoma curetaje dacriocistitis
dacrioestenosis dactiloscopia debridamiento dehiscencia
densitometr\u00eda dermatoglifo desbridamiento descompresi\u00f3n
desfibrilaci\u00f3n desfibrilador deshidrataci\u00f3n desnutrici\u00f3n
diabetes dial\u00edtico diast\u00f3lico digit\u00e1lico discinesia
disecci\u00f3n dislipidemias disociaci\u00f3n dispareunia displasias
distocia divert\u00edculo Doppler drenaje duodenitis ecocardiograma
electrocardiograma electroencefalograma electromiograma
electrofisiolog\u00eda embolizaci\u00f3n embrionario emesis encefalitis
endarterectom\u00eda endoc\u00e1rdico endoscopia endosc\u00f3pico
endotelial enfermer\u00eda enterorrafia eosin\u00f3filo ependimoma
epidemiol\u00f3gico epididimitis epidural epig\u00e1strico
epilept\u00f3geno epistaxis eritrocito eritropoyetina escler\u00f3tica
esfinterotom\u00eda esofagogastroduodenoscopia esofagorrafia
esofagostom\u00eda esplenectom\u00eda espondiloartropat\u00eda esputo
esteatorrea estimulaci\u00f3n estradiol estr\u00f3geno euforia
euvolemia exanguinotransfusi\u00f3n ex\u00e9resis exocrino
expectoraci\u00f3n expectorante extracorp\u00f3rea extracci\u00f3n
extrauterino fasc\u00edculo fasciculaci\u00f3n fenotipo feocromocitoma
fertilidad fertilizaci\u00f3n fibrilaci\u00f3n fibrin\u00f3geno
fibrinolisis fibrobroncoscopia fibroma fibromialgia fibrosis
flebitis flem\u00f3n fluoroscopia f\u00f3rceps fototerapia fractura
galactorrea gammapat\u00eda ganglio gasometr\u00eda gastrostom\u00eda
genoma gen\u00f3mica genotipo gestaci\u00f3n gestacional glicemia
glomerular glomeruloesclerosis glucag\u00f3n glucagonoma
gluconeog\u00e9nesis glucosuria gonadotropina granulocito gripe
hematemesis hemat\u00edes hematopoyesis hematopoy\u00e9tico
hemianopsia hemicr\u00e1nea hemicuerpo hemocromatosis hemodi\u00e1lisis
hemodin\u00e1mico hemofilia hemoglobina hem\u00f3lisis hemoperitoneo
hemoptisis hemorragia hemorr\u00e1gico hemostasia hemost\u00e1tico
heparina hepatitis hepatocarcinoma hepatocito hepatomegalia
hepatoesplenomegalia hepatotoxicidad hernia herniorrafia
herpesvirus hidatidosis hidrocefalia hidrocele hidronefrosis
hidropes\u00eda hidroterapia hiperacusia hiperactividad
hiperbilirrubinemia hipercalcemia hipercapnia hipercolesterolemia
hiperglucemia hiperhidrosis hiperinsulinemia hiperkalemia
hiperlipidemia hipermetrop\u00eda hipernatremia hiperparatiroidismo
hiperplasia hiperprolactinemia hipertensi\u00f3n hipertermia
hipertiroidismo hipertrofia hiperuricemia hiperventilaci\u00f3n
hipervolemia hipoacusia hipocalcemia hipocapnia hipoglucemia
hipogonadismo hipokalemia hipomagnesemia hiponatremia
hipoparatiroidismo hipopituitarismo hipotensi\u00f3n hipotermia
hipotiroidismo hipovolemia hipoxemia hipoxia histerectom\u00eda
histeroscopia histiocitosis histolog\u00eda histoplasmosis
homeostasis homeost\u00e1tico hormonoterapia hospitalizaci\u00f3n
iatrog\u00e9nico iatrogenia ictericia ictus ileostom\u00eda ile\u00edtis
imagenolog\u00eda inmunizaci\u00f3n inmunocomprometido
inmunodeficiencia inmunoglobulina inmunosupresi\u00f3n
inmunoterapia inotropismo in\u00f3tropo insuficiencia intensivo
interconsulta intubaci\u00f3n intususcepci\u00f3n isquemia
""")

EXTRA_BLOCKS.append(r"""
queratina queratitis queratoplastia labio legrado leiomioma
leishmaniasis leptospirosis leucemia leucocitosis
leucoencefalopat\u00eda leucopenia leucorrea linfangitis linfedema
linfocitosis linfoma lipasa litiasis lobectom\u00eda lordosis
lumbalgia lupus luxaci\u00f3n macroadenoma macrocitosis
malabsorci\u00f3n malformaci\u00f3n mamograf\u00eda mastectom\u00eda
mastitis mastoiditis mediastinitis mediastino megacolon melanoma
melena meningitis meningioma meningocele meningoencefalitis
menopausia menorragia metaplasia metotrexato miastenia
microalbuminuria microangiopat\u00eda microcitosis microhematuria
microlitiasis midriasis mielitis mielodisplasia mielofibrosis
mielograma mieloma mielomeningocele mielopat\u00eda migra\u00f1a
miocardiopat\u00eda miocarditis miopat\u00eda monitorizaci\u00f3n
morfolog\u00eda mucormicosis mucositis mucoviscidosis narcolepsia
necrosectom\u00eda necrosis neoplasia nefritis nefrocalcinosis
nefrolitiasis nefropat\u00eda nefrostom\u00eda nefrot\u00f3xico
nefrotoxicidad neumoconiosis neumolog\u00eda neumon\u00eda neumonitis
neumoperitoneo neumot\u00f3rax neuralgia neurinoma neuritis
neuroblastoma neurocirug\u00eda neurocisticercosis neuroendocrino
neurofibromatosis neurol\u00e9ptico neuromielitis neuropat\u00eda
neurotransmisor neutropenia nicturia nosocomial nutrici\u00f3n
nutricional obesidad obst\u00e9trico obstrucci\u00f3n oclusi\u00f3n
odontolog\u00eda oftalmolog\u00eda oftalmoplej\u00eda oligoastrocitoma
oligodendroglioma oligospermia oliguria omfalitis oncolog\u00eda
onicomicosis onic\u00f3lisis ooforectom\u00eda orquiepididimitis
orquitis ortopedia ortostatismo osteocondritis osteodistrofia
osteomalacia osteomielitis osteopenia osteoporosis otitis
otorrinolaringolog\u00eda otosclerosis oxigenoterapia oximetr\u00eda
pancitopenia pancreatitis pancreatoduodenectom\u00eda
panhipopituitarismo panuve\u00edtis papiledema papiloma paracentesis
paraganglioma paraneopl\u00e1sico paraparesia paraplej\u00eda
parasitolog\u00eda parasitosis par\u00e1lisis paresia parkinsonismo
parotidectom\u00eda parotiditis patolog\u00eda pediatr\u00eda peliosis
p\u00e9nfigo pericardiocentesis pericarditis periodontitis
periodontosis periostitis peritonitis perin\u00e9 petequias
pielonefritis pieloplastia piomiositis placenta placentaria
plasmaf\u00e9resis pl\u00e1stica pleocitosis pleurodesis policitemia
polidipsia polimialgia polimiositis polineuropat\u00eda
polipectom\u00eda poliposis polisomnograf\u00eda polaquiuria poliuria
porfiria posoperatorio preoperatorio preeclampsia prematuridad
priapismo proctolog\u00eda prolactinoma proptosis prostatectom\u00eda
prostatitis proteinuria protocolo prurito psoriasis psiquiatr\u00eda
ptosis puerperio pulmonar p\u00farpura quimioterapia quilot\u00f3rax
quir\u00f3fano quir\u00fargico rabdomi\u00f3lisis rabdomiosarcoma
radiculitis radiculopat\u00eda radiolog\u00eda radioterapia reanimaci\u00f3n
rectocele reflujo regurgitaci\u00f3n resecci\u00f3n resonancia
retinopat\u00eda reumatolog\u00eda rinitis rinofima rinoplastia
rinorrea rotaci\u00f3n r\u00fabrica rub\u00e9ola sacralizaci\u00f3n
salpingectom\u00eda salpingitis sarcoidosis sarcoma sepsis septicemia
serolog\u00eda serositis sialadenitis sialolitiasis sibilancias
sigmoidoscopia silicosis sinusitis siringomielia soplo
somatostatinoma somatotropina subluxaci\u00f3n sudoraci\u00f3n
suprarrenalectom\u00eda s\u00edfilis s\u00edncope s\u00edndrome talasemia
tamponamiento tanatolog\u00eda telangiectasia tendinitis
tenosinovitis teratoma terapia terap\u00e9utica tetania t\u00e9tanos
timpanocentesis timpanometr\u00eda timpanoplastia tinnitus
tiroidectom\u00eda tiroiditis tirotoxicosis tonsilectom\u00eda
tonsilitis toracentesis toracocentesis toracotom\u00eda toxemia
toxicolog\u00eda toxoplasmosis trabeculectom\u00eda traqueostom\u00eda
trasplante traumatolog\u00eda tromboange\u00edtis trombocitopenia
trombocitosis tromboembolia tromboembolismo trombofilia
tromboflebitis tromb\u00f3lisis trombosis tuberculosis tumoraci\u00f3n
ultrasonido ultrasonograf\u00eda ureterolitiasis ureteroscopia
uretritis uretrorragia urolog\u00eda urticaria uve\u00edtis vaginitis
vaginosis valvulopat\u00eda valvuloplastia varices varicocele
vasculitis vasoespasmo ventilaci\u00f3n ventilatorio ventriculostom\u00eda
vertebroplastia v\u00e9rtigo vesiculitis virolog\u00eda virilizaci\u00f3n
vitrectom\u00eda vit\u00edligo v\u00f3lvulo xerostom\u00eda zoonosis z\u00f3ster
""")

# Common specialty titlecase + lowercase already partly in especialidades;
# add more clinical drugs / lab / signs
EXTRA_BLOCKS.append(r"""
abacavir abciximab acenocumarol acetazolamida acetilciste\u00edna
aciclovir adrenalina albendazol alendronato alfentanilo
alopurinol alprazolam alteplasa amikacina amiodarona amitriptilina
amlodipino amoxicilina ampicilina anastrozol apixab\u00e1n
aripiprazol asparaginasa atenolol atomoxetina atorvastatina
atracurio atropina azatioprina azitromicina aztreonam baclofeno
beclometasona benazepril benzatina betametasona bevacizumab
bicalutamida bisacodilo bisoprolol bleomicina bortezomib
bromocriptina budesonida bumetanida bupivaca\u00edna buprenorfina
bupropi\u00f3n busulf\u00e1n cabergolina calcipotriol calcitonina
calcitriol candesart\u00e1n capecitabina captopril carbamazepina
carboplatino carvedilol cefalexina cefazolina cefepima
cefixaxima ceftriaxona cefuroxima celecoxib cetirizina
cetuximab ciclobenzaprina ciclosporina ciclosfosfamida
ciprofloxacino ciproterona cisplatino citalopram claritromicina
clindamicina clobazam clopidogrel clorambucilo cloranfenicol
clorazepato clorfenamina cloroquina clorpromazina clotrimazol
cloxacilina code\u00edna colchicina colistina dabigatr\u00e1n
dacarbazina dactinomicina dapagliflozina darbepoetina
darunavir dasatinib daunorrubicina deferasirox deferoxamina
desflurano desmopresina dexametasona dexmedetomidina
dextrometorfano diazepam diclofenaco digoxina diltiazem
dimenhidrinato dipirona dobutamina docetaxel domperidona
donepezilo dopamina doxazosina doxorrubicina doxiciclina
duloxetina edoxab\u00e1n efavirenz emtricitabina enalapril
enoxaparina entecavir epinefrina epoetina ergotamina
eritromicina erlotinib ertapenem escitalopram esmolol
esomeprazol espironolactona etambutol etanercept etomidato
etop\u00f3sido etoricoxib everolimus exemestano ezetimiba
famotidina fenito\u00edna fenobarbital fentanilo fexofenadina
filgrastim finasterida fluconazol fludarabina flumazenilo
fluoxetina fluticasona fluvastatina fondaparinux
fosfenito\u00edna furosemida gabapentina ganciclovir gemcitabina
gentamicina glibenclamida gliclazida glimepirida glucagon
haloperidol heparina hidralazina hidroclorotiazida
hidrocortisona hidromorfona hidroxicloroquina hidroxicina
ibuprofeno ifosfamida imatinib imipenem imipramina
indometacina infliximab insulina interfer\u00f3n ipratropio
irinotec\u00e1n isoniazida isoproterenol itraconazol ivabradina
ivermectina ketamina ketoconazol ketorolaco labetalol
lamivudina lamotrigina lansoprazol latanoprost leflunomida
letrozol leuprorelina levetiracetam levofloxacino
levomepromazina levonorgestrel levotiroxina lidoca\u00edna
linezolid lisinopril litio loperamida loratadina lorazepam
losart\u00e1n lovastatina manitol mebendazol meperidina
meropenem mesalamina metformina metimazol metoclopramida
metoprolol metronidazol midazolam mirtazapina misoprostol
mitomicina montelukast morfina moxifloxacino micofenolato
nalbufina naloxona naproxeno nebivolol neostigmina
nifedipino nitrofuranto\u00edna nitroglicerina nitroprusiato
noretisterona norfloxacino nortriptilina nistatina
octre\u00f3tido olanzapina omeprazol ondansetr\u00f3n oseltamivir
oxaliplatino oxcarbazepina oxicodona oxitocina paclitaxel
pantoprazol paracetamol paroxetina penicilina pentamidina
pentoxifilina fenilefrina fenito\u00edna fentolamina
piperacilina pioglitazona piperacilina tazobactam
pravastatina prazosina prednisona prednisolona pregabalina
primidona probenecid procainamida procarbazina prometazina
propofol propranolol propiltiouracilo protamina pirazinamida
piridostigmina quetiapina quinidina quinina rabeprazol
raloxifeno ramipril ranitidina remifentanilo ribavirina
rifampicina risperidona ritonavir rituximab rivaroxab\u00e1n
rivastigmina rocuronio ropinirol rosuvastatina salbutamol
salmeterol saquinavir sertralina sevoflurano sildenafilo
simvastatina sirolimus sitagliptina sotalol espironolactona
estreptoquinasa succinilcolina sucralfato sulfadiazina
sulfametoxazol sulfasalazina sumatript\u00e1n sunitinib
tacrolimus tadalafilo tamoxifeno tamsulosina tegafur
telmisart\u00e1n tenecteplasa tenofovir terazosina terbinafina
testosterona tetraciclina teofilina tiagabina ticarcilina
tigeciclina timolol tioguanina tiopental tizanidina
tobramicina tocilitumab topiramato topotec\u00e1n tramadol
trandolapril trastuzumab trazodona triamcinolona
triamtereno trimetoprima valaciclovir valproato
valsart\u00e1n vancomicina vardenafilo vareniclina
vecuronio venlafaxina verapamilo vigabatrina
voriconazol warfarina zidovudina zoledr\u00f3nico
zolpidem zonisamida
""")


def all_extra_tokens() -> set[str]:
    bag: set[str] = set()
    bag |= morph_terms()
    for block in EXTRA_BLOCKS:
        for w in codecs.decode(block, "unicode_escape").split():
            bag.add(w)
    return bag


if __name__ == "__main__":
    words = sorted(all_extra_tokens(), key=lambda s: (s.casefold(), s))
    print("morph+extra:", len(words))
    print("sample:", words[:10])
