<?php
if( isset($_POST["ajax"]) && $_POST["ajax"] == true ) {
    include_once("core/miniMain.php");
}
else {
    include_once("core/main.php");
}
include_once("adm_academico_pre_requisito.php");

require_once __DIR__ . "/core/aws-php-sdk/aws_config_bedrock.php";


if(!check_modulo_habilitado(2,true)) die("module not enabled");
if(!check_persona_access(7)) die($lang["ACCESS_DENIED"]);
$arrTiposAcceso = check_persona_access(7,true);
$arrModificarAutorizado = check_persona_access(530,true);
$arrEliminarEqGen = check_persona_access(1028,true);
$strAction = basename(__FILE__);

$intMenuID = 2;
set_drawMenu($intMenuID);

require_once("core/xmlfunctions.php");
require_once("soap/nusoap.php");



/**
 * Eval�a un resultado de aprendizaje seg�n nivel de Bloom
 * y devuelve la respuesta del modelo como texto JSON.
 *
 * @param string $nivel_bloom
 * @param string $resultado_aprendizaje
 * @param int $maxTokens
 * @param float $temperature
 * @return string
 * @throws Exception
 */

//revisar si puedo regresar a 300 ya con el prompt catching
function evaluar_resultado_aprendizaje_json_text($nivel_bloom, $resultado_aprendizaje, $maxTokens = 300, $temperature = 0.0)
{
    $nivel_bloom = trim((string)$nivel_bloom);
    $resultado_aprendizaje = trim((string)$resultado_aprendizaje);

    if ($nivel_bloom === '') {
        throw new Exception('Debe enviar el nivel de Bloom');
    }

    if ($resultado_aprendizaje === '') {
        throw new Exception('Debe enviar el resultado de aprendizaje');
    }

    $systemPrompt = "Act�a como un evaluador experto en dise�o curricular universitario con experiencia en redacci�n de resultados de aprendizaje y en la taxonom�a de Bloom.

Tu tarea es evaluar la calidad de un resultado de aprendizaje escrito para educaci�n universitaria.

Debes evaluar el resultado considerando el nivel de Bloom seleccionado por el usuario y compar�ndolo con los dem�s niveles para verificar si el verbo principal realmente corresponde al nivel indicado o si pertenece a otro nivel.

Taxonom�a de Bloom de referencia:

6. Crear
Definici�n: Combinar partes para integrar un todo nuevo.
Verbos recomendados: Componer, Construir, Crear, Derivar, Desarrollar, Dise�ar, Formular, Generar, Inventar, Modificar, Proponer.

5. Evaluar
Definici�n: Juzgar el valor de la informaci�n o de las ideas.
Verbos recomendados: Argumentar, Calificar, Convencer, Criticar, Defender, Determinar, Elegir, Evaluar, Justificar, Juzgar, Seleccionar, Validar, Valorar.

4. Analizar
Definici�n: Desglosar la informaci�n en sus componentes.
Verbos recomendados: Analizar, Categorizar, Contrastar, Descomponer, Diagramar, Diferenciar, Examinar, Organizar, Relacionar, Simplificar.

3. Aplicar
Definici�n: Llevar a la pr�ctica los hechos, las reglas, los conceptos y las ideas.
Verbos recomendados: Aplicar, Calcular, Demostrar, Ejecutar, Ilustrar, Implementar, Modelar, Predecir, Presentar, Resolver, Usar.

2. Comprender
Definici�n: Entender el significado de los hechos.
Verbos recomendados:  Clasificar, Comparar, Comprender, Describir, Discutir, Ejemplificar, Explicar, Interpretar, Parafrasear, Reformular, Resumir.

1. Recordar
Definici�n: Reconocer y tener presente los hechos.
Verbos recomendados: Citar, Definir, Emparejar, Enumerar, Esquematizar, Etiquetar, Identificar, Listar, Nombrar, Recitar, Reconocer, Recordar.

Criterios de evaluaci�n:

- La ortograf�a es obligatoria.
- El resultado debe ser claro, espec�fico y medible.
- Debe contener un solo verbo principal.
- Puede incluir verbos secundarios como parte del prop�sito (por ejemplo: \"para analizar\", \"para evaluar\"), siempre que exista un �nico verbo principal claro.
- Si hay m�s de un verbo principal (por ejemplo: \"aplicar y analizar\"), el estado debe ser incorrecto.
- El verbo principal debe pertenecer o ser coherente con el nivel de Bloom seleccionado.
- Si el verbo principal corresponde a otro nivel de Bloom, el estado debe ser incorrecto.
- El resultado debe incluir al menos un verbo y un complemento que indique qu� se va a hacer y sobre qu�.
- Si el resultado es solo una palabra o no forma una oraci�n completa, el estado debe ser incorrecto.
- El resultado debe describir una acci�n observable del estudiante.
- Debe tener sentido completo como oraci�n acad�mica.
- El resultado no debe contener errores ortogr�ficos.
- Si el resultado contiene al menos un error ortogr�fico, el estado debe ser incorrecto, aunque el resto del contenido sea v�lido.
- Evita ser excesivamente estricto: si el resultado es claro, correcto y usable, clasif�calo como correcto aunque no sea perfecto.
- No penalices cambios menores de redacci�n que no afectan la claridad.
- Solo usa puede_mejorarse cuando el resultado sea v�lido pero claramente mejorable.

Clasificaci�n:

1. correcto
El resultado es claro, tiene un solo verbo principal adecuado y est� bien alineado al nivel.

2. puede_mejorarse
El resultado es v�lido, mantiene el nivel y tiene un verbo correcto, pero puede mejorar en precisi�n o claridad.

3. incorrecto
Debe marcarse como incorrecto si:
- contiene al menos un error ortogr�fico
- el verbo principal corresponde a otro nivel de Bloom
- hay m�s de un verbo principal
- no hay verbo claro
- no describe una acci�n observable
- est� incompleto o no tiene sentido acad�mico
- es solo una palabra o una frase incompleta

Reglas obligatorias de salida:

- Debes devolver exactamente tres campos: estado, sugerencia y justificacion.
- No agregues campos adicionales.
- No uses emojis.
- No uses markdown.
- No uses listas.
- No uses asteriscos ni numeraci�n.
- No agregues texto fuera del JSON.

Reglas de contenido:

- El campo sugerencia debe contener solo el resultado corregido.
- La sugerencia debe usar un solo verbo principal.
- La sugerencia debe ser clara y no m�s compleja de lo necesario.
- No sugieras cambios si el resultado ya es correcto.
- No incluyas explicaciones en la sugerencia.

- El campo justificacion debe ser una sola oraci�n corta.
- La justificacion debe estar en segunda persona.
- La justificacion debe ser directa y simple.

- Si el resultado contiene al menos un error ortogr�fico:
  - el estado debe ser incorrecto
  - no puede ser puede_mejorarse
  - la sugerencia debe corregir la ortograf�a
  - la justificacion debe indicar que existe al menos un error ortogr�fico

- Si el estado es correcto:
  - sugerencia debe ser cadena vac�a
  - justificacion debe ser cadena vac�a

- Si el estado es puede_mejorarse o incorrecto:
  - sugerencia debe proponer una mejora alineada al nivel seleccionado
  - justificacion debe explicar brevemente el problema

Antes de clasificar el resultado, revisa expl�citamente si contiene errores ortogr�ficos. Si detectas al menos uno, debes clasificarlo como incorrecto.

IMPORTANTE PARA EVITAR ERRORES JSON:
En los campos sugerencia y justificacion, NO uses comillas dobles para citar texto
Usa comillas simples en su lugar
Ejemplo CORRECTO: El verbo 'analizar' no corresponde al nivel

Devuelve �nicamente el JSON.";

    $userPrompt = "Nivel seleccionado: {$nivel_bloom}

Resultado a evaluar: {$resultado_aprendizaje}";

//Esquema sin description
   /* $schema = [
        'type' => 'object',
        'properties' => [
            'estado' => [
                'type' => 'string',
                'enum' => ['correcto', 'puede_mejorarse', 'incorrecto']
            ],
            'sugerencia' => [
                'type' => 'string'
            ],
            'justificacion' => [
                'type' => 'string'
            ]
        ],
        'required' => ['estado', 'sugerencia', 'justificacion'],
        'additionalProperties' => false
    ];*/

    $schema = [
    'type' => 'object',
    'properties' => [
        'estado' => [
            'type' => 'string',
            'enum' => ['correcto', 'puede_mejorarse', 'incorrecto'],
            'description' => 'Estado del resultado de aprendizaje: correcto, puede_mejorarse o incorrecto'
        ],
        'sugerencia' => [
            'type' => 'string',
            'description' => 'Resultado de aprendizaje corregido. Cadena vac�a si estado es correcto. No usar comillas dobles, usar comillas simples'
        ],
        'justificacion' => [
            'type' => 'string',
            'description' => 'Explicaci�n o retroalimentaci�n breve. Cadena vac�a si estado es correcto. No usar comillas dobles, usar comillas simples'
        ]
    ],
    'required' => ['estado', 'sugerencia', 'justificacion'],
    'additionalProperties' => false
];



   // $start = microtime(true);



    $respuesta = AWSConfigBedrock::llm_generate_json_cached(
        $systemPrompt,
        $userPrompt,
        $schema,
        (int)$maxTokens,
        (float)$temperature
    );


    //Nota esto es para debugear el tiempo y verlo en el error log

    /*
    $end = microtime(true);

    $variable ="Tiempo: " . ($end - $start);

    error_log($variable);
    */

    return $respuesta;
}


/**
 * Eval�a una referencia bibliogr�fica usando LLM
 * 
 * @param string $referencia_bibliografica
 * @param int $maxTokens
 * @param float $temperature
 * @return array
 * @throws Exception
 */
function evaluar_bibliografia_json($referencia_bibliografica, $maxTokens = 300, $temperature = 0.0)
{
    $referencia_bibliografica = trim((string)$referencia_bibliografica);

    if ($referencia_bibliografica === '') {
        throw new Exception('Debe enviar la referencia bibliogr�fica');
    }


$systemPrompt = "Act�a como un experto en el estilo bibliogr�fico Chicago.

Tu tarea es evaluar si una bibliograf�a est� correctamente escrita seg�n el formato Chicago para bibliograf�as (Chicago Manual of Style / Chicago-Deusto, sistema de Notas y Bibliograf�a).


IMPORTANTE:

Eval�a �nicamente bibliograf�as correspondientes al formato Chicago para bibliograf�as (Bibliography), sistema de notas y bibliograf�a.

No aceptes bibliograf�as escritas en formato de nota al pie o nota final (Footnotes o Endnotes), aunque tambi�n pertenezcan al estilo Chicago.

No aceptes bibliograf�as escritas en el sistema Chicago autor-fecha (lista de referencias), en el que el a�o aparece inmediatamente despu�s del autor en lugar de al final de la entrada. Este sistema corresponde a un estilo de cita distinto (autor-a�o) y queda fuera del alcance de este validador, que eval�a exclusivamente el formato de bibliograf�a tradicional.

Si la bibliograf�a corresponde al formato de nota al pie, nota final, o al sistema autor-fecha (lista de referencias), debes clasificarla como incorrecto y, en la sugerencia, convertirla al formato Chicago para bibliograf�a tradicional utilizando �nicamente la informaci�n presente en la bibliograf�a original.

Chequeos obligatorios de formato (antes de clasificar como correcto):

- Bibliograf�a tradicional: Autor. T�tulo. � Ciudad: Editorial, A�o. El a�o va al final, no justo despu�s del autor.
- Autor-fecha: si el a�o (t�picamente cuatro cifras) aparece inmediatamente despu�s del autor y antes del t�tulo (por ejemplo Apellido, Nombre, 2019. T�tulo�), es incorrecto; la sugerencia debe reordenar al formato tradicional.
- Nota al pie o cita en texto: numeraci�n, ib�d., op. cit., a�o entre par�ntesis tras la editorial, u orden propio de notas = incorrecto; la sugerencia debe convertir a bibliograf�a tradicional.

Debes:

1. Verificar si la bibliograf�a sigue el formato Chicago para bibliograf�as.
2. Evaluar si la bibliograf�a est� correctamente estructurada.
3. Proporcionar una sugerencia de correcci�n �nicamente cuando sea necesaria.

Criterios de evaluaci�n:

- La bibliograf�a debe contener al menos un autor (personal o institucional), un seud�nimo, una frase descriptiva en lugar de autor, o un editor/traductor/compilador cuando no hay autor; y un t�tulo.
- Los elementos deben aparecer en el orden correspondiente al formato Chicago.
- La puntuaci�n debe ser consistente con el estilo Chicago.
- El t�tulo principal debe estar en cursiva (etiqueta <i> o <em>); su ausencia total es un defecto obligatorio a corregir, no una variaci�n aceptable.
- No debe contener errores ortogr�ficos.
- La bibliograf�a debe ser clara y suficientemente completa para identificar la fuente.
- La ausencia de elementos que no est�n presentes en la bibliograf�a original no debe considerarse un error si la bibliograf�a sigue siendo v�lida (esta excepci�n NO aplica a la cursiva del t�tulo, que siempre es obligatoria cuando hay un t�tulo principal).
- No inventes informaci�n faltante.

Reglas espec�ficas de estructura, puntuaci�n y formato Chicago para bibliograf�a:

Autor:
- Solo se invierte el nombre del primer autor (Apellido, Nombre); los autores siguientes van en orden normal, separados por coma y la conjunci�n y (nunca el signo &).
- OBLIGATORIO verificar el orden del primer autor: debe comenzar por apellido(s), coma, nombre(s). Si el primer autor aparece como Nombre Apellido o Nombre Apellido sin invertir (por ejemplo Juan Garc�a L�pez en lugar de Garc�a L�pez, Juan), es un error que impide clasificar como correcto; corr�gelo en la sugerencia invirtiendo solo el primer autor.
- Con cuatro o m�s autores se citan TODOS los autores en la bibliograf�a; nunca se usa et al. en la bibliograf�a (et al. es exclusivo de las notas al pie).
- Si dos o m�s autores comparten apellido, este se repite completo para cada uno, sin abreviar ni omitir.
- La entrada de bibliograf�a siempre debe comenzar con el nombre del autor, aunque ese nombre se repita en el t�tulo.
- Los grados o m�ritos acad�micos despu�s del nombre del autor (por ejemplo, PhD, Dr.) deben eliminarse, ya que no forman parte del formato Chicago.
- Los autores conocidos �nicamente por iniciales (por ejemplo, T. S. Eliot, C. S. Lewis) son v�lidos as�; no se debe forzar el nombre completo. Debe haber espacio entre cada inicial.
- Los monarcas, santos y personas citadas solo por su nombre de pila se alfabetizan y citan por ese nombre, sin t�tulo honor�fico (por ejemplo, rey, santo).
- Los seud�nimos, tanto ampliamente conocidos como desconocidos, y las frases descriptivas en lugar de autor, son formas v�lidas y no deben tratarse como ausencia de autor.
- Cuando no hay autor, la entrada puede comenzar v�lidamente con el nombre del editor, traductor o compilador, seguido de la abreviatura singular correspondiente (ed., trad., comp.); esto no debe considerarse 'falta de autor'.
- Un autor corporativo o institucional es v�lido como autor, incluso si la misma entidad figura tambi�n como editorial.

T�tulo:
- Los t�tulos principales (de libros y revistas) van en cursiva, siempre, sin excepci�n. Los t�tulos secundarios (cap�tulos, art�culos) o de trabajos in�ditos van en redonda y entre comillas.
- En t�tulos de libros y art�culos en espa�ol (y en general en lenguas distintas del ingl�s), solo lleva may�scula inicial la primera palabra y los nombres propios. Si el t�tulo est� en ingl�s, el uso de may�scula en cada palabra principal (Title Case) es correcto y no debe marcarse como error.
- El nombre de una revista (no el t�tulo del art�culo) sigue una convenci�n distinta: en espa�ol lleva may�scula en todas las palabras significativas del nombre.
- El subt�tulo va precedido de dos puntos y un espacio (en cursiva si el t�tulo lo est�) y siempre comienza con may�scula. Si hay dos subt�tulos, el primero lleva dos puntos y el segundo punto y coma; ambos inician con may�scula.
- Si el t�tulo termina en signo de interrogaci�n o exclamaci�n, no se a�aden dos puntos antes de un subt�tulo, y se omite cualquier punto final redundante.
- Si el t�tulo original aparece en may�scula sostenida, debe convertirse a may�sculas y min�sculas seg�n el uso normal.
- La traducci�n de un t�tulo va entre corchetes, sin cursiva ni comillas, despu�s del t�tulo original (nunca entre par�ntesis, que es solo para texto corrido).
- El t�tulo de otra obra contenido dentro de un t�tulo en cursiva va entre comillas, no en cursiva.
- Nunca se usa la palabra en entre el t�tulo de un art�culo de revista y el nombre de la revista (en s� se usa correctamente para introducir el t�tulo del libro en cap�tulos o contribuciones).
- Un t�tulo principal en texto plano, sin ninguna etiqueta <i> ni <em>, es siempre un defecto de formato que impide clasificar la entrada como correcto. No importa si el resto de la puntuaci�n, orden y ortograf�a son perfectos: la falta de cursiva por s� sola basta para que el estado sea, como m�nimo, puede_mejorarse.

Editor, traductor, compilador, edici�n y volumen:
- Cuando hay autor adem�s de editor/traductor/compilador, las expresiones editado por y traducido por se escriben completas en la bibliograf�a (no abreviadas como en las notas); las formas nominales (ed., trad., vol.) s� se abrevian. Las formas plurales (eds., comps.) nunca se usan despu�s del t�tulo; siempre se usa la forma singular desarrollada, sin importar cu�ntos responsables haya.
- Los t�rminos gen�ricos introducci�n a, prefacio a, ep�logo a van en min�scula, salvo que inicien la entrada justo despu�s de un punto.
- La edici�n se abrevia: Segunda edici�n se escribe 2.� ed.; Edici�n revisada (sin n�mero) se escribe ed. rev.
- El orden correcto cuando hay edici�n y volumen es: T�tulo. Edici�n. Volumen. Ciudad: Editorial, A�o (la edici�n precede al volumen).
- El n�mero de volumen siempre se expresa en n�meros ar�bigos, aunque en la obra original aparezca en n�meros romanos o en letra.
- El t�tulo de una colecci�n o serie no va en cursiva, ni entre comillas, ni entre par�ntesis.

Pie editorial (ciudad, editorial y a�o):
- El formato correcto en bibliograf�a es Ciudad: Editorial, A�o. con dos puntos entre ciudad y editorial, coma antes del a�o, y sin par�ntesis (los par�ntesis son exclusivos de las notas).
- En el nombre de la editorial se omiten part�culas como S.A., Ltd., Inc., Co., & Co. y Publishing Co., as� como el art�culo The al inicio. La palabra Press se conserva en editoriales universitarias. No es un error que estas part�culas falten; s� es corregible si est�n presentes.
- Los nombres de editoriales extranjeras nunca se traducen.
- Para libros, la fecha de publicaci�n incluye solo el a�o, nunca mes ni d�a.
- No debe confundirse la fecha de publicaci�n con menciones de reimpresi�n o renovaci�n de copyright.
- s.f. (sin fecha) y s.l. (sin lugar) son sustitutos v�lidos cuando el dato correspondiente no est� disponible; no deben tratarse como informaci�n faltante.
- Para fuentes consultadas en l�nea, la falta de ciudad de publicaci�n no es un error y no requiere agregarse s.l.

P�ginas, cap�tulos y art�culos:
- En la bibliograf�a no se citan p�ginas para libros completos. Para art�culos de revista o cap�tulos de libro se indica el rango completo de p�ginas (primera y �ltima), nunca solo una p�gina puntual.
- Cap�tulo de libro editado: Autor del cap�tulo. T�tulo del cap�tulo entre comillas. En T�tulo del libro en cursiva, editado por Nombre del editor, p�ginas inicio-fin. Ciudad: Editorial, A�o.
- Los rangos de p�ginas DEBEN abreviarse seg�n estas reglas; verifica cada rango y corr�gelo en la sugerencia si no cumple (no lo dejes pasar):
  * Si el primer n�mero es menor de 100, el segundo se escribe COMPLETO (3-10, 71-72, 96-117). Incorrecto: 3-1, 96-17 si debe ser 96-117.
  * Si el primer n�mero es 100 o m�ltiplo de 100, el segundo se escribe COMPLETO (100-104, 1100-1113).
  * Si el primer n�mero termina en 01-09 dentro de una centena (101-109, 201-209�), el segundo se abrevia solo con la parte que cambia (101-8, 808-33, 1103-4). Incorrecto: 101-108, 1103-04.
  * Si el primer n�mero termina en 10-99 dentro de una centena (110-199, 321-399�), el segundo se abrevia con m�nimo dos d�gitos (321-28, 498-532, 1087-89, 1496-500). Incorrecto: 321-8, 1087-9, 1496-00.
  * Rangos sin abreviar cuando deb�an abreviarse, o abreviados mal, impiden clasificar como correcto; estado puede_mejorarse si solo falla la abreviaci�n.

Publicaciones peri�dicas (revistas y magacines):
- El volumen de una revista se expresa en n�meros ar�bigos y sigue al nombre de la revista sin puntuaci�n intermedia. El n�mero de entrega, si se indica, va precedido de n.� tras una coma.
- El a�o va entre par�ntesis despu�s del volumen o de la entrega.
- Las p�ginas que siguen al n�mero de volumen se separan con dos puntos sin espacio (por ejemplo, 10:120-149). Si el n�mero de p�gina sigue al n�mero de entrega en lugar del volumen, se usa coma en vez de dos puntos.
- Si la revista no tiene n�mero de volumen, se escribe una coma tras el nombre de la revista antes de la entrega o fecha.
- Si la revista solo dispone de fecha (sin volumen ni entrega), esa fecha es un dato indispensable y no debe ir entre par�ntesis.
- Los magacines se citan habitualmente solo por fecha completa, sin par�ntesis, aunque tengan volumen y entrega disponibles.

Fuentes electr�nicas, URL y DOI:
- La ausencia de fecha de consulta no debe considerarse un error, salvo que sea la �nica fecha disponible.
- Si hay URL o DOI, el protocolo (http, https) y el prefijo doi deben ir en min�scula; el resto del identificador no debe alterarse en may�sculas o min�sculas.
- Los URL no deben aparecer encerrados entre signos como par�ntesis angulares; si aparecen as�, deben eliminarse esos signos conservando el URL intacto.
- El DOI se prefiere sobre el URL cuando ambos est�n disponibles, y se escribe en min�scula como doi: seguido de dos puntos sin espacio.
- El localizador electr�nico final de un libro (URL, DOI, o indicaci�n de formato como edici�n para Kindle o CD-ROM) debe ir en la �ltima posici�n de la entrada bibliogr�fica.

Formato enriquecido:

- La bibliograf�a puede contener texto enriquecido.
- El t�tulo principal DEBE llevar cursiva (italic); esto no es opcional. Los enlaces (links) se incluyen cuando existan URL o DOI en la fuente original.
- La cursiva puede llegar representada en el texto original mediante la etiqueta <i>...</i> o mediante la etiqueta <em>...</em>. Debes tratar ambas como equivalentes: cualquiera de las dos cuenta como cursiva ya aplicada al evaluar si un t�tulo cumple el requisito de formato Chicago.
- Si el t�tulo no tiene NINGUNA de las dos etiquetas (texto completamente plano), esto se trata como cursiva faltante y debe corregirse, nunca como una variante aceptable.
- Sin importar si la cursiva original ven�a en <i> o en <em>, cuando definas la sugerencia debes normalizar siempre a la etiqueta <i>...</i>. Nunca uses <em> en la sugerencia.
- Debes conservar todo el formato enriquecido v�lido existente.
- Debes conservar todos los enlaces v�lidos existentes.
- No elimines etiquetas de formato enriquecido v�lidas.
- No agregues informaci�n nueva mediante el formato.
- Cuando el t�tulo no tenga cursiva, debes agregarla en la sugerencia usando <i>...</i>. Esto es obligatorio, no opcional.
- Si la bibliograf�a cambia de orden durante la correcci�n, conserva el formato enriquecido asociado a cada elemento.

Clasificaci�n:

1. correcto
   La bibliograf�a cumple el formato Chicago para bibliograf�as tradicional (a�o al final, no autor-fecha), el primer autor est� en orden Apellido, Nombre, no tiene errores ortogr�ficos, es estructuralmente correcta, los rangos de p�ginas cumplen las reglas de abreviaci�n si existen, Y el t�tulo principal est� envuelto en <i> o <em> de principio a fin. Si falta la cursiva del t�tulo, el a�o va justo despu�s del autor, o el primer autor no est� invertido, el estado NUNCA puede ser correcto, sin excepci�n.

2. puede_mejorarse
   La bibliograf�a sigue el estilo Chicago para bibliograf�as pero presenta peque�os errores de formato, puntuaci�n, estructura o formato enriquecido que pueden corregirse sin agregar informaci�n nueva. El ejemplo m�s com�n y obligatorio de este caso es un t�tulo principal en texto plano, sin cursiva: si ese es el �nico problema de la entrada, el estado correcto es puede_mejorarse.

3. incorrecto
   La bibliograf�a debe clasificarse como incorrecta si:

- no sigue el formato Chicago para bibliograf�as;
- corresponde al formato Chicago de nota al pie o nota final (Footnote o Endnote) en lugar del formato Chicago para bibliograf�a;
- corresponde al sistema Chicago autor-fecha (lista de referencias) en lugar del formato Chicago para bibliograf�a tradicional;
- le faltan elementos esenciales (autor, entidad responsable, seud�nimo, frase descriptiva, editor/traductor/compilador en ausencia de autor, o t�tulo);
- tiene errores graves de estructura;
- contiene errores ortogr�ficos;
- no permite identificar adecuadamente la fuente.

Reglas obligatorias de salida:

- Devuelve exactamente tres campos: estado, sugerencia y justificacion.
- No agregues campos adicionales.
- No uses emojis.
- No uses markdown.
- No uses listas.
- No uses asteriscos ni numeraci�n.
- No agregues texto fuera del JSON.

Reglas de contenido:

- El campo sugerencia debe contener �nicamente la bibliograf�a corregida.

- No agregues informaci�n que no exista en la bibliograf�a original.

- No inventes autor, a�o, ciudad, editorial, DOI, URL, volumen, n�mero ni p�ginas.

- Solo puedes corregir el orden, la puntuaci�n, el formato bibliogr�fico, la ortograf�a y el formato enriquecido (italic y links) utilizando exclusivamente la informaci�n proporcionada.

- Conserva toda la informaci�n existente.

- Conserva los enlaces y el formato enriquecido v�lidos.

- Si el t�tulo principal no tiene cursiva (ni <i> ni <em>), debes agregarla obligatoriamente usando <i>...</i> en la sugerencia, sin modificar el contenido textual. Esta correcci�n es obligatoria siempre que falte, no una mejora opcional.

- Si la cursiva original viene marcada con <em>...</em>, sustit�yela por <i>...</i> en la sugerencia, conservando el texto interior sin cambios.

- La justificacion debe ser una sola oraci�n corta.

- Si el estado es correcto:

  - sugerencia debe ser cadena vac�a.
  - justificacion debe ser cadena vac�a.

- Si el estado es puede_mejorarse o incorrecto:

  - la sugerencia debe ser diferente de la bibliograf�a original.
  - la justificacion debe explicar brevemente el problema detectado.
  - la correcci�n mencionada en la justificacion debe verse reflejada en la sugerencia.

- PROHIBIDO devolver sugerencia id�ntica a la entrada original: compara el texto completo incluyendo etiquetas HTML. Si el estado es puede_mejorarse o incorrecto, la sugerencia DEBE contener al menos un cambio visible (orden del autor, posici�n del a�o, cursiva <i>, abreviaci�n de p�ginas, puntuaci�n, conversi�n desde autor-fecha o nota, etc.). Si no puedes mejorar nada, el estado debe ser correcto con sugerencia vac�a; nunca puede_mejorarse ni incorrecto con sugerencia igual o vac�a.

Antes de clasificar, verifica si existen errores ortogr�ficos. Si detectas alguno, el estado debe ser incorrecto.

Antes de clasificar, verifica el formato: si el a�o va justo despu�s del autor (autor-fecha) o es nota/cita, estado incorrecto y sugerencia en bibliograf�a tradicional.

Antes de clasificar, verifica el primer autor: debe ser Apellido, Nombre; si no, corr�gelo en la sugerencia.

Antes de clasificar, si hay rangos de p�ginas, verifica la abreviaci�n seg�n las reglas; corrige en la sugerencia si no cumplen.

Antes de clasificar, ejecuta el chequeo de cursiva descrito al inicio de este prompt: identifica el t�tulo principal y confirma si est� envuelto por <i> o <em> de principio a fin. Si no lo est�, el estado no puede ser correcto bajo ninguna circunstancia, incluso si el resto de la entrada es perfecta.

Verificaci�n final:

- Si la sugerencia es id�ntica a la bibliograf�a original (incluyendo HTML), no clasifiques como puede_mejorarse ni incorrecto; reclasifica como correcto o aplica un cambio real en la sugerencia.
- Si el estado es puede_mejorarse o incorrecto, la sugerencia no puede estar vac�a ni ser igual a la original.
- Si la bibliograf�a cumple el formato Chicago, no tiene errores ortogr�ficos, no requiere cambios visibles, Y el t�tulo principal est� en cursiva, clasifica como correcto.
- Si el t�tulo principal no est� en cursiva, el estado nunca es correcto, incluso si esta fue la �nica raz�n por la que dudaste en clasificarla como tal. En ese caso el estado correcto es puede_mejorarse.

IMPORTANTE PARA EVITAR ERRORES JSON:

- Devuelve �nicamente un objeto JSON v�lido.
- En los campos sugerencia y justificacion no uses comillas dobles para citar texto. Utiliza comillas simples cuando sea necesario.

Devuelve �nicamente el JSON.
";

$userPrompt = "Bibliograf�a a evaluar: {$referencia_bibliografica}";

$schema = [
    'type' => 'object',
    'properties' => [
        'estado' => [
            'type' => 'string',
            'enum' => ['correcto', 'puede_mejorarse', 'incorrecto'],
            'description' => 'Estado de la bibliograf�a seg�n el formato Chicago para bibliograf�as'
        ],
        'sugerencia' => [
            'type' => 'string',
            'description' => 'Bibliograf�a corregida en formato Chicago. Debe conservar el hipertexto permitido (italic y links) cuando corresponda. Cadena vac�a si estado es correcto. Debe ser distinta de la entrada original si estado no es correcto. No usar comillas dobles; usar comillas simples.'
        ],
        'justificacion' => [
            'type' => 'string',
            'description' => 'Explicaci�n breve del problema detectado. Cadena vac�a si estado es correcto. No usar comillas dobles; usar comillas simples.'
        ]
    ],
    'required' => ['estado', 'sugerencia', 'justificacion'],
    'additionalProperties' => false
];

    //Esquema con descripcion


    // tiempo por llamada
       // $start = microtime(true);


    $respuesta = AWSConfigBedrock::llm_generate_json_cached(
        $systemPrompt,
        $userPrompt,
        $schema,
        (int)$maxTokens,
        (float)$temperature
    );

     //Nota esto es para debugear el tiempo y verlo en el error log

    /*
    $end = microtime(true);

    $variable ="Tiempo: " . ($end - $start);

    error_log($variable);
    */


    return $respuesta;
}




function obtener_prompt_validacion_campo($nombreCampo)
{
    switch ($nombreCampo) {


//Descripci�n institucional del campo
case 'DescInst':
    return "
VALIDACI�N ESPEC�FICA DEL CAMPO

Campo evaluado: Descripci�n institucional del curso

Eval�a que el texto funcione como una descripci�n institucional del curso.

REGLA DE PRIORIDAD:
Antes de validar si corresponde a una descripci�n institucional, verifica ortograf�a, gram�tica y coherencia general.
Si el texto tiene al menos una falta ortogr�fica, error gramatical o incoherencia l�gica, el estado debe ser incorrecto.
Esta regla tiene prioridad sobre cualquier otra clasificaci�n.
No uses puede_mejorarse cuando exista una falta ortogr�fica, error gramatical o incoherencia.

Una descripci�n institucional v�lida puede:
Presentar el prop�sito general del curso.
Explicar de qu� trata el curso.
Mencionar temas, conceptos, herramientas, preguntas gu�a o habilidades que se abordar�n.
Incluir contexto acad�mico, profesional, hist�rico, social, jur�dico, econ�mico, empresarial, t�cnico o disciplinar relacionado con el curso.
Explicar qu� aprender�, analizar�, desarrollar� o comprender� el estudiante.
Usar preguntas introductorias o ret�ricas si ayudan a presentar el contenido del curso.
Mencionar brevemente la relaci�n del curso con otros cursos, semestres o �reas de formaci�n.

Debe verificar que:
El texto est� relacionado claramente con un curso acad�mico.
El texto tenga tono acad�mico, formal o institucional.
El texto sea comprensible para estudiantes, docentes y autoridades acad�micas.
El texto describa el contenido, prop�sito, enfoque o valor formativo del curso.
El texto no sea �nicamente una lista aislada de temas sin redacci�n explicativa.
El texto no sea �nicamente una explicaci�n de evaluaci�n, asistencia, tareas, calendario, normas de clase o metodolog�a operativa.
El texto no sea demasiado informal, promocional, personal o ambiguo.
El texto no trate sobre un tema general sin vincularlo con un curso.

No seas excesivamente estricto:
Acepta descripciones breves si explican razonablemente el prop�sito o contenido del curso.
Acepta descripciones que combinen prop�sito, temas, habilidades y contexto.
Acepta textos que no usen literalmente la frase 'el curso', siempre que sea claro que describen una asignatura o experiencia acad�mica.

REGLA SOBRE CORRECCI�N:
Si el estado es incorrecto porque el texto tiene faltas ortogr�ficas, errores gramaticales o problemas de redacci�n, html_corregido debe incluir el HTML corregido.

Si el estado es incorrecto porque el texto est� bien escrito pero no corresponde a una descripci�n institucional del curso, html_corregido tambi�n debe incluir una versi�n sugerida del texto, manteniendo exactamente la misma estructura HTML, orientada a convertirlo en una descripci�n institucional v�lida.

La sugerencia debe conservar el tema central cuando sea posible, pero vincularlo expl�citamente con un curso acad�mico, su prop�sito, contenido o valor formativo.

Si no existe informaci�n suficiente para convertirlo en una descripci�n institucional espec�fica sin inventar datos, redacta una versi�n gen�rica y prudente sin agregar nombres de curso, facultad, evaluaciones, bibliograf�a ni contenidos demasiado espec�ficos.
";

case 'Aporte':
    return "
VALIDACI�N ESPEC�FICA DEL CAMPO

Campo evaluado: Aportes al plan de estudios/perfil de egreso

Eval�a que el texto explique el aporte del curso al plan de estudios, a la carrera o al perfil de egreso.

REGLA DE PRIORIDAD:
Antes de validar si corresponde al campo, verifica ortograf�a, gram�tica y coherencia general.
Si el texto tiene al menos una falta ortogr�fica, error gramatical o incoherencia l�gica, el estado debe ser incorrecto.
Esta regla tiene prioridad sobre cualquier otra clasificaci�n.
No uses puede_mejorarse cuando exista una falta ortogr�fica, error gramatical o incoherencia.

Una respuesta v�lida puede:
Explicar por qu� el curso es relevante dentro de la formaci�n del estudiante.
Mostrar c�mo el curso se conecta con otros cursos, �reas o etapas del plan de estudios.
Describir qu� conocimientos, habilidades, criterios, competencias o perspectivas aporta al futuro egresado.
Justificar la importancia acad�mica, profesional o formativa del curso.
Mencionar v�nculos con el ejercicio profesional, el contexto laboral, social, disciplinar o institucional.
Relacionar el curso con objetivos del programa, competencias de carrera o perfil de egreso.
Explicar c�mo el curso sirve como base, complemento, integraci�n o aplicaci�n de otros aprendizajes.

Debe verificar que:
El texto est� relacionado claramente con el aporte del curso dentro de un programa acad�mico.
El texto explique la relevancia formativa del curso, no solo su contenido.
El texto conecte el curso con la carrera, el plan de estudios, el perfil de egreso o el desempe�o profesional futuro.
El texto tenga tono acad�mico, formal o institucional.
El texto sea comprensible para estudiantes, docentes y autoridades acad�micas.

No debe considerarse v�lido si:
El texto solo describe de qu� trata el curso sin explicar su aporte al plan de estudios o perfil de egreso.
El texto solo enumera temas sin explicar su valor formativo.
El texto solo habla de metodolog�a, evaluaci�n, tareas, asistencia o normas de clase.
El texto trata sobre un tema general sin vincularlo con la formaci�n del estudiante o la carrera.
El texto es demasiado informal, promocional, personal o ambiguo.

No seas excesivamente estricto:
Acepta textos que llamen a este campo 'justificaci�n', siempre que expliquen la importancia formativa del curso.
Acepta textos que respondan a la pregunta '�en qu� manera este curso conecta con el resto de los cursos de la carrera?'.
Acepta textos breves si explican razonablemente el aporte del curso al programa o al futuro egresado.
Acepta textos que mencionen cursos relacionados, competencias, PLOs, objetivos del programa o aplicaciones profesionales.

REGLA SOBRE CORRECCI�N:
Si el estado es incorrecto porque el texto tiene faltas ortogr�ficas, errores gramaticales o problemas de redacci�n, html_corregido debe incluir el HTML corregido.

Si el estado es incorrecto porque el texto est� bien escrito pero no corresponde al campo Aportes al plan de estudios/perfil de egreso., html_corregido tambi�n debe incluir una versi�n sugerida del texto, manteniendo exactamente la misma estructura HTML, orientada a explicar el aporte del curso al plan de estudios, la carrera o el perfil de egreso.

La sugerencia debe conservar el tema central cuando sea posible, pero vincularlo expl�citamente con la formaci�n del estudiante, el plan de estudios, la carrera, el perfil de egreso o el desempe�o profesional futuro.

Si no existe informaci�n suficiente para redactar un aporte espec�fico sin inventar datos, redacta una versi�n gen�rica y prudente sin agregar nombres de carrera, facultad, cursos espec�ficos, competencias oficiales o PLOs que no est�n presentes en el texto original.
";


case 'Conocimientos':
    return "
VALIDACI�N ESPEC�FICA DEL CAMPO

Campo evaluado: conocimientos previos

Eval�a que el texto describa los conocimientos previos esperados para cursar la asignatura.

REGLA DE PRIORIDAD:
Antes de validar si corresponde al campo, verifica ortograf�a, gram�tica y coherencia general.
Si el texto tiene al menos una falta ortogr�fica, error gramatical o incoherencia l�gica, el estado debe ser incorrecto.
Esta regla tiene prioridad sobre cualquier otra clasificaci�n.
No uses puede_mejorarse cuando exista una falta ortogr�fica, error gramatical o incoherencia.

Una respuesta v�lida puede:
Indicar conocimientos, habilidades o bases acad�micas necesarias antes de cursar el curso.
Mencionar conceptos, �reas, materias o herramientas que el estudiante deber�a dominar previamente.
Presentarse en forma de lista o en texto continuo.
Ser breve o extensa, dependiendo del curso.
Indicar expl�citamente que no se requieren conocimientos previos, usando expresiones como 'Ninguno' o equivalentes.
Incluir recomendaciones o expectativas generales de preparaci�n acad�mica.

Debe verificar que:
El texto est� relacionado con conocimientos previos del estudiante.
El texto tenga sentido como preparaci�n acad�mica para el curso.
El texto sea claro y comprensible para estudiantes y docentes.
El texto tenga un tono acad�mico o neutro.

No debe considerarse v�lido si:
El texto describe el contenido del curso en lugar de conocimientos previos.
El texto habla de metodolog�a, evaluaci�n, tareas o normas de clase.
El texto describe objetivos del curso o resultados de aprendizaje.
El texto trata sobre un tema general sin vincularlo con preparaci�n previa del estudiante.
El texto es demasiado informal, promocional o ambiguo.

No seas excesivamente estricto:
Acepta respuestas muy breves como 'Ninguno'.
Acepta listas de conocimientos sin redacci�n extensa.
Acepta textos que incluyan recomendaciones generales de preparaci�n.
Acepta textos que mencionen cursos previos, habilidades o �reas de conocimiento.

REGLA SOBRE CORRECCI�N:
Si el estado es incorrecto porque el texto tiene faltas ortogr�ficas, errores gramaticales o problemas de redacci�n, html_corregido debe incluir el HTML corregido.

Si el estado es incorrecto porque el texto est� bien escrito pero no corresponde al campo conocimientos previos, html_corregido tambi�n debe incluir una versi�n sugerida del texto, manteniendo exactamente la misma estructura HTML, orientada a describir los conocimientos previos esperados.

La sugerencia debe mantener el nivel de especificidad del texto original cuando sea posible.

Si no existe informaci�n suficiente para redactar conocimientos previos espec�ficos sin inventar datos, redacta una versi�n gen�rica y prudente como 'No se requieren conocimientos previos espec�ficos para cursar esta asignatura'.
";


case 'Marco':
    return "
VALIDACI�N ESPEC�FICA DEL CAMPO

Campo evaluado: Marco normativo institucional

Eval�a que el texto describa las normas, reglas o lineamientos aplicables al desarrollo del curso.

REGLA DE PRIORIDAD:
Antes de validar si corresponde al campo, verifica ortograf�a, gram�tica y coherencia general.
Si el texto tiene al menos una falta ortogr�fica, error gramatical o incoherencia l�gica, el estado debe ser incorrecto.
Esta regla tiene prioridad sobre cualquier otra clasificaci�n.
No uses puede_mejorarse cuando exista una falta ortogr�fica, error gramatical o incoherencia.

Una respuesta v�lida puede:
Describir normas de conducta, disciplina o comportamiento dentro del curso.
Incluir reglas de asistencia, puntualidad, participaci�n o uso de dispositivos.
Mencionar pol�ticas de honestidad acad�mica, plagio o integridad.
Referirse a reglamentos institucionales de la universidad o facultad.
Estar redactada en forma de lista, numeraci�n o texto continuo.
Incluir consecuencias o sanciones relacionadas con el incumplimiento de normas.
Combinar normas institucionales generales con reglas espec�ficas del curso.

Debe verificar que:
El texto est� relacionado con normas, reglas o lineamientos del curso.
El texto tenga un enfoque normativo o regulatorio, no acad�mico.
El texto sea claro y comprensible para estudiantes y docentes.
El texto tenga tono formal o institucional.

No debe considerarse v�lido si:
El texto describe el contenido del curso.
El texto explica objetivos, competencias o resultados de aprendizaje.
El texto describe metodolog�a de ense�anza sin relaci�n con normas.
El texto trata sobre temas generales sin relaci�n con reglas o lineamientos.
El texto es demasiado informal, promocional o ambiguo.

No seas excesivamente estricto:
Acepta textos extensos o detallados con m�ltiples reglas.
Acepta listas, numeraciones o p�rrafos largos.
Acepta referencias a reglamentos institucionales sin explicarlos completamente.
Acepta redacci�n mixta entre normas institucionales y reglas del curso.

REGLA SOBRE CORRECCI�N:
Si el estado es incorrecto porque el texto tiene faltas ortogr�ficas, errores gramaticales o problemas de redacci�n, html_corregido debe incluir el HTML corregido.

Si el estado es incorrecto porque el texto est� bien escrito pero no corresponde al campo Marco normativo institucional, html_corregido tambi�n debe incluir una versi�n sugerida del texto, manteniendo exactamente la misma estructura HTML, orientada a describir normas institucionales o reglas del curso.

La sugerencia debe mantener el nivel de formalidad del texto original.

Si no existe informaci�n suficiente para redactar normas espec�ficas sin inventar datos, redacta una versi�n gen�rica y prudente que incluya lineamientos b�sicos como respeto, integridad acad�mica y cumplimiento de reglamentos institucionales.
";



        default:
            return "
VALIDACI�N ESPEC�FICA DEL CAMPO

Campo evaluado: {$nombreCampo}

No hay reglas espec�ficas adicionales para este campo.
";


    }
}



/**
 * Valida coherencia y ortograf�a de un texto HTML usando LLM
 * 
 * @param string $textoHTML Texto en formato HTML
 * @param string $nombreCampo Nombre del campo para referencia
 * @param int $maxTokens
 * @param float $temperature
 * @return array
 * @throws Exception
 */

function validar_texto_coherencia_ortografia($textoHTML, $nombreCampo = "texto", $maxTokens = 3000, $temperature = 0.0)
{
    $textoHTML = trim((string)$textoHTML);
    
    if ($textoHTML === '') {
        throw new Exception('El texto est� vac�o');
    }

    // 1. Eliminar comentarios condicionales de Microsoft Office
    $textoHTML = preg_replace('/<!--\[if[^\]]*\]>.*?<!\[endif\]-->/is', '', $textoHTML);
    
    // 2. Eliminar comentarios XML de Office
    $textoHTML = preg_replace('/<\?xml[^>]*\?>/i', '', $textoHTML);
    $textoHTML = preg_replace('/<o:[^>]*>.*?<\/o:[^>]*>/is', '', $textoHTML);
    $textoHTML = preg_replace('/<w:[^>]*>.*?<\/w:[^>]*>/is', '', $textoHTML);
    $textoHTML = preg_replace('/<m:[^>]*>.*?<\/m:[^>]*>/is', '', $textoHTML);

    // 3. Eliminar comentarios HTML normales 
    $textoHTML = preg_replace('/<!--.*?-->/s', '', $textoHTML);


//Este el prompt actual
$systemPromptBase = "Act�a como un experto en redacci�n acad�mica y correcci�n de textos.

Recibir�s un texto en formato HTML. Tu tarea es:

1. Evaluar la coherencia del texto (que tenga sentido l�gico y est� bien estructurado)
2. Detectar errores ortogr�ficos y gramaticales
3. Si hay correcciones necesarias, devolver el HTML corregido

REGLAS CR�TICAS DE PRESERVACI�N HTML

IMPORTANTE: El HTML debe conservar su estructura y formato visual. NO lo modernices ni lo transformes.

1. PRESERVAR ETIQUETAS Y ESTRUCTURA:
   Mant�n exactamente las mismas etiquetas HTML que existan en el original
   Mant�n el anidamiento exacto de todas las etiquetas
   No agregues ni elimines etiquetas
   No cambies el orden de anidamiento
   No cambies el formato visual del texto

2. PRESERVAR ATRIBUTOS:
   Mant�n todos los atributos exactamente como est�n
   No agregues atributos style=\"...\" donde no existan
   No elimines atributos existentes
   No cambies valores de atributos
   No agregues clases CSS

3. PRESERVAR CONTENIDO NO TEXTUAL:
   Mant�n todas las entidades HTML (&nbsp;, &amp;, &lt;, &gt;, etc.) exactamente como est�n
   No elimines espacios &nbsp;
   No elimines etiquetas vac�as si forman parte de la estructura original
   No simplifiques la estructura HTML

4. SOLO CORRIGE EL TEXTO VISIBLE:
   Corrige ortograf�a dentro del contenido textual
   Corrige gram�tica y puntuaci�n
   Mejora redacci�n sin cambiar significado
   Mejora coherencia del texto
   Corrige acentos y tildes
   Corrige may�sculas y min�sculas cuando sea necesario

5. PROHIBIDO HACER:
   No modernices el HTML
   No cambies etiquetas por otras distintas
   No elimines ni reestructures contenedores
   No cambies el formato visual del texto
   No cambies el estilo de maquetaci�n existente
   No alteres el contenido HTML fuera del texto visible

6. VALIDACI�N DE SALIDA:
   El HTML corregido debe conservar la misma estructura del original
   Solo el texto dentro de las etiquetas debe cambiar
   La cantidad de etiquetas debe mantenerse
   Los atributos deben mantenerse
   El anidamiento debe ser id�ntico

CRITERIOS DE EVALUACI�N

Clasifica el texto en:
correcto: El texto es coherente, tiene sentido l�gico y NO tiene errores ortogr�ficos
puede_mejorarse: Solo usar si el texto es v�lido pero la redacci�n podr�a ser m�s clara o precisa, SIN errores ortogr�ficos
incorrecto: Debe marcarse como incorrecto si tiene al menos un error ortogr�fico, errores gramaticales o es incoherente

REGLAS ESTRICTAS DE CLASIFICACI�N:

Si el texto contiene al menos un error ortogr�fico:
  El estado DEBE ser incorrecto
  NO puede ser puede_mejorarse
  NO puede ser correcto

Si el texto es incoherente o no tiene sentido l�gico:
  El estado DEBE ser incorrecto

Si el texto tiene errores gramaticales graves:
  El estado DEBE ser incorrecto

Solo usa puede_mejorarse cuando:
  El texto NO tiene errores ortogr�ficos
  El texto es coherente
  Pero la redacci�n podr�a mejorar en claridad o precisi�n

REGLAS DE SALIDA

Debes devolver exactamente tres campos: estado, explicacion y html_corregido
No agregues campos adicionales
No uses emojis
No uses markdown
No uses listas en la explicaci�n
No uses asteriscos ni numeraci�n

Reglas de contenido:

El campo explicacion debe ser una o dos oraciones cortas y claras
La explicacion debe ser objetiva y directa
No uses comillas dobles dentro de la explicacion, usa comillas simples si es necesario

El campo html_corregido debe contener el HTML corregido completo
Si no hay correcciones, html_corregido debe ser cadena vac�a

Si el estado es correcto:
  html_corregido debe ser cadena vac�a
  explicacion debe ser cadena vac�a

Si el estado es puede_mejorarse o incorrecto:
  html_corregido debe contener el HTML con las correcciones aplicadas
  explicacion debe explicar brevemente los problemas encontrados
  Las correcciones mencionadas en la explicacion deben verse reflejadas en html_corregido

IMPORTANTE PARA EVITAR ERRORES JSON:
Escapa correctamente las comillas dobles dentro del HTML: usa \\\" 
No uses saltos de l�nea literales en el JSON, usa \\n si es necesario
No uses caracteres especiales sin escapar
El HTML debe ser una cadena JSON v�lida

IMPORTANTE PARA EVITAR ERRORES JSON - REGLAS ESTRICTAS:

1. ESCAPAR COMILLAS EN HTML:
   Dentro del campo html_corregido, TODAS las comillas dobles deben escaparse con barra invertida
   Ejemplo CORRECTO en JSON: \"<div>texto con \\\"comillas\\\" aqu�</div>\"
   Ejemplo INCORRECTO: \"<div>texto con \"comillas\" aqu�</div>\"

2. NO USAR COMILLAS DOBLES EN EXPLICACION:
   En el campo explicacion, NO uses comillas dobles para citar palabras
   Usa comillas simples en su lugar
   Ejemplo CORRECTO: Errores ortogr�ficos: 'intelectualess', 'formacci�n'

3. CARACTERES ESPECIALES:
   Escapa barras invertidas: \\\\
   No uses saltos de l�nea literales, usa \\n
   No uses caracteres especiales sin escapar

4. VALIDACI�N ANTES DE RESPONDER:
   Verifica mentalmente que todas las comillas dobles dentro de strings est�n escapadas con \\\" 
   Verifica que no haya comillas dobles sin escapar en explicacion
   Verifica que el JSON sea sint�cticamente v�lido

Verificaci�n final antes de responder:

Compara el HTML original con el html_corregido que planeas devolver
Verifica que TODAS las etiquetas HTML sean id�nticas
Verifica que TODOS los atributos sean id�nticos
Verifica que solo el texto dentro de las etiquetas haya cambiado
Si agregaste, eliminaste o modificaste alguna etiqueta HTML, corr�gelo antes de responder

RECUERDA: Tu objetivo es corregir el TEXTO, no el C�DIGO HTML. Act�a como un corrector ortogr�fico que trabaja dentro del HTML sin tocar las etiquetas.

Devuelve �nicamente el JSON.";


    $systemPromptCampo = obtener_prompt_validacion_campo($nombreCampo);
    $systemPrompt = $systemPromptBase . "\n\n" . $systemPromptCampo;


    
    $userPrompt = "HTML a evaluar:\n\n{$textoHTML}";


$schema = [
    'type' => 'object',
    'properties' => [
        'estado' => [
            'type' => 'string',
            'enum' => ['correcto', 'puede_mejorarse', 'incorrecto'],
            'description' => 'Estado de la validaci�n'
        ],
        'explicacion' => [
            'type' => 'string',
            'description' => 'Explicaci�n breve del problema. Cadena vac�a si estado es correcto. No usar comillas dobles.'
        ],
        'html_corregido' => [
            'type' => 'string',
            'description' => 'HTML corregido manteniendo estructura exacta. Cadena vac�a si estado es correcto. Escapar comillas dobles con \\\"'
        ]
    ],
    'required' => ['estado', 'explicacion', 'html_corregido'],
    'additionalProperties' => false
];


    $respuesta =  AWSConfigBedrock::llm_generate_json_cached(
        $systemPrompt,
        $userPrompt,
        $schema,
        $maxTokens,
        $temperature
    );

    return $respuesta;
}



function generarUpdateFromAutEstadoCasoTabla( $intAutEstadoCaso, $arrWhereCampos ) {
    $intAutEstadoCaso = intval($intAutEstadoCaso);
    if( $intAutEstadoCaso > 0 ){
        $boolOk = false;
        $arrInfo = array();
        $strQuery = "SELECT *
                     FROM   aut_estado_caso_tabla,
                            aut_tabla,
                            aut_llave
                     WHERE  aut_estado_caso_tabla.aut_estado_caso = {$intAutEstadoCaso}
                     AND    aut_estado_caso_tabla.aut_tabla = aut_tabla.aut_tabla
                     AND    aut_tabla.aut_tabla = aut_llave.aut_tabla";
        $qTMP = db_query($strQuery);
        while( $rTMP = db_fetch_assoc($qTMP) ) {
            $arrInfo[$rTMP["NOMBRE_AUT_TABLA"]]["CAMPO_NOMBRE"] = $rTMP['CAMPO_UPDATE'];
            $arrInfo[$rTMP["NOMBRE_AUT_TABLA"]]["CAMPO_VALOR"] = $rTMP['VALOR'];
            $arrInfo[$rTMP["NOMBRE_AUT_TABLA"]]["WHERE"][$rTMP['CAMPO']] = $rTMP['CAMPO'];
        }
        db_free_result($qTMP);

        reset($arrInfo);
        foreach( $arrInfo as  $arrTMP['key'] => $arrTMP['value']) {
            $strQuery = '';
            $strWhere = '';
            $strQuery ="UPDATE  {$arrTMP['key']}
                        SET     {$arrTMP['value']['CAMPO_NOMBRE']} = {$arrTMP['value']['CAMPO_VALOR']} ";

            reset($arrTMP['value']['WHERE']);
            foreach( $arrTMP['value']['WHERE'] as  $arrTMP2['key'] => $arrTMP2['value'])  {
                if( isset($arrWhereCampos[$arrTMP2['key']]) ) {
                    $strWhere .= empty($strWhere) ? '' : ' AND ';
                    $strWhere .= "{$arrTMP2['key']} = {$arrWhereCampos[$arrTMP2['key']]}";
                }
            }

            if( !empty($strWhere) ) {
                $strQuery .= " WHERE {$strWhere}";
                db_query($strQuery);
                $boolOk = true;
            }


        }
    }

    return $boolOk;

}

function limpiarHTMLVacio($html) {
    if (empty($html)) return null;
    
    $texto = strip_tags($html);
    $texto = str_replace('&nbsp;', '', $texto);
    $texto = str_replace('&amp;nbsp;', '', $texto);
    $texto = trim($texto);
    
    return empty($texto) ? null : $html;
}

function getSyllabusUA($intCurso) {
    global $cfg;
    
    $intCurso = intval($intCurso);
    if ($intCurso <= 0) return null;
    
    $strQuery = "SELECT SYLLABUS_UA, CURSO, 
                        ADD_USER, ADD_FECHA, FECHA_INICIO, FECHA_FIN, MOD_USER, MOD_FECHA
                 FROM {$cfg["academico"]["schema"]}.SYLLABUS_UA
                 WHERE CURSO = {$intCurso}
                   AND FECHA_FIN IS NULL";
    
    $qTMP = db_query($strQuery);
    $rTMP = db_fetch_assoc($qTMP);
    db_free_result($qTMP);
    
    return $rTMP ? $rTMP : null;
}


function guardarClobSyllabusUA($intSyllabusUA, $campo, $valor) {
    global $cfg;

    
    
    $intSyllabusUA = intval($intSyllabusUA);
    if ($intSyllabusUA <= 0) return;
    
    $valorLimpio = limpiarHTMLVacio($valor);
    
    // Limpiar SOLO este campo espec�fico primero
    $strQuery = "UPDATE {$cfg["academico"]["schema"]}.SYLLABUS_UA
                 SET {$campo} = NULL
                 WHERE SYLLABUS_UA = {$intSyllabusUA}";
    db_query($strQuery);
    
    if ($valorLimpio === null) {
        return;
    }
    
    $intCountToUpdate = 0;
    while ($intCountToUpdate <= strlen($valorLimpio)) {
        $strTMPTexto = substr($valorLimpio, $intCountToUpdate, 4000);
        $strTMPTexto = db_escape($strTMPTexto);
        
        if ($intCountToUpdate == 0) {
            $strQuery = "UPDATE {$cfg["academico"]["schema"]}.SYLLABUS_UA
                         SET {$campo} = '{$strTMPTexto}'
                         WHERE SYLLABUS_UA = {$intSyllabusUA}";
        } else {
            $strQuery = "UPDATE {$cfg["academico"]["schema"]}.SYLLABUS_UA
                         SET {$campo} = {$campo} || '{$strTMPTexto}'
                         WHERE SYLLABUS_UA = {$intSyllabusUA}";
        }
        
        db_query($strQuery);
        $intCountToUpdate += 4000;
    }
}

function sanitizarHtmlBiblio($html) {
    if ($html === null || $html === '') {
        return '';
    }

    $html = trim((string) $html);
    $html = preg_replace('/<!--\[if[^\]]*\]>.*?<!\[endif\]-->/is', '', $html);
    $html = preg_replace('/<\?xml[^>]*\?>/i', '', $html);
    $html = preg_replace('/<o:[^>]*>.*?<\/o:[^>]*>/is', '', $html);
    $html = preg_replace('/<w:[^>]*>.*?<\/w:[^>]*>/is', '', $html);
    $html = preg_replace('/<m:[^>]*>.*?<\/m:[^>]*>/is', '', $html);
    $html = preg_replace('/<!--.*?-->/s', '', $html);
    //quitar p de aqui: 

    return strip_tags($html, '<b><i><u><strong><em><br><a>');
}

function normalizarReferenciaBiblioPost($html) {
    $html = sanitizarHtmlBiblio(trim((string) $html));
    return limpiarHTMLVacio($html);
}

function getReferenciaBiblio($intSyllabusUABiblio) {
    global $cfg;

    $intSyllabusUABiblio = intval($intSyllabusUABiblio);
    if ($intSyllabusUABiblio <= 0) {
        return '';
    }

    return getTextoClob('REFERENCIA_COMPLETA', 'SYLLABUS_UA_BIBLIO', 'SYLLABUS_UA_BIBLIO', $intSyllabusUABiblio);
}

function getReferenciaBiblioLog($intLogId) {
    global $cfg;

    $intLogId = intval($intLogId);
    if ($intLogId <= 0) {
        return '';
    }

    return getTextoClob('REFERENCIA_COMPLETA', 'SYLLABUS_UA_BIBLIO_LOG', 'LOG_ID', $intLogId);
}

function renderReferenciaBiblioVista($html) {
    $html = trim((string) $html);
    if ($html === '' || limpiarHTMLVacio($html) === null) {
        return '<em>Sin informaci�n</em>';
    }

    return $html;
}

function guardarReferenciaBiblio($intSyllabusUABiblio, $valor) {
    global $cfg;

    $intSyllabusUABiblio = intval($intSyllabusUABiblio);
    if ($intSyllabusUABiblio <= 0) {
        return;
    }

    $valorLimpio = limpiarHTMLVacio(sanitizarHtmlBiblio($valor));

    $strQuery = "UPDATE {$cfg['academico']['schema']}.SYLLABUS_UA_BIBLIO
                 SET REFERENCIA_COMPLETA = NULL
                 WHERE SYLLABUS_UA_BIBLIO = {$intSyllabusUABiblio}";
    db_query($strQuery);

    if ($valorLimpio === null) {
        return;
    }

    $intCountToUpdate = 0;
    while ($intCountToUpdate <= strlen($valorLimpio)) {
        $strTMPTexto = substr($valorLimpio, $intCountToUpdate, 4000);
        $strTMPTexto = db_escape($strTMPTexto);

        if ($intCountToUpdate == 0) {
            $strQuery = "UPDATE {$cfg['academico']['schema']}.SYLLABUS_UA_BIBLIO
                         SET REFERENCIA_COMPLETA = '{$strTMPTexto}'
                         WHERE SYLLABUS_UA_BIBLIO = {$intSyllabusUABiblio}";
        } else {
            $strQuery = "UPDATE {$cfg['academico']['schema']}.SYLLABUS_UA_BIBLIO
                         SET REFERENCIA_COMPLETA = REFERENCIA_COMPLETA || '{$strTMPTexto}'
                         WHERE SYLLABUS_UA_BIBLIO = {$intSyllabusUABiblio}";
        }

        db_query($strQuery);
        $intCountToUpdate += 4000;
    }
}

function obtenerCurrvalSeqBiblio() {
    $qCurrval = db_query("SELECT SEQ_SYLLABUS_UA_BIBLIO.CURRVAL AS ID FROM DUAL");
    if (!$qCurrval) {
        return 0;
    }
    $rCurrval = db_fetch_array($qCurrval);
    db_free_result($qCurrval);

    return isset($rCurrval['ID']) ? intval($rCurrval['ID']) : 0;
}



function getTextoClob($strCampo, $strTabla, $strCampoId, $strKeyMatch) {

        if( empty($strCampo) ) return "";
        $strQuery = "SELECT MAX(dbms_lob.getlength({$strCampo}) ) FROM {$strTabla} WHERE {$strCampoId} = '{$strKeyMatch}'";
        $intCountPartes = sqlGetValueFromKey($strQuery);
        $strCampos = "";
        $intCount = 1;
        $intParte = 0;
        while( $intCount <= $intCountPartes ) {
            $intParte++;
            $strCampos .= (empty($strCampos)) ? "" : ",";
            $strCampos .= "DBMS_LOB.substr({$strCampo}, 4000, {$intCount}) PARTE{$intParte}";
            $intCount += 4000;
        }

        if( empty($strCampos) ) return "";
        $strQuery = "SELECT {$strCampoId}, {$strCampos} FROM {$strTabla} where {$strCampoId}='{$strKeyMatch}'";
        $ret = sqlGetValueFromKey($strQuery);

        $strTextoClob = "";
        for( $i = 1; $i <= $intParte; $i++ ) {
            if( !is_null($ret["PARTE{$i}"]) && !empty($ret["PARTE{$i}"]) )
                $strTextoClob .= $ret["PARTE{$i}"];
        }
        return $strTextoClob;

    }



function getLogCampoSyllabusOLD($intSyllabusUA, $campo) {
    global $cfg;
    
    $intSyllabusUA = intval($intSyllabusUA);
    $campo = db_escape($campo);
    
    // Query que compara cada registro con el anterior usando LAG()
    // Y tambi�n filtra registros iguales al valor actual de SYLLABUS_UA
    $strQuery = "
        SELECT 
            L.LOG_ID,
            L.SYLLABUS_UA,
            L.VALOR_ANTERIOR,
            L.ADD_FECHA_LOG,
            L.ADD_USER_LOG,
            L.USUARIO
        FROM (
            SELECT 
                L.LOG_ID,
                L.SYLLABUS_UA,
                L.{$campo} AS VALOR_ANTERIOR,
                L.ADD_FECHA_LOG,
                L.ADD_USER_LOG,
                p.USUARIO,
                L_ANT.{$campo} AS VALOR_PREVIO,
                S.{$campo} AS VALOR_ACTUAL_TABLA
            FROM (
                SELECT 
                    LOG_ID,
                    SYLLABUS_UA,
                    {$campo},
                    ADD_FECHA_LOG,
                    ADD_USER_LOG,
                    LAG(LOG_ID) OVER (ORDER BY ADD_FECHA_LOG, LOG_ID) AS LOG_ID_ANTERIOR
                FROM {$cfg['academico']['schema']}.SYLLABUS_UA_LOG
                WHERE SYLLABUS_UA = {$intSyllabusUA}
                  AND TIPO_OPERACION = 'U'
                  AND {$campo} IS NOT NULL
            ) L
            LEFT JOIN {$cfg['academico']['schema']}.SYLLABUS_UA_LOG L_ANT
                ON L.LOG_ID_ANTERIOR = L_ANT.LOG_ID
            LEFT JOIN {$cfg['academico']['schema']}.PERSONA p
                ON L.ADD_USER_LOG = p.PERSONA
            LEFT JOIN {$cfg['academico']['schema']}.SYLLABUS_UA S
                ON L.SYLLABUS_UA = S.SYLLABUS_UA
        ) L
        WHERE (
            L.VALOR_PREVIO IS NULL
            OR DBMS_LOB.COMPARE(L.VALOR_ANTERIOR, L.VALOR_PREVIO) != 0
        )
        AND (
            L.VALOR_ACTUAL_TABLA IS NULL
            OR DBMS_LOB.COMPARE(L.VALOR_ANTERIOR, L.VALOR_ACTUAL_TABLA) != 0
        )
        ORDER BY L.ADD_FECHA_LOG DESC, L.LOG_ID DESC
    ";
    
    $arrLog = array();
    $qLog = db_query($strQuery);
    
    while($rLog = db_fetch_assoc($qLog)) {
        $arrLog[] = $rLog;
    }
    
    db_free_result($qLog);
    return $arrLog;
}

function getLogCampoSyllabus($intSyllabusUA, $campo) {
    global $cfg;
    
    $intSyllabusUA = intval($intSyllabusUA);
    $campo = db_escape($campo);
    
    $strQuery = "
        SELECT 
            L.LOG_ID,
            L.SYLLABUS_UA,
            L.VALOR_ANTERIOR,
            L.ADD_FECHA_LOG,
            L.ADD_USER_LOG,
            L.USUARIO
        FROM (
            SELECT 
                L.LOG_ID,
                L.SYLLABUS_UA,
                L.{$campo} AS VALOR_ANTERIOR,
                L.ADD_FECHA_LOG,
                L.ADD_USER_LOG,
                p.USUARIO,
                L_ANT.{$campo} AS VALOR_PREVIO,
                S.{$campo} AS VALOR_ACTUAL_TABLA
            FROM (
                SELECT 
                    LOG_ID,
                    SYLLABUS_UA,
                    {$campo},
                    ADD_FECHA_LOG,
                    ADD_USER_LOG,
                    ADD_USER,
                    LAG(LOG_ID) OVER (ORDER BY ADD_FECHA_LOG, LOG_ID) AS LOG_ID_ANTERIOR
                FROM {$cfg['academico']['schema']}.SYLLABUS_UA_LOG
                WHERE SYLLABUS_UA = {$intSyllabusUA}
                  AND TIPO_OPERACION = 'U'
                  AND {$campo} IS NOT NULL
            ) L
            LEFT JOIN {$cfg['academico']['schema']}.SYLLABUS_UA_LOG L_ANT
                ON L.LOG_ID_ANTERIOR = L_ANT.LOG_ID
            LEFT JOIN {$cfg['academico']['schema']}.PERSONA p
                ON NVL(L.ADD_USER_LOG, L.ADD_USER) = p.PERSONA
            LEFT JOIN {$cfg['academico']['schema']}.SYLLABUS_UA S
                ON L.SYLLABUS_UA = S.SYLLABUS_UA
        ) L
        WHERE (
            L.VALOR_PREVIO IS NULL
            OR DBMS_LOB.COMPARE(L.VALOR_ANTERIOR, L.VALOR_PREVIO) != 0
        )
        AND (
            L.VALOR_ACTUAL_TABLA IS NULL
            OR DBMS_LOB.COMPARE(L.VALOR_ANTERIOR, L.VALOR_ACTUAL_TABLA) != 0
        )
        ORDER BY L.ADD_FECHA_LOG DESC, L.LOG_ID DESC
    ";
    
    $arrLog = array();
    $qLog = db_query($strQuery);
    while($rLog = db_fetch_assoc($qLog)) {
        $arrLog[] = $rLog;
    }
    db_free_result($qLog);
    return $arrLog;
}



// Funcin para obtener bitacora individual de un RA específico
function getLogRA($intSyllabusUARA) {
    global $cfg;
    
    $intSyllabusUARA = intval($intSyllabusUARA);
    


        $strQuery = "
        SELECT 
            L.LOG_ID,
            L.SYLLABUS_UA_RA,
            L.DESCRIPCION_RA,
            L.BLOOM_NIVEL,
            bn.NOMBRE as BLOOM_NOMBRE,
            L.TIPO_OPERACION,
            TO_CHAR(NVL(L.MOD_FECHA, L.ADD_FECHA), 'DD/MM/YYYY HH24:MI:SS') as FECHA_LOG,
            L.ADD_USER_LOG,
            p.USUARIO,
            LAG(L.DESCRIPCION_RA) OVER (ORDER BY L.ADD_FECHA_LOG, L.LOG_ID) AS DESCRIPCION_ANTERIOR,
            LAG(L.BLOOM_NIVEL) OVER (ORDER BY L.ADD_FECHA_LOG, L.LOG_ID) AS BLOOM_ANTERIOR
        FROM {$cfg['academico']['schema']}.SYLLABUS_UA_RA_LOG L
        LEFT JOIN {$cfg['academico']['schema']}.PERSONA p 
            ON L.ADD_USER_LOG = p.PERSONA
        LEFT JOIN {$cfg['academico']['schema']}.BLOOM_NIVEL bn 
            ON L.BLOOM_NIVEL = bn.BLOOM_NIVEL
        WHERE L.SYLLABUS_UA_RA = {$intSyllabusUARA}
          AND L.TIPO_OPERACION = 'U'
        ORDER BY L.ADD_FECHA_LOG DESC, L.LOG_ID DESC
    ";
    
    $arrLog = array();
    $qLog = db_query($strQuery);
    
    while($rLog = db_fetch_assoc($qLog)) {
        $arrLog[] = $rLog;
    }
    
    db_free_result($qLog);
    return $arrLog;
}

// Funci�n para obtener bit�cora individual de una Bibliograf�a espec�fica
function getLogBiblio($intSyllabusUABiblio) {
    global $cfg;

    $intSyllabusUABiblio = intval($intSyllabusUABiblio);

    $strQuery = "
        SELECT
            L.LOG_ID,
            L.SYLLABUS_UA_BIBLIO,
            L.TIPO_OPERACION,
            TO_CHAR(NVL(L.MOD_FECHA, L.ADD_FECHA), 'DD/MM/YYYY HH24:MI:SS') as FECHA_LOG,
            L.ADD_USER_LOG,
            p.USUARIO
        FROM {$cfg['academico']['schema']}.SYLLABUS_UA_BIBLIO_LOG L
        LEFT JOIN {$cfg['academico']['schema']}.PERSONA p
            ON L.ADD_USER_LOG = p.PERSONA
        WHERE L.SYLLABUS_UA_BIBLIO = {$intSyllabusUABiblio}
          AND L.TIPO_OPERACION = 'U'
        ORDER BY L.ADD_FECHA_LOG DESC, L.LOG_ID DESC
    ";

    $arrLog = array();
    $qLog = db_query($strQuery);
    while($rLog = db_fetch_assoc($qLog)) {
        $rLog['REFERENCIA_COMPLETA'] = getReferenciaBiblioLog($rLog['LOG_ID']);
        $arrLog[] = $rLog;
    }
    db_free_result($qLog);
    return $arrLog;
}

// Funci�n para obtener bit�cora general de todas las Bibliograf�as de un syllabus
function getLogTodosBiblio($intSyllabusUA) {
    global $cfg;

    $intSyllabusUA = intval($intSyllabusUA);

    $strQuery = "
        SELECT
            L.LOG_ID,
            L.SYLLABUS_UA_BIBLIO,
            L.TIPO_OPERACION,
            TO_CHAR(L.ADD_FECHA_LOG, 'DD/MM/YYYY HH24:MI:SS') as FECHA_LOG,
            p.USUARIO
        FROM {$cfg['academico']['schema']}.SYLLABUS_UA_BIBLIO_LOG L
        LEFT JOIN {$cfg['academico']['schema']}.PERSONA p
            ON L.ADD_USER_LOG = p.PERSONA
        WHERE L.SYLLABUS_UA = {$intSyllabusUA}
        ORDER BY L.ADD_FECHA_LOG DESC, L.LOG_ID DESC
    ";

    $arrLog = array();
    $qLog = db_query($strQuery);
    while($rLog = db_fetch_assoc($qLog)) {
        $rLog['REFERENCIA_COMPLETA'] = getReferenciaBiblioLog($rLog['LOG_ID']);
        $arrLog[] = $rLog;
    }
    db_free_result($qLog);
    return $arrLog;
}

// Funci�n para obtener Bibliograf�as activas de un syllabus
function getBiblioActivos($intSyllabusUA) {
    global $cfg;

    $intSyllabusUA = intval($intSyllabusUA);

    $strQuery = "
        SELECT
            b.SYLLABUS_UA_BIBLIO,
            TO_CHAR(b.ADD_FECHA, 'DD/MM/YYYY HH24:MI:SS') as FECHA_CREACION,
            p.USUARIO as USUARIO_CREACION
        FROM {$cfg['academico']['schema']}.SYLLABUS_UA_BIBLIO b
        LEFT JOIN {$cfg['academico']['schema']}.PERSONA p
            ON b.ADD_USER = p.PERSONA
        WHERE b.SYLLABUS_UA = {$intSyllabusUA}
        ORDER BY b.ADD_FECHA
    ";

    $arrBiblio = array();
    $qBiblio = db_query($strQuery);
    while($rBiblio = db_fetch_assoc($qBiblio)) {
        $rBiblio['REFERENCIA_COMPLETA'] = getReferenciaBiblio($rBiblio['SYLLABUS_UA_BIBLIO']);
        $arrBiblio[] = $rBiblio;
    }
    db_free_result($qBiblio);
    return $arrBiblio;
}

// Funci�n para obtener Bibliograf�as eliminadas de un syllabus
function getBiblioEliminados($intSyllabusUA) {
    global $cfg;

    $intSyllabusUA = intval($intSyllabusUA);

    $strQuery = "
        SELECT
            L.LOG_ID,
            L.SYLLABUS_UA_BIBLIO,
            TO_CHAR(L.ADD_FECHA_LOG, 'DD/MM/YYYY HH24:MI:SS') as FECHA_ELIMINACION,
            p.USUARIO
        FROM {$cfg['academico']['schema']}.SYLLABUS_UA_BIBLIO_LOG L
        LEFT JOIN {$cfg['academico']['schema']}.PERSONA p
            ON L.ADD_USER_LOG = p.PERSONA
        WHERE L.SYLLABUS_UA = {$intSyllabusUA}
          AND L.TIPO_OPERACION = 'D'
        ORDER BY L.ADD_FECHA_LOG DESC
    ";

    $arrBiblio = array();
    $qBiblio = db_query($strQuery);
    while($rBiblio = db_fetch_assoc($qBiblio)) {
        $rBiblio['REFERENCIA_COMPLETA'] = getReferenciaBiblioLog($rBiblio['LOG_ID']);
        $arrBiblio[] = $rBiblio;
    }
    db_free_result($qBiblio);
    return $arrBiblio;
}


// Funcin para obtener bitacora general de todos los RA de un syllabus
function getLogTodosRA($intSyllabusUA) {
    global $cfg;
    
    $intSyllabusUA = intval($intSyllabusUA);
    
    $strQuery = "
        SELECT 
            L.LOG_ID,
            L.SYLLABUS_UA_RA,
            L.DESCRIPCION_RA,
            bn.NOMBRE as BLOOM_NOMBRE,
            L.TIPO_OPERACION,
            TO_CHAR(L.ADD_FECHA_LOG, 'DD/MM/YYYY HH24:MI:SS') as FECHA_LOG,
            p.USUARIO
        FROM {$cfg['academico']['schema']}.SYLLABUS_UA_RA_LOG L
        LEFT JOIN {$cfg['academico']['schema']}.PERSONA p 
            ON L.ADD_USER_LOG = p.PERSONA
        LEFT JOIN {$cfg['academico']['schema']}.BLOOM_NIVEL bn 
            ON L.BLOOM_NIVEL = bn.BLOOM_NIVEL
        WHERE L.SYLLABUS_UA = {$intSyllabusUA}
        ORDER BY L.ADD_FECHA_LOG DESC, L.LOG_ID DESC
    ";
    
    $arrLog = array();
    $qLog = db_query($strQuery);
    
    while($rLog = db_fetch_assoc($qLog)) {
        $arrLog[] = $rLog;
    }
    
    db_free_result($qLog);
    return $arrLog;
}

// Funci�n para obtener RA activos de un syllabus
function getRAActivos($intSyllabusUA) {
    global $cfg;
    
    $intSyllabusUA = intval($intSyllabusUA);
    
    $strQuery = "
        SELECT 
            ra.SYLLABUS_UA_RA,
            ra.DESCRIPCION_RA,
            bn.NOMBRE as BLOOM_NOMBRE,
            TO_CHAR(ra.ADD_FECHA, 'DD/MM/YYYY HH24:MI:SS') as FECHA_CREACION,
            p.USUARIO as USUARIO_CREACION
        FROM {$cfg['academico']['schema']}.SYLLABUS_UA_RA ra
        LEFT JOIN {$cfg['academico']['schema']}.BLOOM_NIVEL bn 
            ON ra.BLOOM_NIVEL = bn.BLOOM_NIVEL
        LEFT JOIN {$cfg['academico']['schema']}.PERSONA p 
            ON ra.ADD_USER = p.PERSONA
        WHERE ra.SYLLABUS_UA = {$intSyllabusUA}
        ORDER BY ra.ADD_FECHA
    ";
    
    $arrRA = array();
    $qRA = db_query($strQuery);
    
    while($rRA = db_fetch_assoc($qRA)) {
        $arrRA[] = $rRA;
    }
    
    db_free_result($qRA);
    return $arrRA;
}

// Funci�n para obtener RA eliminados de un syllabus
function getRAEliminados($intSyllabusUA) {
    global $cfg;
    
    $intSyllabusUA = intval($intSyllabusUA);
    
    $strQuery = "
        SELECT 
            L.SYLLABUS_UA_RA,
            L.DESCRIPCION_RA,
            bn.NOMBRE as BLOOM_NOMBRE,
            TO_CHAR(L.ADD_FECHA_LOG, 'DD/MM/YYYY HH24:MI:SS') as FECHA_ELIMINACION,
            p.USUARIO
        FROM {$cfg['academico']['schema']}.SYLLABUS_UA_RA_LOG L
        LEFT JOIN {$cfg['academico']['schema']}.PERSONA p 
            ON L.ADD_USER_LOG = p.PERSONA
        LEFT JOIN {$cfg['academico']['schema']}.BLOOM_NIVEL bn 
            ON L.BLOOM_NIVEL = bn.BLOOM_NIVEL
        WHERE L.SYLLABUS_UA = {$intSyllabusUA}
          AND L.TIPO_OPERACION = 'D'
        ORDER BY L.ADD_FECHA_LOG DESC
    ";
    
    $arrRA = array();
    $qRA = db_query($strQuery);
    
    while($rRA = db_fetch_assoc($qRA)) {
        $arrRA[] = $rRA;
    }
    
    db_free_result($qRA);
    return $arrRA;
}



    
  



function getSubjectTemplate($intTemplate){
    $strSubjectTemplate = '';
    if($intTemplate > 0){
        $strSubjectTemplate = sqlGetValueFromKey("SELECT DBMS_LOB.SUBSTR(asunto) asunto FROM template WHERE template = {$intTemplate}");
    }
    return $strSubjectTemplate;
}

function sendEmailFromAutEstadoCaso( $intFacultad, $intAutEstadoCaso, $intAutCaso ) {
    global $cfg;
    $boolSendEmail = false;
    if($intFacultad > 0 && $intAutEstadoCaso > 0){
        $strQuery = "SELECT persona.usuario,
                            fnt_get_correo(carne.carne) email,
                            aut_autoriza_persona.email_template template
                     FROM   aut_autoriza_persona,
                            persona,
                            carne
                     WHERE  aut_autoriza_persona.aut_estado_caso = {$intAutEstadoCaso}
                     AND    aut_autoriza_persona.persona = persona.persona
                     AND    carne.persona = persona.persona
                     UNION
                     SELECT persona.usuario,
                            fnt_get_correo(carne.carne) email,
                            aut_autoriza_perfil.email_requerido template
                     FROM   aut_autoriza_perfil,
                            persona_perfil,
                            persona,
                            carne,
                            persona_carrera,
                            carrera,
                            facultad
                     WHERE  aut_autoriza_perfil.aut_estado_caso = {$intAutEstadoCaso}
                     AND    facultad.facultad = {$intFacultad}
                     AND    aut_autoriza_perfil.perfil = persona_perfil.perfil
                     AND    persona_perfil.persona = persona.persona
                     AND    persona_carrera.persona = persona.persona
                     AND    persona_carrera.carrera = carrera.carrera
                     AND    carrera.facultad = facultad.facultad
                     AND    carne.persona = persona.persona";
        $qTMP = db_query($strQuery);
        while( $rTMP = db_fetch_assoc($qTMP) ) {
            $strMensaje = getTextoClob('texto', 'template', 'template', $rTMP['TEMPLATE']);
            $strTypeUrl = ( $cfg["core"]["HTTPS"] ) ? $cfg["core"]["url_secure"] : $cfg["core"]["url"];
            $strAsunto = getSubjectTemplate($rTMP['TEMPLATE']);
            $strlink = "<a href='{$strTypeUrl}adm_academico_autorizador_equivalencias.php?u={$rTMP['USUARIO']}&facultad={$intFacultad}' target='_blank'>Click aqu�</a>";
            $strMensaje = sprintf($strMensaje, $strlink);
            db_enviar_mail("academico@{$_SERVER["SERVER_NAME"]}", $rTMP['EMAIL'], $strAsunto, $strMensaje,"user");
            $boolSendEmail = true;
        }
        db_free_result($qTMP);
    }

    if( $boolSendEmail && $intAutCaso > 0){
        db_query("UPDATE aut_caso SET email_enviado = 'Y' WHERE aut_caso = {$intAutCaso}");
    }

}

function sendEmailNotificacion($intAutEstadoCaso, $intAutCasoEstado, $intFacultad){
    $boolSendEmailNotificacion = false;
    $intAutEstadoCaso = intval($intAutEstadoCaso);
    $intFacultad = intval($intFacultad);

    /* Personas a las que se env�a email notificaci�n */
    if($intAutEstadoCaso > 0 && $intFacultad > 0){
        $strQuery = "SELECT PERSONA.USUARIO,
                            FNT_GET_CORREO(CARNE.CARNE) EMAIL,
                            AUT_NOTIFICA_PERSONA.TEMPLATE
                     FROM   AUT_NOTIFICA_PERSONA
                        INNER JOIN PERSONA
                            ON  AUT_NOTIFICA_PERSONA.PERSONA = PERSONA.PERSONA
                        INNER JOIN CARNE
                            ON  PERSONA.PERSONA = CARNE.PERSONA
                     WHERE  AUT_NOTIFICA_PERSONA.AUT_ESTADO_CASO = {$intAutEstadoCaso}
                     UNION
                     SELECT PERSONA.USUARIO,
                            FNT_GET_CORREO(CARNE.CARNE) EMAIL,
                            AUT_NOTIFICA_PERFIL.TEMPLATE
                     FROM   AUT_NOTIFICA_PERFIL
                        INNER JOIN PERSONA_PERFIL
                            ON  AUT_NOTIFICA_PERFIL.PERFIL = PERSONA_PERFIL.PERFIL
                        INNER JOIN PERSONA
                            ON  PERSONA_PERFIL.PERSONA = PERSONA.PERSONA
                        INNER JOIN CARNE
                            ON  PERSONA.PERSONA = CARNE.PERSONA
                        INNER JOIN PERSONA_CARRERA
                            ON  PERSONA.PERSONA = PERSONA_CARRERA.PERSONA
                        INNER JOIN CARRERA
                            ON  PERSONA_CARRERA.CARRERA = CARRERA.CARRERA
                        INNER JOIN FACULTAD
                            ON  CARRERA.FACULTAD = FACULTAD.FACULTAD
                     WHERE  AUT_NOTIFICA_PERFIL.AUT_ESTADO_CASO = {$intAutEstadoCaso}
                     AND    FACULTAD.FACULTAD = {$intFacultad}";
        $qTMP = db_query($strQuery);
        while($rTMP = db_fetch_assoc($qTMP)){
            $strMensaje = getTextoClob('texto', 'template', 'template', $rTMP['TEMPLATE']);
            $strAsunto = getSubjectTemplate($rTMP['TEMPLATE']);
            db_enviar_mail("academico@{$_SERVER["SERVER_NAME"]}", $rTMP['EMAIL'], $strAsunto, $strMensaje,"user");
            $boolSendEmailNotificacion = true;
        }
        db_free_result($qTMP);
    }

    /* Notificaci�n persona que solicita autorizaci�n */
    if($intAutEstadoCaso > 0){

        $strQuery = "SELECT AUT_CASO.AUT_CASO,
                            AUT_NOTIFICA_ESPECIFICA.TEMPLATE TEMPLATE,
                            fnt_get_correo(carne.carne) EMAIL
                     FROM   AUT_CASO
                            INNER JOIN AUT_CASO_ESTADO
                                ON AUT_CASO.AUT_CASO = AUT_CASO_ESTADO.AUT_CASO
                            INNER JOIN AUT_ESTADO_CASO
                                ON AUT_CASO_ESTADO.AUT_ESTADO_CASO = AUT_ESTADO_CASO.AUT_ESTADO_CASO
                            INNER JOIN AUT_NOTIFICA_ESPECIFICA
                                ON AUT_CASO_ESTADO.AUT_ESTADO_CASO = AUT_NOTIFICA_ESPECIFICA.AUT_ESTADO_CASO
                            INNER JOIN CARNE
                                ON AUT_CASO.PERSONA_INICIA = CARNE.PERSONA
                    WHERE   AUT_NOTIFICA_ESPECIFICA.AUT_ESTADO_CASO = {$intAutEstadoCaso}
                    AND     AUT_NOTIFICA_ESPECIFICA.TIPO = 3
                    AND     AUT_CASO_ESTADO.ACTIVO = 'Y'
                    AND     AUT_CASO.AUT_TIPO_CASO = AUT_ESTADO_CASO.AUT_TIPO_CASO
                    AND     AUT_CASO_ESTADO.NOTIFICACION_ENVIADA = 'N'
                    ORDER   BY AUT_CASO.AUT_CASO";
        $qTMP = db_query($strQuery);
        while($rTMP = db_fetch_assoc($qTMP)){
            $strMensaje = getTextoClob('texto', 'template', 'template', $rTMP['TEMPLATE']);
            $strAsunto = getSubjectTemplate($rTMP['TEMPLATE']);
            db_enviar_mail("academico@{$_SERVER["SERVER_NAME"]}", $rTMP['EMAIL'], $strAsunto, $strMensaje,"user");
            $boolSendEmailNotificacion = true;
        }
        db_free_result($qTMP);
    }

    /* Notificaci�n persona que se le realiza el proceso de autorizaci�n */
    if($intAutEstadoCaso > 0){

        $strQuery = "SELECT AUT_CASO.AUT_CASO,
                            AUT_NOTIFICA_ESPECIFICA.TEMPLATE,
                            FNT_GET_CORREO(CARNE.CARNE) EMAIL
                     FROM   AUT_CASO
                        INNER JOIN AUT_CASO_ESTADO
                            ON AUT_CASO.AUT_CASO = AUT_CASO_ESTADO.AUT_CASO
                        INNER JOIN AUT_CASO_LLAVE
                            ON AUT_CASO.AUT_CASO = AUT_CASO_LLAVE.AUT_CASO
                        INNER JOIN AUT_LLAVE
                            ON AUT_CASO_LLAVE.AUT_LLAVE = AUT_LLAVE.AUT_LLAVE
                        INNER JOIN AUT_ESTADO_CASO
                            ON AUT_CASO_ESTADO.AUT_ESTADO_CASO = AUT_ESTADO_CASO.AUT_ESTADO_CASO
                        INNER JOIN AUT_NOTIFICA_ESPECIFICA
                            ON AUT_CASO_ESTADO.AUT_ESTADO_CASO = AUT_NOTIFICA_ESPECIFICA.AUT_ESTADO_CASO
                        INNER JOIN CARNE
                            ON AUT_CASO_LLAVE.VALOR = CARNE.PERSONA
                     WHERE  AUT_NOTIFICA_ESPECIFICA.AUT_ESTADO_CASO = {$intAutEstadoCaso}
                     AND    AUT_NOTIFICA_ESPECIFICA.TIPO = 4
                     AND    AUT_CASO_ESTADO.ACTIVO = 'Y'
                     AND    AUT_CASO.AUT_TIPO_CASO = AUT_ESTADO_CASO.AUT_TIPO_CASO";
        $qTMP = db_query($strQuery);
        while($rTMP = db_fetch_assoc($qTMP)){
            $strMensaje = getTextoClob('texto', 'template', 'template', $rTMP['TEMPLATE']);
            $strAsunto = getSubjectTemplate($rTMP['TEMPLATE']);
            db_enviar_mail("academico@{$_SERVER["SERVER_NAME"]}", $rTMP['EMAIL'], $strAsunto, $strMensaje,"user");
            $boolSendEmailNotificacion = true;
            $arrEmailNotificacion[$rTMP['AUT_CASO']] = $rTMP['AUT_CASO'];
        }
        db_free_result($qTMP);
    }

    if($boolSendEmailNotificacion && $intAutCasoEstado > 0){
        db_query("UPDATE aut_caso_estado SET notificacion_enviada = 'Y' WHERE aut_caso_estado = {$intAutCasoEstado}");
    }

    if( !empty($arrEmailNotificacion) ){
        reset($arrEmailNotificacion);
        foreach( $arrEmailNotificacion as  $arrTMP['key'] => $arrTMP['value'])  {
            db_query("UPDATE aut_caso SET email_enviado = 'Y' WHERE aut_caso = {$arrTMP['key']}");
        }
    }

}


function getAreaPensumByCiclo($intPensum, $intCiclo){
    $intAreaPensum = 0;
    $strQuery=" SELECT area_pensum
                FROM pensum_estructura
                WHERE pensum = {$intPensum}
                AND ciclo = {$intCiclo}";
    $qTMP = db_query($strQuery);
    while ( $rTMP = db_fetch_assoc($qTMP) ){
        $intAreaPensum = $rTMP["AREA_PENSUM"];
    }
    db_free_result($qTMP);
    return $intAreaPensum;
}

function getPensum($intCurso,$strParametro, $intFacultad){

        $arrInfo = array();
        $boolIsTexto = ( preg_match('/^[a-zA-Z���������� ]+$/',$strParametro) ) ? true : false;
        $boolIsEspacio = strpos($strParametro,' ');
        $boolIsPensum = false;
        $strFilterPensum = "";

        if( !$boolIsTexto && !$boolIsEspacio ){
            $boolIsPensum = true;
        }else{
            $boolIsPensum = false;
        }

        if( $boolIsPensum ){
            $strFilterPensum .= getFilterQuery('pensum.codigo',$strParametro);
        }

        if(!empty($strFilterPensum)){
            $strQuery = " SELECT DISTINCT
                                 pensum.pensum,
                                 pensum.codigo,
                                 pensum.nombre,
                                 pensum.total_ciclos
                           FROM  acad_persona_carrera
                             INNER JOIN pensum
                                     ON acad_persona_carrera.pensum = pensum.pensum
                             INNER JOIN carrera
                                     ON acad_persona_carrera.carrera = carrera.carrera
                             INNER JOIN facultad
                                     ON carrera.facultad = facultad.facultad
                             INNER JOIN curso_pensum
                                     ON pensum.pensum=curso_pensum.pensum
                           WHERE pensum.total_ciclos >0
                             AND facultad.facultad = {$intFacultad}
                             {$strFilterPensum}
                             AND pensum.pensum not in (select curso_pensum.pensum from curso_pensum where curso_pensum.curso = {$intCurso} )
                             ORDER BY pensum.nombre ASC";
                $qTMP = db_query($strQuery);
        }
        return $qTMP;
}

function getCiclobyPensum($intPensum){
    $arrCiclosPensum = Array();
	$strQuery="SELECT
					DISTINCT (numero_ciclo),
					pensum_estructura.nombre
				FROM
					CURSO_PENSUM
					INNER JOIN pensum_estructura 
					ON curso_pensum.pensum = pensum_estructura.pensum
						AND pensum_estructura.ciclo = curso_pensum.numero_ciclo
				WHERE curso_pensum.pensum = {$intPensum}
				ORDER BY curso_pensum.numero_ciclo ASC";
    $qTMP = db_query($strQuery);
    while ( $rTMP = db_fetch_assoc($qTMP) ){
        $arrCiclosPensum[$rTMP["NUMERO_CICLO"]][] = $rTMP["NUMERO_CICLO"];
		$arrCiclosPensum[$rTMP["NUMERO_CICLO"]][] = $rTMP["NOMBRE"];
    }
    db_free_result($qTMP);
    return $arrCiclosPensum;
}

function getCicloByPensumCurso($intPensum,$intCurso){
    $arrCiclosPensum2 = Array();
    $strQuery="   SELECT DISTINCT
                         numero_ciclo
                    FROM curso_pensum
                   WHERE pensum = {$intPensum}
                     AND curso={$intCurso}
                ORDER BY numero_ciclo";
    $qTMP2 = db_query($strQuery);
    while ( $rTMP2 = db_fetch_assoc($qTMP2) ){
        $arrCiclosPensum2[$rTMP2["NUMERO_CICLO"]] = $rTMP2["NUMERO_CICLO"];
    }
    db_free_result($qTMP2);
    return $arrCiclosPensum2;
}

function getTipoCursoPensum(){
    $arrTipoCurso = Array();
    $strQuery="   SELECT tipo_curso_pensum,
                         nombre
                    FROM tipo_curso_pensum
                ORDER BY tipo_curso_pensum";
    $qTMP = db_query($strQuery);
    while ( $rTMP = db_fetch_assoc($qTMP) ){
        $arrTipoCurso[$rTMP["TIPO_CURSO_PENSUM"]] = $rTMP["NOMBRE"];
    }
    db_free_result($qTMP);
    return $arrTipoCurso;
}

function getColores( $boolReturnArray = false ) {

    $arrColores = array();

    $arrColores["ffffff"] = "#ffffff";
    $arrColores["ffccc9"] = "#ffccc9";
    $arrColores["ffce93"] = "#ffce93";
    $arrColores["fffc9e"] = "#fffc9e";
    $arrColores["ffffc7"] = "#ffffc7";
    $arrColores["9aff99"] = "#9aff99";
    $arrColores["96fffb"] = "#96fffb";
    $arrColores["cdffff"] = "#cdffff";
    $arrColores["cbcefb"] = "#cbcefb";
    $arrColores["cfcfcf"] = "#cfcfcf";
    $arrColores["fd6864"] = "#fd6864";
    $arrColores["fe996b"] = "#fe996b";
    $arrColores["fffe65"] = "#fffe65";
    $arrColores["fcff2f"] = "#fcff2f";
    $arrColores["67fd9a"] = "#67fd9a";
    $arrColores["38fff8"] = "#38fff8";
    $arrColores["68fdff"] = "#68fdff";
    $arrColores["9698ed"] = "#9698ed";
    $arrColores["c0c0c0"] = "#c0c0c0";
    $arrColores["fe0000"] = "#fe0000";
    $arrColores["f8a102"] = "#f8a102";
    $arrColores["ffcc67"] = "#ffcc67";
    $arrColores["f8ff00"] = "#f8ff00";
    $arrColores["34ff34"] = "#34ff34";
    $arrColores["68cbd0"] = "#68cbd0";
    $arrColores["34cdf9"] = "#34cdf9";
    $arrColores["6665cd"] = "#6665cd";
    $arrColores["9b9b9b"] = "#9b9b9b";
    $arrColores["cb0000"] = "#cb0000";
    $arrColores["f56b00"] = "#f56b00";
    $arrColores["ffcb2f"] = "#ffcb2f";
    $arrColores["ffc702"] = "#ffc702";
    $arrColores["32cb00"] = "#32cb00";
    $arrColores["00d2cb"] = "#00d2cb";
    $arrColores["3166ff"] = "#3166ff";
    $arrColores["6434fc"] = "#6434fc";
    $arrColores["656565"] = "#656565";
    $arrColores["9a0000"] = "#9a0000";
    $arrColores["ce6301"] = "#ce6301";
    $arrColores["cd9934"] = "#cd9934";
    $arrColores["999903"] = "#999903";
    $arrColores["009901"] = "#009901";
    $arrColores["329a9d"] = "#329a9d";
    $arrColores["3531ff"] = "#3531ff";
    $arrColores["6200c9"] = "#6200c9";
    $arrColores["343434"] = "#343434";
    $arrColores["680100"] = "#680100";
    $arrColores["963400"] = "#963400";
    $arrColores["986536"] = "#986536";
    $arrColores["646809"] = "#646809";
    $arrColores["036400"] = "#036400";
    $arrColores["34696d"] = "#34696d";
    $arrColores["00009b"] = "#00009b";
    $arrColores["303498"] = "#303498";
    $arrColores["000000"] = "#000000";
    $arrColores["330001"] = "#330001";
    $arrColores["643403"] = "#643403";
    $arrColores["663234"] = "#663234";
    $arrColores["343300"] = "#343300";
    $arrColores["013300"] = "#013300";
    $arrColores["003532"] = "#003532";
    $arrColores["010066"] = "#010066";
    $arrColores["340096"] = "#340096";

    if( $boolReturnArray )
        return $arrColores;
    else {
        $strOpciones = implode(",",$arrColores);
        return $strOpciones;
    }

}

function drawSelectCiclos( $arrCiclos = array(), $intFila=0){
    ?>
    <select id="sltCiclo_<?php print $intFila;?>" name="sltCiclo_<?php print $intFila;?>" class="field_selectbox inputSizeComplete">
        <option value="0">Seleccione un ciclo</option>
        <?php
        reset($arrCiclos);
        foreach( $arrCiclos as  $rTMP['key'] => $rTMP['value'])  {
            print "<option value='{$rTMP["value"][0]}' >".$rTMP["value"][1]." - ".$rTMP["value"][0]."</option>";
        }
        ?>
    </select>
    <?php
}

function drawSelectTag($arrColores = array(), $intFila=0){
    ?>
    <input type="hidden" class="field_textbox inputSizeComplete" id="txtColorCode_<?php print $intFila;?>" name="txtColorCode_<?php print $intFila;?>" value="ffffff">
    <div id="DivNewUpdateColor_<?php print $intFila;?>" name="DivNewUpdateColor_<?php print $intFila;?>" class="divColores">&nbsp;</div>
    <select id="UpdateColor_<?php print $intFila;?>" name="UpdateColor_<?php print $intFila;?>" class="field_selectbox field_selectbox">
        <?php
        reset($arrColores);
        foreach( $arrColores as  $rTMP['key'] => $rTMP['value'])  {
            print "<option value='{$rTMP["key"]}' >".$rTMP["key"]."</option>";
        }
        ?>
    </select>
        <script type="text/javascript">
        $('#UpdateColor_<?php print $intFila;?>').colourPicker({
            ico: 'core/jquery/colourpicker/jquery.colourpicker.gif',
            title:    "Seleccione color"
        });


        $("input[name*='UpdateColor_<?php print $intFila;?>']").hide().change(function(){
            strColores = $(this).val();
            $("#txtColorCode_<?php print $intFila;?>").val(strColores);

            strColores = "#" + strColores;
            $("div[name*='DivNewUpdateColor_<?php print $intFila;?>'").css("background", strColores);
        });
    </script>
    <?php
}

function drawSelectTipoCurso( $arrTipoCurso = array(), $intFila=0){
    ?>
    <select id="sltTipoCurso_<?php print $intFila;?>" name="sltTipoCurso_<?php print $intFila;?>" class="field_selectbox inputSizeComplete">
        <option value="0">Seleccione un tipo de curso</option>
        <?php
        reset($arrTipoCurso);
        foreach( $arrTipoCurso as  $rTMP['key'] => $rTMP['value'])  {
            print "<option value='{$rTMP["key"]}' >".$rTMP["value"]."</option>";
        }
        ?>
    </select>
    <?php
}


 if( isset($_GET["sendAutoCompletePensum"]) && $_GET["sendAutoCompletePensum"]==true){
    header("Content-Type: application/json;");
    $strParametro = isset($_GET["term"])?db_escape(user_input_delmagic($_GET["term"], true)):'';
    $intFacultad = isset($_GET["facultad"])? intval($_GET["facultad"]):0;
    $intCurso = isset($_GET["intCurso"])? intval($_GET["intCurso"]):0;
    $result = array();
    if( !empty($strParametro) ){
        $qTMP = getPensum($intCurso, $strParametro, $intFacultad);
        while ( $arrDatos = db_fetch_assoc($qTMP) ){
            $arrTMP = array();
            $arrTMP["id"] = utf8_encode($arrDatos["PENSUM"]);
            $arrTMP["label"] =  utf8_encode($arrDatos["CODIGO"]);
            $arrTMP["value"] =  utf8_encode($arrDatos["CODIGO"]);
            $arrTMP["nombre"] =  utf8_encode($arrDatos["NOMBRE"]);
            array_push($result, $arrTMP);
        }
    }

    print json_encode($result);
    die();
}

if(isset($_POST['cargarSelectCiclo']) && $_POST['cargarSelectCiclo']== true){
    $arrCiclos= array();
    $intFila = isset($_POST["intFila"])? intval($_POST["intFila"]):0;
    $intPensum = isset($_POST["intPensum"])? intval($_POST["intPensum"]):0;
    $intCurso = isset($_POST["intCurso"])? intval($_POST["intCurso"]):0;
    $arrCiclos = getCiclobyPensum($intPensum);
    drawSelectCiclos($arrCiclos,$intFila);
    exit();
}

if(isset($_POST['cargarSelectTipoCurso']) && $_POST['cargarSelectTipoCurso']== true){
    $arrTipoCurso= array();
    $intFila = isset($_POST["intFila"])? intval($_POST["intFila"]):0;
    $arrTipoCurso = getTipoCursoPensum();
    drawSelectTipoCurso($arrTipoCurso,$intFila);
    exit();
}

if(isset($_POST['cargarSelectTag']) && $_POST['cargarSelectTag']== true){
    $arrColores = array();
    $intFila = isset($_POST["intFila"])? intval($_POST["intFila"]):0;
    $arrColores = getColores(true);
    drawSelectTag($arrColores,$intFila);
    exit();
}

if( isset($_POST["getDetailCatedratico"]) ){
    header("Content-Type: text/html; charset=iso-8859-1");
    $intCatedratico = isset($_POST["intCatedratico"]) ? $_POST["intCatedratico"] : 0;
    $arrDetailCatedratico = getDetailCatedraticos($intCatedratico);
    ?>
    <table width="100%" cellpadding="5" cellspacing="5" border="0">
        <tr>
            <td width="20%">
                <?php academico_draw_avatar_persona($intCatedratico); ?>
            </td>
            <td width="10%">&nbsp;</td>
            <td width="70%" nowrap="nowrap" valign="top">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="vertical-align: top;">
                    <tr>
                        <td width="10%">&nbsp;</td>
                        <td width="90%">&nbsp;</td>
                    </tr>
                    <?php print isset($arrDetailCatedratico[$intCatedratico]["NOMBRE"]) ? "<tr><td></td><td align='left' style='color: #278EAF;'>".$arrDetailCatedratico[$intCatedratico]["NOMBRE"]."</td></tr>" : "&nbsp;";?>
                    <?php print isset($arrDetailCatedratico[$intCatedratico]["IDENTIFICACION"]) ? "<tr><td class='editTitles'><img src=". strGetCoreImageWithPath("acad_id.png") ."></td><td>&nbsp;".$arrDetailCatedratico[$intCatedratico]["IDENTIFICACION"]."</td></tr>" : "&nbsp;";?>
                    <?php print isset($arrDetailCatedratico[$intCatedratico]["CARNE"]) ? "<tr><td class='editTitles'><img src=". strGetCoreImageWithPath("carne.png") ."></td><td>&nbsp;".$arrDetailCatedratico[$intCatedratico]["CARNE"]."</td></tr>" : "&nbsp;";?>
                    <?php print isset($arrDetailCatedratico[$intCatedratico]["TELEFONO"]) ? "<tr><td class='editTitles'><img src=". strGetCoreImageWithPath("telefono.png") ."></td><td>&nbsp;".$arrDetailCatedratico[$intCatedratico]["TELEFONO"]."</td></tr>" : "&nbsp;";?>
                    <?php print isset($arrDetailCatedratico[$intCatedratico]["EMAIL"]) ? "<tr><td class='editTitles'><img src=". strGetCoreImageWithPath("e-mail.png") ."></td><td>&nbsp;".$arrDetailCatedratico[$intCatedratico]["EMAIL"]."</td></tr>" : "&nbsp;";?>
                    <?php print isset($arrDetailCatedratico[$intCatedratico]["FECHA_NACIMIENTO"]) ? "<tr><td class='editTitles'><img src=". strGetCoreImageWithPath("birthday.png") ."></td><td>&nbsp;".show_date($arrDetailCatedratico[$intCatedratico]["FECHA_NACIMIENTO"],false)."</td></tr>" : "&nbsp;";?>
                </table>
            </td>
        </tr>
    </table>
    <?php
    die();
}

if(isset($_POST['sendUndoGeneral'])){
            $intCurso =  isset($_POST['cursoOr'])?intval($_POST['cursoOr']):0;
            $intUser = isset($_SESSION["wt"]["originalUserToTest"]) ? intval($_SESSION["wt"]["originalUserToTest"]) : intval($_SESSION["wt"]["uid"]);
            $intCursoEquivalente = isset($_POST['cursoEq'])?intval($_POST['cursoEq']):0;
            $arrResult = array();
            $strQuery = " SELECT aut_caso_estado.aut_caso_estado
                                FROM aut_caso_llave
                                    INNER JOIN aut_caso
                                        ON AUT_CASO_LLAVE.AUT_CASO = aut_caso.aut_caso
                                    INNER JOIN aut_caso_estado
                                        ON aut_caso.aut_caso = aut_caso_estado.aut_caso
                                    INNER JOIN aut_llave
                                        ON aut_caso_llave.aut_llave = aut_llave.aut_llave
                                    INNER JOIN aut_tabla
                                        ON aut_llave.aut_tabla =  aut_tabla.aut_tabla
                                WHERE aut_caso_estado.activo = 'Y'
                                AND aut_caso_llave.valor = {$intCurso}
                                AND aut_llave.campo = 'CURSO_EQUIVALENTE'
                                AND aut_tabla.nombre_aut_tabla = 'CURSO_EQUIVALENTE'
                                 ";
            $intCasoEstado = sqlGetValueFromKey($strQuery);
            $intCasoEstado = intval($intCasoEstado);
            if($intCasoEstado > 0 && $intCursoEquivalente > 0 && $intCurso > 0){

                $strQueryUpdate = "UPDATE aut_caso_estado
                                            SET activo = 'N',
                                                    mod_user = {$intUser},
                                                    mod_fecha = now()
                                            WHERE aut_caso_estado.aut_caso_estado = {$intCasoEstado}
                                            AND     aut_caso_estado.activo = 'Y' ";
                db_query($strQueryUpdate);

                $strQueryCursoEquivalente = "UPDATE curso_equivalente
                                                                    SET  estado  = NULL,
                                                                            mod_user = {$intUser},
                                                                            mod_fecha = NOW()
                                                           WHERE curso_equivalente.curso = {$intCursoEquivalente}
                                                           AND      curso_equivalente.curso_equivalente = {$intCurso}";

                db_query($strQueryCursoEquivalente);

            }


            return $arrResult;

            //exit();
        }

if(isset($_POST["setAutorizacion"])){
    $intCurso = isset($_POST["curso"])?$_POST["curso"]:0;
    print (UpdateAutorizacion($intCurso)==true)?"Y":"N";

    exit();
}

if(isset($_POST["setBouble"])){
    $intCurso = isset($_POST["curso"])?intval($_POST["curso"]):0;
    tablaBuble($intCurso);
    exit();
}

if( isset($_GET["sendAutoCompleteCursos"]) ) {
    header("Content-Type: text/html; charset=iso-8859-1");
    $strParametro = user_input_delmagic(db_escape($_GET["term"]),true);
    $intOption = isset($_GET["cod"])? 1 : 0;
    $intFacultad = isset($_GET["facultad"]) ? $_GET["facultad"] : 0;
    $strFilterAutoComplete = getFilterQuery("c.codigo,c.nombre",$strParametro,true,false);
    // cambios para filtros de cursos entre Economia y Madrid
    // LGAM 05/09/2022
    if ($intFacultad == 68 || $intFacultad == 88) {
    	$strQuery = "SELECT c.curso, c.codigo, c.nombre, c.umas
                 FROM   curso c, area a
                 WHERE  c.area = a.area
		 AND    c.activo = 'Y'
                 AND    a.facultad in (68,88)  
	    {$strFilterAutoComplete}";
    }
    else {
	$strQuery = "SELECT c.curso, c.codigo, c.nombre, c.umas
                 FROM   curso c, area a
                 WHERE  c.area = a.area
                 AND    c.activo = 'Y'
                 AND    a.facultad = {$intFacultad}
	    {$strFilterAutoComplete}";
    }
    $qTMP = db_query($strQuery);
    $result = array();

    while ( $rTMP = db_fetch_assoc($qTMP) ){
        $arrTMP = array();
        $arrTMP["id"] = utf8_encode($rTMP["CURSO"]);
        $arrTMP["label"] = ($intOption == 1) ? utf8_encode($rTMP["CODIGO"]." - ".$rTMP["NOMBRE"]) : utf8_encode($rTMP["CODIGO"]." - ".$rTMP["NOMBRE"]);
        $arrTMP["value"] = ($intOption == 1) ? utf8_encode($rTMP["CODIGO"]) : utf8_encode($rTMP["NOMBRE"]);
        $arrTMP["result"] = ($intOption == 1) ? utf8_encode($rTMP["NOMBRE"])  : utf8_encode($rTMP["CODIGO"]);

        array_push($result, $arrTMP);
    }
    print json_encode($result);
    die();

}

//Ajax para blur validaci�n pre-requisitos

if(isset($_POST["drawBlurValidar"])){
    header("Content-Type: text/html; charset=iso-8859-1");
    drawBlurValidarPreRequisitos();
    die();
}


// Ajax para mostrar versiones de Syllabus
if(isset($_POST["drawBlurVersionesSyllabus"])) {
    header("Content-Type: text/html; charset=iso-8859-1");
    $intCurso = isset($_POST["curso"]) ? intval($_POST["curso"]) : 0;
    drawBlurVersionesSyllabus($intCurso);
    die();
}

// Ajax para mostrar detalle de una versi�n
if(isset($_POST["drawBlurDetalleSyllabusVersion"])) {
    header("Content-Type: text/html; charset=iso-8859-1");
    $intSyllabusUA = isset($_POST["syllabusUA"]) ? intval($_POST["syllabusUA"]) : 0;
    drawBlurDetalleSyllabusVersion($intSyllabusUA);
    die();
}

// Ajax para mostrar bit�cora de un campo espec�fico
if(isset($_POST["drawBlurBitacoraCampo"])) {
    header("Content-Type: text/html; charset=iso-8859-1");
    $intSyllabusUA = isset($_POST["syllabusUA"]) ? intval($_POST["syllabusUA"]) : 0;
    $strCampo = isset($_POST["campo"]) ? $_POST["campo"] : "";
    drawBlurBitacoraCampo($intSyllabusUA, $strCampo);
    die();
}

// Ajax para mostrar detalle completo de un log espec�fico
if(isset($_POST["drawBlurDetalleLogCampo"])) {
    header("Content-Type: text/html; charset=iso-8859-1");
    $intLogID = isset($_POST["logID"]) ? intval($_POST["logID"]) : 0;
    $strCampo = isset($_POST["campo"]) ? $_POST["campo"] : "";
    $strNombreCampo = isset($_POST["nombreCampo"]) ? $_POST["nombreCampo"] : "";
    drawBlurDetalleLogCampo($intLogID, $strCampo, $strNombreCampo);
    die();
}

// Ajax para ver el valor actual de un campo (desde SYLLABUS_UA, no del log)

if(isset($_POST["drawBlurValorActualCampo"])) {
    header("Content-Type: text/html; charset=iso-8859-1");
    $intSyllabusUA   = isset($_POST["syllabusUA"])   ? intval($_POST["syllabusUA"])   : 0;
    $strCampo        = isset($_POST["campo"])        ? $_POST["campo"]                : "";
    $strNombreCampo  = isset($_POST["nombreCampo"])  ? $_POST["nombreCampo"]          : "";
    drawBlurValorActualCampo($intSyllabusUA, $strCampo, $strNombreCampo);
    die();
}

// Manejar bit�cora individual de RA
if(isset($_POST["drawBlurBitacoraRA"])) {
    header("Content-Type: text/html; charset=iso-8859-1");
    $intSyllabusUARA = isset($_POST["syllabus_ua_ra"]) ? intval($_POST["syllabus_ua_ra"]) : 0;
    drawBlurBitacoraRA($intSyllabusUARA);
    die();
}

// Manejar bit�cora de RA eliminado
if(isset($_POST["drawBlurBitacoraRAEliminado"])) {
    header("Content-Type: text/html; charset=iso-8859-1");
    $intSyllabusUARA = isset($_POST["syllabus_ua_ra"]) ? intval($_POST["syllabus_ua_ra"]) : 0;
    drawBlurBitacoraRAEliminado($intSyllabusUARA);
    die();
}

// Manejar bit�cora general de todos los RA
if(isset($_POST["drawBlurBitacoraTodosRA"])) {
    header("Content-Type: text/html; charset=iso-8859-1");
    $intSyllabusUA = isset($_POST["syllabus_ua"]) ? intval($_POST["syllabus_ua"]) : 0;
    drawBlurBitacoraTodosRA($intSyllabusUA);
    die();
}

// Ajax para mostrar bit�cora individual de una Bibliograf�a
if(isset($_POST["drawBlurBitacoraBiblio"])) {
    header("Content-Type: text/html; charset=iso-8859-1");
    $intSyllabusUABiblio = isset($_POST["syllabus_ua_biblio"]) ? intval($_POST["syllabus_ua_biblio"]) : 0;
    drawBlurBitacoraBiblio($intSyllabusUABiblio);
    die();
}

// Ajax para mostrar bit�cora de Bibliograf�a eliminada
if(isset($_POST["drawBlurBitacoraBiblioEliminado"])) {
    header("Content-Type: text/html; charset=iso-8859-1");
    $intSyllabusUABiblio = isset($_POST["syllabus_ua_biblio"]) ? intval($_POST["syllabus_ua_biblio"]) : 0;
    drawBlurBitacoraBiblioEliminado($intSyllabusUABiblio);
    die();
}

// Ajax para mostrar bit�cora general de todas las Bibliograf�as
if(isset($_POST["drawBlurBitacoraTodosBiblio"])) {
    header("Content-Type: text/html; charset=iso-8859-1");
    $intSyllabusUA = isset($_POST["syllabus_ua"]) ? intval($_POST["syllabus_ua"]) : 0;
    drawBlurBitacoraTodosBiblio($intSyllabusUA);
    die();
}



// Ajax para contar versiones de Syllabus
if(isset($_POST["contarVersionesSyllabus"])) {
    header("Content-Type: application/json");
    $intCurso = isset($_POST["curso"]) ? intval($_POST["curso"]) : 0;
    
    $strQuery = "SELECT COUNT(*) as TOTAL 
                 FROM {$cfg['academico']['schema']}.SYLLABUS_UA
                 WHERE CURSO = {$intCurso}";
    
    $qCount = db_query($strQuery);
    $rCount = db_fetch_array($qCount);
    
    echo json_encode(array('total' => intval($rCount['TOTAL'])));
    die();
}


if( isset($_GET["sendAutoCompletePersona"]) ) {
    header("Content-Type: text/html; charset=iso-8859-1");

    $strTextoFilter = user_input_delmagic($_GET["term"], true);
    $intFacultad = isset($_GET["intFacultad"]) ? $_GET["intFacultad"] : 0;
    $strFilter = "";
    $strEstudiantes  = academico_getTipoPersonaEstudiante();

    if(!empty($strTextoFilter)){
        $strParametro = upper_tildes($strTextoFilter);
        $strParametro = str_replace(array("�","�","�","�","�"),array("A","E","I","O","U"),$strParametro);
        $arrExplode = explode(" ",$strParametro);
        foreach( $arrExplode as  $arrTMP['key'] => $arrTMP['value']){
            $strFilter .= " AND  (UPPER(translate(persona.nombre1, '����������', 'aeiouAEIOU')) LIKE '%{$arrTMP["value"]}%' OR
                                  UPPER(translate(persona.nombre2, '����������', 'aeiouAEIOU')) LIKE '%{$arrTMP["value"]}%' OR
                                  UPPER(translate(persona.apellido1, '����������', 'aeiouAEIOU')) LIKE '%{$arrTMP["value"]}%' OR
                                  UPPER(translate(persona.apellido2, '����������', 'aeiouAEIOU')) LIKE '%{$arrTMP["value"]}%' OR
                                  UPPER(translate(persona.apellido_casada, '����������', 'aeiouAEIOU')) LIKE '%{$arrTMP["value"]}%' OR
                                  UPPER(translate(carne.carne, '����������', 'aeiouAEIOU')) LIKE '%{$arrTMP["value"]}%' ) ";
        }

        if( $intFacultad > 0 )
                $strFilter .=" AND persona_tipo_persona.facultad={$intFacultad} ";

        if( !empty($strEstudiantes) && !empty($strFilter) ){
            $strQuery ="(SELECT DISTINCT persona.persona,
                                persona.nombre1,
                                persona.nombre2,
                                persona.apellido1,
                                persona.apellido2,
                                carne.carne
                        FROM    persona
                                INNER JOIN vw_persona_tipo_persona
                                    ON vw_persona_tipo_persona.persona = persona.persona
                                INNER JOIN carne
                                    ON carne.persona = persona.persona
                        WHERE   vw_persona_tipo_persona.tipo_persona IN ({$strEstudiantes})
                                {$strFilter})";
            $qTMP = db_query($strQuery);

            $result = array();
            while ( $rTMP = db_fetch_assoc($qTMP) ){
                $arrTMP = array();
                $arrTMP["id"] = utf8_encode($rTMP["PERSONA"]);
                $arrTMP["carne"] = utf8_encode($rTMP["CARNE"]);
                $arrTMP["value"] = utf8_encode($rTMP["CARNE"].' - '.$rTMP["NOMBRE1"]." ".$rTMP["NOMBRE2"]." ".$rTMP["APELLIDO1"]." ".$rTMP["APELLIDO2"]);
                array_push($result, $arrTMP);
            }
            print json_encode($result);
        }

    }

    die();

}

if( isset($_POST["getDetailPersona"]) ){
    header("Content-Type: text/html; charset=iso-8859-1");
    $intPersona = isset($_POST["intPersona"]) ? intval($_POST["intPersona"]) : 0;
    $intCurso = isset($_POST["intCurso"]) ? intval($_POST["intCurso"]) : 0;
    $intPensum = isset($_POST["intPensum"]) ? intval($_POST["intPensum"]) : 0;
    $strCarne = isset($_POST["strCarne"]) ? $_POST["strCarne"] : '';

    drawDetallePersona($intPersona,$strCarne, $intCurso, $intPensum);
    die();
}

if( isset($_POST["getPensumAlumno"])){
    header("Content-Type: text/html; charset=iso-8859-1");
    $intPersona = isset($_POST["intPersona"]) ? intval($_POST["intPersona"]) : 0;
    $intCurso = isset($_GET["curso"]) ? intval($_GET["curso"]) : 0;
    $strCarne = isset($_POST["strCarne"]) ? $_POST["strCarne"] : '';
    $arrListadoPensum = array();
    $strClass = "row2";
    if($intPersona > 0){
        $strQuery = "SELECT acad_persona_carrera.pensum,
                            pensum.codigo,
                            carrera.nombre nombre_carrera,
                            pensum.nombre nombre_pensum
                     FROM   acad_persona_carrera
                        INNER JOIN persona
                            ON acad_persona_carrera.persona = persona.persona
                        INNER JOIN pensum
                            ON acad_persona_carrera.pensum = pensum.pensum
                        INNER JOIN carrera
                            ON acad_persona_carrera.carrera = carrera.carrera
                     WHERE persona.persona = {$intPersona}
                     AND   pensum.activo = 'Y'";
        $qTMP = db_query($strQuery);
        while($rTMP = db_fetch_assoc($qTMP)){
            $arrListadoPensum[$rTMP["PENSUM"]]["CODIGO"] = $rTMP["CODIGO"];
            $arrListadoPensum[$rTMP["PENSUM"]]["NOMBRE_CARRERA"] = $rTMP["NOMBRE_CARRERA"];
            $arrListadoPensum[$rTMP["PENSUM"]]["NOMBRE_PENSUM"] = $rTMP["NOMBRE_PENSUM"];
        }
        db_free_result($qTMP);

        //$strQuery  = "SELECT carne FROM carne WHERE persona = {$intPersona} AND actual = 'Y' ";
        //$strCarne = sqlGetValueFromKey($strQuery);
    }
    $intCountPensum = count($arrListadoPensum);
    if($intCountPensum > 0){
        ?>
        <select name="sltCarrerasPensum" class="field_selectbox inputSizeComplete" >
            <?php
            foreach($arrListadoPensum as $key => $arrTMP){
                ?>
                <option value="<?php print $key; ?>"><?php print $arrTMP["NOMBRE_CARRERA"]." - ".$arrTMP["NOMBRE_PENSUM"]; ?></option>
                <?php
            }
            ?>
        </select>
        <script type="text/javascript">
            $(function(){
                var intPensum = $("select[name='sltCarrerasPensum']").val();
                fntGetDetailPersona('<?php print $intPersona; ?>', intPensum, '<?php print $strCarne; ?>');

                $("select[name='sltCarrerasPensum']").change(function(){
                    fntGetDetailPersona('<?php print $intPersona; ?>', $(this).val(),'<?php print $strCarne; ?>');
                });

            });
        </script>
        <?php
    }
    else{
        $intPensum = !empty($arrListadoPensum) ? key($arrListadoPensum) : 0;
        if($intPensum > 0){

            foreach($arrListadoPensum as $key => $arrTMP){
                print $arrTMP["NOMBRE_CARRERA"]." - ".$arrTMP["NOMBRE_PENSUM"];
            }
            ?>
            <script type="text/javascript">
                $(function(){
                    fntGetDetailPersona('<?php print $intPersona; ?>', '<?php print $intPensum; ?>', '<?php print $strCarne; ?>');
                });
            </script>
            <?php
        }
    }

    die();
}

if( isset($_GET["tipocurso"]) ) {

    header("Content-Type: text/html; charset=iso-8859-1");

    //$intTipoCurso = intval($_GET["tipocurso"]);
    $intTipoCurso = db_escape($_GET["tipocurso"]);


    $arrInfo = array();
    $arrInfo = sqlGetValueFromKey("SELECT TC.TIPO_CURSO, TC.HISTORIAL, TC.PROMEDIO, TC.ACREDITA_UMAS FROM {$cfg["academico"]["schema"]}.TIPO_CURSO TC WHERE TC.TIPO_CURSO = '{$intTipoCurso}'");
    //debugQuery("SELECT TC.TIPO_CURSO, TC.HISTORIAL, TC.PROMEDIO, TC.ACREDITA_UMAS FROM ACADEMICO.TIPO_CURSO{$cfg["core"]["conexion_esquema_academico"]} TC, ACADEMICO.CURSOS{$cfg["core"]["conexion_esquema_academico"]} C WHERE C.CURSO = '{$intCurso}' AND C.TIPO_CURSO = TC.TIPO_CURSO");

    $arrInfo["TIPO_NOTA"] = sqlGetValueFromKey("SELECT TN.NOMBRE FROM {$cfg["academico"]["schema"]}.TIPO_CURSO TC, {$cfg["academico"]["schema"]}.TIPO_NOTA TN WHERE TC.TIPO_CURSO = '{$intTipoCurso}' AND TC.TIPO_NOTA = TN.TIPO_NOTA");
    $arrInfo["FORMA_COBRO"] = sqlGetValueFromKey("SELECT FC.TIPO_COBRO FROM {$cfg["academico"]["schema"]}.TIPO_CURSO TC, {$cfg["academico"]["schema"]}.TIPO_COBRO FC WHERE TC.TIPO_CURSO = '{$intTipoCurso}' AND TC.TIPO_COBRO = FC.TIPO_COBRO");
    $arrInfo["TIPO_MOV_ACAD"] = sqlGetValueFromKey("SELECT TMA.TIPO_MOV_ACAD || ' - ' || TMA.DESCRIPCION FROM {$cfg["academico"]["schema"]}.TIPO_CURSO TC, {$cfg["academico"]["schema"]}.TIPO_MOV_ACAD TMA WHERE TC.TIPO_CURSO = '{$intTipoCurso}' AND TC.TIPO_MOV_ACAD = TMA.TIPO_MOV_ACAD");
    ?>
    <table width="100%" cellpadding="3" cellspacing="0" id="tablaTipoCursoInfo">
        <tr>
            <td width="20%" class="editTitles"><?php print $lang["ACADEMICO_CURSOS_PENSUM_DETALLE_TIPO_NOTA"]; ?></td>
            <td width="70%">
                <?php print isset($arrInfo["TIPO_NOTA"]) ? $arrInfo["TIPO_NOTA"] : "&nbsp;"; ?>
            </td>
            <td width="10%">&nbsp;</td>
        </tr>
        <tr>
            <td align="right" class="editTitles"><?php print $lang["ACADEMICO_CURSOS_PENSUM_DETALLE_HISTORIAL"]; ?></td>
            <td align="left">
                <?php print isset($arrInfo["HISTORIAL"]) ? (($arrInfo["HISTORIAL"] == 'Y') ? "Si" : "No") : "&nbsp;"; ?>
            </td>
            <td >&nbsp;</td>
        </tr>
        <tr>
            <td align="right" class="editTitles"><?php print $lang["ACADEMICO_CURSOS_PENSUM_DETALLE_PROMEDIO"]; ?></td>
            <td align="left">
                 <?php print isset($arrInfo["PROMEDIO"]) ? (($arrInfo["PROMEDIO"] == 'Y') ? "Si" : "No") : "&nbsp;"; ?>
            </td>
            <td >&nbsp;</td>
        </tr>
        <tr>
            <td align="right" class="editTitles"><?php print $lang["ACADEMICO_CURSOS_PENSUM_DETALLE_ACREDITA_UMA"]; ?></td>
            <td align="left">
                <?php print isset($arrInfo["ACREDITA_UMAS"]) ? (($arrInfo["ACREDITA_UMAS"] == 'Y') ? "Si" : "No") : "&nbsp;"; ?>
            </td>
            <td >&nbsp;</td>
        </tr>
    </table>
    <?php
    die();
}

if (isset ($_GET['getCursosImpartidos']) ){
    header("Content-Type: text/html; charset=iso-8859-1");
    $intCurso = isset($_GET["intCurso"]) ? intval($_GET["intCurso"]) : 0;
    $intFacultad = isset($_GET["intFacultad"]) ? intval($_GET["intFacultad"]) : 0;
    drawTabCursosImpartido($intCurso,$intFacultad);
    die();
}

//Ajax detalle usuario autorizador
if( isset($_POST["getDetailUser"]) ){
    header("Content-Type: text/html; charset=iso-8859-1");
    $intCurso = isset($_POST["curso"]) ? intval($_POST["curso"]) : 0;
    $intCursoEquivalente = isset($_POST["curso_equivalente"]) ? intval($_POST["curso_equivalente"]) : 0;
    $arrCasos = array();
    $strQuery = "SELECT aut_caso.aut_caso,
                        aut_caso_estado.aut_caso_estado,
                        aut_caso.add_user persona,
                        aut_caso.add_fecha fecha,
                        'ESTADO' campo_update,
                        '1' valor,
                        persona.nombre1,
                        persona.nombre2,
                        persona.apellido1,
                        persona.apellido2,
                        persona.apellido_casada
                FROM    aut_caso
                        INNER JOIN aut_caso_llave llave1
                            ON  aut_caso.aut_caso = llave1.aut_caso
                            AND llave1.aut_llave = (SELECT aut_llave.aut_llave
                                                        FROM aut_llave
                                                        INNER JOIN aut_tabla
                                                            ON aut_llave.aut_tabla = aut_tabla.aut_tabla
                                                        WHERE aut_tabla.nombre_aut_tabla = 'CURSO_EQUIVALENTE' AND aut_llave.campo = 'CURSO')
                            AND llave1.valor = {$intCurso}
                        INNER JOIN aut_caso_llave llave2
                            ON  aut_caso.aut_caso = llave2.aut_caso
                            AND llave2.aut_llave = (SELECT aut_llave.aut_llave
                                                        FROM aut_llave
                                                        INNER JOIN aut_tabla
                                                            ON aut_llave.aut_tabla = aut_tabla.aut_tabla
                                                        WHERE aut_tabla.nombre_aut_tabla = 'CURSO_EQUIVALENTE' AND aut_llave.campo = 'CURSO_EQUIVALENTE')
                            AND llave2.valor = {$intCursoEquivalente}
                        INNER JOIN aut_caso_estado
                            ON  aut_caso.aut_caso = aut_caso_estado.aut_caso
                        INNER JOIN persona
                            ON  aut_caso.add_user = persona.persona
                WHERE   aut_caso.aut_tipo_caso = 3
                ORDER BY aut_caso.add_fecha DESC";
    $qTMP = db_query($strQuery);
    while($rTMP = db_fetch_assoc($qTMP)){
        $arrCasos[ $rTMP['AUT_CASO'] ][0][$rTMP['PERSONA']]  = $rTMP;
    }
    db_free_result($qTMP);

    $strQuery = "SELECT aut_caso.aut_caso,
                        aut_caso_estado.aut_caso_estado,
                        aut_persona_caso_estado.persona,
                        aut_persona_caso_estado.fecha,
                        aut_estado_caso_tabla.campo_update,
                        aut_estado_caso_tabla.valor,
                        persona.nombre1,
                        persona.nombre2,
                        persona.apellido1,
                        persona.apellido2,
                        persona.apellido_casada
                FROM    aut_caso
                        INNER JOIN aut_caso_llave llave1
                            ON  aut_caso.aut_caso = llave1.aut_caso
                            AND llave1.aut_llave = (SELECT aut_llave.aut_llave
                                                        FROM aut_llave
                                                        INNER JOIN aut_tabla
                                                            ON aut_llave.aut_tabla = aut_tabla.aut_tabla
                                                        WHERE aut_tabla.nombre_aut_tabla = 'CURSO_EQUIVALENTE' AND aut_llave.campo = 'CURSO')
                            AND llave1.valor = {$intCurso}
                        INNER JOIN aut_caso_llave llave2
                           ON  aut_caso.aut_caso = llave2.aut_caso
                            AND llave2.aut_llave = (SELECT aut_llave.aut_llave
                                                        FROM aut_llave
                                                        INNER JOIN aut_tabla
                                                            ON aut_llave.aut_tabla = aut_tabla.aut_tabla
                                                        WHERE aut_tabla.nombre_aut_tabla = 'CURSO_EQUIVALENTE' AND aut_llave.campo = 'CURSO_EQUIVALENTE')
                            AND llave2.valor = {$intCursoEquivalente}
                        INNER JOIN aut_caso_estado
                            ON  aut_caso.aut_caso = aut_caso_estado.aut_caso
                        INNER JOIN aut_persona_caso_estado
                            ON  aut_persona_caso_estado.aut_caso_estado = aut_caso_estado.aut_caso_estado
                        INNER JOIN aut_transicion_estado_caso
                            ON  aut_persona_caso_estado.aut_transicion_estado_caso = aut_transicion_estado_caso.aut_transicion_estado_caso
                        INNER JOIN aut_estado_caso_tabla
                            ON  aut_transicion_estado_caso.aut_estado_caso_siguiente = aut_estado_caso_tabla.aut_estado_caso
                        INNER JOIN persona
                            ON  aut_persona_caso_estado.persona = persona.persona
                WHERE   aut_caso.aut_tipo_caso = 3
                ORDER BY aut_caso.aut_caso ASC, aut_estado_caso_tabla.valor";
    $qTMP = db_query($strQuery);
    while($rTMP = db_fetch_assoc($qTMP)){
        $arrCasos[ $rTMP['AUT_CASO'] ][$rTMP['AUT_CASO_ESTADO']][$rTMP['PERSONA']]  = $rTMP;
    }
    db_free_result($qTMP);

    ?>
    <table width="100%" cellpadding="5" cellspacing="7">
        <?php
        if(!empty($arrCasos)){
            ?>
            <tr>
                <td width="80%" nowrap="nowrap" class="row0">Nombre</td>
                <td width="5%" class="row0">&nbsp;</td>
                <td width="10%" class="row0">Fecha</td>
            </tr>
            <?php
        }else{
            print "Pendiente de solicitar";
        }
        //drawDebug($arrDetailUser);
        $strEstadoCaso = "";
        $strClass = "row2";
     //   reset($arrDetailUser);
        reset($arrCasos);
        $intCountCasos = 0;
        foreach( $arrCasos as  $arrDetailUser['key'] => $arrDetailUser['value'])  {
            $intCountCasos++;
            if($intCountCasos != 1){
                 $strClass = "row0";
                ?>
                <tr>
                    <td colspan="3" class="row0">&nbsp;</td>
                </tr>
                <?php
            }
            $intNumeroDetalles = count($arrDetailUser['value']);
            $strBorderBotton = "";
            $intFilas = 0;
            foreach( $arrDetailUser['value'] as  $arrTMP['key'] => $arrTMP['value'])  {




                foreach( $arrTMP["value"] as  $arrTMP2['key'] => $arrTMP2['value'])  {
                    $intFilas++;
                    if($intNumeroDetalles == $intFilas){
                        $strBorderBotton = "border-bottom: 1px solid #FFFFFF;";
                    }
                    else{
                        $strClass = ($strClass=="row2") ? "row1" : "row2";
                    }

                    if($arrTMP2["value"]["VALOR"] == 1 ){
                        $strEstadoCaso = "Solicitado por: ";
                    }
                    elseif($arrTMP2["value"]["VALOR"] == 2){
                        $strEstadoCaso = "Autorizado por: ";
                    }
                    elseif($arrTMP2["value"]["VALOR"] == 3){
                        $strEstadoCaso = "Rechazado por: ";
                    }
                    elseif($arrTMP2["value"]["VALOR"] == 4){
                        $strEstadoCaso = "Solicitud anulaci�n por: ";
                    }
                    elseif($arrTMP2["value"]["VALOR"] == 5){
                        $strEstadoCaso = "Anulado por: ";
                    }


                    ?>
                    <tr>
                        <td class="<?php print $strClass; ?>" style="<?php print $strBorderBotton;?>">
                            <?php print isset($arrTMP2["value"]["NOMBRE1"]) ? $strEstadoCaso.$arrTMP2["value"]["NOMBRE1"]." ".$arrTMP2["value"]["APELLIDO1"] : ""; ?>
                        </td>
                        <td class="<?php print $strClass; ?>" style="<?php print $strBorderBotton;?>">&nbsp;</td>
                        <td class="<?php print $strClass; ?>" style="<?php print $strBorderBotton;?>">
                            <?php print isset($arrTMP2["value"]["FECHA"]) ? show_date($arrTMP2["value"]["FECHA"],TRUE) : ""; ?>
                        </td>
                    </tr>
                    <?php

                }
            }


        }
        ?>
    </table>
    <?php
    die();
}

if( isset($_POST['eliminarEq']) ){
    $intUser = isset($_SESSION["wt"]["originalUserToTest"]) ? intval($_SESSION["wt"]["originalUserToTest"]) : intval($_SESSION["wt"]["uid"]);
    $intAutCaso = isset($_REQUEST['hdnAutCaso']) ? intval($_REQUEST['hdnAutCaso']) : 0;
    $intCurso = isset($_REQUEST['hidCurso']) ? intval($_REQUEST['hidCurso']) : 0;
    $intCursoEquivalente = isset($_REQUEST['hidCursoEquivalente']) ? intval($_REQUEST['hidCursoEquivalente']) : 0;
    if( $intAutCaso > 0){
        $strQuery = "delete from aut_persona_caso_estado where aut_caso_estado in  ( select aut_caso_estado from aut_caso_estado where aut_caso = {$intAutCaso})";
        db_query($strQuery);
        
        $strQuery = "delete from aut_caso_estado where aut_caso = {$intAutCaso}";
        db_query($strQuery);
        
        $strQuery = "delete from curso_equivalente 
                                        where curso_equivalente.curso in (select valor 
                                                        from aut_caso_llave 
                                                        inner join aut_llave
                                                            on aut_llave.aut_llave = aut_caso_llave.aut_llave
                                                        inner join aut_tabla
                                                            on aut_llave.aut_tabla = aut_tabla.aut_tabla
                                                        where aut_caso_llave.aut_caso = {$intAutCaso} 
                                                        and aut_llave.campo = 'CURSO'
                                                        and aut_tabla.nombre_aut_tabla = 'CURSO_EQUIVALENTE'
                                                         )
                                        and curso_equivalente.curso_equivalente in (select valor 
                                                        from aut_caso_llave 
                                                        inner join aut_llave
                                                            on aut_llave.aut_llave = aut_caso_llave.aut_llave
                                                        inner join aut_tabla
                                                            on aut_llave.aut_tabla = aut_tabla.aut_tabla
                                                        where aut_caso_llave.aut_caso = {$intAutCaso} 
                                                        and aut_llave.campo = 'CURSO_EQUIVALENTE'
                                                        and aut_tabla.nombre_aut_tabla = 'CURSO_EQUIVALENTE'
                                                         )";
        db_query($strQuery);
        
        $strQuery = "delete from aut_caso_llave where aut_caso = {$intAutCaso}";
        db_query($strQuery);

        $strQuery = "delete from aut_caso where aut_caso = {$intAutCaso}";
        db_query($strQuery);
            
    }
    else if($intCurso > 0 && $intCursoEquivalente > 0){
        $strQuery = "delete from curso_equivalente 
                    where curso_equivalente.curso = {$intCurso}
                    and curso_equivalente.curso_equivalente = {$intCursoEquivalente}";
        db_query($strQuery);
    }
    
    
}


if( isset($_POST['solicitarAnulacion']) ){
    function insertAutCasoEstado( $intAutCaso, $intAutEstadoCaso, $intUser) {

        $intAutCaso = intval($intAutCaso);
        $intAutEstadoCaso = intval($intAutEstadoCaso);
        $intUser = intval($intUser);

        $intAutCasoEstado = 0;
        $intAutCasoEstadoOld = 0;

        if( $intAutCaso > 0 && $intAutEstadoCaso > 0 && $intUser > 0){

            $intAutCasoEstadoOld = db_insert_id(false, "AUT_CASO_ESTADO_S");
            $strQuery = "CALL SP_INSERT_AUT_CASO_ESTADO( {$intAutCaso}, {$intAutEstadoCaso}, {$intUser} )";
            db_query($strQuery);

            $intAutCasoEstado = db_insert_id(false, "AUT_CASO_ESTADO_S");

            if( $intAutCasoEstado == $intAutCasoEstadoOld ){
                $intAutCasoEstado = 0;
            }

        }

        return $intAutCasoEstado;

    }
    function insertAutPersonaCasoEstado( $intAutCasoEstado, $intPersona, $strComentario, $intAutTransicionEstadoCaso, $intUser ) {

        $intAutCasoEstado = intval($intAutCasoEstado);
        $intPersona = intval($intPersona);
        $intAutTransicionEstadoCaso = intval($intAutTransicionEstadoCaso);
        $intUser = intval($intUser);

        if( $intAutCasoEstado > 0 && $intPersona > 0 && $intAutTransicionEstadoCaso > 0 && $intUser > 0) {

            $strQuery = "CALL   SP_INSERT_AUT_PERSONA_CASO_EST( {$intAutCasoEstado}, {$intPersona}, '{$strComentario}', {$intAutTransicionEstadoCaso}, {$intUser} )";
            db_query($strQuery);
            //drawDebug($strQuery);

        }

    }

    $intUser = isset($_SESSION["wt"]["originalUserToTest"]) ? intval($_SESSION["wt"]["originalUserToTest"]) : intval($_SESSION["wt"]["uid"]);
    $intCurso = isset($_POST["hidCurso"]) ? intval($_POST["hidCurso"]) : 0;
    $intCursoEquivalente = isset($_POST["hidCursoEquivalente"]) ? intval($_POST["hidCursoEquivalente"]) : 0;
    $facultad = isset($_REQUEST["facultad"]) ? intval($_REQUEST["facultad"]) : 0;
    $arrDetailUser = Array();
    $intAutEstadoCasoSiguiente = 0;

    $intAutCaso = isset($_REQUEST['hdnAutCaso']) ? intval($_REQUEST['hdnAutCaso']) : 0;
    $intAutEstadoCaso = isset($_REQUEST['hdnAutEstadoCasoCursoGeneral']) ? intval($_REQUEST['hdnAutEstadoCasoCursoGeneral']) : 0;
    $intAutTransicionEstadoCaso = isset($_REQUEST['hdnAutTransicionEstadoCasoCursoGeneral']) ? intval($_REQUEST['hdnAutTransicionEstadoCasoCursoGeneral']) : 0;

    $intAutCasoEstado = sqlGetValueFromKey("SELECT aut_caso_estado FROM aut_caso_estado WHERE aut_caso = {$intAutCaso} AND activo = 'Y' ");

    insertAutPersonaCasoEstado($intAutCasoEstado, $intUser, '',$intAutTransicionEstadoCaso, $intUser);


    //Reglas
    $intReglas = 0;
    if($intAutTransicionEstadoCaso > 0){
        $intReglas = sqlGetValueFromKey("SELECT COUNT(aut_regla) cuantas FROM aut_regla WHERE aut_transicion_estado_caso = {$intAutTransicionEstadoCaso} ");
    }
    if( $intReglas > 0 ) {
        $intRequeridos = 0;
        $intOpcionales = 0;
        if($intAutCasoEstado > 0  && $intAutEstadoCaso > 0){
            $strQuery ="SELECT  aut_autoriza_persona.requerido,
                                aut_persona_caso_estado.persona
                        FROM    aut_persona_caso_estado,
                                aut_autoriza_persona
                        WHERE   aut_persona_caso_estado.aut_caso_estado = {$intAutCasoEstado}
                        AND     aut_autoriza_persona.aut_estado_caso = {$intAutEstadoCaso}
                        AND     aut_persona_caso_estado.persona = aut_autoriza_persona.persona";
            $qTMP = db_query($strQuery);
            while( $rTMP = db_fetch_assoc($qTMP) ) {
                if( $rTMP['REQUERIDO'] == 'Y' ) {
                    $arrRespuestas["REQUERIDO"][$rTMP['PERSONA']] = $rTMP['PERSONA'];
                    $intRequeridos++;
                }
                else {
                    $arrRespuestas["OPCIONAL"][$rTMP['PERSONA']] = $rTMP['PERSONA'];
                    $intOpcionales++;
                }
            }
            db_free_result($qTMP);

            $strQuery ="SELECT  aut_autoriza_perfil.requerido,
                                aut_persona_caso_estado.persona
                        FROM    aut_persona_caso_estado,
                                aut_autoriza_perfil,
                                persona_perfil
                        WHERE   aut_persona_caso_estado.aut_caso_estado = {$intAutCasoEstado}
                        AND     aut_autoriza_perfil.aut_estado_caso = {$intAutEstadoCaso}
                        AND     aut_autoriza_perfil.perfil = persona_perfil.perfil
                        AND     persona_perfil.persona = aut_persona_caso_estado.persona";
            $qTMP = db_query($strQuery);
            while( $rTMP = db_fetch_assoc($qTMP) ) {
                if( $rTMP['REQUERIDO'] == 'Y' ) {
                    $arrRespuestas["REQUERIDO"][$rTMP['PERSONA']] = $rTMP['PERSONA'];
                    $intRequeridos++;
                }
                else {
                    $arrRespuestas["OPCIONAL"][$rTMP['PERSONA']] = $rTMP['PERSONA'];
                    $intOpcionales++;
                }
            }
            db_free_result($qTMP);
        }

        $arrReglas = array();
        $arrResultado = array();

        if( $intAutTransicionEstadoCaso > 0 ){
            $strQuery = "SELECT regla.aut_regla,
                                regla.num_resp_necesarias,
                                regla.persona_req_opcional,
                                grupo.grupo,
                                grupo.aut_estado_caso,
                                grupo.aut_estado_caso_siguiente
                         FROM   aut_regla regla,
                                aut_grupo_regla grupo
                         WHERE  regla.aut_regla = grupo.aut_regla
                         AND    regla.aut_transicion_estado_caso = {$intAutTransicionEstadoCaso}
                         ORDER  BY regla.aut_regla, grupo.grupo";
            $qTMP = db_query($strQuery);
            while( $rTMP = db_fetch_assoc($qTMP) ) {
                $arrReglas[$rTMP["GRUPO"]][$rTMP["PERSONA_REQ_OPCIONAL"] == "Y" ? "requerido" : "opcional"] = $rTMP["NUM_RESP_NECESARIAS"];
                $arrResultado[$rTMP["GRUPO"]][$rTMP["PERSONA_REQ_OPCIONAL"] == "Y" ? "requerido" : "opcional"] = false;
                $arrResultado[$rTMP["GRUPO"]]['AUT_ESTADO_CASO_SIGUIENTE'] = $rTMP['AUT_ESTADO_CASO_SIGUIENTE'];
            }
            db_free_result($qTMP);


            $boolOK = false;

            foreach( $arrReglas as  $arrTMP['key'] => $arrTMP['value'])  {
                if( isset($arrTMP["value"]["requerido"]) && $intRequeridos >= $arrTMP["value"]["requerido"] ) $arrResultado[$arrTMP["key"]]["requerido"] = true;
                if( isset($arrTMP["value"]["opcional"]) && $intOpcionales >= $arrTMP["value"]["opcional"] ) $arrResultado[$arrTMP["key"]]["opcional"] = true;
            }

            foreach( $arrResultado as  $arrTMP['key'] => $arrTMP['value'])  {
                if( ( isset($arrTMP["value"]["requerido"]) && $arrTMP["value"]["requerido"] && isset($arrTMP["value"]["opcional"]) && $arrTMP["value"]["opcional"] ) ||
                    ( !isset($arrTMP["value"]["requerido"]) && isset($arrTMP["value"]["opcional"]) && $arrTMP["value"]["opcional"] ) ||
                    ( !isset($arrTMP["value"]["opcional"]) && isset($arrTMP["value"]["requerido"]) && $arrTMP["value"]["requerido"] ) ) {
                    $boolOK = true;
                    $intAutEstadoCasoSiguiente = $arrTMP["value"]["AUT_ESTADO_CASO_SIGUIENTE"];
                    break;
                }
            }

            if( !$boolOK ) {
                $intAutEstadoCasoSiguiente = 0;
            }
        }

    }
    else{
        if($intAutTransicionEstadoCaso > 0){
            $intAutEstadoCasoSiguiente = sqlGetValueFromKey("SELECT aut_estado_caso_siguiente FROM aut_transicion_estado_caso WHERE aut_transicion_estado_caso = {$intAutTransicionEstadoCaso}");
        }
        $intAutEstadoCasoSiguiente = intval($intAutEstadoCasoSiguiente);
    }

    if( $intAutEstadoCasoSiguiente > 0 ) {
        $intAutCasoEstadoNew = insertAutCasoEstado( $intAutCaso, $intAutEstadoCasoSiguiente, $intUser);

        if( $intAutCasoEstadoNew > 0 && $intAutCaso > 0 && $intUser > 0) {
            db_query("UPDATE    aut_caso_estado
                      SET       ACTIVO = 'N',
                                MOD_USER = {$intUser},
                                MOD_FECHA = NOW()
                      WHERE     aut_caso = {$intAutCaso}");
            db_query("UPDATE    aut_caso_estado
                      SET       ACTIVO = 'Y',
                                MOD_USER = {$intUser},
                                MOD_FECHA = NOW()
                      WHERE     aut_caso = {$intAutCaso}
                      AND       aut_caso_estado = {$intAutCasoEstadoNew}");
        }
        $arrWhereCampos['CURSO'] = $intCurso;
        $arrWhereCampos['CURSO_EQUIVALENTE'] = $intCursoEquivalente;
        $bool = generarUpdateFromAutEstadoCasoTabla($intAutEstadoCasoSiguiente, $arrWhereCampos);

        sendEmailFromAutEstadoCaso($facultad,$intAutEstadoCasoSiguiente, $intAutCaso);
        sendEmailNotificacion($intAutEstadoCasoSiguiente, $intAutCasoEstado, $facultad);

    }

    die();


}


if( isset($_POST["BlurSolicitarAnulacion"]) ){
    header("Content-Type: text/html; charset=iso-8859-1");
    $strAction = basename(__FILE__);
    $intFacultad = isset($_REQUEST["facultad"]) ? $_REQUEST["facultad"] : 0;
    global $cfg;
    $boolEquivalenteI = isset($_POST["boolEquivalenteI"]) ? ($_POST["boolEquivalenteI"]):false;
    $boolDeleteEq = isset($_POST["DeleteEquivalencia"]) ? ($_POST["DeleteEquivalencia"] == 'true')?true:false:false;
    $intCurso = isset($_POST["curso"]) ? intval($_POST["curso"]) : 0;
    $intCursoEquivalente = isset($_POST["curso_equivalente"]) ? intval($_POST["curso_equivalente"]) : 0;
    $intContadorCursoEquivalente = isset($_POST["intContadorCursoEquivalente"]) ? intval($_POST["intContadorCursoEquivalente"]) : 0;
    $intUser = isset($_SESSION["wt"]["originalUserToTest"]) ? intval($_SESSION["wt"]["originalUserToTest"]) : intval($_SESSION["wt"]["uid"]);
    $intCursoRedirigir = 0;
    $intCursoRedirigir = ($boolEquivalenteI == 'false')? $intCurso : $intCursoEquivalente ;
    $arrDetailCurso = array();
    $arrAutTransicionEstadoCaso = array();
    $intCountPersonaAutorizada = 0;
    $boolPersonaAutorizada = false;
    $intAutEstadoCaso = 0;
    $intAutCasoEstado = 0;
    $intAutCaso = 0;

    $strQuery = "SELECT c.codigo, c.nombre
                 FROM   curso c
                 WHERE  c.curso = {$intCurso}";
    $qTMP = db_query($strQuery);
    while($rTMP = db_fetch_assoc($qTMP)){
        $arrDetailCurso = $rTMP;
    }
    db_free_result($qTMP);


    //**Transicion**//
    if($intCurso > 0 && $intCursoEquivalente > 0){
        $strQuery = "SELECT aut_caso.aut_caso,
                            aut_caso_estado.aut_estado_caso,
                            aut_caso_estado.aut_caso_estado
                    FROM    aut_caso
                            INNER JOIN aut_caso_llave llave1
                                ON  aut_caso.aut_caso = llave1.aut_caso
                                AND llave1.aut_llave = (SELECT aut_llave.aut_llave
                                                        FROM aut_llave
                                                        INNER JOIN aut_tabla
                                                            ON aut_llave.aut_tabla = aut_tabla.aut_tabla
                                                        WHERE aut_tabla.nombre_aut_tabla = 'CURSO_EQUIVALENTE' AND aut_llave.campo = 'CURSO')
                                AND llave1.valor = {$intCurso}
                            INNER JOIN aut_caso_llave llave2
                                ON  aut_caso.aut_caso = llave2.aut_caso
                                AND llave2.aut_llave = (SELECT aut_llave.aut_llave
                                                        FROM aut_llave
                                                        INNER JOIN aut_tabla
                                                            ON aut_llave.aut_tabla = aut_tabla.aut_tabla
                                                        WHERE aut_tabla.nombre_aut_tabla = 'CURSO_EQUIVALENTE' AND aut_llave.campo = 'CURSO_EQUIVALENTE')
                                AND llave2.valor = {$intCursoEquivalente}
                            INNER JOIN aut_caso_estado
                                ON  aut_caso.aut_caso = aut_caso_estado.aut_caso
                    WHERE   aut_caso.aut_tipo_caso = 3
                    AND     aut_caso_estado.activo = 'Y'";
        $qTMP = db_query($strQuery);
        while($rTMP = db_fetch_assoc($qTMP)){
            $intAutEstadoCaso = $rTMP['AUT_ESTADO_CASO'];
            $intAutCaso = $rTMP['AUT_CASO'];
            $intAutCasoEstado = $rTMP['AUT_CASO_ESTADO'];
        }
        db_free_result($qTMP);
    }

    if($intAutEstadoCaso > 0){
        $strQuery = "SELECT  aut_tipo_transicion.texto_boton,
                             aut_transicion_estado_caso.aut_transicion_estado_caso
                     FROM    aut_tipo_transicion
                             INNER JOIN aut_transicion_estado_caso
                                ON aut_transicion_estado_caso.aut_tipo_transicion = aut_tipo_transicion.aut_tipo_transicion
                             INNER JOIN aut_estado_caso_tabla
                                ON aut_transicion_estado_caso.aut_estado_caso_siguiente = aut_estado_caso_tabla.aut_estado_caso
                     WHERE   aut_transicion_estado_caso.aut_estado_caso = {$intAutEstadoCaso}
                     ORDER   BY aut_transicion_estado_caso.orden";
        $qTMP = db_query($strQuery);
        while($rTMP = db_fetch_assoc($qTMP)){
            $arrAutTransicionEstadoCasoAnularEqGeneral[$rTMP['AUT_TRANSICION_ESTADO_CASO']]['AUT_TRANSICION_ESTADO_CASO'] = $rTMP['AUT_TRANSICION_ESTADO_CASO'];
            $arrAutTransicionEstadoCasoAnularEqGeneral[$rTMP['AUT_TRANSICION_ESTADO_CASO']]['TEXTO_BOTON'] = $rTMP['TEXTO_BOTON'];
        }
        db_free_result($qTMP);
    }

    //drawDebug($arrAutTransicionEstadoCasoAnularEqGeneral);


    if($intUser > 0 && $intAutEstadoCaso > 0){
        $strQuery = "SELECT  null perfil, aut_autoriza_persona.persona
                     FROM    aut_autoriza_persona
                     WHERE   aut_autoriza_persona.aut_estado_caso = {$intAutEstadoCaso}
                     AND     aut_autoriza_persona.persona = {$intUser}
                     UNION
                     SELECT  aut_autoriza_perfil.perfil,
                             null persona
                     FROM    aut_autoriza_perfil
                        INNER JOIN persona_perfil
                            ON  aut_autoriza_perfil.perfil = persona_perfil.perfil
                     WHERE   aut_autoriza_perfil.aut_estado_caso = {$intAutEstadoCaso}
                     AND     persona_perfil.persona = {$intUser}";
        $qTMP = db_query($strQuery);
        while($rTMP = db_fetch_assoc($qTMP)){
            $intCountPersonaAutorizada++;
        }
        db_free_result($qTMP);
        if($intCountPersonaAutorizada > 0){
            $boolPersonaAutorizada = true;
        }
    }

    $intContador = 0;
    $boolExisteAutPersonaCasoEstado = false;
    if($intAutCasoEstado > 0 && $intUser > 0){
        $strQuery = "SELECT aut_caso_estado
                     FROM   aut_persona_caso_estado
                     WHERE  aut_caso_estado = {$intAutCasoEstado}
                     AND    persona = {$intUser}";
        $qTMP = db_query($strQuery);
        while($rTMP = db_fetch_assoc($qTMP)){
            $intContador++;
        }
        db_free_result($qTMP);
    }
    if($intContador > 0){
        $boolExisteAutPersonaCasoEstado = true;
    }

    ?>
    <form method="post" id="frmSolicitudAnulacion">
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td class="heading1" colspan="2">
                    En este momento se <?php  print ($boolDeleteEq == true)?'eliminar�n las equivalencias ingresadas':'enviar� la solicitud de anulaci�n para las equivalencias ingresadas';?>.
                </td>
            </tr>
            <tr>
                <td width="30%" class="row0">C�digo</td>
                <td width="70%" class="row0">Curso</td>
            </tr>
            <?php
            if( !empty($arrDetailCurso) ){
                $strClass = "row2";
                $intContadorEquivalenciaI = 0;
                ?>
                <tr>
                    <td class="<?php print $strClass; ?>">
                        <?php print isset($arrDetailCurso["CODIGO"]) ? $arrDetailCurso["CODIGO"] : ""; ?>
                        <input type="hidden" name="hidCurso" value="<?php print $intCurso ?>">
                        <input type="hidden" name="hidCursoEquivalente" value="<?php print $intCursoEquivalente; ?>">
                        <input type="hidden" name="hdnAutCaso" value="<?php print $intAutCaso; ?>" readonly="readonly">
                        <input type="hidden" name="hdnAutEstadoCasoCursoGeneral" value="<?php print $intAutEstadoCaso; ?>" readonly="readonly">
                        <input type="hidden" name="hdnAutTransicionEstadoCasoCursoGeneral" value="0" readonly="readonly">
                    </td>
                    <td class="<?php print $strClass; ?>">
                        <?php print isset($arrDetailCurso["NOMBRE"]) ? $arrDetailCurso["NOMBRE"] : ""; ?>
                    </td>
                </tr>
                <?php
            }
            ?>
            <tr>
                <td colspan="2">&nbsp;</td>
            </tr>
        </table>
    </form>
    <script type="text/javascript">
        $(function(){
            $('#divSolicitarAnulacion').dialog('removeAllButtons');
        });

        <?php
        if($boolPersonaAutorizada && !empty($arrAutTransicionEstadoCasoAnularEqGeneral) && !$boolExisteAutPersonaCasoEstado && $boolDeleteEq == false ){
            reset($arrAutTransicionEstadoCasoAnularEqGeneral);
            foreach( $arrAutTransicionEstadoCasoAnularEqGeneral as  $arrTMP['key'] => $arrTMP['value'])  {
                ?>
                $('#divSolicitarAnulacion').dialog('addbutton', '<?php print $arrTMP['value']['TEXTO_BOTON']; ?>', function(){
                    fntAutorizador(<?php print $arrTMP['value']['AUT_TRANSICION_ESTADO_CASO']; ?>);
                });
                <?php
            }
        }      
        if($boolDeleteEq == true){
            ?>
            $('#divSolicitarAnulacion').dialog('addbutton', 'Eliminar Equivalencia',fntEliminarEquivalencia);
            function fntEliminarEquivalencia(){
                $.ajax({
                    url:"<?php print $strAction; ?>?facultad=<?php print $intFacultad; ?>&curso=<?php print $intCursoEquivalente ;?>",
                    data: $("#frmSolicitudAnulacion").serialize()+"&eliminarEq=true",
                    type:"post",
                    dataType: "html",
                    success: function(data){
                        document.location.href="<?php print $strAction; ?>?facultad=<?php print $intFacultad; ?>&curso=<?php print $intCursoRedirigir ;?>";
                    }
                });
            }
            <?php
        }
        ?>

        
        $('#divSolicitarAnulacion').dialog('addbutton', 'Cerrar',fntOnClickCerrarAnulacionEqGeneral);
        


        //$("#divSolicitarAnulacion").dialog('open');


        function fntAutorizador(intAutTransicionEstadoCaso){
            $("input[name='hdnAutTransicionEstadoCasoCursoGeneral']").val(intAutTransicionEstadoCaso);
            $.ajax({
                url:"<?php print $strAction; ?>?facultad=<?php print $intFacultad; ?>&curso=<?php print $intCursoEquivalente ;?>",
                data: $("#frmSolicitudAnulacion").serialize()+"&solicitarAnulacion=true",
                type:"post",
                dataType: "html",
                success: function(data){
                    document.location.href="<?php print $strAction; ?>?facultad=<?php print $intFacultad; ?>&curso=<?php print $intCursoRedirigir ;?>";
                }
            });
        }
        function fntOnClickCerrarAnulacionEqGeneral(){
            $("#divSolicitarAnulacion").dialog( "close" );
        }

    </script>
    <?php
    die();
}

//Ajax para crear el aut_caso de las equivalencias generales
if( isset($_POST["createAutCaso"]) ){
    $intUser = isset($_SESSION["wt"]["originalUserToTest"]) ? intval($_SESSION["wt"]["originalUserToTest"]) : intval($_SESSION["wt"]["uid"]);
    $facultad = isset($_REQUEST["facultad"]) ? intval($_REQUEST["facultad"]) : 0;
    $intAutTipoCaso = 3;

    //Funciones para solicitud de equivalencia general
    function insertAutCaso( $strDescripcion, $intPersonaInicia, $intCarreraInicia, $intFacultad, $intAutTipoCaso, $strValorUpdate, $strEmailEnviado, $intUser) {

        $strDescripcion = substr(db_escape(user_input_delmagic(trim($strDescripcion))),0,500);
        $intPersonaInicia = intval($intPersonaInicia);
        $intCarreraInicia = !empty($intCarreraInicia) ? intval($intCarreraInicia) : "NULL";
        $intFacultad = !empty($intFacultad) ? intval($intFacultad) : "NULL";
        $intAutTipoCaso = intval($intAutTipoCaso);
        $strValorUpdate = substr(db_escape(user_input_delmagic(trim($strValorUpdate))),0,500);
        $strEmailEnviado = substr(db_escape(user_input_delmagic(trim($strEmailEnviado))),0,1);
        $intUser = intval($intUser);

        $intCursoImpartidoStaff = 0;
        $intAutCaso = 0;

        if( $intUser > 0){

            $intAutCasoOld = db_insert_id(false, "AUT_CASO_S");
            $strQuery = "CALL SP_INSERT_AUT_CASO('{$strDescripcion}', {$intPersonaInicia}, {$intCarreraInicia}, {$intFacultad}, {$intAutTipoCaso}, {$strValorUpdate}, '{$strEmailEnviado}', {$intUser} )";
            db_query($strQuery);

            $intAutCaso = db_insert_id(false, "AUT_CASO_S");

            if( $intAutCaso == $intAutCasoOld ){
                $intAutCaso = 0;
            }

        }

        return $intAutCaso;

    }

    function getAutTipoCaso( $intAutTipoCaso ) {
        $intAutTipoCaso = intval($intAutTipoCaso);
        $arrReturn = array();
        $strQuery = "";
        $strQuery ="SELECT  aut_tipo_caso,
                            descripcion,
                            padre,
                            inicio,
                            envia_email,
                            diagrama,
                            uora
                    FROM    aut_tipo_caso
                    WHERE   aut_tipo_caso = {$intAutTipoCaso}";
        $arrReturn = sqlGetValueFromKey($strQuery);
        unset($strQuery);
        return $arrReturn;
    }

    function insertAutCasoEstado( $intAutCaso, $intAutEstadoCaso, $intUser) {

        $intAutCaso = intval($intAutCaso);
        $intAutEstadoCaso = intval($intAutEstadoCaso);
        $intUser = intval($intUser);

        $intAutCasoEstado = 0;
        $intAutCasoEstadoOld = 0;

        if( $intAutCaso > 0 && $intAutEstadoCaso > 0 && $intUser > 0){

            $intAutCasoEstadoOld = db_insert_id(false, "AUT_CASO_ESTADO_S");
            $strQuery = "CALL SP_INSERT_AUT_CASO_ESTADO( {$intAutCaso}, {$intAutEstadoCaso}, {$intUser} )";
            db_query($strQuery);

            $intAutCasoEstado = db_insert_id(false, "AUT_CASO_ESTADO_S");

            if( $intAutCasoEstado == $intAutCasoEstadoOld ){
                $intAutCasoEstado = 0;
            }

        }

        return $intAutCasoEstado;

    }

    function insertAutCasoLlave( $intAutCaso, $intAutEstadoCaso, $arrWhereCampos, $intUser) {

        $intAutCaso = intval($intAutCaso);
        $intAutEstadoCaso = intval($intAutEstadoCaso);
        $intUser = intval($intUser);

        if( $intAutCaso > 0 && $intAutEstadoCaso > 0 && $intUser > 0 ) {

            $strQuery = "SELECT *
                         FROM   aut_estado_caso_tabla,
                                aut_tabla,
                                aut_llave
                         WHERE  aut_estado_caso_tabla.aut_estado_caso = {$intAutEstadoCaso}
                         AND    aut_estado_caso_tabla.aut_tabla = aut_tabla.aut_tabla
                         AND    aut_tabla.aut_tabla = aut_llave.aut_tabla";
            $qTMP = db_query($strQuery);
            while( $rTMP = db_fetch_assoc($qTMP) ) {
                if( isset($arrWhereCampos[$rTMP['CAMPO']]) ) {
                    $strQuery2 = "CALL SP_INSERT_AUT_CASO_LLAVE( {$intAutCaso}, {$rTMP['AUT_LLAVE']}, '{$arrWhereCampos[$rTMP['CAMPO']]}', {$intUser} )";
                    db_query($strQuery2);
                }
            }
            db_free_result($qTMP);

        }

    }

    foreach($_POST as  $arrTMP['key'] => $arrTMP['value']){
        $arrExplode = explode("_",$arrTMP["key"]);

        if($arrExplode[0] == "hidCursoI" ){
            $intCursoI = isset($_POST["hidCursoI_{$arrExplode[1]}"]) ? $_POST["hidCursoI_{$arrExplode[1]}"] : 0;
            $intEquivalenteI = isset($_POST["hidCursoEquivalenteI_{$arrExplode[1]}"]) ? $_POST["hidCursoEquivalenteI_{$arrExplode[1]}"] : 0;
            $intAutCaso = insertAutCaso("",$intUser,"",$facultad,$intAutTipoCaso,'NULL','N',$intUser);

            if( $intAutCaso > 0 && $intAutTipoCaso > 0 ){
                $arrAutTipoCaso = getAutTipoCaso($intAutTipoCaso);
                $arrWhereCampos['CURSO'] = $intCursoI;
                $arrWhereCampos['CURSO_EQUIVALENTE'] = $intEquivalenteI;
                generarUpdateFromAutEstadoCasoTabla($arrAutTipoCaso['INICIO'], $arrWhereCampos);
                $intAutCasoEstado = insertAutCasoEstado($intAutCaso, $arrAutTipoCaso['INICIO'], $intUser);
                db_query("UPDATE AUT_CASO_ESTADO SET ACTIVO = 'Y' WHERE AUT_CASO_ESTADO = {$intAutCasoEstado}");
                insertAutCasoLlave($intAutCaso, $arrAutTipoCaso['INICIO'], $arrWhereCampos, $intUser);
                sendEmailFromAutEstadoCaso($facultad, $arrAutTipoCaso['INICIO'], $intAutCaso);
                sendEmailNotificacion($arrAutTipoCaso['INICIO'], $intAutCasoEstado, $facultad);
            }
        }

        if($arrExplode[0] == "hidCursoII"){
            $intCursoII = isset($_POST["hidCursoII_{$arrExplode[1]}"]) ? $_POST["hidCursoII_{$arrExplode[1]}"] : 0;
            $intEquivalenteII = isset($_POST["hidCursoEquivalenteII_{$arrExplode[1]}"]) ? $_POST["hidCursoEquivalenteII_{$arrExplode[1]}"] : 0;
            $intAutCaso = insertAutCaso("",$intUser,"",$facultad,$intAutTipoCaso,'NULL','N',$intUser);
            if( $intAutCaso > 0 && $intAutTipoCaso > 0 ){
                $arrAutTipoCaso = getAutTipoCaso($intAutTipoCaso);
                $arrWhereCampos['CURSO'] = $intCursoII;
                $arrWhereCampos['CURSO_EQUIVALENTE'] = $intEquivalenteII;
                generarUpdateFromAutEstadoCasoTabla($arrAutTipoCaso['INICIO'], $arrWhereCampos);
                $intAutCasoEstado = insertAutCasoEstado($intAutCaso, $arrAutTipoCaso['INICIO'], $intUser);
                db_query("UPDATE AUT_CASO_ESTADO SET ACTIVO = 'Y' WHERE AUT_CASO_ESTADO = {$intAutCasoEstado}");
                insertAutCasoLlave($intAutCaso, $arrAutTipoCaso['INICIO'], $arrWhereCampos, $intUser);
                sendEmailFromAutEstadoCaso($facultad, $arrAutTipoCaso['INICIO'], $intAutCaso);
                sendEmailNotificacion($arrAutTipoCaso['INICIO'], $intAutCasoEstado, $facultad);
            }

        }
    }




    die();
}

if( isset($_POST['getTipoLogo']) ){
    header("Content-Type: text/html; charset=iso-8859-1");
    $intArea = isset($_POST['intArea']) ? intval($_POST['intArea']) : 0;
    $intFacultad = isset($_POST['intFacultad']) ? intval($_POST['intFacultad']) : 0;
    $intCurso = isset($_POST['intCurso']) ? intval($_POST['intCurso']) : 0;
    $intContadorLogo = 0;
    $strIsLogo = sqlGetValueFromKey("SELECT islogo FROM area WHERE area = {$intArea} AND facultad = {$intFacultad} AND islogo = 'Y'");
    $sinValorLogos = sqlGetValueFromKey("SELECT logos FROM curso WHERE curso = {$intCurso}");

    if( $strIsLogo == 'Y'){
        ?>
        <table width="100%" cellpadding="3" cellspacing="0">
            <tr>
                <td width="40%" class="editTitles">Logos</td>
                <td width="60%">
                    <input type="text" name="txtLogos" class="field_textbox inputSizeComplete" value="<?php print isset($sinValorLogos) ? $sinValorLogos : '';?>">
                </td>
            </tr>
        </table>
        <script type="text/javascript" language="JavaScript">
            $("#umas").css("display","none");
            $("#divTitleUmas").css("display","");
            $("#spanUmas").css("display","none");
            $("#umas").val(0);
            $("#ects").css("display","none");
            $("#divTitleEcts").css("display","");
            $("#spanEcts").css("display","none");
            $("#Ects").val(0);
        </script>
        <?php
    }else{
        ?>
        <script type="text/javascript" language="JavaScript">
            $("#umas").css("display","");
            $("#divTitleUmas").css("display","");
            $("#spanUmas").css("display","none");
            $("#ects").css("display","");
            $("#divTitleEcts").css("display","");
            $("#spanEcts").css("display","none");
        </script>
        <?php
        print "&nbsp;";
    }
    exit();
}


if(isset($_POST['sendVerbosBloom']) && $_POST['sendVerbosBloom'] == true) {
    $intNivelBloom = isset($_POST['nivelBloom']) ? intval($_POST['nivelBloom']) : 0;
    
    if($intNivelBloom > 0) {
        $strQuery = "SELECT BLOOM_VERBO, VERBO 
                     FROM {$cfg['academico']['schema']}.BLOOM_VERBO 
                     WHERE BLOOM_NIVEL = {$intNivelBloom}
                     ORDER BY VERBO";
        
        $qVerbos = db_query($strQuery);
        $arrVerbos = array();
        
        while($rVerbo = db_fetch_array($qVerbos)) {
            $arrVerbos[] = array(
                'BLOOM_VERBO' => utf8_encode($rVerbo['BLOOM_VERBO']),
                'VERBO' => utf8_encode ($rVerbo['VERBO'])
            );
        }
        
        header('Content-Type: application/json');
        echo json_encode(array(
            'success' => true,
            'verbos' => $arrVerbos
        ));
    } else {
        header('Content-Type: application/json');
        echo json_encode(array(
            'success' => false,
            'message' => 'Nivel no v�lido'
        ));
    }
    exit;
}


if(isset($_POST['validarRA']) && $_POST['validarRA'] == true){
    header('Content-Type: application/json; charset=utf-8');
    
    $nivelBloom = isset($_POST['nivelBloom']) ? trim($_POST['nivelBloom']) : '';
    $descripcionRA = isset($_POST['descripcionRA']) ? trim($_POST['descripcionRA']) : '';
    $descripcionInst = isset($_POST['descripcionInstitucional']) ? trim($_POST['descripcionInstitucional']) : '';

    
    try {

        $textoCompleto = $descripcionRA;
        if ($descripcionInst !== '') {
            $textoCompleto = "Contexto del curso: " . $descripcionInst . "\n\nResultado de aprendizaje a evaluar: " . $descripcionRA;
        }
        $resultado = evaluar_resultado_aprendizaje_json_text($nivelBloom, $textoCompleto);
        
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit();
}

if(isset($_POST['validarBibliografia']) && $_POST['validarBibliografia'] == true){
    header('Content-Type: application/json; charset=utf-8');
    
    //$referenciaBibliografica = isset($_POST['referenciaBibliografica']) ? trim(strip_tags($_POST['referenciaBibliografica'])) : '';
    $referenciaBibliografica = isset($_POST['referenciaBibliografica']) ? sanitizarHtmlBiblio(trim($_POST['referenciaBibliografica'])) : '';
    
    try {
        $resultado = evaluar_bibliografia_json($referenciaBibliografica);
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit();
}


if(isset($_POST['validarDescripcion']) && $_POST['validarDescripcion'] == true){
    header('Content-Type: application/json; charset=utf-8');
    
    $textoHTML = isset($_POST['textoHTML']) ? $_POST['textoHTML'] : '';
    $nombreCampo = isset($_POST['nombreCampo']) ? trim($_POST['nombreCampo']) : 'texto';
    
    try {
        $resultado = validar_texto_coherencia_ortografia($textoHTML, $nombreCampo);
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit();
}

include_once("hml_html/html_input_button.php");

$strAction = basename(__FILE__);
$page_name = $lang["ACADEMICO_CURSOS_TITLE"];
$objPreRequisito = new pre_requisito_controller(isset($_GET["curso"])?$_GET["curso"]:0,$strAction,"&facultad={$_GET["facultad"]}");
$objPreRequisito->process();
$index_page = false;
//$intFacultadNew = facultades_draw_selector($strAction);

//ajax mostrar pre-requisitos en tab de pensa


$intCurso = isset($_GET["curso"]) ? intval($_GET["curso"]) : 0;
$intPensum = isset($_GET["pensum"]) ? intval($_GET["pensum"]) : 0;
$intFacultad = isset($_GET["facultad"]) ? intval($_GET["facultad"]) : 0;
$intCarrera = isset($_GET["carrera"]) ? intval($_GET["carrera"]) : 0;
$arrBreadCrum = academico_getBreadCrumCurso($intFacultad, $intCurso);
$arrValorEstadosFinalesAutorizador = getValoresTipoCasoFinal();
$intFacultad = facultades_draw_selector($strAction);


// Verificar si el syllabus est� completo para permitir nueva versi�n
$boolSyllabusCompleto = false;
if($intCurso > 0) {
    // 1. Verificar que existe SYLLABUS_UA
$qCheck = db_query("SELECT SYLLABUS_UA, DESCRIPCION_INSTITUCIONAL, APORTE_PLAN_ESTUDIOS, 
                           CONOCIMIENTOS_PREVIOS, MARCO_NORMATIVO
                    FROM {$cfg['academico']['schema']}.SYLLABUS_UA 
                    WHERE CURSO = {$intCurso}
                      AND FECHA_FIN IS NULL");
                          

                          
    
    if($rCheck = db_fetch_array($qCheck)) {
        $intSyllabusUA = intval($rCheck['SYLLABUS_UA']);
        
        // 2. Verificar que todos los campos est�n llenos
      $camposLlenos = !empty(trim($rCheck['DESCRIPCION_INSTITUCIONAL'])) &&
                   !empty(trim($rCheck['APORTE_PLAN_ESTUDIOS'])) &&
                   !empty(trim($rCheck['CONOCIMIENTOS_PREVIOS'])) &&
                   !empty(trim($rCheck['MARCO_NORMATIVO']));
        
        // 3. Verificar que existe al menos 1 RA
        $qRA = db_query("SELECT COUNT(*) as TOTAL 
                        FROM {$cfg['academico']['schema']}.SYLLABUS_UA_RA 
                        WHERE SYLLABUS_UA = {$intSyllabusUA}");
        $rRA = db_fetch_array($qRA);
        $hayRA = (intval($rRA['TOTAL']) > 0);
        
        // 4. Verificar que existe al menos 1 Bibliograf�a
        $qBiblio = db_query("SELECT COUNT(*) as TOTAL 
                            FROM {$cfg['academico']['schema']}.SYLLABUS_UA_BIBLIO 
                            WHERE SYLLABUS_UA = {$intSyllabusUA}");
        $rBiblio = db_fetch_array($qBiblio);
        $hayBiblio = (intval($rBiblio['TOTAL']) > 0);
        
        // Solo est� completo si cumple TODAS las condiciones
        $boolSyllabusCompleto = $camposLlenos && $hayRA && $hayBiblio;
    }
}

academico_check_access(0,0,0,$intCurso,1,7);

    //draw_header();
    draw_header(true, true);
    draw_breadcrum($arrBreadCrum);
    theme_draw_centerbox_open($page_name);

    insert_javascript_format_number();
    jquery_includeLibrary("multiselect");
    jquery_includeLibrary("multiselectfilter");
    jquery_includeLibrary("colourpicker");

    ?>
    <style type="text/css">
        .divColores{
            width: 15px;
            border: 1px solid gray;
        }
    </style>
    <?php
    if( $intCurso == 0 ) die($lang["ACCESS_DENIED"]);

    //drawDebug($_POST)

   if ( isset($_POST["hdnAddPensum"]) && $_POST["hdnAddPensum"]=='Y'){
    $intAddUser = ( isset($_SESSION["wt"]["originalUserToTest"]) ? $_SESSION["wt"]["originalUserToTest"] : $_SESSION["wt"]["uid"] );
    $intCursoPensum = isset($_POST["hidGuardar"])? intval($_POST["hidGuardar"]):0;
    reset($_POST);
        foreach($_POST as $arrTMP['key'] => $arrTMP['value'] ){
            $arrExplode= explode("_",$arrTMP['key']);
            if( $arrExplode[0]=='txtIdPensum' && intval($arrExplode[1]) > 0){
                $intPensum = isset($_POST["txtIdPensum_{$arrExplode[1]}"]) ? intval($_POST["txtIdPensum_{$arrExplode[1]}"]):0;
                $intNumeroCiclo = isset($_POST["sltCiclo_{$arrExplode[1]}"]) ? intval($_POST["sltCiclo_{$arrExplode[1]}"]):0;
                $intTipoCursoPensum = isset($_POST["sltTipoCurso_{$arrExplode[1]}"]) ? intval($_POST["sltTipoCurso_{$arrExplode[1]}"]):0;
                $strGrupo = isset($_POST["txtColorCode_{$arrExplode[1]}"]) ? $_POST["txtColorCode_{$arrExplode[1]}"]:'';

                if($intPensum>0 && $intCurso>0 && $intNumeroCiclo>0 && $intTipoCursoPensum>0 && $intAddUser>0 && !empty($strGrupo)){
                     $intAreaPensum= getAreaPensumByCiclo($intPensum, $intNumeroCiclo);
                     $strQuery=" call SP_INSERT_CURSO_PENSUM ({$intPensum},{$intCursoPensum},{$intNumeroCiclo},{$intTipoCursoPensum},{$intAreaPensum},'{$strGrupo}',{$intAddUser})";
                     db_query($strQuery);
                }
            }
        }
   }


    if( isset($_POST["hidGuardar"]) ) {
        //die();
        //Guardo temporalmente el post para reutilizacion
        $intCursoId = isset($_POST["hidGuardar"]) ? intval($_POST["hidGuardar"]) : 0;
        $strTabDetalleCurso = isset($_POST["hdnTabDetalleCurso"]) ? 'Y' : 'N';
        $strTabCursosRelacionados = isset($_POST["hdnTabCursosRelacionados"]) ? 'Y' : 'N';
        $arrPost = $_POST;
        $intAddUser = ( isset($_SESSION["wt"]["originalUserToTest"]) ? $_SESSION["wt"]["originalUserToTest"] : $_SESSION["wt"]["uid"] );

        /*db_query("  UPDATE  curso
                    SET     subordinado_de = NULL,
                            mod_user = {$intAddUser},
                            MOD_FECHA = NOW()
                    WHERE   CURSO IN (SELECT curso FROM curso WHERE subordinado_de = {$intCursoId}) ");*/
        if( $strTabDetalleCurso == 'Y' && $intCursoId > 0 && $intAddUser > 0){
            $strQuery = "call SP_UPDATE_CURSO({$intCursoId},{$intCursoId},{$intAddUser},'N')";
            db_query($strQuery);
        }
        //Aqui actualizo el campo subordinado_de de los curso que se seleccionaron como cursos dependientes.

        foreach( $arrPost as  $arrTMP['key'] => $arrTMP['value'])  {
            $arrExplode = explode("_", $arrTMP["key"]);
            if( count($arrExplode) > 1 && $arrExplode[0] == "hidDependiente" ) {
                $intCursoDependienteId = $arrTMP["value"];
                if ($intCursoDependienteId) {
                    /*$strQuery = "UPDATE {$cfg["academico"]["schema"]}.CURSO
                                 SET    SUBORDINADO_DE = {$intCursoId},
                                        MOD_USER = {$intAddUser},
                                        MOD_FECHA = NOW()
                                 WHERE  CURSO = {$intCursoDependienteId}";*/
                    $strQuery = "call SP_UPDATE_CURSO({$intCursoDependienteId},{$intCursoId},{$intAddUser},'Y')";
                    db_query($strQuery);
                    //drawDebug($strQuery);
                }

            }

        }

        //Registrar los cursos equivalentes.
        reset($arrPost);
        $arrInfoInsertE = array();
        $arrinfoCursoEqui = array();

        $strQuery = "SELECT    ce.curso, ce.curso_equivalente
                     FROM      {$cfg["academico"]["schema"]}.curso_equivalente ce
                     WHERE      ce.curso = {$intCursoId}";
        $qTMP = db_query($strQuery);
        while ( $rTMP = db_fetch_array($qTMP)){
              $arrinfoCursoEqui[$rTMP["CURSO_EQUIVALENTE"]] = $rTMP["CURSO_EQUIVALENTE"];
        }
        db_free_result($qTMP);

        //drawDebug($arrInfoCursoE);
        //drawDebug($arrinfoCursoEqui);
        //drawDebug($arrPost);
        //die();

        $intRowEquivalente = 0;
        $intRowReference = 0;

        $strCursoEquivalente2 = "";
        $strCursoEquivalente3 = "";

        $intCursoEquivalente1 = 0;
        $intCursoEquivalente2 = "";
        $intCursoEquivalente3 = "";

        //db_query("DELETE FROM {$cfg["academico"]["schema"]}.CURSO_EQUIVALENTE WHERE CURSO = '{$intCursoId}' AND CURSO_EQUIVALENTE = ''");


        $intLastRow = 0;
        $intLastRow2 = 0;
        foreach( $arrPost as  $arrTMP['key'] => $arrTMP['value'])  {
            $arrExplode = explode("_", $arrTMP["key"]);


            //Insertar cursos equivalente seccion I
            if( $arrExplode[0] == "hidNewCursoEquivalenteI" && $arrTMP["value"] > 0 && $intCursoId > 0 && $intAddUser > 0){

                $strSobresee = (isset($_POST["sltCursoEquivalenteSobresee_{$arrExplode[1]}"])) ? $_POST["sltCursoEquivalenteSobresee_{$arrExplode[1]}"] : "NULL";

                $strQuery = "call SP_INSERT_CURSO_EQUIVALENTE({$arrTMP["value"]},{$intCursoId}, NULL, NULL,'{$strSobresee}',NULL,NULL,NULL,{$intAddUser})";

                db_query($strQuery);
            }

            //Update cursos equivalente seccion I
            if( $arrExplode[0] == "hidUpdateCursoEquivalenteI" && $arrTMP["value"] > 0 && $intCursoId > 0 && $intAddUser > 0 ){
                $strDeleteCursoEquivalenteI = isset($_POST["hidDeleteCursoEquivalenteI_{$arrExplode[1]}"]) ? $_POST["hidDeleteCursoEquivalenteI_{$arrExplode[1]}"] : "N";

                $intCurso_equivalenteOld = isset($_POST["hCursoEquivalente1I_{$arrExplode[1]}"]) ? intval($_POST["hCursoEquivalente1I_{$arrExplode[1]}"]) : 0;
                $intCurso_equivalenteOld2 = isset($_POST["hCursoEquivalente2I_{$arrExplode[1]}"]) ? intval($_POST["hCursoEquivalente2I_{$arrExplode[1]}"]) :0;
                $intCurso_equivalenteOld3 = isset($_POST["hCursoEquivalente3I_{$arrExplode[1]}"]) ? intval($_POST["hCursoEquivalente3I_{$arrExplode[1]}"]) : 0;

                $intUpdateCurso = isset($_POST["hidUpdateCursoEquivalenteI_{$arrExplode[1]}"]) ? intval($_POST["hidUpdateCursoEquivalenteI_{$arrExplode[1]}"]) : 0;
                $intCursoOld = isset($_POST["hidCursoEquivalenteI_{$arrExplode[1]}"]) ? intval($_POST["hidCursoEquivalenteI_{$arrExplode[1]}"]) : 0;
                $strSobresee = (isset($_POST["sltCursoEquivalenteSobresee_{$arrExplode[1]}"])) ? $_POST["sltCursoEquivalenteSobresee_{$arrExplode[1]}"] : "NULL";
                /*$strQuery = "UPDATE curso_equivalente
                             SET    curso = {$intUpdateCurso},
                                    add_user = {$intAddUser},
                                    add_fecha = NOW()
                             WHERE  curso_equivalente = {$intCursoId}
                             AND    curso = {$intCursoOld}";*/


                if ($intCurso_equivalenteOld==$intCursoId && $intCurso_equivalenteOld2 == 0 && $intCurso_equivalenteOld3 == 0) {
                    $strQuery = "call SP_UPDATE_CURSO_EQUIVALENTE({$intUpdateCurso},{$intCursoId}, NULL, NULL,'{$strSobresee}',NULL,NULL,NULL,{$intAddUser},{$intCursoOld},NULL)";
                    db_query($strQuery);
                }
                elseif ($strDeleteCursoEquivalenteI == "N"  ){

                    $strCursoSobresee1I= isset($_POST["hCursoSobresee1I_{$arrExplode[1]}"]) ? ($_POST["hCursoSobresee1I_{$arrExplode[1]}"]) : NULL;
                    $strCursoSobresee2I= isset($_POST["hCursoSobresee2I_{$arrExplode[1]}"]) ? ($_POST["hCursoSobresee2I_{$arrExplode[1]}"]) : NULL;
                    $strCursoSobresee3I= isset($_POST["hCursoSobresee3I_{$arrExplode[1]}"]) ? ($_POST["hCursoSobresee3I_{$arrExplode[1]}"]) : NUll;
                    // se revisa si el curso equivalente se va modificar uno nuevo o permanece el anterior solo modificando el sobresee
                    if ($intUpdateCurso != $intCursoOld ){
                        //al actualizar un curso_equivalente se retira del curso por lo cual funciona como Delete
                        UpdateMultipleEquivalencias($intCursoId,$intCurso_equivalenteOld,$intCurso_equivalenteOld2,$intCurso_equivalenteOld3,$strCursoSobresee1I,$strCursoSobresee2I,$strCursoSobresee3I,$intCursoOld,$intCursoId,$intAddUser);
                        //ingreso un nuevo curso con el curso_equivalente  al desear Actualizar debe aparecer este curso y desaparecer de su estado de Curso_equivalente en el curso respectivo
                        $strQuery2 = "call SP_INSERT_CURSO_EQUIVALENTE({$intUpdateCurso},{$intCursoId}, NULL, NULL,'{$strSobresee}',    NULL,NULL,NULL,{$intAddUser})";
                        db_query($strQuery2);
                    }else{
                        //conjunto de ifs que revisan en base al curso que se modificara en que posicion esta para asignar el nuevo strsobresee
                        ($intCursoId==$intCurso_equivalenteOld)? $strCursoSobresee1I = $strSobresee:"";
                        ($intCursoId==$intCurso_equivalenteOld2)? $strCursoSobresee2I = $strSobresee:"";
                        ($intCursoId==$intCurso_equivalenteOld3)? $strCursoSobresee3I = $strSobresee:"";
                        UpdateMultipleEquivalencias("NULL",$intCurso_equivalenteOld,$intCurso_equivalenteOld2,$intCurso_equivalenteOld3,$strCursoSobresee1I,$strCursoSobresee2I,$strCursoSobresee3I,$intCursoOld,$intCursoId,$intAddUser);
                    }
                }
            }
            //Delete curso equivalente seccion I
            if( $arrExplode[0] == "hidDeleteCursoEquivalenteI" && $intCursoId > 0 && $intAddUser > 0){
                $strDeleteCursoEquivalenteI = isset($_POST["hidDeleteCursoEquivalenteI_{$arrExplode[1]}"]) ? $_POST["hidDeleteCursoEquivalenteI_{$arrExplode[1]}"] : "N";

                if($strDeleteCursoEquivalenteI == "Y" && $intCursoId > 0 && $intAddUser > 0){
                   // $intCursoEquivalente = isset($_POST["hidCursoEquivalenteI_{$arrExplode[1]}"]) ? intval($_POST["hidCursoEquivalenteI_{$arrExplode[1]}"]) : 0;
                    $intCursoOld = isset($_POST["hidCursoEquivalenteI_{$arrExplode[1]}"]) ? intval($_POST["hidCursoEquivalenteI_{$arrExplode[1]}"]) : 0;

                    $intCurso_equivalente = isset($_POST["hCursoEquivalente1I_{$arrExplode[1]}"]) ? intval($_POST["hCursoEquivalente1I_{$arrExplode[1]}"]) : 0;
                    $intCurso_equivalente2 = isset($_POST["hCursoEquivalente2I_{$arrExplode[1]}"]) ? intval($_POST["hCursoEquivalente2I_{$arrExplode[1]}"]) :0;
                    $intCurso_equivalente3 = isset($_POST["hCursoEquivalente3I_{$arrExplode[1]}"]) ? intval($_POST["hCursoEquivalente3I_{$arrExplode[1]}"]) : 0;
                    /*$strQuery = "DELETE
                                 FROM   curso_equivalente
                                 WHERE  curso = {$intCursoEquivalente}
                                 AND    curso_equivalente = {$intCursoId}";*/
                    if($intCurso_equivalente==$intCursoId && $intCurso_equivalente2 == 0 && $intCurso_equivalente3 == 0 ){

                        //**** Proceso de eliminacion temporal aunque la equivalencia este autorizada, solicitado por tuncho ****//.
                        /*$intAutCaso = 0;
                        $strQuery = "SELECT  aut_caso.aut_caso,
                                             aut_caso_estado.aut_caso_estado
                                     FROM    aut_caso
                                             INNER JOIN aut_caso_llave llave1
                                                ON  aut_caso.aut_caso = llave1.aut_caso
                                                AND llave1.aut_llave = (SELECT aut_llave.aut_llave FROM aut_llave WHERE aut_llave.campo = 'CURSO')
                                                AND llave1.valor = {$intCursoOld}
                                             INNER JOIN aut_caso_llave llave2
                                               ON  aut_caso.aut_caso = llave2.aut_caso
                                                AND llave2.aut_llave = (SELECT aut_llave.aut_llave FROM aut_llave WHERE aut_llave.campo = 'CURSO_EQUIVALENTE')
                                                AND llave2.valor = {$intCursoId}
                                             INNER JOIN aut_caso_estado
                                                ON  aut_caso.aut_caso = aut_caso_estado.aut_caso
                                             INNER JOIN aut_persona_caso_estado
                                                ON  aut_persona_caso_estado.aut_caso_estado = aut_caso_estado.aut_caso_estado
                                     WHERE   aut_caso.aut_tipo_caso = 3";
                        $qTMP = db_query($strQuery);
                        while( $rTMP = db_fetch_assoc($qTMP) ){
                            $intAutCaso = $rTMP['AUT_CASO'];
                        }
                        db_free_result($qTMP);

                        if($intAutCaso > 0){
                            $strQuery = "SELECT aut_caso_estado
                                         FROM   aut_caso_estado
                                         WHERE  aut_caso = {$intAutCaso}";
                            $qTMP = db_query($strQuery);
                            while( $rTMP = db_fetch_assoc($qTMP) ){
                                //Elimina el registro de persona caso estado
                                db_query("DELETE FROM aut_persona_caso_estado WHERE aut_caso_estado = {$rTMP['AUT_CASO_ESTADO']}");
                            }
                            db_free_result($qTMP);

                            //Elimina el registro de caso estado
                            db_query("DELETE FROM aut_caso_estado WHERE aut_caso = {$intAutCaso}");

                            //Elimina el registro de caso llave
                            db_query("DELETE FROM aut_caso_llave WHERE aut_caso = {$intAutCaso}");

                            //Elimina el registro de caso
                            db_query("DELETE FROM aut_caso WHERE aut_caso = {$intAutCaso}");

                        }*/
                        //**** Fin proceso de eliminacion temporal ****//


                        $strQuery = "call SP_DELETE_CURSO_EQUIVALENTE({$intCursoOld},{$intCursoId},{$intAddUser})";
                        db_query($strQuery);

                    }
                    else{
                        //al actualizar un curso_equivalente se retira del curso por lo cual funciona como Delete
                        $strCursoSobresee1I= isset($_POST["hCursoSobresee1I_{$arrExplode[1]}"]) ? ($_POST["hCursoSobresee1I_{$arrExplode[1]}"]) : NULL;
                        $strCursoSobresee2I= isset($_POST["hCursoSobresee2I_{$arrExplode[1]}"]) ? ($_POST["hCursoSobresee2I_{$arrExplode[1]}"]) : NULL;
                        $strCursoSobresee3I= isset($_POST["hCursoSobresee3I_{$arrExplode[1]}"]) ? ($_POST["hCursoSobresee3I_{$arrExplode[1]}"]) : NUll;
                        UpdateMultipleEquivalencias($intCursoId,$intCurso_equivalente,$intCurso_equivalente2,$intCurso_equivalente3,$strCursoSobresee1I,$strCursoSobresee2I,$strCursoSobresee3I,$intCursoOld,$intCursoId,$intAddUser);
                    }

                }
            }

            //Insertar cursos equivalente seccion II
            if( $arrExplode[0] == "hidNewCursoEquivalenteII" ) {
                if( $intLastRow != $arrExplode[1] ) {
                    $intLastRow = $arrExplode[1];

                    $intCursoEquivalente1 = (isset($_POST["hidNewCursoEquivalenteII_{$arrExplode[1]}_1"]) && ($_POST["hidNewCursoEquivalenteII_{$arrExplode[1]}_1"] > 0)) ? intval($_POST["hidNewCursoEquivalenteII_{$arrExplode[1]}_1"]) : "NULL";
                    $intCursoEquivalente2 = (isset($_POST["hidNewCursoEquivalenteII_{$arrExplode[1]}_2"]) && ($_POST["hidNewCursoEquivalenteII_{$arrExplode[1]}_2"] > 0)) ? intval($_POST["hidNewCursoEquivalenteII_{$arrExplode[1]}_2"]) : "NULL";
                    $intCursoEquivalente3 = (isset($_POST["hidNewCursoEquivalenteII_{$arrExplode[1]}_3"]) && ($_POST["hidNewCursoEquivalenteII_{$arrExplode[1]}_3"] > 0)) ? intval($_POST["hidNewCursoEquivalenteII_{$arrExplode[1]}_3"]) : "NULL";
                    $strSobresee1 = (isset($_POST["sltSobresee_{$arrExplode[1]}_1"])) ? $_POST["sltSobresee_{$arrExplode[1]}_1"] : "NULL";
                    $strSobresee2 = (isset($_POST["sltSobresee_{$arrExplode[1]}_2"])) ? $_POST["sltSobresee_{$arrExplode[1]}_2"] : "NULL";
                    $strSobresee3 = (isset($_POST["sltSobresee_{$arrExplode[1]}_3"])) ? $_POST["sltSobresee_{$arrExplode[1]}_3"] : "NULL";
                    $arrInfoInsertE[intval($_POST["hidNewCursoEquivalenteII_{$arrExplode[1]}_1"])] = (intval($_POST["hidNewCursoEquivalenteII_{$arrExplode[1]}_1"]) > 0) ? intval($_POST["hidNewCursoEquivalenteII_{$arrExplode[1]}_1"]) : "";

                    if( !empty($intCursoId) && !empty($intCursoEquivalente1) ) {

                        foreach( $arrInfoInsertE as  $rTMP['key'] => $rTMP['value'])  {

                            if ( !isset($arrinfoCursoEqui[$intCursoEquivalente1]) && $rTMP["value"] > 0 && $intCursoId > 0 && $intAddUser > 0 ){

                                /*$strQuery = "INSERT INTO {$cfg["academico"]["schema"]}.CURSO_EQUIVALENTE
                                             (CURSO, CURSO_EQUIVALENTE, CURSO_EQUIVALENTE2, CURSO_EQUIVALENTE3, ADD_USER, ADD_FECHA)
                                             VALUES
                                             ({$intCursoId}, {$rTMP["value"]}, '{$intCursoEquivalente2}', '{$intCursoEquivalente3}', {$intAddUser}, NOW())";*/
                                $strQuery = "call SP_INSERT_CURSO_EQUIVALENTE({$intCursoId},{$rTMP["value"]},{$intCursoEquivalente2},{$intCursoEquivalente3},'{$strSobresee1}',NULL,'{$strSobresee2}','{$strSobresee3}',{$intAddUser})";
                                db_query($strQuery);

                            }

                        }
                    }
                }
            }

            //Delete cursos equivalentes seccion II
            if($arrExplode[0] == "hidDeleteCursoEquivalenteII" && $intCursoId > 0 && $intCursoEquivalente1 > 0 && $intAddUser > 0){
                $strDeleteCursoEquivalenteII = isset($_POST["hidDeleteCursoEquivalenteII_{$arrExplode[1]}"]) ? $_POST["hidDeleteCursoEquivalenteII_{$arrExplode[1]}"] : "N";
                if($strDeleteCursoEquivalenteII == "Y" && $intCursoId > 0 && $intCursoEquivalente1 > 0 && $intAddUser > 0){
                    //$strQuery = "DELETE FROM CURSO_EQUIVALENTE WHERE CURSO = {$intCursoId} AND CURSO_EQUIVALENTE = {$intCursoEquivalente1}";

                    //**** Proceso de eliminacion temporal aunque la equivalencia este autorizada, solicitado por tuncho ****//.
                    /*$intAutCaso = 0;
                    $strQuery = "SELECT  aut_caso.aut_caso,
                                         aut_caso_estado.aut_caso_estado
                                 FROM    aut_caso
                                         INNER JOIN aut_caso_llave llave1
                                            ON  aut_caso.aut_caso = llave1.aut_caso
                                            AND llave1.aut_llave = (SELECT aut_llave.aut_llave FROM aut_llave WHERE aut_llave.campo = 'CURSO')
                                            AND llave1.valor = {$intCursoId}
                                         INNER JOIN aut_caso_llave llave2
                                           ON  aut_caso.aut_caso = llave2.aut_caso
                                            AND llave2.aut_llave = (SELECT aut_llave.aut_llave FROM aut_llave WHERE aut_llave.campo = 'CURSO_EQUIVALENTE')
                                            AND llave2.valor = {$intCursoEquivalente1}
                                         INNER JOIN aut_caso_estado
                                            ON  aut_caso.aut_caso = aut_caso_estado.aut_caso
                                         INNER JOIN aut_persona_caso_estado
                                            ON  aut_persona_caso_estado.aut_caso_estado = aut_caso_estado.aut_caso_estado
                                 WHERE   aut_caso.aut_tipo_caso = 3";
                    $qTMP = db_query($strQuery);
                    while( $rTMP = db_fetch_assoc($qTMP) ){
                        $intAutCaso = $rTMP['AUT_CASO'];
                    }
                    db_free_result($qTMP);

                    if($intAutCaso > 0){
                        $strQuery = "SELECT aut_caso_estado
                                     FROM   aut_caso_estado
                                     WHERE  aut_caso = {$intAutCaso}";
                        $qTMP = db_query($strQuery);
                        while( $rTMP = db_fetch_assoc($qTMP) ){
                            //Elimina el registro de persona caso estado
                            db_query("DELETE FROM aut_persona_caso_estado WHERE aut_caso_estado = {$rTMP['AUT_CASO_ESTADO']}");
                        }
                        db_free_result($qTMP);

                        //Elimina el registro de caso estado
                        db_query("DELETE FROM aut_caso_estado WHERE aut_caso = {$intAutCaso}");

                        //Elimina el registro de caso llave
                        db_query("DELETE FROM aut_caso_llave WHERE aut_caso = {$intAutCaso}");

                        //Elimina el registro de caso
                        db_query("DELETE FROM aut_caso WHERE aut_caso = {$intAutCaso}");

                    }*/
                    //**** Fin proceso de eliminacion temporal ****//

                    $strQuery = "call SP_DELETE_CURSO_EQUIVALENTE({$intCursoId},{$intCursoEquivalente1},{$intAddUser})";
                    db_query($strQuery);
                }

            }
            //Update cursos equivalentes seccion II
            if ( $arrExplode[0] == "hidUpdateEquivalente" ){

                $intCursoEquivalenteOld = (isset($_POST["hidUpdateControlEquivalente_{$arrExplode[1]}"]) && $_POST["hidUpdateControlEquivalente_{$arrExplode[1]}"] > 0) ? intval($_POST["hidUpdateControlEquivalente_{$arrExplode[1]}"]) : "";
                $intCursoEquivalente1 = (isset($_POST["hidUpdateEquivalente_{$arrExplode[1]}_1"]) && $_POST["hidUpdateEquivalente_{$arrExplode[1]}_1"] > 0) ? intval($_POST["hidUpdateEquivalente_{$arrExplode[1]}_1"]) : "NULL";
                $intCursoEquivalente2 = (isset($_POST["hidUpdateEquivalente_{$arrExplode[1]}_2"]) && $_POST["hidUpdateEquivalente_{$arrExplode[1]}_2"] > 0) ? intval($_POST["hidUpdateEquivalente_{$arrExplode[1]}_2"]) : "NULL";
                $intCursoEquivalente3 = (isset($_POST["hidUpdateEquivalente_{$arrExplode[1]}_3"]) && $_POST["hidUpdateEquivalente_{$arrExplode[1]}_3"] > 0) ? intval($_POST["hidUpdateEquivalente_{$arrExplode[1]}_3"]) : "NULL";
                $strSobresee1 = (isset($_POST["sltSobresee_{$arrExplode[1]}_1"])) ? $_POST["sltSobresee_{$arrExplode[1]}_1"] : "NULL";
                $strSobresee2 = (isset($_POST["sltSobresee_{$arrExplode[1]}_2"])) ? $_POST["sltSobresee_{$arrExplode[1]}_2"] : "NULL";
                $strSobresee3 = (isset($_POST["sltSobresee_{$arrExplode[1]}_3"])) ? $_POST["sltSobresee_{$arrExplode[1]}_3"] : "NULL";

                if($intCursoEquivalenteOld > 0 && $intAddUser > 0 && $intCursoId > 0){
                    /*$strQuery = "UPDATE  {$cfg["academico"]["schema"]}.CURSO_EQUIVALENTE
                                 SET     CURSO_EQUIVALENTE = '{$intCursoEquivalente1}',
                                         CURSO_EQUIVALENTE2 = '{$intCursoEquivalente2}',
                                         CURSO_EQUIVALENTE3 = '{$intCursoEquivalente3}',
                                         MOD_USER = {$intAddUser},
                                         MOD_FECHA = NOW()
                                 WHERE   CURSO = {$intCursoId}
                                 AND     CURSO_EQUIVALENTE = {$intCursoEquivalenteOld}";*/
                    $strQuery = "call SP_UPDATE_CURSO_EQUIVALENTE({$intCursoId},{$intCursoEquivalente1},{$intCursoEquivalente2},{$intCursoEquivalente3},'{$strSobresee1}',NULL,'{$strSobresee2}','{$strSobresee3}',{$intAddUser},NULL,{$intCursoEquivalenteOld})";
                    db_query($strQuery);
                }

            }
        }


        $boolUndoSolicitudIndividual = false;
        $boolUndoSolicitudCompuesta = false;
        reset($_POST);
        foreach( $_POST as  $arrUndoSolicitud['key'] => $arrUndoSolicitud['value'])  {
            $arrExplode = explode("_",$arrUndoSolicitud['key']);
            if($arrExplode[0] == "hdnEstadoFinal"){
                if(isset($_POST["hdnEstadoFinal_{$arrExplode[1]}"]) && isset($_POST["hdnSeccionI_{$arrExplode[1]}"]) && $_POST["hdnEstadoFinal_{$arrExplode[1]}"] == 'Y'  && $_POST["hdnSeccionI_{$arrExplode[1]}"] == "Y"){
                    $boolUndoSolicitudIndividual = true;
                    $intCursoEqUndo =  isset($_POST["hidCursoEquivalenteI_{$arrExplode[1]}"])?intval($_POST["hidCursoEquivalenteI_{$arrExplode[1]}"]):0;
                }

            }
        }

        reset($_POST);
        foreach( $_POST as  $arrUndoSolicitud['key'] => $arrUndoSolicitud['value'])  {
            $arrExplode = explode("_",$arrUndoSolicitud['key']);
            if($arrExplode[0] == "hdnEstadoFinalCompuesto" ){
                if(isset($_POST["hdnEstadoFinalCompuesto_{$arrExplode[1]}"]) && isset($_POST["hdnSeccionII_{$arrExplode[1]}"]) && $_POST["hdnEstadoFinalCompuesto_{$arrExplode[1]}"] == 'Y'  && $_POST["hdnSeccionII_{$arrExplode[1]}"] == "Y"){
                    $boolUndoSolicitudCompuesta = true;
                    $intCursoEqUndo1 = isset($_POST["hidUpdateEquivalente_{$arrExplode[1]}_1"])?intval($_POST["hidUpdateEquivalente_{$arrExplode[1]}_1"]):0;
                    $intCursoEqUndo2 = isset($_POST["hidUpdateEquivalente_{$arrExplode[1]}_2"])?intval($_POST["hidUpdateEquivalente_{$arrExplode[1]}_2"]):0;
                    $intCursoEqUndo3 = isset($_POST["hidUpdateEquivalente_{$arrExplode[1]}_3"])?intval($_POST["hidUpdateEquivalente_{$arrExplode[1]}_3"]):0;
                }
            }
        }





        //Blur Equivalencias ingresadas
        $strFiltroIndivualUndoEstadosFinales = "AND    CE.estado IS NULL";
        $strFiltroSolicitudesGenerales = "AND  (
                                                                CE.curso_equivalente = {$intCursoId}
                                                                OR     CE.curso_equivalente2 = {$intCursoId}
                                                                OR     CE.curso_equivalente3 = {$intCursoId}
                                                                )";

        $strFiltroCompuestoUndoEstadosFinales = "AND curso_equivalente.estado IS NULL";
         $strFiltroCompuestoUndoCursosEquivalente1 = "";
         $strFiltroCompuestoUndoCursosEquivalente2 = "";
         $strFiltroCompuestoUndoCursosEquivalente3 = "";

        if($boolUndoSolicitudIndividual == true){
            $strFiltroIndivualUndoEstadosFinales = "";
            foreach( $arrValorEstadosFinalesAutorizador as  $arrTMP['key'] => $arrTMP['value'])  {
                $strFiltroIndivualUndoEstadosFinales = ($strFiltroIndivualUndoEstadosFinales == "")?" CE.estado = {$arrTMP['key']} ":$strFiltroIndivualUndoEstadosFinales." OR    CE.estado = {$arrTMP['key']}";
            }

            if(!empty($strFiltroIndivualUndoEstadosFinales)){
                $strFiltroIndivualUndoEstadosFinales = "AND ( {$strFiltroIndivualUndoEstadosFinales} ) ";
                $strFiltroSolicitudesGenerales = "  AND CE.curso_equivalente = {$intCursoId}
                                                                AND CE.curso = {$intCursoEqUndo}
                                                                ";
            }
            else{
                $strFiltroIndivualUndoEstadosFinales = "AND    CE.estado IS NULL";
            }
        }

        if($boolUndoSolicitudCompuesta == true){
            $strFiltroCompuestoUndoEstadosFinales = "";
            foreach( $arrValorEstadosFinalesAutorizador as  $arrTMP['key'] => $arrTMP['value'])  {
                $strFiltroCompuestoUndoEstadosFinales = ($strFiltroCompuestoUndoEstadosFinales == "")?" curso_equivalente.estado = {$arrTMP['key']} ":$strFiltroCompuestoUndoEstadosFinales." OR    curso_equivalente.estado = {$arrTMP['key']}";
            }

            if(!empty($strFiltroCompuestoUndoEstadosFinales)){
                $strFiltroCompuestoUndoEstadosFinales = "AND ( {$strFiltroCompuestoUndoEstadosFinales} )";

                $strFiltroCompuestoUndoCursosEquivalente1 = ($intCursoEqUndo1 > 0)?"AND curso_equivalente.curso_equivalente = {$intCursoEqUndo1} " : "";
                $strFiltroCompuestoUndoCursosEquivalente2 = ($intCursoEqUndo2 > 0)?"AND curso_equivalente.curso_equivalente2 = {$intCursoEqUndo2} " : "";
                $strFiltroCompuestoUndoCursosEquivalente3 = ($intCursoEqUndo3 > 0)?"AND curso_equivalente.curso_equivalente3 = {$intCursoEqUndo3} " : "";

            }
            else{
                $strFiltroCompuestoUndoEstadosFinales = "AND curso_equivalente.estado IS NULL";
            }
        }

        $arrEquivalenciaIngresada = Array();
        $arrEquivalenciasIngresadas = Array();

        $strQuery = " SELECT C.curso, C.nombre, C.codigo
                            FROM   curso C
                                INNER JOIN  curso_equivalente CE
                                    ON C.curso = CE.curso
                                INNER JOIN area A
                                    ON C.area = A.area
                            WHERE A.facultad = {$intFacultad}
                            {$strFiltroSolicitudesGenerales}
                            {$strFiltroIndivualUndoEstadosFinales}";
        $qTMP = db_query($strQuery);


        while($rTMP = db_fetch_assoc($qTMP)){
            $arrEquivalenciaIngresada[$rTMP["CURSO"]]["CURSO"][$rTMP["CURSO"]]["CURSO_EQUIVALENTE"] = $intCursoId;
            $arrEquivalenciaIngresada[$rTMP["CURSO"]]["CURSO"][$rTMP["CURSO"]]["NOMBRE"] = $rTMP["NOMBRE"];
            $arrEquivalenciaIngresada[$rTMP["CURSO"]]["CURSO"][$rTMP["CURSO"]]["CODIGO"] = $rTMP["CODIGO"];
        }
        db_free_result($qTMP);


        $strQuery2 = "SELECT curso_equivalente.curso_equivalente,
                             curso.nombre,
                             curso.codigo,
                             curso_equivalente.curso_equivalente2,
                             curso2.nombre nombre2,
                             curso2.codigo codigo2,
                             curso_equivalente.curso_equivalente3,
                             curso3.nombre nombre3,
                             curso3.codigo codigo3
                     FROM    curso_equivalente
                        INNER JOIN curso
                            ON curso_equivalente.curso_equivalente = curso.curso
                        LEFT JOIN curso curso2
                            ON curso_equivalente.curso_equivalente2 = curso2.curso
                        LEFT JOIN curso curso3
                            ON curso_equivalente.curso_equivalente3 = curso3.curso
                     WHERE  curso_equivalente.curso = {$intCursoId}
                     {$strFiltroCompuestoUndoCursosEquivalente1}
                     {$strFiltroCompuestoUndoCursosEquivalente2}
                     {$strFiltroCompuestoUndoCursosEquivalente3}
                     {$strFiltroCompuestoUndoEstadosFinales} ";
        $qTMP = db_query($strQuery2);
        while($rTMP = db_fetch_assoc($qTMP)){
            if(!empty($rTMP["CURSO_EQUIVALENTE"])){
                $arrEquivalenciasIngresadas[$rTMP["CURSO_EQUIVALENTE"]]["CURSO"][$intCursoId]["CURSO_EQUIVALENTE"] = $rTMP["CURSO_EQUIVALENTE"];
                $arrEquivalenciasIngresadas[$rTMP["CURSO_EQUIVALENTE"]]["CURSO"][$intCursoId]["NOMBRE"] = $rTMP["NOMBRE"];
                $arrEquivalenciasIngresadas[$rTMP["CURSO_EQUIVALENTE"]]["CURSO"][$intCursoId]["CODIGO"] = $rTMP["CODIGO"];
                $arrEquivalenciasIngresadas[$rTMP["CURSO_EQUIVALENTE"]]["CURSO"][$intCursoId]["CURSO_EQUIVALENTE2"] = $rTMP["CURSO_EQUIVALENTE2"];
                $arrEquivalenciasIngresadas[$rTMP["CURSO_EQUIVALENTE"]]["CURSO"][$intCursoId]["NOMBRE2"] = $rTMP["NOMBRE2"];
                $arrEquivalenciasIngresadas[$rTMP["CURSO_EQUIVALENTE"]]["CURSO"][$intCursoId]["CODIGO2"] = $rTMP["CODIGO2"];
                $arrEquivalenciasIngresadas[$rTMP["CURSO_EQUIVALENTE"]]["CURSO"][$intCursoId]["CURSO_EQUIVALENTE3"] = $rTMP["CURSO_EQUIVALENTE3"];
                $arrEquivalenciasIngresadas[$rTMP["CURSO_EQUIVALENTE"]]["CURSO"][$intCursoId]["NOMBRE3"] = $rTMP["NOMBRE3"];
                $arrEquivalenciasIngresadas[$rTMP["CURSO_EQUIVALENTE"]]["CURSO"][$intCursoId]["CODIGO3"] = $rTMP["CODIGO3"];
            }
        }
        db_free_result($qTMP);

        //drawDebug($arrEquivalenciasIngresadas);
        if( (!empty($arrEquivalenciaIngresada) || !empty($arrEquivalenciasIngresadas) ) && check_persona_access(637) ){

            $strNombreCurso = sqlGetValueFromKey("SELECT nombre FROM curso WHERE curso = {$intCursoId}");
            ?>
            <div id="divEquivalenciasIngresadas" title="Solicitar autorizaci�n equivalencia general" style="display: none;">
                <form method="post" id="frmEquivalenciasIngresadas">
                    <table width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td class="heading1" colspan="2">
                                En este momento se enviar� la solicitud de autorizaci�n para las equivalencias ingresadas.
                            </td>
                        </tr>
                        <?php
                        if( !empty($arrEquivalenciaIngresada) ){
                            ?>
                            <tr>
                                <td colspan="2">Al aprobar el curso <span style="color: #278EAF"><?php print $strNombreCurso; ?></span> se dar� por aprobado el curso:</td>
                            </tr>
                            <tr>
                                <td width="30%" class="row0">C�digo</td>
                                <td width="70%" class="row0">Curso</td>
                            </tr>
                            <?php
                        }
                        $strClass = "row2";
                        $intContadorEquivalenciaI = 0;
                        reset($arrEquivalenciaIngresada);
                        foreach( $arrEquivalenciaIngresada as  $arrTMP['key'] => $arrTMP['value'])  {
                            reset($arrTMP["value"]);
                            foreach( $arrTMP["value"] as  $arrC['key'] => $arrC['value'])  {
                                reset($arrC["value"]);
                                foreach( $arrC["value"] as  $arrCE['key'] => $arrCE['value'])  {
                                    $strClass = ($strClass == "row2") ? "row1" : "row2";
                                    $intContadorEquivalenciaI++;
                                    ?>
                                    <tr>
                                        <td class="<?php print $strClass; ?>">
                                            <?php print isset($arrCE["value"]["CODIGO"]) ? $arrCE["value"]["CODIGO"] : ""; ?>
                                            <input type="hidden" name="hidCursoI_<?php print $intContadorEquivalenciaI; ?>" value="<?php print $arrCE["key"]; ?>">
                                            <input type="hidden" name="hidCursoEquivalenteI_<?php print $intContadorEquivalenciaI; ?>" value="<?php print $arrCE["value"]["CURSO_EQUIVALENTE"]; ?>">
                                        </td>
                                        <td class="<?php print $strClass; ?>">
                                            <?php print isset($arrCE["value"]["NOMBRE"]) ? $arrCE["value"]["NOMBRE"] : ""; ?>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            }
                        }
                        ?>
                        <tr>
                            <td colspan="2">&nbsp;</td>
                        </tr>
                        <?php
                        if( !empty($arrEquivalenciasIngresadas) ){
                            ?>
                            <tr>
                                <td colspan="2">Se dar� por aprobado el curso <span style="color: #278EAF"><?php print $strNombreCurso; ?></span> al aprobar el curso:</td>
                            </tr>
                            <tr>
                                <td width="30%" class="row0">C�digo</td>
                                <td width="70%" class="row0">Curso</td>
                            </tr>
                        <?php
                        }
                        $intContadorEquivalenciaII = 0;
                        reset($arrEquivalenciasIngresadas);
                        foreach( $arrEquivalenciasIngresadas as  $arrTMP['key'] => $arrTMP['value'])  {
                            reset($arrTMP["value"]);
                            foreach( $arrTMP["value"] as  $arrC['key'] => $arrC['value'])  {
                                reset($arrC["value"]);
                                foreach( $arrC["value"] as  $arrCE['key'] => $arrCE['value'])  {
                                    $strClass = ($strClass == "row2") ? "row1" : "row2";
                                    $intContadorEquivalenciaII++;
                                    ?>
                                    <tr>
                                        <td class="<?php print $strClass; ?>">
                                            <?php print isset($arrCE["value"]["CODIGO"]) ? $arrCE["value"]["CODIGO"] : ""; ?>
                                            <br>
                                            <?php print isset($arrCE["value"]["CODIGO2"]) ? $arrCE["value"]["CODIGO2"] : ""; ?>
                                            <br>
                                            <?php print isset($arrCE["value"]["CODIGO3"]) ? $arrCE["value"]["CODIGO3"] : ""; ?>
                                            <input type="hidden" name="hidCursoII_<?php print $intContadorEquivalenciaII; ?>" value="<?php print $arrCE["key"]; ?>">
                                            <input type="hidden" name="hidCursoEquivalenteII_<?php print $intContadorEquivalenciaII; ?>" value="<?php print $arrCE["value"]["CURSO_EQUIVALENTE"]; ?>">
                                        </td>
                                        <td class="<?php print $strClass; ?>">
                                            <?php print isset($arrCE["value"]["NOMBRE"]) ? $arrCE["value"]["NOMBRE"] : ""; ?>
                                            <br>
                                            <?php print isset($arrCE["value"]["NOMBRE2"]) ? $arrCE["value"]["NOMBRE2"] : ""; ?>
                                            <br>
                                            <?php print isset($arrCE["value"]["NOMBRE3"]) ? $arrCE["value"]["NOMBRE3"] : ""; ?>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            }
                        }
                        ?>
                    </table>
                </form>
            </div>

            <script type="text/javascript">

            $(function(){
                $("#divEquivalenciasIngresadas").dialog({
                    show: "explode",
                    hide: "explode",
                    modal: "true",
                    resizable: false,
                    draggable: false,
                    width: 650,
                    buttons: {
                        "Solicitar": function() {
                            $.ajax({
                                url:"<?php print $strAction; ?>?facultad=<?php print $intFacultad; ?>",
                                data: $("#frmEquivalenciasIngresadas").serialize()+"&createAutCaso=true",
                                type:"post",
                                dataType: "html",
                                success: function(data){
                                    window.location.href=window.location.href;
                                }
                            });
                            $( this ).dialog( "close" );
                        },
                        "Cancelar": function() {

                            $( this ).dialog( "close" );
                        }
                    }
                })
            });
            </script>
            <?php
        }

        reset($arrPost);



        //drawDebug($arrInfoInsertS);
        if( $intCursoId > 0 ){
            db_query("DELETE FROM {$cfg["academico"]["schema"]}.CURSO_AREA_PENSUM WHERE CURSO = {$intCursoId}");
        }
        reset($arrPost);
        foreach( $arrPost as  $arrTMP['key'] => $arrTMP['value'])  {
            $arrExplode = explode("_", $arrTMP["key"]);
            if( $arrExplode[0] == "hidAreaPensum" ) {
                $intAreaPensum = isset($_POST["selectAreaPensum_{$arrExplode[1]}"]) ? intval($_POST["selectAreaPensum_{$arrExplode[1]}"]) : 0;

                if( $intAreaPensum > 0 && $intCursoId > 0 && $intAddUser > 0 ){
                    db_query("INSERT INTO {$cfg["academico"]["schema"]}.CURSO_AREA_PENSUM
                              (AREA_PENSUM, CURSO, ADD_USER, ADD_FECHA)
                              VALUES
                              ({$intAreaPensum}, {$intCursoId}, {$intAddUser}, NOW())");
                }
            }
        }
        //die();
        //Aqui actualizo el la informacion del curso, incluyendo el curso padre.
        $strCodigo = !empty($_POST["codigo"]) ? db_escape($_POST["codigo"]) : '';
        $strNombre = !empty($_POST["nombre"]) ? db_escape($_POST["nombre"]) : '';
        $sinNumeroUmas = isset($_POST["umas"]) ? floatval($_POST["umas"]) : 0;
        $sinNumeroEcts = isset($_POST["ects"]) ? floatval($_POST["ects"]) : 0;
        $sinNumeroLogos = !empty($_POST["txtLogos"]) ? floatval($_POST["txtLogos"]) : 0;
        $intNumeroArea = ( !empty($_POST["area"]) ) ? "'{$_POST["area"]}'" : "NULL" ;
        //$intTipoCurso = ( intval($_POST["select_tipo_curso"]) > 0 ) ? "'{$_POST["select_tipo_curso"]}'" : "NULL" ;
        $intTipoCurso = ( empty($_POST["select_tipo_curso"]) ) ? "NULL" : "'{$_POST["select_tipo_curso"]}'";
        $intCursoPadre = ( !empty($_POST["hidSubordinadoDe"]) ) ? "{$_POST["hidSubordinadoDe"]}" : "NULL" ;
        $strNombreIngles = !empty($_POST["nombreIngles"]) ? db_escape($_POST["nombreIngles"]) : "NULL";
        $strActivo = isset($_POST["activo"]) ? "Y" : "N";
        $sinMontoMaximo = !empty($_POST["txtMontoMaximoCurso"]) ? floatval($_POST["txtMontoMaximoCurso"]) : 0;
        $strFilosofiaIns = isset($_POST["chkFilosofiaIns"]) ? "Y" : "N";
        $strDetalleConvenio = isset($_POST["chkDetalleConvenio"]) ? "Y" : "N";
        $strNombreConvenio = isset($_POST["txtNombreConvenio"]) ? db_escape($_POST["txtNombreConvenio"]) : '';

        //drawDebug($_POST);
        //$richTextDes = new phpdbform_rich_textarea("frm_detalle", "txtDescripcion", "<span class='editTitles'>Descripci�n</span>", 150);
        //$richTextDes->process();
        //drawDebug($richTextDes->value);

        $strDescripcion = isset($_POST["frm_detalle_txtDescripcion"]) ? db_escape(user_input_delmagic($_POST["frm_detalle_txtDescripcion"])) : '';
        $strBibliografia = isset($_POST["frm_detalle_txtBibliografia"]) ? db_escape($_POST["frm_detalle_txtBibliografia"]) : '';
        $strObjetivos = isset($_POST["frm_detalle_txtObjetivos"]) ? db_escape($_POST["frm_detalle_txtObjetivos"]) : '';
        $strEnsenanza = isset($_POST["frm_detalle_txtEnsenanza"]) ? db_escape($_POST["frm_detalle_txtEnsenanza"]) : '';
        $strCalificacion = isset($_POST["frm_detalle_txtCalificacion"]) ? db_escape($_POST["frm_detalle_txtCalificacion"]) : '';
        $strEsquemaCurso = isset($_POST["frm_detalle_txtEsquemaCurso"]) ? db_escape($_POST["frm_detalle_txtEsquemaCurso"]) : '';
        $strImpartidoIns = isset($_POST["frm_detalle_txtImpartidoIns"]) ? db_escape($_POST["frm_detalle_txtImpartidoIns"]) : '';


                // ========== GUARDAR SYLLABUS UA ==========
        $strDescInst = isset($_POST["frm_sylUA_txtDescInst"]) ? $_POST["frm_sylUA_txtDescInst"] : '';
        $strAporte = isset($_POST["frm_sylUA_txtAporte"]) ? $_POST["frm_sylUA_txtAporte"] : '';
        $strConocimientos = isset($_POST["frm_sylUA_txtConocimientos"]) ? $_POST["frm_sylUA_txtConocimientos"] : '';
        $strMarco = isset($_POST["frm_sylUA_txtMarco"]) ? $_POST["frm_sylUA_txtMarco"] : '';

        // Verificar qu� campos fueron editados (DEFINIR ANTES DE USAR)
        $strEditedDescInst = isset($_POST["hidEditedDescInst"]) ? $_POST["hidEditedDescInst"] : "N";
        $strEditedAporte = isset($_POST["hidEditedAporte"]) ? $_POST["hidEditedAporte"] : "N";
        $strEditedConocimientos = isset($_POST["hidEditedConocimientos"]) ? $_POST["hidEditedConocimientos"] : "N";
        $strEditedMarco = isset($_POST["hidEditedMarco"]) ? $_POST["hidEditedMarco"] : "N";


        $hayRA = false;
        foreach($_POST as $key => $value) {
            if(strpos($key, 'hidNewRA_') === 0 || strpos($key, 'hidUpdateRA_') === 0 || strpos($key, 'hidDeleteRA_') === 0) {
                $hayRA = true;
                break;
            }
        }

        
        // Verificar si hay contenido en al menos un campo

        $hayContenidoSyllabusUA = (
            ($strEditedDescInst == "Y" && limpiarHTMLVacio($strDescInst) !== null) ||
            ($strEditedAporte == "Y" && limpiarHTMLVacio($strAporte) !== null) ||
            ($strEditedConocimientos == "Y" && limpiarHTMLVacio($strConocimientos) !== null) ||
            ($strEditedMarco == "Y" && limpiarHTMLVacio($strMarco) !== null)
        );


        $crearNuevaVersion = (isset($_POST["hidTipoGuardado"]) && $_POST["hidTipoGuardado"] == "nueva_version");

       // if ($hayContenidoSyllabusUA && $intCursoId > 0 && $intAddUser > 0) {

        if (($hayContenidoSyllabusUA || $crearNuevaVersion) && $intCursoId > 0 && $intAddUser > 0) {

            // Buscar si existe Syllabus UA
            $syllabusUA = getSyllabusUA($intCursoId);
            
            if (!$syllabusUA) {
                // INSERTAR NUEVO
                $strQuery = "INSERT INTO {$cfg["academico"]["schema"]}.SYLLABUS_UA
                             (SYLLABUS_UA, CURSO, ADD_USER, ADD_FECHA)
                             VALUES
                             (SEQ_SYLLABUS_UA.NEXTVAL, {$intCursoId}, 
                              {$intAddUser}, SYSDATE)";
                
                db_query($strQuery);
                
                // Obtener el ID generado
                $intSyllabusUA = sqlGetValueFromKey(
                    "SELECT SYLLABUS_UA FROM {$cfg["academico"]["schema"]}.SYLLABUS_UA 
                     WHERE CURSO = {$intCursoId}"
                );
                
            } else {
                // ACTUALIZAR EXISTENTE
                $intSyllabusUA = $syllabusUA['SYLLABUS_UA'];
                
                $strQuery = "UPDATE {$cfg["academico"]["schema"]}.SYLLABUS_UA
                             SET MOD_USER = {$intAddUser},
                                 MOD_FECHA = SYSDATE
                             WHERE SYLLABUS_UA = {$intSyllabusUA}";
                
                db_query($strQuery);
            }
            
            
            // Guardar cada CLOB (solo si tiene contenido)
            $strEditedDescInst = isset($_POST["hidEditedDescInst"]) ? $_POST["hidEditedDescInst"] : "N";
            $strEditedAporte = isset($_POST["hidEditedAporte"]) ? $_POST["hidEditedAporte"] : "N";
            $strEditedConocimientos = isset($_POST["hidEditedConocimientos"]) ? $_POST["hidEditedConocimientos"] : "N";
            $strEditedMarco = isset($_POST["hidEditedMarco"]) ? $_POST["hidEditedMarco"] : "N";


            // ========== CONTROL DE VERSIONES: NUEVA VERSI�N ==========
            // IMPORTANTE: Este bloque se ejecuta ANTES de guardar CLOBs
            // para preservar los datos originales en la versi�n anterior
            if(isset($_POST["hidTipoGuardado"]) && $_POST["hidTipoGuardado"] == "nueva_version") {
                
                // Paso 1: Crear nueva versi�n
                $strQuery = "INSERT INTO {$cfg['academico']['schema']}.SYLLABUS_UA (
                                SYLLABUS_UA,
                                CURSO,
                                FECHA_INICIO,
                                FECHA_FIN,
                                ADD_USER,
                                ADD_FECHA
                             ) VALUES (
                                SEQ_SYLLABUS_UA.NEXTVAL,
                                {$intCursoId},
                                SYSDATE,
                                NULL,
                                {$intAddUser},
                                SYSDATE
                             )";
                db_query($strQuery);
                
                // Obtener el ID de la nueva versi�n
                $qNewVersion = db_query("SELECT SEQ_SYLLABUS_UA.CURRVAL as NEW_ID FROM DUAL");
                $rNewVersion = db_fetch_array($qNewVersion);
                $intNewSyllabusUA = $rNewVersion['NEW_ID'];
                
                // Paso 2: Copiar campos CLOB desde la versi�n anterior (datos originales)
                $strDescInstOriginal = getTextoClob('DESCRIPCION_INSTITUCIONAL', 'SYLLABUS_UA', 'SYLLABUS_UA', $intSyllabusUA);
                $strAporteOriginal = getTextoClob('APORTE_PLAN_ESTUDIOS', 'SYLLABUS_UA', 'SYLLABUS_UA', $intSyllabusUA);
                $strConocimientosOriginal = getTextoClob('CONOCIMIENTOS_PREVIOS', 'SYLLABUS_UA', 'SYLLABUS_UA', $intSyllabusUA);
                $strMarcoOriginal = getTextoClob('MARCO_NORMATIVO', 'SYLLABUS_UA', 'SYLLABUS_UA', $intSyllabusUA);
                
                if(!empty($strDescInstOriginal)) {
                    guardarClobSyllabusUA($intNewSyllabusUA, 'DESCRIPCION_INSTITUCIONAL', $strDescInstOriginal);
                }
                if(!empty($strAporteOriginal)) {
                    guardarClobSyllabusUA($intNewSyllabusUA, 'APORTE_PLAN_ESTUDIOS', $strAporteOriginal);
                }
                if(!empty($strConocimientosOriginal)) {
                    guardarClobSyllabusUA($intNewSyllabusUA, 'CONOCIMIENTOS_PREVIOS', $strConocimientosOriginal);
                }
                if(!empty($strMarcoOriginal)) {
                    guardarClobSyllabusUA($intNewSyllabusUA, 'MARCO_NORMATIVO', $strMarcoOriginal);
                }
                
                // Paso 3: Copiar Resultados de Aprendizaje (datos originales)
                $qRA = db_query("SELECT * FROM {$cfg['academico']['schema']}.SYLLABUS_UA_RA 
                                 WHERE SYLLABUS_UA = {$intSyllabusUA}");
                while($rRA = db_fetch_array($qRA)) {
                    $strDescRA = db_escape($rRA['DESCRIPCION_RA']);
                    $intBloomNivel = intval($rRA['BLOOM_NIVEL']);
                    
                    $strQuery = "INSERT INTO {$cfg['academico']['schema']}.SYLLABUS_UA_RA (
                                    SYLLABUS_UA_RA,
                                    SYLLABUS_UA,
                                    DESCRIPCION_RA,
                                    BLOOM_NIVEL,
                                    ADD_USER,
                                    ADD_FECHA
                                 ) VALUES (
                                    SEQ_SYLLABUS_UA_RA.NEXTVAL,
                                    {$intNewSyllabusUA},
                                    '{$strDescRA}',
                                    {$intBloomNivel},
                                    {$intAddUser},
                                    SYSDATE
                                 )";
                    db_query($strQuery);
                }
                
                // Paso 4: Copiar Bibliograf�a (datos originales)
                $qBiblio = db_query("SELECT SYLLABUS_UA_BIBLIO
                                     FROM {$cfg['academico']['schema']}.SYLLABUS_UA_BIBLIO 
                                     WHERE SYLLABUS_UA = {$intSyllabusUA}");
                while($rBiblio = db_fetch_array($qBiblio)) {
                    $strReferencia = getReferenciaBiblio($rBiblio['SYLLABUS_UA_BIBLIO']);
                    if ($strReferencia === '') {
                        continue;
                    }

                    $strQuery = "INSERT INTO {$cfg['academico']['schema']}.SYLLABUS_UA_BIBLIO (
                                    SYLLABUS_UA_BIBLIO,
                                    SYLLABUS_UA,
                                    REFERENCIA_COMPLETA,
                                    ADD_USER,
                                    ADD_FECHA
                                 ) VALUES (
                                    SEQ_SYLLABUS_UA_BIBLIO.NEXTVAL,
                                    {$intNewSyllabusUA},
                                    ' ',
                                    {$intAddUser},
                                    SYSDATE
                                 )";
                    db_query($strQuery);

                    $intNewBiblioId = obtenerCurrvalSeqBiblio();
                    if ($intNewBiblioId > 0) {
                        guardarReferenciaBiblio($intNewBiblioId, $strReferencia);
                    }
                }
                
                // Paso 5: Cerrar la versi�n anterior
                $strQuery = "UPDATE {$cfg['academico']['schema']}.SYLLABUS_UA
                             SET FECHA_FIN = SYSDATE,
                                 MOD_USER = {$intAddUser},
                                 MOD_FECHA = SYSDATE
                             WHERE SYLLABUS_UA = {$intSyllabusUA}
                               AND FECHA_FIN IS NULL";
                db_query($strQuery);
                
                // Paso 6: Cambiar el ID de trabajo para que los cambios se apliquen a la nueva versi�n
                $intSyllabusUA = $intNewSyllabusUA;
            }
            // ========== FIN CONTROL DE VERSIONES ==========


            if($strEditedDescInst == "Y") {
                guardarClobSyllabusUA($intSyllabusUA, 'DESCRIPCION_INSTITUCIONAL', $strDescInst);
            }
            if($strEditedAporte == "Y") {
                guardarClobSyllabusUA($intSyllabusUA, 'APORTE_PLAN_ESTUDIOS', $strAporte);
            }
            if($strEditedConocimientos == "Y") {
                guardarClobSyllabusUA($intSyllabusUA, 'CONOCIMIENTOS_PREVIOS', $strConocimientos);
            }
            if($strEditedMarco == "Y") {
                guardarClobSyllabusUA($intSyllabusUA, 'MARCO_NORMATIVO', $strMarco);
            }

        }

        if($intCursoId > 0 && $intAddUser > 0) {
    // Buscar o crear SYLLABUS_UA
    $syllabusUA = getSyllabusUA($intCursoId);
    
    if (!$syllabusUA) {
        // Crear registro si no existe
        $strQuery = "INSERT INTO {$cfg["academico"]["schema"]}.SYLLABUS_UA
                     (SYLLABUS_UA, CURSO, ADD_USER, ADD_FECHA)
                     VALUES
                     (SEQ_SYLLABUS_UA.NEXTVAL, {$intCursoId}, 
                      {$intAddUser}, SYSDATE)";
        db_query($strQuery);
        
        $intSyllabusUA = sqlGetValueFromKey(
            "SELECT SYLLABUS_UA FROM {$cfg["academico"]["schema"]}.SYLLABUS_UA 
             WHERE CURSO = {$intCursoId}"
        );
    } else {
        $intSyllabusUA = $syllabusUA['SYLLABUS_UA'];
    }
    
    if($intSyllabusUA > 0) {


        // 1. ELIMINAR RA marcados para eliminar
        foreach($_POST as $key => $value) {
            $arrExplode = explode("_", $key);
            
            if($arrExplode[0] == "hidDeleteRA" && $value == "Y") {
                $intRAId = isset($_POST["hidUpdateRA_{$arrExplode[1]}"]) ? intval($_POST["hidUpdateRA_{$arrExplode[1]}"]) : 0;
                
                if($intRAId > 0) {
                    $strQuery = "DELETE FROM {$cfg['academico']['schema']}.SYLLABUS_UA_RA 
                                 WHERE SYLLABUS_UA_RA = {$intRAId}";
                    db_query($strQuery);
                }
            }
        }
        
        // 2. INSERTAR nuevos RA
foreach($_POST as $key => $value) {
    $arrExplode = explode("_", $key);
    
    if($arrExplode[0] == "hidNewRA" && $value == "1") {
        $intIndex = $arrExplode[1];
        
        // DEBUG
      //  error_log("=== INSERTAR RA ===");
      //  error_log("intIndex: " . $intIndex);
      //  error_log("intSyllabusUA: " . $intSyllabusUA);
      //  error_log("intAddUser: " . $intAddUser);
        
        $intNivelBloom = isset($_POST["sltNivelBloom_{$intIndex}"]) ? intval($_POST["sltNivelBloom_{$intIndex}"]) : 0;
        $strDescripcion = isset($_POST["txtDescripcionRA_{$intIndex}"]) ? trim($_POST["txtDescripcionRA_{$intIndex}"]) : "";
        
      //  error_log("intNivelBloom: " . $intNivelBloom);
      //  error_log("strDescripcion: " . $strDescripcion);
        
        if($intNivelBloom > 0 && !empty($strDescripcion)) {
            $strDescripcionEscaped = str_replace("'", "''", $strDescripcion);
            
            $strQuery = "INSERT INTO {$cfg['academico']['schema']}.SYLLABUS_UA_RA 
                        (SYLLABUS_UA_RA, SYLLABUS_UA, DESCRIPCION_RA, BLOOM_NIVEL, ADD_USER, ADD_FECHA)
                        VALUES (
                            {$cfg['academico']['schema']}.SEQ_SYLLABUS_UA_RA.NEXTVAL,
                            {$intSyllabusUA},
                            '{$strDescripcionEscaped}',
                            {$intNivelBloom},
                            {$intAddUser},
                            SYSDATE
                        )";
            
        //    error_log("Query: " . $strQuery);
            db_query($strQuery);
        //    error_log("Query ejecutado OK");
        } else {
        //    error_log("NO SE INSERTA: intNivelBloom=$intNivelBloom, strDescripcion vacio=" . (empty($strDescripcion) ? 'SI' : 'NO'));
        }
    }
}
        
        // 3. ACTUALIZAR RA existentes
            foreach($_POST as $key => $value) {
                $arrExplode = explode("_", $key);
                
                if($arrExplode[0] == "hidUpdateRA") {
                    $intIndex = $arrExplode[1];
                    $intRAId = intval($value);
                    
                    $strDelete = isset($_POST["hidDeleteRA_{$intIndex}"]) ? $_POST["hidDeleteRA_{$intIndex}"] : "N";
                    $strEdited = isset($_POST["hidEditedRA_{$intIndex}"]) ? $_POST["hidEditedRA_{$intIndex}"] : "N";
                    
                    // Solo actualizar si fue editado y no est� marcado para eliminar
                    if($strDelete != "Y" && $strEdited == "Y" && $intRAId > 0) {
                        $intNivelBloom = isset($_POST["sltNivelBloom_{$intIndex}"]) ? intval($_POST["sltNivelBloom_{$intIndex}"]) : 0;
                        $strDescripcion = isset($_POST["txtDescripcionRA_{$intIndex}"]) ? trim($_POST["txtDescripcionRA_{$intIndex}"]) : "";
                        
                        if($intNivelBloom > 0 && !empty($strDescripcion)) {
                            $strDescripcionEscaped = str_replace("'", "''", $strDescripcion);
                            
                            $strQuery = "UPDATE {$cfg['academico']['schema']}.SYLLABUS_UA_RA 
                                        SET DESCRIPCION_RA = '{$strDescripcionEscaped}',
                                            BLOOM_NIVEL = {$intNivelBloom},
                                            MOD_USER = {$intAddUser},
                                            MOD_FECHA = SYSDATE
                                        WHERE SYLLABUS_UA_RA = {$intRAId}";
                            
                            db_query($strQuery);
                        }
                    }
                }
            }

             // PROCESAMIENTO DE BIBLIOGRAF�A
        // ============================================
        
        // 1. ELIMINAR Bibliograf�as marcadas para eliminar
        foreach($_POST as $key => $value) {
            $arrExplode = explode("_", $key);
            
            if($arrExplode[0] == "hidDeleteBiblio" && $value == "Y") {
                $intBiblioId = isset($_POST["hidUpdateBiblio_{$arrExplode[1]}"]) ? intval($_POST["hidUpdateBiblio_{$arrExplode[1]}"]) : 0;
                
                if($intBiblioId > 0) {
                    $strQuery = "DELETE FROM {$cfg['academico']['schema']}.SYLLABUS_UA_BIBLIO 
                                 WHERE SYLLABUS_UA_BIBLIO = {$intBiblioId}";
                    db_query($strQuery);
                }
            }
        }
        
        // 2. INSERTAR nuevas Bibliograf�as
        foreach($_POST as $key => $value) {
            $arrExplode = explode("_", $key);
            
            if($arrExplode[0] == "hidNewBiblio" && $value == "1") {
                $intIndex = $arrExplode[1];
                
                $strReferencia = normalizarReferenciaBiblioPost(
                    isset($_POST["txtReferenciaBiblio_{$intIndex}"]) ? $_POST["txtReferenciaBiblio_{$intIndex}"] : ''
                );
                
                if($strReferencia !== null) {
                    $strQuery = "INSERT INTO {$cfg['academico']['schema']}.SYLLABUS_UA_BIBLIO 
                                (SYLLABUS_UA_BIBLIO, SYLLABUS_UA, REFERENCIA_COMPLETA, ADD_USER, ADD_FECHA)
                                VALUES (
                                    SEQ_SYLLABUS_UA_BIBLIO.NEXTVAL,
                                    {$intSyllabusUA},
                                    ' ',
                                    {$intAddUser},
                                    SYSDATE
                                )";
                    
                    db_query($strQuery);

                    $intNewBiblioId = obtenerCurrvalSeqBiblio();
                    if ($intNewBiblioId > 0) {
                        guardarReferenciaBiblio($intNewBiblioId, $strReferencia);
                    }
                }
            }
        }
        
        // 3. ACTUALIZAR Bibliograf�as existentes
        foreach($_POST as $key => $value) {
            $arrExplode = explode("_", $key);
            
            if($arrExplode[0] == "hidUpdateBiblio") {
                $intIndex = $arrExplode[1];
                $intBiblioId = intval($value);
                
                $strDelete = isset($_POST["hidDeleteBiblio_{$intIndex}"]) ? $_POST["hidDeleteBiblio_{$intIndex}"] : "N";
                $strEdited = isset($_POST["hidEditedBiblio_{$intIndex}"]) ? $_POST["hidEditedBiblio_{$intIndex}"] : "N";
                
                if($strDelete != "Y" && $strEdited == "Y" && $intBiblioId > 0) {
                    $strReferencia = normalizarReferenciaBiblioPost(
                        isset($_POST["txtReferenciaBiblio_{$intIndex}"]) ? $_POST["txtReferenciaBiblio_{$intIndex}"] : ''
                    );
                    
                    if($strReferencia !== null) {
                        guardarReferenciaBiblio($intBiblioId, $strReferencia);

                        $strQuery = "UPDATE {$cfg['academico']['schema']}.SYLLABUS_UA_BIBLIO 
                                    SET MOD_USER = {$intAddUser},
                                        MOD_FECHA = SYSDATE
                                    WHERE SYLLABUS_UA_BIBLIO = {$intBiblioId}";
                        
                        db_query($strQuery);
                    }
                }
            }
        }
    }
}





        if( $intCursoId > 0 && $intAddUser > 0){
            $strQuery = "UPDATE {$cfg["academico"]["schema"]}.CURSO
                         SET    DESCRIPCION = NULL,
                                BIBLIOGRAFIA = NULL,
                                OBJETIVOS = NULL,
                                METODO_ENSENANZA = NULL,
                                ESQUEMA_CALIFICACION = NULL,
                                ESQUEMA_CURSO = NULL,
                                IMPARTIDO_INSTITUCION = NULL,
                                MOD_USER = {$intAddUser},
                                MOD_FECHA = NOW()
                         WHERE  CURSO = '{$intCursoId}'";
            db_query($strQuery);
        }

        if( $strTabDetalleCurso == 'Y' && $intCursoId > 0 && !empty($strCodigo) && !empty($strNombre) && $intAddUser > 0){
            $strQuery = "UPDATE {$cfg["academico"]["schema"]}.CURSO
                         SET    NOMBRE = '{$strNombre}',
                                NOMBRE_ING = '{$strNombreIngles}',
                                CODIGO = '{$strCodigo}',
                                UMAS = {$sinNumeroUmas},
                                ECTS = {$sinNumeroEcts},
                                AREA = {$intNumeroArea},
                                TIPO_CURSO = {$intTipoCurso},
                                SUBORDINADO_DE = {$intCursoPadre},
                                CONVENIO = '{$strDetalleConvenio}',
                                NOMBRE_CONVENIO = '{$strNombreConvenio}',
                                FILOSOFIA_INSTITUCION = '{$strFilosofiaIns}',
                                MOD_USER = {$intAddUser},
                                MOD_FECHA = NOW(),
                                ACTIVO = '{$strActivo}',
                                LOGOS = {$sinNumeroLogos},
                                MONTO_MAXIMO = '{$sinMontoMaximo}'
                         WHERE  CURSO = {$intCursoId}";
            db_query($strQuery);
        }

        if( $strTabCursosRelacionados == 'Y' && $intCursoId > 0 && $intAddUser > 0 ){
            $strQuery = "UPDATE CURSO
                         SET    SUBORDINADO_DE = {$intCursoPadre},
                                MOD_USER = {$intAddUser},
                                MOD_FECHA = NOW()
                         WHERE  CURSO = {$intCursoId}";
            db_query($strQuery);
        }


    }

    if( $intCurso > 0 ){
        $strQuery = "SELECT C.CURSO ID, C.CODIGO, C.UMAS, C.ECTS, C.NOMBRE CURSO, A.AREA, C.NOMBRE_ING, C.MONTO_MAXIMO,
                            C.SUBORDINADO_DE, TIPOC.NOMBRE TIPO_CURSO, TIPOC.EN_PENSUM, C.ACTIVO, C.LOGOS, A.ISLOGO
                     FROM   {$cfg["academico"]["schema"]}.CURSO C,
                            {$cfg["academico"]["schema"]}.AREA A,
                            {$cfg["academico"]["schema"]}.TIPO_CURSO TIPOC
                     WHERE  C.CURSO = {$intCurso}
                     AND    C.AREA = A.AREA
                     AND    C.TIPO_CURSO = TIPOC.TIPO_CURSO";
        //debugQuery($strQuery);
        $arrInfo = sqlGetValueFromKey($strQuery);
    }


    if($arrInfo === false) {
        alert_after_update("error","No hay informaci�n del curso.");
        die();
    }
    $strCodigoCurso = $arrInfo["CODIGO"];
    $strNombreCurso = $arrInfo["CURSO"];
    $intCursoId = intval($arrInfo["ID"]);
    //"id" del curso padre del curso actual
    $intCursoPadre = empty($arrInfo["SUBORDINADO_DE"]) ? 0 : $arrInfo["SUBORDINADO_DE"];
    //drawDebug($arrInfo);
    //drawSelectorCurso($strAction, $intFacultad, "");print "Good for you.";

    theme_draw_layer_open();

        theme_draw_banner_open();

            ?>
            <table align="center" width="100%" cellpadding="0" cellspacing="0"  id="tableDetalleCurso" class="bannerTable">
                <tr>
                    <td width="25%" colspan="4" align="left" class="bannerH1">
                        <?php print $arrInfo["CODIGO"]." - ".$arrInfo["CURSO"]; ?>
                    </td>
                    <!--td width="10%" align="right">&nbsp;</td>
                    <td width="10%" align="right" class="bannerLabel"><?php print $lang["ACADEMICO_CURSOS_PENSUM_DETALLE_NOMBRE"]; ?></td>
                    <td align="left" class="bannerField1">

                        <span id="spanCurso"><?php print $arrInfo["CURSO"]; ?></span>
                    </td-->
                </tr>
                <tr>
                    <td align="left" class="bannerH3" width="50%">
                        <?php print $arrInfo["UMAS"]." ".$lang["ACADEMICO_CURSOS_PENSUM_VISTA_UMAS"]." - "; ?>
                        <?php print $arrInfo["ECTS"]." ".$lang["ACADEMICO_CURSOS_PENSUM_VISTA_ECTS"]; ?>
                    </td>
                    <td align="right" class="bannerH3" width="50%"><?php print  ($arrInfo["EN_PENSUM"] == "Y") ? $lang["ACADEMICO_CURSOS_FACULTAD_CURSO_EN_PENSUM"] : ""; ?>  -  <?php print $arrInfo["TIPO_CURSO"]; ?></td>
                </tr>
                <tr>
                    <td>
                        <?php $boolAutorizacionCurso = drawTablaBannerAutorizacion($intCursoId);?>
                    </td>
                </tr>
            </table>
            <?php

        theme_draw_banner_close();

        draw_toolbar_open(true);

            if( isset($arrTiposAcceso[4]) || isset($arrTiposAcceso[5]) ) {

                ?>
                <table width="100%" cellpadding="3" cellspacing="0" align="center" border="0">

                    <?php
                    if( isset($arrTiposAcceso[4]) ) {

                        ?>
                        <tr>
                            <td align="center" id="TdButtonEdit">
                                <?php
                                DrawJQueryButton("butEditDetalleCurso","Editar","edit");
                                ?>
                                <script language="javascript" type="text/javascript">
                                    OnclickJQueryButton("butEditDetalleCurso", "fntEditarCursoAutorizacion();");
                                </script>
                            </td>
                        </tr>
                        <tr>
                            <td align="center">
                                <?php
                                DrawJQueryButton("buttonSubmit", $lang["SAVE"], "save");
                                ?>
                                <script language="javascript" type="text/javascript">
                                    OnclickJQueryButton("buttonSubmit", "fntCheckAgregarCurso();");
                                    DisableJQueryButton("buttonSubmit", true);
                                </script>
                            </td>
                        </tr>
                        <tr>
                            <td align="center">
                                <?php
                                DrawJQueryButton("buttonCancel", $lang["CANCEL"], "cancel");
                                ?>
                                <script language="javascript" type="text/javascript">
                                    OnclickJQueryButton("buttonCancel", "location.href='<?php print $strAction."?curso={$intCurso}&facultad={$intFacultad}"; ?>'", 125);
                                    DisableJQueryButton("buttonCancel", true);
                                </script>
                            </td>
                        </tr>
                        <?php
                    }

                    /*
                    if( isset($arrTiposAcceso[5]) ) {
                        ?>
                        <tr>
                            <td align="center">
                                <?php
                                drawBotonAutorizacion();
                                ?>
                            </td>
                        </tr>
                        <?php
                    }
                    */
                    ?>
                    <tr>
                        <td align="center" id="tdBotonValidar"  style="display: none;">
                            <?php DrawJQueryButton("buttonValidar", "Validar", "save"); ?>
                            <script type="text/javascript" language="JavaScript">
                                OnclickJQueryButton("buttonValidar","fntBlurValidarPreRequisitoCurso()");

                                function fntBlurValidarPreRequisitoCurso(){

                                    $.ajax({
                                        url:"<?php print $strAction?>",
                                        data:{
                                            drawBlurValidar:true
                                        },
                                        type:'POST',
                                        dataType:'html',
                                        beforeSend: function() {
                                            intTop = ( $(window).height() * 1 ) / 2;
                                            $("#divShowLoadingGeneralSmall").css("top", intTop);
                                            $("#divShowLoadingGeneralSmall").css("left", 0);
                                            $("#divShowLoadingGeneralSmall").show();
                                        },
                                        success: function(data){
                                            $("#divBlurValidarPreRequisitos").html(data);
                                            $("#divBlurValidarPreRequisitos").dialog('open');
                                            $("#divShowLoadingGeneralSmall").hide();
                                        }
                                    });

                                    $("#divBlurValidarPreRequisitos").dialog({
                                        autoOpen: false,
                                        show: "explode",
                                        hide: "explode",
                                        modal: "true",
                                        resizable: false,
                                        width: 600,
                                        buttons: {
                                            "Cerrar": function(){
                                                $( this ).dialog( "close" );
                                            }
                                        }
                                    });


                                }
                            </script>
                        </td>
                    </tr>

                                        <tr>
                        <td align="center" id="tdBotonVerVersiones" style="display: none;">
                            <?php DrawJQueryButton("buttonVerVersiones", "Ver versiones", "find"); ?>
                            <script type="text/javascript" language="JavaScript">
                                OnclickJQueryButton("buttonVerVersiones", "fntMostrarVersionesSyllabus()");
                                
                                function fntMostrarVersionesSyllabus() {
                                    $.ajax({
                                        url: "<?php print $strAction?>",
                                        data: {
                                            drawBlurVersionesSyllabus: true,
                                            curso: <?php print $intCurso; ?>
                                        },
                                        type: 'POST',
                                        dataType: 'html',
                                        beforeSend: function() {
                                            intTop = ( $(window).height() * 1 ) / 2;
                                            $("#divShowLoadingGeneralSmall").css("top", intTop);
                                            $("#divShowLoadingGeneralSmall").css("left", 0);
                                            $("#divShowLoadingGeneralSmall").show();
                                        },
                                        success: function(data) {
                                            $("#divBlurVersionesSyllabus").html(data);
                                            $("#divBlurVersionesSyllabus").dialog("open");
                                            $("#divShowLoadingGeneralSmall").hide();
                                        },
                                        error: function() {
                                            $("#divShowLoadingGeneralSmall").hide();
                                           // alert("Error al cargar las versiones");
                                        }
                                    });
                                    
                                    $("#divBlurVersionesSyllabus").dialog({
                                        autoOpen: false,
                                        show: "explode",
                                        hide: "explode",
                                        modal: "true",
                                        resizable: false,
                                        width: 900,
                                        height: 500,
                                        buttons: {
                                            "Cerrar": function() {
                                                $(this).dialog("close");
                                            }
                                        }
                                    });
                                }



                                    function fntMostrarBitacoraCampo(intSyllabusUA, strCampo) {
                                        $.ajax({
                                            url: "<?php print $strAction; ?>",
                                            data: {
                                                drawBlurBitacoraCampo: true,
                                                syllabusUA: intSyllabusUA,
                                                campo: strCampo
                                            },
                                            type: 'POST',
                                            dataType: 'html',
                                            beforeSend: function() {
                                                $("#divShowLoadingGeneralBig").show();
                                            },
                                            success: function(data) {
                                                $("#divBlurBitacoraCampo").html(data);
                                                $("#divBlurBitacoraCampo").dialog({
                                                    modal: true,
                                                    width: 900,
                                                    height: 600,
                                                    resizable: true,
                                                          open: function() {
                                                        $(this).css({
                                                            'font-family': 'Verdana,Geneva,Arial,Helvetica,sans-serif',
                                                            'font-size': '11px',
                                                            'line-height': '1.6',
                                                            'padding': '15px'
                                                        });
                                                        $(this).find('*').not('table, table *').css({
                                                            'font-family': 'Verdana,Geneva,Arial,Helvetica,sans-serif',
                                                            'font-size': '11px'
                                                        });

                                                    },
                                                    buttons: {
                                                        "Cerrar": function() {
                                                            $(this).dialog("close");
                                                        }
                                                    }
                                                });
                                                $("#divShowLoadingGeneralBig").hide();
                                            },
                                            error: function() {
                                                $("#divShowLoadingGeneralBig").hide();
                                              //  alert("Error al cargar la bit�cora");
                                            }
                                        });
                                    }

                                    function fntVerDetalleLogCampo(intLogID, strCampo, strNombreCampo) {
                                            $.ajax({
                                                url: "<?php print $strAction; ?>",
                                                data: {
                                                    drawBlurDetalleLogCampo: true,
                                                    logID: intLogID,
                                                    campo: strCampo,
                                                    nombreCampo: strNombreCampo
                                                },
                                                type: 'POST',
                                                dataType: 'html',
                                                beforeSend: function() {
                                                    $("#divShowLoadingGeneralSmall").show();
                                                },
                                                success: function(data) {
                                                    $("#divBlurDetalleLogCampo").html(data);
                                                    $("#divBlurDetalleLogCampo").dialog({
                                                        modal: true,
                                                        width: 800,
                                                        height: 600,
                                                        resizable: true,
                                                                      open: function() {
                                                        $(this).css({
                                                            'font-family': 'Verdana,Geneva,Arial,Helvetica,sans-serif',
                                                            'font-size': '11px',
                                                            'line-height': '1.6',
                                                            'padding': '15px'
                                                        });
                                                        $(this).find('*').not('table, table *').css({
                                                            'font-family': 'Verdana,Geneva,Arial,Helvetica,sans-serif',
                                                            'font-size': '11px'
                                                        });

                                                    },
                                                        buttons: {
                                                            "Cerrar": function() {
                                                                $(this).dialog("close");
                                                            }
                                                        }
                                                    });
                                                    $("#divShowLoadingGeneralSmall").hide();
                                                },
                                                error: function() {
                                                    $("#divShowLoadingGeneralSmall").hide();
                                                  //  alert("Error al cargar el detalle");
                                                }
                                            });

                                            
                                        }

                                        function fntVerValorActualCampo(intSyllabusUA, strCampo, strNombreCampo) {
    $.ajax({
        url: "<?php print $strAction; ?>",
        data: {
            drawBlurValorActualCampo: true,
            syllabusUA:   intSyllabusUA,
            campo:        strCampo,
            nombreCampo:  strNombreCampo
        },
        type: 'POST',
        dataType: 'html',
        beforeSend: function() {
            $("#divShowLoadingGeneralSmall").show();
        },
        success: function(data) {
            $("#divBlurValorActualCampo").html(data);
            $("#divBlurValorActualCampo").dialog({
                modal: true,
                width: 800,
                height: 600,
                resizable: true,
                open: function() {
                    $(this).css({
                        'font-family': 'Verdana,Geneva,Arial,Helvetica,sans-serif',
                        'font-size': '11px',
                        'line-height': '1.6',
                        'padding': '15px'
                    });
                    $(this).find('*').not('table, table *').css({
                        'font-family': 'Verdana,Geneva,Arial,Helvetica,sans-serif',
                        'font-size': '11px'
                    });
                },
                buttons: {
                    "Cerrar": function() {
                        $(this).dialog("close");
                    }
                }
            });
            $("#divShowLoadingGeneralSmall").hide();
        },
        error: function() {
            $("#divShowLoadingGeneralSmall").hide();
        }
    });
}


                                        // Funci�n para mostrar bit�cora individual de un RA
function fntMostrarBitacoraRA(intSyllabusUARA) {
    $.ajax({
        url: "<?php print $strAction; ?>",
        data: {
            drawBlurBitacoraRA: true,
            syllabus_ua_ra: intSyllabusUARA
        },
        type: 'POST',
        dataType: 'html',
        beforeSend: function() {
            $("#divShowLoadingGeneralBig").show();
        },
        success: function(data) {
            $("#divBlurBitacoraRA").html(data);
            $("#divBlurBitacoraRA").dialog({
                modal: true,
                width: 900,
                height: 600,
                resizable: true,
                open: function() {
                    $(this).css({
                        'font-family': 'Verdana,Geneva,Arial,Helvetica,sans-serif',
                        'font-size': '11px',
                        'line-height': '1.6',
                        'padding': '15px'
                    });
                    $(this).find('*').not('table, table *').css({
                        'font-family': 'Verdana,Geneva,Arial,Helvetica,sans-serif',
                        'font-size': '11px'
                    });
                },
                buttons: {
                    "Cerrar": function() {
                        $(this).dialog("close");
                    }
                }
            });
            $("#divShowLoadingGeneralBig").hide();
        },
        error: function() {
            $("#divShowLoadingGeneralBig").hide();
        }
    });
}

// Funci�n para mostrar bit�cora de RA eliminado
function fntMostrarBitacoraRAEliminado(intSyllabusUARA) {
    $.ajax({
        url: "<?php print $strAction; ?>",
        data: {
            drawBlurBitacoraRAEliminado: true,
            syllabus_ua_ra: intSyllabusUARA
        },
        type: 'POST',
        dataType: 'html',
        beforeSend: function() {
            $("#divShowLoadingGeneralBig").show();
        },
        success: function(data) {
            $("#divBlurBitacoraRAEliminado").html(data);
            $("#divBlurBitacoraRAEliminado").dialog({
                modal: true,
                width: 900,
                height: 600,
                resizable: true,
                open: function() {
                    $(this).css({
                        'font-family': 'Verdana,Geneva,Arial,Helvetica,sans-serif',
                        'font-size': '11px',
                        'line-height': '1.6',
                        'padding': '15px'
                    });
                    $(this).find('*').not('table, table *').css({
                        'font-family': 'Verdana,Geneva,Arial,Helvetica,sans-serif',
                        'font-size': '11px'
                    });
                },
                buttons: {
                    "Cerrar": function() {
                        $(this).dialog("close");
                    }
                }
            });
            $("#divShowLoadingGeneralBig").hide();
        },
        error: function() {
            $("#divShowLoadingGeneralBig").hide();
        }
    });
}

// Funci�n para mostrar bit�cora general de todos los RA
function fntMostrarBitacoraTodosRA(intSyllabusUA) {
    $.ajax({
        url: "<?php print $strAction; ?>",
        data: {
            drawBlurBitacoraTodosRA: true,
            syllabus_ua: intSyllabusUA
        },
        type: 'POST',
        dataType: 'html',
        beforeSend: function() {
            $("#divShowLoadingGeneralBig").show();
        },
        success: function(data) {
            $("#divBlurBitacoraTodosRA").html(data);
            $("#divBlurBitacoraTodosRA").dialog({
                modal: true,
                width: 1000,
                height: 600,
                resizable: true,
                open: function() {
                    $(this).css({
                        'font-family': 'Verdana,Geneva,Arial,Helvetica,sans-serif',
                        'font-size': '11px',
                        'line-height': '1.6',
                        'padding': '15px'
                    });
                    $(this).find('*').not('table, table *').css({
                        'font-family': 'Verdana,Geneva,Arial,Helvetica,sans-serif',
                        'font-size': '11px'
                    });
                },
                buttons: {
                    "Cerrar": function() {
                        $(this).dialog("close");
                    }
                }
            });
            $("#divShowLoadingGeneralBig").hide();
        },
        error: function() {
            $("#divShowLoadingGeneralBig").hide();
        }
    });
}


// Funci�n para mostrar bit�cora individual de una Bibliograf�a
function fntMostrarBitacoraBiblio(intSyllabusUABiblio) {
    $.ajax({
        url: "<?php print $strAction; ?>",
        data: {
            drawBlurBitacoraBiblio: true,
            syllabus_ua_biblio: intSyllabusUABiblio
        },
        type: 'POST',
        dataType: 'html',
        beforeSend: function() {
            $("#divShowLoadingGeneralBig").show();
        },
        success: function(data) {
            $("#divBlurBitacoraBiblio").html(data);
            $("#divBlurBitacoraBiblio").dialog({
                modal: true,
                width: 900,
                height: 600,
                resizable: true,
                open: function() {
                    $(this).css({
                        'font-family': 'Verdana,Geneva,Arial,Helvetica,sans-serif',
                        'font-size': '11px',
                        'line-height': '1.6',
                        'padding': '15px'
                    });
                    $(this).find('*').not('table, table *').css({
                        'font-family': 'Verdana,Geneva,Arial,Helvetica,sans-serif',
                        'font-size': '11px'
                    });
                },
                buttons: {
                    "Cerrar": function() {
                        $(this).dialog("close");
                    }
                }
            });
            $("#divShowLoadingGeneralBig").hide();
        },
        error: function() {
            $("#divShowLoadingGeneralBig").hide();
        }
    });
}

// Funci�n para mostrar bit�cora de Bibliograf�a eliminada
function fntMostrarBitacoraBiblioEliminado(intSyllabusUABiblio) {
    $.ajax({
        url: "<?php print $strAction; ?>",
        data: {
            drawBlurBitacoraBiblioEliminado: true,
            syllabus_ua_biblio: intSyllabusUABiblio
        },
        type: 'POST',
        dataType: 'html',
        beforeSend: function() {
            $("#divShowLoadingGeneralBig").show();
        },
        success: function(data) {
            $("#divBlurBitacoraBiblioEliminado").html(data);
            $("#divBlurBitacoraBiblioEliminado").dialog({
                modal: true,
                width: 900,
                height: 600,
                resizable: true,
                open: function() {
                    $(this).css({
                        'font-family': 'Verdana,Geneva,Arial,Helvetica,sans-serif',
                        'font-size': '11px',
                        'line-height': '1.6',
                        'padding': '15px'
                    });
                    $(this).find('*').not('table, table *').css({
                        'font-family': 'Verdana,Geneva,Arial,Helvetica,sans-serif',
                        'font-size': '11px'
                    });
                },
                buttons: {
                    "Cerrar": function() {
                        $(this).dialog("close");
                    }
                }
            });
            $("#divShowLoadingGeneralBig").hide();
        },
        error: function() {
            $("#divShowLoadingGeneralBig").hide();
        }
    });
}

// Funci�n para mostrar bit�cora general de todas las Bibliograf�as
function fntMostrarBitacoraTodosBiblio(intSyllabusUA) {
    $.ajax({
        url: "<?php print $strAction; ?>",
        data: {
            drawBlurBitacoraTodosBiblio: true,
            syllabus_ua: intSyllabusUA
        },
        type: 'POST',
        dataType: 'html',
        beforeSend: function() {
            $("#divShowLoadingGeneralBig").show();
        },
        success: function(data) {
            $("#divBlurBitacoraTodosBiblio").html(data);
            $("#divBlurBitacoraTodosBiblio").dialog({
                modal: true,
                width: 1000,
                height: 600,
                resizable: true,
                open: function() {
                    $(this).css({
                        'font-family': 'Verdana,Geneva,Arial,Helvetica,sans-serif',
                        'font-size': '11px',
                        'line-height': '1.6',
                        'padding': '15px'
                    });
                    $(this).find('*').not('table, table *').css({
                        'font-family': 'Verdana,Geneva,Arial,Helvetica,sans-serif',
                        'font-size': '11px'
                    });
                },
                buttons: {
                    "Cerrar": function() {
                        $(this).dialog("close");
                    }
                }
            });
            $("#divShowLoadingGeneralBig").hide();
        },
        error: function() {
            $("#divShowLoadingGeneralBig").hide();
        }
    });
}

                            </script>
                        </td>
                    </tr>

                    <tr>
                        <td>&nbsp;</td>
                    </tr>
                    <tr>
                        <td align="center"><div class="row0" align="left" style=" width: 80%;"><a href="adm_academico_autorizador_equivalencias.php" style="cursor: pointer;">Equivalencias</a></div></td>
                    </tr>
                </table>
                <script type="text/javascript" language="JavaScript">
                    boolAutorizacionCurso = ('<?php print ($boolAutorizacionCurso == true)?"Y":"N";?>' == "Y")?true:false;
                    $(document).ready(function() {
                        <?php
                        if($boolAutorizacionCurso == true){
                            if(isset($arrModificarAutorizado[1])){
                                ?>
                                //console.log("bo");
                                DisableJQueryButton("butEditDetalleCurso", false);
                                <?php
                            }else{
                                ?>
                                DisableJQueryButton("butEditDetalleCurso", true);
                                <?php
                        }
                    ?>
                        //DisableJQueryButton("editButton", true);
                        //pendiente permiso
                            DisableJQueryButton("buttonSolicitarAutorizacion", true);
                       <?php
                    }?>
                });
                </script>
                <?php
            }
        draw_toolbar_close();

        $objPreRequisito->callScriptModificar();
        $arrAccesoModificarPreRequisito = check_persona_access(915,true);
        $arrAccesoModificarCursosEqui = check_persona_access(968,true);
        theme_draw_content_open(true,true);

            javascript_addDynamic_row();
            jquery_includeLibrary("bubblepopup");
            ?>
            <script type="text/javascript">

                var boolEditar = false;
                var strClass = "row1";
                intFacultad = '<?php print isset($_GET['facultad'])?$_GET['facultad']:0;?>';

                var arrCursoPensum = new Array();

                function fntEditTabCursoEQ(){
                    <?php
                    if( isset($arrAccesoModificarCursosEqui[4]) ){
                        ?>
                        /*curso equivalente*/
                        $("#rowCursoEquivalenteI").show();
                        $("input[id*='txtCursoEquivalenteI']").show();
                        $("img[id*='imgCursoEquivalenteI']").show();
                        $("div[id*='divCursoEquivalenteI']").hide();
                        $("input[id*='txtCodigoCursoEquivalenteI_']").show();
                        $("select[name*='sltCursoEquivalenteSobresee_']").show();
                        $("div[id*='divCursoEquivalenteSobresee_']").hide();
                        $("#NotaCursoEquivalente").hide();

                        $("input[id*='txtCodigoCursoEquivalenteII']").show();
                        $("input[id*='txtCursoEquivalenteII']").show();
                        $("div[id*='divCodigoCursoEquivalenteII']").hide();
                        $("div[id*='divCursoEquivalenteII']").hide();
                        $("select[name*='sltSobresee']").show();
                        $("div[id*='divSobresee']").hide();
                        $("div[id*='divAnd']").show();
                        $("div[id*='divOr']").show();
                        $("img[id*='imgDeleteEquivalentesII']").show();

                        //Desabilita los input cuando el curso cuando esta autorizado para evitar la edicion
                        $("img[id*='imgAutorizadoI_']").each(function(){
                            var arrSplit = $(this).attr("id").split("_");
                            $("input[id='txtCodigoCursoEquivalenteI_"+ arrSplit[1] +"']").hide();
                            $("input[id='txtCursoEquivalenteI_"+ arrSplit[1] +"']").hide();
                            $("select[name='sltCursoEquivalenteSobresee_"+ arrSplit[1] +"']").hide();
                            $("div[id='divCursoEquivalenteI_"+ arrSplit[1] +"']").show();
                            $("div[id='divCursoEquivalenteSobresee_"+ arrSplit[1] +"']").show();
                        });

                        $("img[id*='imgAutorizadoII_']").each(function(){
                            var arrSplit = $(this).attr("id").split("_");
                            for(var i=0; i<=3; i++){
                                $("input[id='txtCodigoCursoEquivalenteII_"+ arrSplit[1] +"_"+ i +"']").hide();
                                $("input[id='txtCursoEquivalenteII_"+ arrSplit[1] +"_"+ i +"']").hide();
                                $("select[name='sltSobresee_"+ arrSplit[1] +"_"+ i +"']").hide();
                                $("div[id='divCodigoCursoEquivalenteII_"+ arrSplit[1] +"_"+ i +"']").show();
                                $("div[id='divCursoEquivalenteII_"+ arrSplit[1] +"_"+ i +"']").show();
                                $("div[id='divSobresee_"+ arrSplit[1] +"_"+ i +"']").show();
                            }
                        });
                        $("#imgAddEquivalentes").show();

                        //Curso padre
                        $("input[id='txtCursoPadre']").show();
                        $("input[id='txtCodigoPadre']").show();
                        $("div[id='SubordinadoDe']").hide();
                        $("#DivDeleteCursoPadre").show();

                        //Cursos dependientes
                        $("div[id*='divCursoDependiente']").hide();
                        $("input[id*='txtCursoDependiente']").show();
                        $("input[id*='txtCodigoCursoDependiente']").show();

                        $("#rowDependiente").show();
                        var myForm = document.frm_detalle;
                        for(var j = 0; j < myForm.elements.length; j++){

                            objElement = myForm.elements[j];
                            arrSplit = objElement.name.split("_");
                            //alert(arrSplit[0]);

                            if ( arrSplit[0] == "hidDependiente" ){
                                //var ImgDelete = getDocumentLayer("DeleteDepediente_" + arrSplit[1]);
                                //alert(ImgDelete);
                                //ImgDelete.style.display = "";
                                $("#DeleteDepediente_" + arrSplit[1]).show();
                            }

                            if ( arrSplit[0] == "hidUpdateEquivalente" ){
                                //var TdEquivalente2 = getDocumentLayer("TdEquivalente_" + arrSplit[1] + "_2");
                                //var TdEquivalentes2 = getDocumentLayer("TdEquivalentes_" + arrSplit[1] +"_2");
                                //var TdEquivalente3 = getDocumentLayer("TdEquivalente_" + arrSplit[1] +"_3");
                                //var TdEquivalentes3 = getDocumentLayer("TdEquivalentes_" + arrSplit[1] +"_3");
                                /*var Span1 = getDocumentLayer("rowEquivalentes_" + arrSplit[1] + "_1");
                                var Span2 = getDocumentLayer("rowEquivalentes_" + arrSplit[1] + "_2");
                                var Span3 = getDocumentLayer("rowEquivalentes_" + arrSplit[1] + "_3");*/

                                //var ImgDeleteEquivalentes = getDocumentLayer("DeleteEquivalentes_" + arrSplit[1]);

                                //TdEquivalente2.style.display = "";
                                $("#TdEquivalente_" + arrSplit[1] + "_2").show();
                                //TdEquivalentes2.style.display = "none";

                                //TdEquivalente3.style.display = "";
                                $("#TdEquivalente_" + arrSplit[1] +"_3").show();
                                /*Span1.style.cursor = "pointer";
                                Span2.style.cursor = "pointer";
                                Span3.style.cursor = "pointer";*/
                            }

                        }
                        <?php
                    }
                    ?>


                }
                //Esta funcion se utiliza para desbloquear los cuadros de texto, los select y los checkbox y poder realizar la edicion de los datos.
                function fntEditDetalle() {

                    var myForm = document.frm_detalle;
                    var objCodigo = getDocumentLayer('codigo');
                    var objUmas = getDocumentLayer('umas');
                    var objEcts = getDocumentLayer('ects');
                    var objNombre = getDocumentLayer('nombre');
                    var objNombreIngles = getDocumentLayer('nombreIngles');
                    var objArea = getDocumentLayer('area');
                    var objActivo = getDocumentLayer('activo');
                    var objFilosofiaIns = getDocumentLayer('chkFilosofiaIns');
                    var objDetalleConvenio = getDocumentLayer('chkDetalleConvenio');
                    var objNombreConvenio = getDocumentLayer('txtNombreConvenio');
                    var TdEditButton = getDocumentLayer("TdButtonEdit");
                    var objtxtMontoMaximoCurso = getDocumentLayer("txtMontoMaximoCurso");
                    var objDivMontoMaximoCurso = getDocumentLayer("DivMontoMaximoCurso");


                    $("#divTitleUmas").css("display","");
                    $("#divTitleEcts").css("display","");
                    //objCodigo.style.display = "";
                    $("#codigo").show();
                    if($("#hdnIsLogo").attr("value") == "N"){
                        //objUmas.style.display  = "";
                        $("#umas").show();
                        $("#spanUmas").css("display","none");
                        $("#ects").show();
                        $("#spanEcts").css("display","none");
                    }else{
                        //objUmas.style.display  = "none";
                        $("#umas").hide();
                        $("#spanUmas").css("display","");
                        $("#ects").hide();
                        $("#spanEcts").css("display","");
                    }
                    //objNombre.style.display = "";
                    $("#nombre").show();
                    //objArea.style.display = "";
                    $("#area").show();
                    //objArea.className = "field_selectbox inputSizeComplete";
                    $("#area").addClass("field_selectbox inputSizeComplete");
                    //objNombreIngles.style.display = "";
                    $("#nombreIngles").show();

                    //objActivo.style.display = "";
                    $("#activo").show();

                    //objFilosofiaIns.style.display = "";
                    $("#chkFilosofiaIns").show();

                    //objDetalleConvenio.style.display = "";
                    $("#chkDetalleConvenio").show();

                    //objNombreConvenio.style.display = "";
                    $("#txtNombreConvenio").show();


                    //objtxtMontoMaximoCurso.style.display = "";
                    $("#txtMontoMaximoCurso").show();



                    //getDocumentLayer('spanCodigo').style.display = "none";
                    $("#spanCodigo").hide();
                    //getDocumentLayer('spanCurso').style.display = "none";
                    $("#spanCurso").hide();


                    //getDocumentLayer('spanArea').style.display = "none";
                    $("#spanArea").hide();

                    //getDocumentLayer('spanNombreIngles').style.display = "none";
                    $("#spanNombreIngles").hide();

                    //getDocumentLayer('spanActivo').style.display = "none";
                    $("#spanActivo").hide();

                    //getDocumentLayer('spanFilosofiaIns').style.display = "none";
                    $("#spanFilosofiaIns").hide();

                    //getDocumentLayer('spanDetalleConvenio').style.display = "none";
                    $("#spanDetalleConvenio").hide();

                    //getDocumentLayer('spanNombreConvenio').style.display = "none";
                    $("#spanNombreConvenio").hide();

                    //objDivMontoMaximoCurso.style.display = "none";
                    $("#DivMontoMaximoCurso").hide();


                    //var objTipoCurso = getDocumentLayer('select_tipo_curso');






                    //getDocumentLayer('text_tipo_curso').style.display = "none";
                    $("#text_tipo_curso").hide();

                    //objTipoCurso.disabled = false;
                    $("#select_tipo_curso").attr("disabled",false);
                    //objTipoCurso.style.display = "";
                    $("#select_tipo_curso").show();
                    //objTipoCurso.className = "field_selectbox inputSizePx";
                    $("#select_tipo_curso").addClass("field_selectbox inputSizePx");

             

                    boolEditar = true;
                    //TdEditButton.style.display = "none";
                    $("#TdButtonEdit").hide();
                    DisableJQueryButton("buttonSubmit", false);
                    DisableJQueryButton("buttonCancel", false);
                    //Editar logos
                    $("#divLogos").hide();
                    $("#divTxtlogos").show();
                    fntEditTabCursoEQ();
                    fntEditForm();

                    $("#divButtonAgregarRA").show();

                    $("img[id*='imgEditRA_']").show();
                    $("img[id*='imgDeleteRA_']").show();

                    $("#divButtonAgregarBiblio").show();
$("img[id*='imgEditBiblio_']").show();
$("img[id*='imgDeleteBiblio_']").show();

                    $("#imgEditDescInst").show();
                    $("#imgEditAporte").show();
                    $("#imgEditConocimientos").show();
                    $("#imgEditMarco").show();





                }

                function fntEditarCursoAutorizacion(){
                    $("#divButtonAgregar").show();
                    fntEditDetalle();
                    if(boolAutorizacionCurso == false){
                        //fntEditDetalle();
                    }else{
                        $("#divBlurEditAutorizacion").dialog("open");
                    }
                }
                //Esta funcion quita los espacion en blanco de una cadena (al principio, entre palabras, al final)
                //str: la cadena que va a ser evaluada
                //@param: string str
                function TextTrim(str) {
                   var whitespace = new String(" \t\n\r");
                   var s = new String(str);

                   if (whitespace.indexOf(s.charAt(0)) != -1) {
                      var j=0, i = s.length;
                      while (j < i && whitespace.indexOf(s.charAt(j)) != -1) j++;
                      s = s.substring(j, i);
                   }
                   if (whitespace.indexOf(s.charAt(s.length-1)) != -1) {
                      var i = s.length - 1;
                      while (i >= 0 && whitespace.indexOf(s.charAt(i)) != -1) i--;
                      s = s.substring(0, i+1);
                   }
                   return s;
                }


function fntMsjErrorCheckCampos(haySugerencias){
    haySugerencias = haySugerencias || false; 
    
    if(haySugerencias) {
        addAlertOnFooter("error","Por favor revisar los campos en rojo o las sugerencias de IA.");
    } else {
        addAlertOnFooter("error","Por favor revisar los campos en rojo.");
    }
}



                function fntCheckAgregarCurso(){
                    var boolSubmit = true;
                    var boolRepeat = false;

                    $("input[name*='txtIdPensum_']").each(function(){
                    var arrSplit0 = $(this).attr("name").split("_");
                        if( arrSplit0[1] >0){
                            if( $(this).val() == ''){
                                $(this).addClass("field_textbox_error");
                                boolSubmit = false;
                            }
                            var arrSplit = $(this).attr("name").split("_");
                            var strCodigo = $.trim($(this).val());
                            $("input[name*='txtIdPensum_']").each(function(){
                                var arrSplit2 = $(this).attr("name").split("_");
                                if( arrSplit2[1] >0 && arrSplit[1] > 0){
                                    if( arrSplit2[1] != arrSplit[1] ){
                                        if( strCodigo == $.trim($(this).val()) ){
                                            $(this).addClass("field_textbox_error");
                                            $("input[name*='txtCodigoPensum_"+arrSplit[1]+"']").addClass("field_textbox_error");
                                            boolRepeat = true;
                                            boolSubmit = false;
                                        }else{
                                            $("input[name*='txtCodigoPensum_"+arrSplit[1]+"']").removeClass("field_textbox_error");
                                        }
                                    }
                                }
                            });
                        }
                    });

                    $("select[name*='sltTipoCurso']").each(function(){
                        var arrSplit = $(this).attr('name').split('_');
                        if( arrSplit[1] != 0 ){
                            if( $(this).val() == 0){
                                $(this).addClass("field_textbox_error");
                                boolSubmit = false;
                            }else{
                                $(this).removeClass("field_textbox_error");
                            }
                        }
                    });

                    $("select[name*='sltCiclo']").each(function(){
                        var arrSplit = $(this).attr('name').split('_');
                        if( arrSplit[1] != 0 ){
                            if( $(this).val() == 0){
                                $(this).addClass("field_textbox_error");
                                boolSubmit = false;
                            }else{
                                $(this).removeClass("field_textbox_error");
                            }
                        }
                    });


                    if( boolSubmit == false){
                        if(boolRepeat){
                            addAlertOnFooter("error","Por favor verificar pensum duplicado");
                        }
                    }

                    if(boolSubmit == true){
                        $("input[name*='txtCodigoPensum_']").each(function(){
                            var arrSplit0 = $(this).attr("name").split("_");
                                if(arrSplit0[1]>0){
                                    $(this).removeClass("field_textbox_error");
                                }
                        });
                        removeAlertOnFooter();
                        checkForm();
                    }
                }

                //Esta funcion verifica que los campos que es necesario llenar estan con la informacion necesaria para llevar a cabo
                //la insercion del detalle de curso en la base de datos.
                function checkForm() {
                    var boolError = true;
                    var boolErrorAgregarCurso = true;

                    <?php
                    if( check_persona_access(915,false,4) ){
                        ?>
                        boolError = fntCheckFormPreRequisito();
                        <?php
                    }
                    ?>


                    $("input[name='umas']").removeClass("field_textbox_error");
                    $("input[name='ects']").removeClass("field_textbox_error");
                    $("input[name='txtLogos']").removeClass("field_textbox_error");
                    //Revisa si el campo uma y logo no es null

                    if($("#hdnIsLogo").attr("value") == "N"){
                        if ( $("input[name='umas']").val() == ''){
                            boolError = false;
                            $("input[name='umas']").addClass("field_textbox_error");
                            $("input[name='ects']").addClass("field_textbox_error");
                        }
                    }

                    if($("#hdnIsLogo").attr("value") == "Y"){
                        if ( $("input[name='txtLogos']").val() == ''){
                            boolError = false;
                            $("input[name='txtLogos']").addClass("field_textbox_error");
                        }
                    }


                    //cursos equivalentes seccion I
                    var arrCursosEquivalentesI = new Object();
                    $("select[name*='sltCursoEquivalenteSobresee']").removeClass("field_textbox_error");
                    $("input[id*='txtCursoEquivalenteI_']").removeClass("field_textbox_error");

                    $("input[name*='hidUpdateCursoEquivalenteI_']").each( function() {
                        var arrSplit = $(this).attr("name").split("_");
                        var intCursoEquivalenteLocal = $(this).val();


                        if( arrCursosEquivalentesI[intCursoEquivalenteLocal] ) {
                            boolError = false;
                            $("input[id='txtCursoEquivalenteI_" + arrCursosEquivalentesI[intCursoEquivalenteLocal] +"']").addClass("field_textbox_error");
                            $("input[id='txtCursoEquivalenteI_" + arrSplit[1]+"']").addClass("field_textbox_error");
                        }
                        if( intCursoEquivalenteLocal.length > 0 && $("select[name='sltCursoEquivalenteSobresee_"+arrSplit[1]+"']").val() == "" ){
                            if( $("#hidNoCheckedCamposEquiI_"+arrSplit[1]).val() != "Y" ){
                                if( $("input[name='hidDeleteCursoEquivalenteI_"+arrSplit[1]+"']").val() != "Y" ){
                                    boolError = false;
                                }
                            }
                            $("select[name='sltCursoEquivalenteSobresee_"+arrSplit[1]+"']").addClass("field_textbox_error");
                        }

                        arrCursosEquivalentesI[intCursoEquivalenteLocal] = arrSplit[1];

                    });

                    $("input[name*='hidNewCursoEquivalenteI_']").each(function(){
                        var arrSplit = $(this).attr("name").split("_");
                        var intCursoEquivalenteI = $(this).val();
                        if( arrCursosEquivalentesI[intCursoEquivalenteI] ) {
                            boolError = false;
                            $("input[id='txtCursoEquivalenteI_" + arrCursosEquivalentesI[intCursoEquivalenteI] +"']").addClass("field_textbox_error");
                            $("input[id='txtCursoEquivalenteI_" + arrSplit[1]+"']").addClass("field_textbox_error");
                        }
                        if( intCursoEquivalenteI.length > 0 && $("select[name='sltCursoEquivalenteSobresee_"+arrSplit[1]+"']").val() == "" ){
                            boolError = false;
                            $("select[name='sltCursoEquivalenteSobresee_"+arrSplit[1]+"']").addClass("field_textbox_error");
                        }
                    });


                    //cursos equivalentes seccion II
                    var arrCursosEquivalentesII = new Object();
                    $("select[name*='sltSobresee']").removeClass("field_textbox_error");
                    $("input[id*='txtCursoEquivalenteII_']").removeClass("field_textbox_error");
                    $("input[name*='hidUpdateEquivalente_']").each( function() {
                        var arrSplit = $(this).attr("name").split("_");
                        var intCursoEquivalenteLocalII = $(this).val();
                        if( intCursoEquivalenteLocalII > 0){
                            if( arrCursosEquivalentesII[intCursoEquivalenteLocalII] ) {
                                boolError = false;
                                $("input[id='txtCursoEquivalenteII_" + arrCursosEquivalentesII[intCursoEquivalenteLocalII] +"']").addClass("field_textbox_error");
                                $("input[id='txtCursoEquivalenteII_" + arrSplit[1] + "_" + arrSplit[2] +"']").addClass("field_textbox_error");
                                $("select[name='sltSobresee_"+arrSplit[1]+"_"+arrSplit[2]+"']").addClass("field_textbox_error");
                            }
                            if($("select[name='sltSobresee_"+arrSplit[1]+"_"+arrSplit[2]+"']").val() == ""){
                                if( $("#hidNoCheckedCamposEquiII_"+arrSplit[1]).val() != "Y" ){
                                    if( $("input[name='hidDeleteCursoEquivalenteII_"+arrSplit[1]+"']").val() != "Y" ){
                                        boolError = false;
                                    }
                                }
                                $("select[name='sltSobresee_"+arrSplit[1]+"_"+arrSplit[2]+"']").addClass("field_textbox_error");
                            }
                            arrCursosEquivalentesII[intCursoEquivalenteLocalII] = arrSplit[1] + "_" + arrSplit[2];
                        }
                    });
                    $("input[name*='hidNewCursoEquivalenteII_']").each(function(){
                        var arrSplit = $(this).attr("name").split("_");
                        var intCursoEquivalenteII = $(this).val();
                        if( intCursoEquivalenteII > 0 ){
                           if( arrCursosEquivalentesII[intCursoEquivalenteII] ) {
                                boolError = false;
                                $("input[id='txtCursoEquivalenteII_" + arrCursosEquivalentesII[intCursoEquivalenteII] +"']").addClass("field_textbox_error");
                                $("input[id='txtCursoEquivalenteII_" + arrSplit[1] + "_" + arrSplit[2] +"']").addClass("field_textbox_error");
                           }
                           if($("select[name='sltSobresee_"+arrSplit[1]+"_"+arrSplit[2]+"']").val() == ""){
                                boolError = false;
                                $("select[name='sltSobresee_"+arrSplit[1]+"_"+arrSplit[2]+"']").addClass("field_textbox_error");
                           }
                           arrCursosEquivalentesII[intCursoEquivalenteII] = arrSplit[1] + "_" + arrSplit[2];
                        }
                    });

                    //Cursos dependientes
                    var arrCursosDependientes = new Object();
                    $("input[name*='hidDependiente_']").each(function(){
                        var arrSplit = $(this).attr("name").split("_");
                        var intCursoDependiente = $(this).val();
                        if(intCursoDependiente > 0){
                            if( arrCursosDependientes[intCursoDependiente] ) {
                                boolError = false;
                                $("input[id='txtCursoDependiente_" + arrCursosDependientes[intCursoDependiente] +"']").addClass("field_textbox_error");
                                $("input[id='txtCursoDependiente_" + arrSplit[1]+"']").addClass("field_textbox_error");
                            }
                        }
                        arrCursosDependientes[intCursoDependiente] = arrSplit[1];
                    });

                    //Agregar validacion

// ============================================
// VALIDACI�N 1: Campos de Descripci�n (Syllabus Rich Text)
// ============================================
                    var arrSyllabusFields = ['DescInst', 'Aporte', 'Conocimientos', 'Marco'];
                    
                    for(var i = 0; i < arrSyllabusFields.length; i++) {
                        var fieldName = arrSyllabusFields[i];
                        var hidEdited = $("#hidEdited" + fieldName);
                        
                        if(hidEdited.length > 0 && hidEdited.val() == "Y") {
                            var html = fntGetHtmlCampoSummernote(fieldName);
                            var content = textoPlanoDesdeHtml(html).replace(/\s+/g, '').trim();
                            var wrapEditor = document.getElementById('wrapEditor' + fieldName);
                            
                            if(content == "") {
                                if(wrapEditor) {
                                    wrapEditor.classList.add('field-error');
                                }
                                boolError = false;
                            } else if(wrapEditor) {
                                wrapEditor.classList.remove('field-error');
                            }
                        }
                    }

// ============================================
// VALIDACI�N 2: Resultados de Aprendizaje (RA)
// ============================================

$("select[name*='sltNivelBloom_']").removeClass("field_textbox_error");
$("textarea[name*='txtDescripcionRA_']").removeClass("field_textbox_error");

//var arrRAsPorValidar = [];

$("select[name*='sltNivelBloom_']").each(function(){
    var arrSplit = $(this).attr('name').split('_');
    var intIndex = arrSplit[1];
    
    if(intIndex > 0 && $("#hidDeleteRA_" + intIndex).val() != "Y") {
        if($(this).val() == "" || $(this).val() == null) {
            $(this).addClass("field_textbox_error");
            boolError = false;
        }
        
        var txtDescripcion = $("#txtDescripcionRA_" + intIndex);
        if(txtDescripcion.length > 0) {
            if($.trim(txtDescripcion.val()) == "") {
                txtDescripcion.addClass("field_textbox_error");
                boolError = false;
            } 
        }
    }

});


// ============================================
// VALIDACI�N 3: Bibliografia
// ============================================

$("[id^='wrapEditorBiblio_']").removeClass("field-error");

$("textarea[name*='txtReferenciaBiblio_']").each(function(){
    var arrSplit = $(this).attr('name').split('_');
    var intIndex = arrSplit[1];
    
    if(intIndex > 0 && $("#hidDeleteBiblio_" + intIndex).val() != "Y") {
        var referenciaPlano = textoPlanoDesdeHtml(fntGetHtmlBiblio(intIndex));
        if(referenciaPlano === "") {
            $("#wrapEditorBiblio_" + intIndex).addClass("field-error");
            boolError = false;
        }
    }
});

// ============================================
// VALIDACIONES LLM INDEPENDIENTES
// ============================================
var haySugerencias = false;

// Validar TODAS las secciones sin dependencias
var errorLLMCampos = !validarTodosCamposDescripcionConLLM();
var errorLLMRA = !validarTodosRAsConLLM();
var errorLLMBiblio = !validarTodasBibliografiasConLLM();

// ============================================
// VERIFICAR ESTADOS LLM - CAMPOS PRINCIPALES
// ============================================
var errorEstadosCampos = false;
var campos = ['DescInst', 'Aporte', 'Conocimientos', 'Marco'];

for(var i = 0; i < campos.length; i++) {
    var nombreCampo = campos[i];
    
    if($('#hidEdited' + nombreCampo).val() === 'Y') {
        var estadoLLM = $('#hidEstadoLLM' + nombreCampo).val();
        
        if(estadoLLM === 'puede_mejorarse') {
            haySugerencias = true;
        }
        
        if(estadoLLM !== 'correcto') {
            errorEstadosCampos = true;
        }
    }
}

// ============================================
// VERIFICAR ESTADOS LLM - RA
// ============================================
var errorEstadosRA = false;

$("select[name*='sltNivelBloom_']").each(function(){
    var arrSplit = $(this).attr('name').split('_');
    var intIndex = arrSplit[1];

    if(intIndex == 0) return true;
    
    var txtDescripcion = $("#txtDescripcionRA_" + intIndex);
    var estaVisible = txtDescripcion.css('display') != 'none';
    var esReadonly = txtDescripcion.attr('readonly') == 'readonly';
    var estaEnModoEdicion = txtDescripcion.length > 0 && estaVisible && !esReadonly;
    var estaEliminado = $("#hidDeleteRA_" + intIndex).val() == "Y";
    
    if(estaEnModoEdicion && !estaEliminado) {
        var estadoLLM = $("#hidEstadoLLM_" + intIndex).val();
        var descripcion = $.trim(txtDescripcion.val());

        if(estadoLLM === "puede_mejorarse") {
            haySugerencias = true;
        }
        
        if(estadoLLM !== "correcto") {
            errorEstadosRA = true;
            return false;
        }
    }
});

// ============================================
// VERIFICAR ESTADOS LLM - BIBLIOGRAF�A
// ============================================
var errorEstadosBiblio = false;

$("textarea[name*='txtReferenciaBiblio_']").each(function(){
    var arrSplit = $(this).attr('name').split('_');
    var intIndex = arrSplit[1];
    
    if(intIndex == 0) return true;
    
    var estaEnModoEdicion = fntBiblioEnModoEdicion(intIndex);
    var estaEliminado = $("#hidDeleteBiblio_" + intIndex).val() == "Y";
    
    if(estaEnModoEdicion && !estaEliminado) {
        var estadoLLM = $("#hidEstadoLLMBiblio_" + intIndex).val();
        
        if(estadoLLM === "puede_mejorarse") {
            haySugerencias = true;
        }
        
        if(estadoLLM !== "correcto") {
            errorEstadosBiblio = true;
            return false;
        }
    }
});

// ============================================
// COMBINAR TODOS LOS ERRORES
// ============================================
if(errorLLMCampos || errorLLMRA || errorLLMBiblio || 
   errorEstadosCampos || errorEstadosRA || errorEstadosBiblio) {
    boolError = false;
}
 

                    if(!boolError){
                        fntMsjErrorCheckCampos(haySugerencias);
                    }

                    fntSyncTodosCamposSummernoteToPost();
                    fntSyncTodasBibliografiasToPost();

                    var strError = "";
                    var objForm = getDocumentLayer('frm_detalle');
                    var objFormPensum = getDocumentLayer('frmPensum');
                    var selected = $( "#tabDetalleCurso" ).tabs( "option", "selected" );

                    objHid = document.createElement("input");
                    objHid.type = "hidden";
                    objHid.name = "selectedTab";
                    objHid.id = "selectedTab";
                    objHid.value = selected;
                    objForm.appendChild( objHid );

             if(boolError && boolErrorAgregarCurso && selected != 3){
    objForm.submit();
}

// Solo ejecutar esta validaci�n si NO estamos en pesta�a Syllabus (�ndice 3)
if(selected != 3) {

    for(var i = 0; i < objForm.elements.length; i++) {
        if( objForm.elements[i].type == "text" ) {
            objText = objForm.elements[i];
            
            // Excluir campos ocultos y templates
            var isVisible = $(objText).is(':visible');
            var isTemplate = objText.name.indexOf('_0') > 0;
            
            if(!isVisible || isTemplate) {
                continue;
            }
            
            if( objText.name == "umas" || objText.name == "ects"  ) {
                objText.value = objText.value * 1;
                if( isNaN(objText.value) ) {
                    objText.value = 0;
                    strError += "Debe ingresar un numero en el campo <b>" + objText.name +"</b><br>" ;
                }
            }
            else { 
                objText.value = TextTrim(objText.value);
                if( objText.value.length < 1 ) {
                    strError += "Debe llenar el campo <b>" + objText.name +"</b><br>" ;
                }
            }
        }
    }

    if( strError == "") {
        objForm.submit();
    }
    else{
        $("#showFrame" ).html(strError);
        $("#showFrame" ).dialog( "open" );
    }


} else {
    // En pesta�a Syllabus (3), levantar blur si de versiones si todos los datos estan completos
    //Se considera como completos es decir los campos de hiper texto, al menos 1 RA y al menos 1 bibliografia.
    if(boolError) {
                <?php if($boolSyllabusCompleto) { ?>
            // Ya existe syllabus: Mostrar modal de control de versiones
                //Levantar blur de control de versiones
                $("#divBlurVersionControl").dialog("open");

                    <?php } else { ?>
            // Primera vez: Guardar directamente sin modal
            objForm.submit();
        <?php } ?>

    }
}
                }







                //variable que guarda el valor de velocidad para el "showFrame"
                //$.fx.speeds._default = 100;
                //funcion que muestra el cuadro de dialogo
                $(function() {
                    $( "#showFrame" ).dialog({
                        autoOpen: false,
                        show: "explode",
                        hide: "explode",
                        modal: true,
                        resizable: false,
                        buttons: {
                            Aceptar: function() {
                                $(this).dialog("close");
                            }
                        }
                    });
                });

                // increase the default animation speed to exaggerate the effect
                //$.fx.speeds._default = 100;
                //tabs
                $(function() {
                    $("#tabDetalleCurso").tabs({
                        selected: <?php print isset($_POST["selectedTab"]) ? intval($_POST["selectedTab"]) : 0; ?>,
                        select: function(event, ui) {
                        if( ui.panel.id == 'tabCurso' ) {
                            $("#tdBotonValidar").show();
                            $("#tdBotonVerVersiones").hide();
                        } else if( ui.panel.id == 'tabSyllabusUA' ) {
                            $("#tdBotonValidar").hide();
                            // Verificar si hay m�s de 1 versi�n
                            $.ajax({
                                url: "<?php print $strAction; ?>",
                                data: {
                                    contarVersionesSyllabus: true,
                                    curso: <?php print $intCurso; ?>
                                },
                                type: 'POST',
                                dataType: 'json',
                                success: function(data) {
                                    if(data.total > 1) {
                                        $("#tdBotonVerVersiones").show();
                                    } else {
                                        $("#tdBotonVerVersiones").hide();
                                    }
                                }
                            });
                        } else {
                            $("#tdBotonValidar").hide();
                            $("#tdBotonVerVersiones").hide();
                        }
                    },

                    });

                    <?php
                    $intTabValidar = isset($_POST["selectedTab"]) ? intval($_POST["selectedTab"]) : 0;
            if($intTabValidar == 4){
    ?>
    $("#tdBotonValidar").show();
    <?php
} else if($intTabValidar == 3) { // Pesta�a Syllabus UA
    ?>
    // Verificar si hay m�s de 1 versi�n para mostrar bot�n
    $.ajax({
        url: "<?php print $strAction; ?>",
        data: {
            contarVersionesSyllabus: true,
            curso: <?php print $intCurso; ?>
        },
        type: 'POST',
        dataType: 'json',
        success: function(data) {
            if(data.total > 1) {
                $("#tdBotonVerVersiones").show();
            }
        }
    });
    <?php
}
?>

                });

                //Actualizar informaci�n del curso (historial, promedio, tipoNota)
                function updateInfoCurso(objSelectTipoCurso) {
                    var intTipoCurso = objSelectTipoCurso.value;
                    $.ajax({
                      url: '<?php print $strAction; ?>?tipocurso=' + intTipoCurso,
                      dataType:'html',
                      beforeSend: function() {
                          intTop = ( $(window).height() * 1 ) / 2;
                          $("#divShowLoadingGeneralBig").css("top", intTop);
                          $("#divShowLoadingGeneralBig").css("left", 0);
                          $("#divShowLoadingGeneralBig").css("z-index","1500");
                          $("#divShowLoadingGeneralBig").show();
                      },
                      success: function(data) {
                        $('.result').html(data);
                        $('#divInfoTipoCurso').html(data);
                        $("#divShowLoadingGeneralBig").hide();
                        return false;
                      }
                    });

                }

                function DrawInicio(){
                    var campos = ['DescInst', 'Aporte', 'Conocimientos', 'Marco'];
                    for(var i = 0; i < campos.length; i++) {
                        var fieldName = campos[i];
                        getDocumentLayer("span" + fieldName).style.display = "";
                        getDocumentLayer("wrapEditor" + fieldName).style.display = "none";
                    }
                }

                 addLoadListener(DrawInicio);



                function fntGetLogos(){
                    var intArea = $("select[id='area']").val();
                    $.ajax({
                        url:"<?php print $strAction; ?>",
                        data:{
                            getTipoLogo : true,
                            intArea : intArea,
                            intFacultad : <?php print $intFacultad; ?>,
                            intCurso : <?php print $intCurso; ?>,
                            ajax:true
                        },
                        type:'post',
                        dataType:'html',
                        beforeSend: function() {
                           intTop = ( $(window).height() * 1 ) / 2;
                           $("#divShowLoadingGeneralBig").css("top", intTop);
                           $("#divShowLoadingGeneralBig").css("left", 0);
                           $("#divShowLoadingGeneralBig").show();
                        },
                        success:function(data){
                            $("#tdLogos").html(data);

                            $("#divShowLoadingGeneralBig").hide();
                        }

                    });
                }




                function fntLoadCursosImpartidos(intCurso,intFacultad){
                     $.ajax({
                        url:'<?php print $strAction; ?>?getCursosImpartidos=true&intCurso=' + intCurso+'&intFacultad='+intFacultad,
                        dataType:"html",
                        beforeSend: function() {
                            intTop = ( $(window).height() * 1 ) / 2;
                            $("#divShowLoadingGeneralBig").css("top", intTop);
                            $("#divShowLoadingGeneralBig").css("left", 0);
                            $("#divShowLoadingGeneralBig").show();
                        },
                        success: function(data){
                            $("#divCursosImpartidos").html(data);
                            $("#divShowLoadingGeneralBig").hide();
                        }
                    });
                }

                function fntMantenerDecimales(obj, boolIsEntero, boolIsMayorCero){
                    boolIsEntero = ( !boolIsEntero ) ? false : true;
                    boolIsMayorCero = ( !boolIsMayorCero ) ? false : true;
                    var objValue = obj.value;

                    if ( isNaN(objValue) || ( objValue <= 0 ) )
                        objValue = 0;

                    objValue = ( boolIsMayorCero ) ? ( ( objValue <= 0 ) ? 1 : objValue ) : objValue;

                    obj.value = ( boolIsEntero ) ? parseInt(objValue) : format_monto_sincomas(objValue, 2);
                }

                $.extend($.ui.dialog.prototype, {
                    'addbutton': function(buttonName, func) {
                        var buttons = this.element.dialog('option', 'buttons');
                        buttons[buttonName] = func;
                        this.element.dialog('option', 'buttons', buttons);
                    }
                });

                $.extend($.ui.dialog.prototype, {
                    'removeAllButtons': function() {
                        var buttons = this.element.dialog('option', 'buttons');
                        for(key in buttons){
                            delete buttons[key];
                        }
                        this.element.dialog('option', 'buttons', buttons);
                    }
                });

                $(function(){
                    $("#divSolicitarAnulacion").dialog({
                        show: "explode",
                        autoOpen: false,
                        hide: "explode",
                        modal: "true",
                        resizable: false,
                        width: 650
                    });
                });

            </script>
            <style type="text/css">
                .WidthDiv{
                    width: 100%;
                }
                .rowEqui{
                    padding: 2px 6px;
                    vertical-align: top;
                }
            </style>

            <div id="showFrame" name="showFrame" title="<?php print $lang["ACADEMICO_EDICION_ERROR_TITLE"]; ?>"></div>


            <form name="frm_detalle" id="frm_detalle" action="<?php print $strAction."?curso=".$intCurso."&facultad=".$intFacultad; ?>" method="post">
                <input type="hidden" id="hdnFacultad" value="<?php print $intFacultad;?>">
                <input type="hidden" id="hdnAddPensum" name="hdnAddPensum" value="N">
                <input type="hidden" value="<?php print $arrInfo["ID"]; ?>" name="hidGuardar" id="hidGuardar" readonly="readonly">
                <?php
                $arrAccesoModificarDetalleCurso = check_persona_access(914,true);
                $boolModi_Dt_curso = isset($arrAccesoModificarDetalleCurso[4]);
                $boolView_Dt_curso = isset($arrTiposAcceso[1]);
                ?>
                <table align="left" width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td align="left">
                            <div id="tabDetalleCurso">
                                <ul>
                                    <?php
                                    if( $boolModi_Dt_curso ||  $boolView_Dt_curso ){
                                        ?>
                                        <li><a href="#tabDetalle"><?php print $lang["ACADEMICO_CURSOS_PENSUM_DETALLE_DETALLE"]; ?></a></li>
                                        <?php
                                    }
                                    ?>
                                    <li><a href="#tabCursosRelacionados" id="TabCursosRelacionados"><?php print $lang["ACADEMICO_CURSOS_PENSUM_DETALLE_CURSOS_RELACIONADOS"]; ?></a></li>
                                    <li><a href="#tabInfoPenum" id="TabPensa" ><?php print $lang["ACADEMICO_CURSOS_PENSA"]; ?></a></li>

                                    <?php /*
                                    <li><a href="#tabInfoSyllabus"><?php print $lang["ACADEMICO_CURSOS_PENSUM_DETALLE_PESTANIA_SILLABUS"]; ?></a></li>
                                    
                                    */ ?>
                                    <li><a href="#tabSyllabusUA" id="TabSyllabusUA">Syllabus (Unidad acad�mica)</a></li>
                                    <li><a href="#tabCurso">Prerrequisitos</a></li>
                                    <li><a href="#tabCursosImpartidos" id='TabCursosImpartidos' onclick="fntLoadCursosImpartidos(<?php print $intCurso;?>,<?php print $intFacultad ;?>)" >Cursos impartidos</a></li>
                                </ul>

                                <?php
                                if( $boolModi_Dt_curso || $boolView_Dt_curso ){
                                    ?>
                                    <!--DETALLE-->
                                    <div id="tabDetalle">
                                        <table cellpadding="3" cellspacing="0" width="95%" align="center">
                                            <tr>
                                                <td width="65%">
                                                    <table width="100%" cellpadding="3" cellspacing="0">
                                                        <tr>
                                                            <td width="20%" class="editTitles"><?php print $lang["ACADEMICO_CURSOS_PENSUM_DETALLE_CODIGO"]; ?></td>
                                                            <td width="80%">
                                                                <?php 
                                                                if( $boolModi_Dt_curso ){
                                                                    ?>
                                                                    <input type="text" value="<?php print $arrInfo["CODIGO"]; ?>" name="codigo" id="codigo" class="field_textbox inputSizeComplete"  size="37" style="display: none;" maxlength="50">
                                                                    <input type="hidden" name="hdnTabDetalleCurso" value="Y" readonly="readonly">
                                                                    <span id="spanCodigo"><?php print $arrInfo["CODIGO"]; ?></span>
                                                                    <?php    
                                                                }
                                                                else if( $boolView_Dt_curso  ){
                                                                    ?>
                                                                    <span><?php print $arrInfo["CODIGO"]; ?></span>
                                                                    <?php
                                                                }
                                                                ?> 
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td class="editTitles"><?php print $lang["ACADEMICO_CURSOS_PENSUM_DETALLE_NOMBRE"]; ?></td>
                                                            <td>
                                                                <?php 
                                                                if( $boolModi_Dt_curso ){
                                                                    ?>
                                                                    <input type="text" value="<?php print $arrInfo["CURSO"]; ?>" name="nombre" id="nombre" class="field_textbox inputSizeComplete"  size="37" style="display: none;" maxlength="200">
                                                                    <span id="spanCurso"><?php print $arrInfo["CURSO"]; ?></span>
                                                                    <?php    
                                                                }
                                                                else if( $boolView_Dt_curso  ){
                                                                    ?>
                                                                    <span ><?php print $arrInfo["CURSO"]; ?></span>
                                                                    <?php
                                                                }
                                                                ?>
                                                                
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td class="editTitles"><?php print $lang["ACADEMICO_CURSOS_PENSUM_DETALLE_NAME"]; ?></td>
                                                            <td>
                                                                <?php 
                                                                if( $boolModi_Dt_curso ){
                                                                    ?>
                                                                    <input type="text" value="<?php print $arrInfo["NOMBRE_ING"]; ?>" name="nombreIngles" id="nombreIngles" class="field_textbox inputSizeComplete"  size="37" style="display: none;" maxlength="200">
                                                                    <span id="spanNombreIngles"> <?php print $arrInfo["NOMBRE_ING"]; ?></span>
                                                                    <?php    
                                                                }
                                                                else if( $boolView_Dt_curso  ){
                                                                    ?>
                                                                    <span ><?php print $arrInfo["NOMBRE_ING"]; ?></span>
                                                                    <?php
                                                                }
                                                                ?>
                                                                
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td class="editTitles"><?php print $lang["ACADEMICO_CURSOS_PENSUM_DETALLE_TIPO_CURSO"]; ?></td>
                                                            <td>
                                                                <?php
                                                                $arrInfoTipoCurso = array();
                                                                $arrInfoTipoCurso = sqlGetValueFromKey("SELECT TC.TIPO_CURSO, TC.HISTORIAL, TC.PROMEDIO, TC.ACREDITA_UMAS FROM {$cfg["academico"]["schema"]}.TIPO_CURSO TC, {$cfg["academico"]["schema"]}.CURSO C WHERE C.CURSO = '{$intCurso}' AND C.TIPO_CURSO = TC.TIPO_CURSO");
                                                                //debugQuery("SELECT TC.TIPO_CURSO, TC.HISTORIAL, TC.PROMEDIO, TC.ACREDITA_UMAS FROM ACADEMICO.TIPO_CURSO{$cfg["core"]["conexion_esquema_academico"]} TC, ACADEMICO.CURSOS{$cfg["core"]["conexion_esquema_academico"]} C WHERE C.CURSO = '{$intCurso}' AND C.TIPO_CURSO = TC.TIPO_CURSO");
                                                                $arrInfoTipoCurso["TIPO_NOTA"] = sqlGetValueFromKey("SELECT TN.NOMBRE FROM {$cfg["academico"]["schema"]}.TIPO_CURSO TC, {$cfg["academico"]["schema"]}.CURSO C, {$cfg["academico"]["schema"]}.TIPO_NOTA TN WHERE C.CURSO = '{$intCurso}' AND C.TIPO_CURSO = TC.TIPO_CURSO AND TN.TIPO_NOTA = TC.TIPO_NOTA");
                                                                $arrInfoTipoCurso["FORMA_COBRO"] = sqlGetValueFromKey("SELECT FC.TIPO_COBRO FROM {$cfg["academico"]["schema"]}.TIPO_CURSO TC, {$cfg["academico"]["schema"]}.CURSO C, {$cfg["academico"]["schema"]}.TIPO_COBRO FC WHERE C.CURSO = '{$intCurso}' AND C.TIPO_CURSO = TC.TIPO_CURSO AND FC.TIPO_COBRO = TC.TIPO_COBRO");
                                                                $arrInfoTipoCurso["TIPO_MOV_ACAD"] = sqlGetValueFromKey("SELECT TMA.TIPO_MOV_ACAD || ' - ' || TMA.DESCRIPCION FROM {$cfg["academico"]["schema"]}.TIPO_CURSO TC, {$cfg["academico"]["schema"]}.CURSO C, {$cfg["academico"]["schema"]}.TIPO_MOV_ACAD TMA WHERE C.CURSO = '{$intCurso}' AND C.TIPO_CURSO = TC.TIPO_CURSO AND TC.TIPO_MOV_ACAD = TMA.TIPO_MOV_ACAD");
                                                                //drawDebug($arrInfo);
                                                                ?>
                                                                <?php
                                                                $qTMP = db_query("SELECT TIPO_CURSO, NOMBRE FROM {$cfg["academico"]["schema"]}.TIPO_CURSO");
                                                                //debugQuery("SELECT TIPO_CURSO, NOMBRE FROM ACADEMICO.TIPO_CURSO{$cfg["core"]["conexion_esquema_academico"]}");
                                                                //drawDebug($arrInfo["TIPO_CURSO"]);
                                                                $strTipoCurso = "";
                                                                ?>
                                                                <?php 
                                                                if( $boolModi_Dt_curso ){
                                                                    ?>
                                                                    <select onchange="updateInfoCurso(this);" id="select_tipo_curso" name="select_tipo_curso" style="display: none;" disabled="disabled">
                                                                        <?php
                                                                        while($rTMP = db_fetch_assoc($qTMP)) {
                                                                            if( isset($arrInfoTipoCurso["TIPO_CURSO"]) && $arrInfoTipoCurso["TIPO_CURSO"] == $rTMP["TIPO_CURSO"] ) {
                                                                                $strTipoCurso = $rTMP["NOMBRE"];
                                                                                $strTipoCurso = empty($strTipoCurso) ? "--" : $strTipoCurso;
                                                                                ?>
                                                                                <option value="<?php print $rTMP["TIPO_CURSO"] ?>" selected="selected"><?php print $rTMP["NOMBRE"]; ?></option>
                                                                                <?php
                                                                            }
                                                                            else {
                                                                                ?>
                                                                                <option value="<?php print $rTMP["TIPO_CURSO"] ?>"><?php print $rTMP["NOMBRE"]; ?></option>
                                                                                <?php
                                                                            }
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                    <div id="text_tipo_curso" name="text_tipo_curso"> <?php print $strTipoCurso; ?> </div>
                                                                    <?php    
                                                                }
                                                                else if( $boolView_Dt_curso  ){
                                                                    while($rTMP = db_fetch_assoc($qTMP)) {
                                                                        if( isset($arrInfoTipoCurso["TIPO_CURSO"]) && $arrInfoTipoCurso["TIPO_CURSO"] == $rTMP["TIPO_CURSO"] ) {
                                                                            $strTipoCurso = $rTMP["NOMBRE"];
                                                                            $strTipoCurso = empty($strTipoCurso) ? "--" : $strTipoCurso;
                                                                            break;   
                                                                        }                                                                        
                                                                    }
                                                                    ?>   
                                                                    <span ><?php print $strTipoCurso; ?></span>
                                                                    <?php
                                                                }
                                                                ?>
                                                                
                                                            </td>
                                                        </tr>
                                                    </table>
                                                    <div id="divInfoTipoCurso">
                                                        <table width="100%" cellpadding="3" cellspacing="0" id="tablaTipoCursoInfo">
                                                            <tr>
                                                                <td width="20%" class="editTitles"><?php print $lang["ACADEMICO_CURSOS_PENSUM_DETALLE_TIPO_NOTA"]; ?></td>
                                                                <td width="80%">
                                                                    <?php print isset($arrInfoTipoCurso["TIPO_NOTA"]) ? $arrInfoTipoCurso["TIPO_NOTA"] : "&nbsp;"; ?>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td align="right" class="editTitles"><?php print $lang["ACADEMICO_CURSOS_PENSUM_DETALLE_HISTORIAL"]; ?></td>
                                                                <td align="left">
                                                                    <?php print isset($arrInfoTipoCurso["HISTORIAL"]) ? (($arrInfoTipoCurso["HISTORIAL"] == 'Y') ? "Si" : "No") : "&nbsp;"; ?>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td align="right" class="editTitles"><?php print $lang["ACADEMICO_CURSOS_PENSUM_DETALLE_PROMEDIO"]; ?></td>
                                                                <td align="left">
                                                                    <?php print isset($arrInfoTipoCurso["PROMEDIO"]) ? (($arrInfoTipoCurso["PROMEDIO"] == 'Y') ? "Si" : "No") : "&nbsp;"; ?>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td align="right" class="editTitles"><?php print $lang["ACADEMICO_CURSOS_PENSUM_DETALLE_ACREDITA_UMA"]; ?></td>
                                                                <td align="left">
                                                                    <?php print isset($arrInfoTipoCurso["ACREDITA_UMAS"]) ? (($arrInfoTipoCurso["ACREDITA_UMAS"] == 'Y') ? "Si" : "No") : "&nbsp;"; ?>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td align="right" class="editTitles" valign="top">Monto m�ximo</td>
                                                                <td align="left" valign="top">
                                                                    <table align="center" width="100%" cellpadding="3" cellspacing="0">
                                                                        <tr>
                                                                            <td width="40%" align="left">
                                                                                <?php 
                                                                                if( $boolModi_Dt_curso ){
                                                                                    ?>
                                                                                    <div id="DivMontoMaximoCurso"><?php print number_format($arrInfo["MONTO_MAXIMO"], 2); ?></div>
                                                                                    <input type="text" name="txtMontoMaximoCurso" class="field_textbox inputSizeComplete contentTextAlignRight" value="<?php print number_format($arrInfo["MONTO_MAXIMO"], 2, ".", ""); ?>" onchange="fntMantenerDecimales(this);" style="display: none;">
                                                                                    <?php    
                                                                                }
                                                                                else if( $boolView_Dt_curso  ){
                                                                                    ?>
                                                                                    <span ><?php print number_format($arrInfo["MONTO_MAXIMO"], 2); ?></span>
                                                                                    <?php
                                                                                }
                                                                                ?>
                                                                            </td>
                                                                            <td width="60%">&nbsp;</td>
                                                                        </tr>
                                                                    </table>
                                                                </td>
                                                            </tr>                   
                                                       </table>
                                                    </div>
                                                </td>
                                                <td width="35%" valign="top">
                                                    <table width="100%" cellpadding="3" cellspacing="0">
                                                        <tr>
                                                            <td width="20%" class="editTitles" nowrap><?php print $lang["ACADEMICO_CURSOS_PENSUM_DETALLE_AREA_ACADEMICA"]; ?></td>
                                                            <td width="80%" nowrap>
                                                                <?php 
                                                                $strQuery = "SELECT AREA, NOMBRE FROM AREA WHERE FACULTAD = '{$intFacultad}' AND ACTIVO = 'Y' ORDER BY NOMBRE";
                                                                $qTMP = db_query($strQuery);
                                                                $strNombreArea = "";
                                                                if( $boolModi_Dt_curso ){
                                                                    ?>
                                                                    <select id="area" name="area" class="field_selectbox inputSizeComplete" style="display: none;" onchange="fntGetLogos();">
                                                                        <?php
                                                                        while($rTMP = db_fetch_assoc($qTMP)) {
                                                                            if( $arrInfo["AREA"] == $rTMP["AREA"] ) {
                                                                                $strNombreArea = $rTMP["NOMBRE"];
                                                                                ?>
                                                                                <option value="<?php print $rTMP["AREA"]; ?>" selected="selected"><?php print $rTMP["NOMBRE"]; ?></option>
                                                                                <?php
                                                                            }                   
                                                                            else {
                                                                                ?>
                                                                                <option value="<?php print $rTMP["AREA"]; ?>"><?php print $rTMP["NOMBRE"]; ?></option>
                                                                                <?php
                                                                            }
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                    <span id="spanArea"><?php print $strNombreArea; ?></span>
                                                                    <?php
                                                                }
                                                                else if( $boolView_Dt_curso  ){
                                                                    while($rTMP = db_fetch_assoc($qTMP)) {
                                                                        if( $arrInfo["AREA"] == $rTMP["AREA"] ) {
                                                                            $strNombreArea = $rTMP["NOMBRE"];
                                                                        }
                                                                    }
                                                                    ?>
                                                                    <span ><?php print $strNombreArea; ?></span>
                                                                    <?php
                                                                }
                                                                ?>
                                                                
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td class="editTitles" >
                                                                <?php  
                                                                if( $boolModi_Dt_curso ){
                                                                    ?><input type="hidden" id="hdnIsLogo" value="<?php print $arrInfo['ISLOGO'];?>" readonly="readonly"><?php    
                                                                }   
                                                                ?>
                                                                <div id="divTitleUmas" style="display: <?php print ($arrInfo['ISLOGO'] == 'Y')? "":"" ;?>"><?php print $lang["ACADEMICO_CURSOS_PENSUM_DETALLE_UMAS"]; ?></div>
                                                            </td>
                                                            <td>
                                                                <table width="100%" cellpadding="0" cellspacing="0">
                                                                    <tr>
                                                                        <td width="40%" nowrap="nowrap" >          
                                                                            <?php  
                                                                            if( $boolModi_Dt_curso ){
                                                                                ?>
                                                                                <input type="text" value="<?php print $arrInfo["UMAS"]; ?>" name="umas" id="umas" class="field_textbox" size="6" style="display: none;" maxlength="9">
                                                                                <?php    
                                                                            }   
                                                                            ?>
                                                                            <span id="spanUmas" style="display: <?php print ($arrInfo['ISLOGO'] == 'Y')? "":"" ;?>"> <?php print $arrInfo["UMAS"]; ?></span>
                                                                        </td>
                                                                        <td width="60%" id="tdLogos" nowrap="nowrap">
                                                                            <?php
                                                                            if( $arrInfo['ISLOGO'] == 'Y' ){
                                                                                ?>
                                                                                <table width="100%" cellpadding="3" cellspacing="0">
                                                                                    <tr>
                                                                                        <td nowrap="nowrap" width="30%" class="editTitles">LOGOS</td>
                                                                                        <td nowrap="nowrap" width="70%">
                                                                                             <?php  
                                                                                            if( $boolModi_Dt_curso ){
                                                                                                ?>
                                                                                                <div id="divLogos">
                                                                                                    <?php print isset($arrInfo['LOGOS']) ? $arrInfo['LOGOS'] : 0; ?>
                                                                                                </div>
                                                                                                <div id="divTxtlogos" style="display: none;">
                                                                                                    <input type="text" name="txtLogos" class="field_textbox" size="6" value="<?php print isset($arrInfo['LOGOS']) ? $arrInfo['LOGOS'] : 0; ?>" maxlength="9">
                                                                                                </div>
                                                                                                <?php
                                                                                            }
                                                                                            else if( $boolView_Dt_curso  ){
                                                                                                ?>
                                                                                                <div >
                                                                                                    <?php print isset($arrInfo['LOGOS']) ? $arrInfo['LOGOS'] : 0; ?>
                                                                                                </div>
                                                                                                <?php      
                                                                                            }?>
                                                                                            
                                                                                        </td>
                                                                                    </tr>
                                                                                </table>
                                                                                <?php
                                                                            }
                                                                            
                                                                            ?>
                                                                        </td>
                                                                    </tr>
                                                                </table>
                                                            </td>
							</tr>
							<tr>
                                                            <td class="editTitles" >
                                                                <?php
                                                                if( $boolModi_Dt_curso ){
                                                                    ?><input type="hidden" id="hdnIsLogo" value="<?php print $arrInfo['ISLOGO'];?>" readonly="readonly"><?php
                                                                }
                                                                ?>
                                                                <div id="divTitleEcts" style="display: <?php print ($arrInfo['ISLOGO'] == 'Y')? "":"" ;?>"><?php print $lang["ACADEMICO_CURSOS_PENSUM_DETALLE_ECTS"]; ?></div>
                                                            </td>
							    <td>
                                                               <table width="100%" cellpadding="0" cellspacing="0">
                                                                    <tr>
                                                                        <td width="40%" nowrap="nowrap" >
                                                                            <?php
                                                                            if( $boolModi_Dt_curso ){
                                                                                ?>
                                                                                <input type="text" value="<?php print $arrInfo["ECTS"]; ?>" name="ects" id="ects" class="field_textbox" size="6" style="display: none;" maxlength="9">
                                                                                <?php
                                                                            }
                                                                            ?>
                                                                            <span id="spanEcts" style="display: <?php print ($arrInfo['ISLOGO'] == 'Y')? "":"" ;?>"> <?php print $arrInfo["ECTS"]; ?></span>
                                                                        </td>
                                                                        <td width="60%" id="tdLogos" nowrap="nowrap">
                                                                            <?php
                                                                            if( $arrInfo['ISLOGO'] == 'Y' ){
                                                                                ?>
                                                                                <table width="100%" cellpadding="3" cellspacing="0">
                                                                                    <tr>
                                                                                        <td nowrap="nowrap" width="30%" class="editTitles">LOGOS</td>
                                                                                        <td nowrap="nowrap" width="70%">
                                                                                             <?php
                                                                                            if( $boolModi_Dt_curso ){
                                                                                                ?>
                                                                                                <div id="divLogos">
                                                                                                    <?php print isset($arrInfo['LOGOS']) ? $arrInfo['LOGOS'] : 0; ?>
                                                                                                </div>
                                                                                                <div id="divTxtlogos" style="display: none;">
                                                                                                    <input type="text" name="txtLogos" class="field_textbox" size="6" value="<?php print isset($arrInfo['LOGOS']) ? $arrInfo['LOGOS'] : 0; ?>" maxlength="9">
                                                                                                </div>
                                                                                                <?php
                                                                                            }
                                                                                            else if( $boolView_Dt_curso  ){
                                                                                                ?>
                                                                                                <div >
                                                                                                    <?php print isset($arrInfo['LOGOS']) ? $arrInfo['LOGOS'] : 0; ?>
                                                                                                </div>
                                                                                                <?php
                                                                                            }?>

                                                                                        </td>
                                                                                    </tr>
                                                                                </table>
                                                                                <?php
                                                                            }
                                                                            ?>
                                                                        </td>
                                                                    </tr>
                                                                </table>
							    </td>
							</tr>
                                                        <tr>
                                                            <td class="editTitles"><?php print $lang["ACADEMICO_CURSOS_PENSUM_DETALLE_ACTIVO"]; ?></td>
                                                            <td>
                                                                <?php  
                                                                if( $boolModi_Dt_curso ){
                                                                    ?>
                                                                    <input type="checkbox" name="activo" id="activo" class="field_checkbox" <?php print ($arrInfo["ACTIVO"] == "Y") ? "checked='checked'" : "" ;?> style="display: none;">
                                                                    <span id="spanActivo"> <?php print ($arrInfo["ACTIVO"] == "Y") ? "Si" : "No";  ?></span>
                                                                    <?php 
                                                                }
                                                                else if( $boolView_Dt_curso  ){
                                                                    ?>
                                                                    <span > <?php print ($arrInfo["ACTIVO"] == "Y") ? "Si" : "No";  ?></span>
                                                                    <?php      
                                                                }?>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                            <tr><td colspan="2">&nbsp;</td></tr>
                                            <tr><td colspan="2">&nbsp;</td></tr>
                                            <tr>
                                                <td colspan="2">
                                                    <?php
                                                    $arrInfo = sqlGetValueFromKey("SELECT CONVENIO, NOMBRE_CONVENIO, FILOSOFIA_INSTITUCION FROM {$cfg["academico"]["schema"]}.CURSO WHERE CURSO = '{$intCurso}'");
                                                    ?>
                                                    <table width="100%" align="center" cellpadding="3" cellspacing="0">
                                                        <tr>
                                                            <td class="Heading1" align="left" colspan="4"><?php print $lang["ACADEMICO_CURSOS_PENSUM_DETALLE_FILOSOFIA_INSTITUCION"]; ?></td>
                                                        </tr>

                                                        <tr><td colspan="4">&nbsp;</td></tr>

                                                        <tr>
                                                            <td width="10%" class="editTitles" align="left" nowrap>
                                                                <?php print $lang["ACADEMICO_CURSOS_PENSUM_DETALLE_FILOSOFIA_INSTITUCION_CUMPLE"]; ?>
                                                            </td>
                                                            <td  width="30%" align="left">
                                                                <?php  
                                                                if( $boolModi_Dt_curso ){
                                                                    ?>
                                                                    <input type="checkbox" <?php print ($arrInfo["FILOSOFIA_INSTITUCION"] == 'Y') ? "checked='checked'" : ""; ?> class="field_checkbox" name="chkFilosofiaIns" id="chkFilosofiaIns" style="display: none;">
                                                                    <span id="spanFilosofiaIns"><?php print ($arrInfo["FILOSOFIA_INSTITUCION"] == 'Y') ? "Si" : "No"; ?></span>
                                                                    <?php 
                                                                }
                                                                else if( $boolView_Dt_curso  ){
                                                                    ?>
                                                                    <span><?php print ($arrInfo["FILOSOFIA_INSTITUCION"] == 'Y') ? "Si" : "No"; ?></span>
                                                                    <?php      
                                                                }?>
                                                                
                                                            </td>
                                                            <td width="10%">&nbsp;</td>
                                                            <td width="50%">&nbsp;</td>
                                                        </tr>

                                                        <tr><td colspan="4">&nbsp;</td></tr>

                                                        <tr>
                                                            <td class="Heading1" align="left" colspan="4"><?php print $lang["ACADEMICO_CURSOS_PENSUM_DETALLE_CONVENIO"]; ?></td>
                                                        </tr>

                                                        <tr><td colspan="4">&nbsp;</td></tr>

                                                        <tr>
                                                            <td class="editTitles" nowrap>
                                                                <?php print $lang["ACADEMICO_CURSOS_PENSUM_DETALLE_CONVENIO"]; ?>
                                                            </td>
                                                            <td align="left">
                                                                <?php  
                                                                if( $boolModi_Dt_curso ){
                                                                    ?>
                                                                    <input type="checkbox" <?php print ($arrInfo["CONVENIO"] == 'Y') ? "checked='checked'" : ""; ?> class="field_checkbox" name="chkDetalleConvenio" id="chkDetalleConvenio" style="display: none;">
                                                                    <span id="spanDetalleConvenio"><?php print ($arrInfo["CONVENIO"] == 'Y') ? "Si" : "No"; ?></span>
                                                                    <?php 
                                                                }
                                                                else if( $boolView_Dt_curso  ){
                                                                    ?>
                                                                    <span><?php print ($arrInfo["CONVENIO"] == 'Y') ? "Si" : "No"; ?></span>
                                                                    <?php      
                                                                }?>
                                                                
                                                            </td>
                                                            <td class="editTitles" align="right"  nowrap><?php print $lang["ACADEMICO_CURSOS_PENSUM_DETALLE_NOMBRE_CONVENIO"]; ?></td>
                                                            <td align="left">
                                                                <?php  
                                                                if( $boolModi_Dt_curso ){
                                                                    ?>
                                                                    <input type="text" align="left" style="display: none;" class="field_textbox" name="txtNombreConvenio" id="txtNombreConvenio" size="50" value="<?php print $arrInfo["NOMBRE_CONVENIO"]; ?>" maxlength="200">
                                                                    <span id="spanNombreConvenio"><?php print $arrInfo["NOMBRE_CONVENIO"]; ?></span>
                                                                    <?php 
                                                                }
                                                                else if( $boolView_Dt_curso  ){
                                                                    ?>
                                                                    <span><?php print $arrInfo["NOMBRE_CONVENIO"]; ?></span>
                                                                    <?php      
                                                                }?>
                                                                
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                            <tr><td align="center"></td></tr>
                                        </table>
                                    </div>
                                    <?php
                                }
                                ?>

                                <!--CURSOS RELACIONADOS-->
                                <div id="tabCursosRelacionados">
                                    <input type="hidden" name="hdnTabCursosRelacionados" readonly="readonly" value="Y">
                                    <table width="95%" align="center" cellpadding="0" cellspacing="0" id="tblCursoRelacionado">
                                        <tr>
                                            <td width="4%">&nbsp;</td>
                                            <td width="4%">&nbsp;</td>
                                            <td width="92%">&nbsp;</td>
                                        </tr>
                                        <tr>
                                            <td class="bannerH1" colspan="3">Equivalencias</td>
                                        </tr>
                                        <!--SECCION EQUIVALENTE 1 -->
                                        <tr>
                                            <td>&nbsp;</td>
                                            <td colspan="2" class="heading1" style="color: black">Al aprobar el curso <span style="color: #278EAF"><?php print $strCodigoCurso.' - '.$strNombreCurso; ?></span> se dar� por aprobado el curso:</td>
                                        </tr>
                                        <tr>
                                            <td>&nbsp;</td>
                                            <td>&nbsp;</td>
                                            <td>
                                                <table width="100%" align="center" cellpadding="5" cellspacing="0" id="tblCursoEquivalenteI">
                                                    <tr>
                                                        <td width="2%">&nbsp;</td>
                                                        <td width="25%" class="row0">C�digo</td>
                                                        <td width="25%" class="row0">Curso</td>
                                                        <td width="28%" class="row0">Sobresee</td>
                                                        <td width="5%" class="row0" align="center">Estado</td>
                                                        <td width="4%" class="row0" align="center">&nbsp;</td>
                                                        <td width="4%" class="row0">&nbsp;</td>
                                                        <td width="4%" class="row0">&nbsp;</td>
                                                        <td width="3%" class="row0">&nbsp;</td>
                                                    </tr>
                                                    <?php
                                                    $strClass = "row2";
                                                    $intContadorCursosEquivalente1 = 0;
                                                    $arrCondicion = array("N"=>"No","S"=>"Siempre","U"=>"�nicamente si el curso fue reprobado");
                                                    $intUser = isset($_SESSION["wt"]["originalUserToTest"]) ? intval($_SESSION["wt"]["originalUserToTest"]) : intval($_SESSION["wt"]["uid"]);

                                                    $strQuery = "SELECT  C.curso,
                                                                                    C.codigo, C.nombre,CE.estado,
                                                                                    CASE
                                                                                        WHEN CE.curso_equivalente = {$intCursoId} THEN '1'
                                                                                        WHEN CE.curso_equivalente2 = {$intCursoId} THEN '2'
                                                                                        WHEN CE.curso_equivalente3 = {$intCursoId} THEN '3'
                                                                                        ELSE '0'
                                                                                    END origen,
                                                                                    CE.curso_equivalente,
                                                                                    CE.curso_equivalente2,
                                                                                    CE.curso_equivalente3 ,
                                                                                    CE.sobresee,
                                                                                    CE.sobresee2,
                                                                                    CE.sobresee3
                                                                 FROM           curso C
                                                                                        INNER JOIN  curso_equivalente CE
                                                                                            ON C.curso = CE.curso
                                                                                    INNER JOIN area A
                                                                                        ON C.area = A.area
                                                                 WHERE  (
                                                                                CE.curso_equivalente = {$intCursoId}
                                                                                OR     CE.curso_equivalente2 = {$intCursoId}
                                                                                OR     CE.curso_equivalente3 = {$intCursoId}
                                                                                )
                                                                              AND    A.facultad = {$intFacultad}
                                                                 ORDER  BY C.nombre";
                                                    $qTMP = db_query($strQuery);
                                                    //drawDebug($strQuery);

                                                    if( $rTMP = db_fetch_assoc($qTMP) ) {

                                                        do {
                                                            $strClass = ($strClass == "row2") ? "row1" : "row2";
                                                            $intContadorCursosEquivalente1++;
                                                            ?>
                                                            <tr>
                                                                <td>&nbsp;</td>
                                                                <td class="<?php print $strClass; ?>">
                                                                    <div id="divCursoEquivalenteI_<?php print $intContadorCursosEquivalente1; ?>">
                                                                        <a href="adm_academico_detalle_curso.php?curso=<?php print $rTMP["CURSO"]; ?>&facultad=<?php print $intFacultad; ?>">
                                                                            <?php print isset($rTMP["CODIGO"])? $rTMP["CODIGO"] : ""; ?>
                                                                        </a>
                                                                    </div>
                                                                    <input type="text" class="field_textbox inputSizeComplete" id="txtCodigoCursoEquivalenteI_<?php print $intContadorCursosEquivalente1;  ?>" value="<?php print isset($rTMP["CODIGO"]) ? $rTMP["CODIGO"] : ""; ?>" style="display: none;">
                                                                </td>
                                                                <td class="<?php print $strClass; ?>">
                                                                    <input type="text" class="field_textbox inputSizeComplete" id="txtCursoEquivalenteI_<?php print $intContadorCursosEquivalente1;  ?>" value="<?php print isset($rTMP["NOMBRE"]) ? $rTMP["NOMBRE"] : ""; ?>" style="display: none;">
                                                                    <input type="hidden" name="hidCursoEquivalenteI_<?php print $intContadorCursosEquivalente1; ?>" value="<?php print $rTMP["CURSO"]; ?>">

                                                                    <input type="hidden" id="hidUpdateCursoEquivalenteI_<?php print $intContadorCursosEquivalente1; ?>" name="hidUpdateCursoEquivalenteI_<?php print $intContadorCursosEquivalente1; ?>" value="<?php print $rTMP["CURSO"]; ?>">

                                                                    <input type="hidden" id="hCursoEquivalente1I_<?php print $intContadorCursosEquivalente1; ?>" name="hCursoEquivalente1I_<?php print $intContadorCursosEquivalente1; ?>" value="<?php print isset($rTMP["CURSO_EQUIVALENTE"])? intval($rTMP["CURSO_EQUIVALENTE"]):"NULL"; ?>">
                                                                    <input type="hidden" id="hCursoEquivalente2I_<?php print $intContadorCursosEquivalente1; ?>" name="hCursoEquivalente2I_<?php print $intContadorCursosEquivalente1; ?>" value="<?php print isset($rTMP["CURSO_EQUIVALENTE2"])? intval($rTMP["CURSO_EQUIVALENTE2"]) :"NULL"; ?>">
                                                                    <input type="hidden" id="hCursoEquivalente3I_<?php print $intContadorCursosEquivalente1; ?>" name="hCursoEquivalente3I_<?php print $intContadorCursosEquivalente1; ?>" value="<?php print isset($rTMP["CURSO_EQUIVALENTE3"])? intval($rTMP["CURSO_EQUIVALENTE3"]):"NULL"; ?>">

                                                                    <input type="hidden" id="hCursoSobresee1I_<?php print $intContadorCursosEquivalente1; ?>" name="hCursoSobresee1I_<?php print $intContadorCursosEquivalente1; ?>" value="<?php print isset($rTMP["SOBRESEE"])? ($rTMP["SOBRESEE"]):NULL; ?>">
                                                                    <input type="hidden" id="hCursoSobresee2I_<?php print $intContadorCursosEquivalente1; ?>" name="hCursoSobresee2I_<?php print $intContadorCursosEquivalente1; ?>" value="<?php print isset($rTMP["SOBRESEE2"])? ($rTMP["SOBRESEE2"]) :NULL; ?>">
                                                                    <input type="hidden" id="hCursoSobresee3I_<?php print $intContadorCursosEquivalente1; ?>" name="hCursoSobresee3I_<?php print $intContadorCursosEquivalente1; ?>" value="<?php print isset($rTMP["SOBRESEE3"])? ($rTMP["SOBRESEE3"]):NULL; ?>">


                                                                    <input type="hidden" name="hidDeleteCursoEquivalenteI_<?php print $intContadorCursosEquivalente1; ?>" value="N">
                                                                    <div id="divCursoEquivalenteI_<?php print $intContadorCursosEquivalente1; ?>">
                                                                        <a href="adm_academico_detalle_curso.php?curso=<?php print $rTMP["CURSO"]?>&facultad=<?php print $intFacultad; ?>">
                                                                            <?php print isset($rTMP["NOMBRE"]) ? $rTMP["NOMBRE"] : ""; ?>
                                                                        </a>
                                                                    </div>
                                                                </td>
                                                                <td class="<?php print $strClass; ?>">
                                                                    <div id="divCursoEquivalenteSobresee_<?php print $intContadorCursosEquivalente1; ?>">
                                                                        <?php

                                                                        // elige Sobresee dependiendo de Que Curso Equivalente sea

                                                                        if (($rTMP["ORIGEN"]) == '1') {
                                                                            $strSobreeDeQuery = ($rTMP["SOBRESEE"]);
                                                                        }elseif (($rTMP["ORIGEN"]) == '2') {
                                                                            $strSobreeDeQuery = ($rTMP["SOBRESEE2"]);
                                                                        }elseif(($rTMP["ORIGEN"]) == '3'){
                                                                            $strSobreeDeQuery = ($rTMP["SOBRESEE3"]);
                                                                        }else{
                                                                            $strSobreeDeQuery = "";
                                                                        }
                                                                        if(!empty($strSobreeDeQuery) ){
                                                                            print isset($arrCondicion[$strSobreeDeQuery]) ? $arrCondicion[$strSobreeDeQuery] :"&nbsp;" ;
                                                                        }else{
                                                                            print "&nbsp;";
                                                                        }

                                                                        ?>
                                                                    </div>
                                                                    <select name="sltCursoEquivalenteSobresee_<?php print $intContadorCursosEquivalente1;  ?>" class="field_selectbox inputSizeComplete" style="display: none;">
                                                                        <option value=""></option>
                                                                        <?php
                                                                        $keySobreseeI = $strSobreeDeQuery;//variable para elegir Sobresee en arrCondicion
                                                                        reset($arrCondicion);
                                                                        foreach( $arrCondicion as  $arrTMP['key'] => $arrTMP['value'])  {
                                                                            $strSelected = ($arrTMP["key"] == $keySobreseeI) ? "selected" : "";
                                                                            ?>
                                                                            <option value="<?php print $arrTMP["key"]; ?>" <?php print $strSelected; ?>><?php print $arrTMP["value"]; ?></option>
                                                                            <?php
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                </td>
                                                                <td class="<?php print $strClass; ?>" align="center">
                                                                    <?php
                                                                    $strPathImgOk = strGetCoreImageWithPath("ok.png");
                                                                    $strPathImgWarning = strGetCoreImageWithPath("warning.gif");
                                                                    $strPathImgWarningGray = strGetCoreImageWithPath("acad_warning_gray.png");
                                                                    $strPathImgUndo = "";
                                                                    if(!empty($rTMP["ESTADO"]) &&  $rTMP["ESTADO"] == 2  ){
                                                                        print "<img id='imgAutorizadoI_{$intContadorCursosEquivalente1}' name='imgEquivalenteI_{$intContadorCursosEquivalente1}' src='{$strPathImgOk}' onmouseover='fntGetDetailUserAutoriza({$intContadorCursosEquivalente1},true,{$rTMP["CURSO"]},{$intCursoId});'>";
                                                                    }else{
                                                                        if(isset($arrValorEstadosFinalesAutorizador[$rTMP["ESTADO"]]) && check_persona_access(637)){
                                                                            ?>
                                                                            <input type="hidden" id="hdnSeccionI_<?php print $intContadorCursosEquivalente1;?>" name="hdnSeccionI_<?php print $intContadorCursosEquivalente1;?>" value="N">
                                                                            <input type="hidden" id="hdnEstadoFinal_<?php print $intContadorCursosEquivalente1;?>" name="hdnEstadoFinal_<?php print $intContadorCursosEquivalente1;?>" value="N">
                                                                            <?php
                                                                            print "<img id='imgPendienteI_{$intContadorCursosEquivalente1}' name='imgEquivalenteI_{$intContadorCursosEquivalente1}'  src='{$strPathImgWarningGray}' onmouseover='fntGetDetailUserAutoriza({$intContadorCursosEquivalente1},true,{$rTMP["CURSO"]},{$intCursoId});'>";
                                                                        }
                                                                        else{
                                                                            print "<img id='imgPendienteI_{$intContadorCursosEquivalente1}' name='imgEquivalenteI_{$intContadorCursosEquivalente1}'  src='{$strPathImgWarning}' onmouseover='fntGetDetailUserAutoriza({$intContadorCursosEquivalente1},true,{$rTMP["CURSO"]},{$intCursoId});'>";
                                                                        }
                                                                    }
                                                                    ?>
                                                                </td>
                                                                <td class="<?php print $strClass; ?>" align="center">
                                                                    <?php
                                                                    if($rTMP["ESTADO"] == 2 ){
                                                                        if( isset($arrAccesoModificarCursosEqui[4]) ){
                                                                            $strPathImgDel = strGetCoreImageWithPath("del.png");
                                                                            print "<img id='imgSolicitudAnularEqI{$intContadorCursosEquivalente1}' name='imgSolicitudAnularEqI{$intContadorCursosEquivalente1}' src='{$strPathImgDel}' onclick='fntGetSolicitudAnulacion({$rTMP["CURSO"]},{$intCursoId},{$intContadorCursosEquivalente1},true)' style='cursor:pointer'>";
                                                                        }
                                                                    }else{
                                                                        print "&nbsp;";
                                                                    }
                                                                    ?>
                                                                </td>
                                                                <td class="<?php print $strClass; ?>" id="rowCursoEquivalenteI_<?php print $rTMP["CURSO"]; ?>" name="rowCursoEquivalenteI_<?php print $rTMP["CURSO"]; ?>">
                                                                    <?php
                                                                    if( empty($rTMP["ESTADO"]) ){
                                                                        ?>
                                                                        <img id="imgCursoEquivalenteI" src="<?php print strGetCoreImageWithPath("del.png"); ?>" style="cursor: pointer; display: none" title="Eliminar" onclick="fntRowCursoEquivalenteI(<?php print $intContadorCursosEquivalente1; ?>);" >&nbsp;
                                                                        <?php
                                                                    }else if($rTMP["ESTADO"] == 2){
                                                                        print "<input type='hidden' id='hidNoCheckedCamposEquiI_{$intContadorCursosEquivalente1}' value='Y' readonly=\"readonly\">&nbsp;";
                                                                    }else{
                                                                        print "&nbsp;";
                                                                    }
                                                                    ?>
                                                                    <script type="text/javascript">
                                                                        $("input[id='txtCursoEquivalenteI_<?php print $intContadorCursosEquivalente1; ?>']").autocomplete({
                                                                           source : "<?php print $strAction; ?>?sendAutoCompleteCursos=true&facultad=<?php print $intFacultad; ?>",
                                                                           minLength: 2,
                                                                           select: function( event, ui ) {
                                                                               $("input[name='hidUpdateCursoEquivalenteI_<?php print $intContadorCursosEquivalente1; ?>']").val(ui.item.id);
                                                                               $("input[id='txtCodigoCursoEquivalenteI_<?php print $intContadorCursosEquivalente1; ?>']").val(ui.item.result);
                                                                           }
                                                                        });

                                                                        $("input[id='txtCodigoCursoEquivalenteI_<?php print $intContadorCursosEquivalente1; ?>']").autocomplete({
                                                                           source : "<?php print $strAction; ?>?sendAutoCompleteCursos=true&cod=true&facultad=<?php print $intFacultad; ?>",
                                                                           minLength: 2,
                                                                           select: function( event, ui ) {
                                                                               $("input[name='hidUpdateCursoEquivalenteI_<?php print $intContadorCursosEquivalente1; ?>']").val(ui.item.id);
                                                                               $("input[id='txtCursoEquivalenteI_<?php print $intContadorCursosEquivalente1; ?>']").val(ui.item.result);
                                                                           }
                                                                        });
                                                                    </script>
                                                                </td>
                                                                <td class="<?php print $strClass; ?>">
                                                                    <?php
                                                                    if(isset($arrValorEstadosFinalesAutorizador[$rTMP["ESTADO"]]) && check_persona_access(637)){
                                                                        $strPathImgUndo = strGetCoreImageWithPath("undo_green.png");
                                                                        print "<img title='Nueva solicitud de autorizaci�n' id='imgAutorizadoI_{$intContadorCursosEquivalente1}' name='imgEquivalenteI_{$intContadorCursosEquivalente1}'  src='{$strPathImgUndo}' style='cursor: pointer;' onclick='fntUndoSolicitudIndividualEquivalenciaGeneral({$intContadorCursosEquivalente1},{$rTMP["CURSO"]},{$intCursoId});'>";
                                                                    }
                                                                    else{
                                                                        print "&nbsp;";
                                                                    }
                                                                    ?>
                                                                </td>
                                                                <td class="<?php print $strClass; ?>">
                                                                    <?php 
                                                                    if( isset($arrEliminarEqGen[3]) ){
                                                                        ?>
                                                                        <img id="imgDeleteCursoEquivalente" src="<?php print strGetCoreImageWithPath("del.png"); ?>" style="cursor: pointer;" title="Eliminar equivalencia general" onclick="fntDeleteEquivalencia(<?php print $rTMP["CURSO"]; ?>,<?php print $intCursoId; ?>,<?php print $intContadorCursosEquivalente1; ?>,true);" >                                                                        
                                                                        <?php
                                                                    }
                                                                    else{
                                                                        print '&nbsp;';
                                                                    }
                                                                    ?>
                                                                </td>
                                                            </tr>
                                                            <?php

                                                        }while($rTMP = db_fetch_assoc($qTMP));
                                                    }
                                                    else {
                                                        ?>
                                                        <tr id="rowNinguno" name="rowNinguno">
                                                            <td width="2%">&nbsp;</td>
                                                            <td width="28%" class="editTitlesLeft" style="font-style: italic;">Ninguno</td>
                                                            <td width="30%" class="editTitlesLeft" style="font-style: italic;">Ninguno</td>
                                                            <td width="30%">&nbsp;</td>
                                                            <td width="10%">&nbsp;</td>
                                                        </tr>
                                                        <?php
                                                    }
                                                    ?>
                                                    <tr id="rowCursoEquivalenteI" style="display: none;">
                                                        <td colspan="8">
                                                            <img src="<?php print strGetCoreImageWithPath("add.png"); ?>" style="cursor: pointer;" onclick="addRowCursoEquivalenteI();" title="<?php print $lang["ADD"];?>">
                                                        </td>
                                                    </tr>
                                                </table>
                                                <script type="text/javascript">
                                                    var strClass = "<?php print $strClass; ?>";
                                                    var intContador1 = <?php print $intContadorCursosEquivalente1; ?>;
                                                </script>
                                            </td>
                                        </tr>
                                        <!--SECCION EQUIVALENTE 2 -->
                                        <tr >
                                            <td>&nbsp;</td>
                                            <td colspan="2" class="Heading1" style="color: black">Se dar� por aprobado el curso <span style="color: #278EAF"><?php print $strCodigoCurso.' - '.$strNombreCurso; ?></span> al aprobar el curso:</td>
                                        </tr>
                                        <tr >
                                            <td>&nbsp;</td>
                                            <td>&nbsp;</td>
                                            <td>
                                                <table align="center" width="100%" cellpadding="3" cellspacing="0" id="tblCursoEquivalenteII">
                                                    <?php
                                                    $strClass = "row2";
                                                    $intContCursosEquivalentesII = 0;
                                                    /*$strQuery = "SELECT
                                                                        curso_equivalente CURSO,
                                                                        (SELECT nombre FROM curso WHERE curso = curso_equivalente) EQUIVALENTE,
                                                                        (SELECT codigo FROM curso WHERE curso = curso_equivalente) CODIGOEQUIVALENTE,
                                                                        curso_equivalente2 CURSO2,
                                                                        (SELECT nombre FROM curso WHERE CURSO = curso_equivalente2) EQUIVALENTE2,
                                                                        (SELECT codigo FROM curso WHERE curso = curso_equivalente2) CODIGOEQUIVALENTE2,
                                                                        curso_equivalente3 CURSO3,
                                                                        (SELECT nombre FROM curso WHERE CURSO = curso_equivalente3) EQUIVALENTE3,
                                                                        (SELECT codigo FROM curso WHERE curso = curso_equivalente3) CODIGOEQUIVALENTE3,
                                                                        sobresee1,
                                                                        sobresee2,
                                                                        sobresee3
                                                                 FROM   curso_equivalente
                                                                 WHERE  curso = {$intCurso}
                                                                 ORDER BY equivalente";*/
                                                    $strQuery = "SELECT     curso.curso curso,
                                                                            curso.nombre equivalente,
                                                                            curso.codigo codigoequivalente,
                                                                            curso2.curso curso2,
                                                                            curso2.nombre equivalente2,
                                                                            curso2.codigo codigoequivalente2,
                                                                            curso3.curso curso3,
                                                                            curso3.nombre equivalente3,
                                                                            curso3.codigo codigoequivalente3,
                                                                            sobresee,
                                                                            sobresee1,
                                                                            sobresee2,
                                                                            sobresee3,
                                                                            estado

                                                                     FROM   curso_equivalente
                                                                        INNER JOIN curso
                                                                            ON curso_equivalente.curso_equivalente = curso.curso
                                                                        LEFT JOIN curso curso2
                                                                            ON curso_equivalente.curso_equivalente2 = curso2.curso
                                                                        LEFT JOIN curso curso3
                                                                            ON curso_equivalente.curso_equivalente3 = curso3.curso
                                                                     WHERE  curso_equivalente.curso = {$intCurso}
                                                                     ORDER BY curso.curso";
                                                    //debugQuery($strQuery);
                                                    $qTMP = db_query($strQuery);
                                                    $arrInfo = array();
                                                    if( $rTMP = db_fetch_assoc($qTMP ) ) {
                                                        ?>
                                                         <tr>
                                                            <td colspan="7" align="left">
                                                                Los cursos ser�n equivalentes al curso <b><?php print $strNombreCurso; ?></b> para requisitos de asignaci�n y cierre de p�nsum. La combinaci�n de m�s de un curso implica que todos los cursos deben ser aprobados.
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td>
                                                                <table width="100%" cellpadding="0" cellspacing="0">
                                                                    <tr>
                                                                        <td width="2%" class="row0">&nbsp;</td>
                                                                        <td width="25%" class="row0">C�digo</td>
                                                                        <td width="30%" class="row0">Curso</td>
                                                                        <td width="28%" class="row0">Sobresee</td>
                                                                        <td width="5%" class="row0" align="center">Estado</td>
                                                                        <td width="5%" class="row0" align="center">&nbsp;</td>
                                                                        <td width="5%" class="row0">&nbsp;</td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                        <?php
                                                        do {
                                                            $intContCursosEquivalentesII++;
                                                            $strClass = ($strClass == "row1") ? "row2" : "row1";
                                                            $strClassEqui = "rowEqui";
                                                            ?>
                                                            <tr id="trRowEquivalente_<?php print $intContCursosEquivalentesII; ?>">
                                                                <td>
                                                                    <table width="100%" cellpadding="0" cellspacing="0">
                                                                        <tr>
                                                                            <td width="2%">
                                                                                &nbsp;
                                                                            </td>
                                                                            <td width="25%" class="<?php print $strClassEqui; ?>">
                                                                                <div id="divCodigoCursoEquivalenteII_<?php print $intContCursosEquivalentesII; ?>_1">
                                                                                    <a href="adm_academico_detalle_curso.php?curso=<?php print $rTMP["CURSO"]?>&facultad=<?php print $intFacultad; ?>">
                                                                                        <?php print isset($rTMP["CODIGOEQUIVALENTE"]) ? $rTMP["CODIGOEQUIVALENTE"] : "";?>
                                                                                    </a>
                                                                                </div>
                                                                                <input type="text" class="field_textbox inputSizeComplete" id="txtCodigoCursoEquivalenteII_<?php print $intContCursosEquivalentesII?>_1" value="<?php print isset($rTMP["CODIGOEQUIVALENTE"]) ? $rTMP["CODIGOEQUIVALENTE"] : "";?>" style="display: none;">
                                                                            </td>
                                                                            <td width="30%" class="<?php print $strClassEqui; ?>" align="left" >
                                                                                <div id="divCursoEquivalenteII_<?php print $intContCursosEquivalentesII; ?>_1">
                                                                                    <a href="adm_academico_detalle_curso.php?curso=<?php print $rTMP["CURSO"]?>&facultad=<?php print $intFacultad; ?>">
                                                                                        <?php print isset($rTMP["EQUIVALENTE"]) ? $rTMP["EQUIVALENTE"] : "";?>
                                                                                    </a>
                                                                                </div>
                                                                                <input type="text" class="field_textbox inputSizeComplete" id="txtCursoEquivalenteII_<?php print $intContCursosEquivalentesII?>_1" value="<?php print isset($rTMP["EQUIVALENTE"]) ? $rTMP["EQUIVALENTE"] : "";?>" style="display: none;">
                                                                                <input type="hidden" name="hidUpdateEquivalente_<?php print $intContCursosEquivalentesII; ?>_1" id="hidUpdateEquivalente_<?php print $intContCursosEquivalentesII; ?>_1" value="<?php print $rTMP["CURSO"]; ?>">
                                                                                <input type="hidden" id="hidUpdateControlEquivalente_<?php print $intContCursosEquivalentesII; ?>" name="hidUpdateControlEquivalente_<?php print $intContCursosEquivalentesII; ?>" value="<?php print $rTMP["CURSO"]; ?>">
                                                                                <input type="hidden" name="hidDeleteCursoEquivalenteII_<?php print $intContCursosEquivalentesII; ?>" value="N">
                                                                            </td>
                                                                            <td width="28%" class="<?php print $strClassEqui; ?>">
                                                                                <div id="divSobresee_<?php print $intContCursosEquivalentesII; ?>_1">
                                                                                    <?php
                                                                                    if(!empty($rTMP["SOBRESEE1"]) || !empty($rTMP["SOBRESEE"]) ){
                                                                                        print isset($arrCondicion[$rTMP["SOBRESEE1"]]) ?  $arrCondicion[$rTMP["SOBRESEE1"]] : $arrCondicion[$rTMP["SOBRESEE"]];
                                                                                    }else{
                                                                                        print "&nbsp;";
                                                                                    }
                                                                                    ?>
                                                                                </div>
                                                                                <select name="sltSobresee_<?php print $intContCursosEquivalentesII; ?>_1" class="field_selectbox inputSizeComplete" style="display: none;">
                                                                                    <option value=""></option>
                                                                                    <?php
                                                                                    $keySobreseII = isset($rTMP["SOBRESEE1"]) ? $rTMP["SOBRESEE1"] : $rTMP["SOBRESEE"];
                                                                                    reset($arrCondicion);
                                                                                    foreach( $arrCondicion as  $arrTMP['key'] => $arrTMP['value'])  {
                                                                                        $strSelected = ($arrTMP["key"] == $keySobreseII ) ? "selected" : "";
                                                                                        ?>
                                                                                        <option value="<?php print $arrTMP["key"]; ?>" <?php print $strSelected; ?>><?php print $arrTMP["value"]; ?></option>
                                                                                        <?php
                                                                                    }
                                                                                    ?>
                                                                                </select>
                                                                            </td>
                                                                            <td width="5%" class="<?php print $strClassEqui; ?>" align="center">
                                                                                <?php
                                                                                $strPathImgOk = strGetCoreImageWithPath("ok.png");
                                                                                $strPathImgWarning = strGetCoreImageWithPath("warning.gif");
                                                                                $strPathImgUndo = "";
                                                                                $strPathImgWarningGray = strGetCoreImageWithPath("acad_warning_gray.png");
                                                                                if(!empty($rTMP["ESTADO"]) && $rTMP["ESTADO"] == 2){
                                                                                    print "<img id='imgAutorizadoII_{$intContCursosEquivalentesII}' name='imgEquivalenteII_{$intContCursosEquivalentesII}' src='{$strPathImgOk}' style='cursor:normal' onmouseover='fntGetDetailUserAutorizaII({$intContCursosEquivalentesII},true,{$intCursoId},{$rTMP["CURSO"]});' >";
                                                                                }else{
                                                                                    if(isset($arrValorEstadosFinalesAutorizador[$rTMP["ESTADO"]]) && check_persona_access(637)){
                                                                                         ?>
                                                                                         <input type="hidden" id="hdnSeccionII_<?php print $intContCursosEquivalentesII;?>" name="hdnSeccionII_<?php print $intContCursosEquivalentesII;?>" value="N">
                                                                                         <input type="hidden" id="hdnEstadoFinalCompuesto_<?php print $intContCursosEquivalentesII;?>" name="hdnEstadoFinalCompuesto_<?php print $intContCursosEquivalentesII;?>" value="N">
                                                                                         <?php
                                                                                         print "<img id='imgPendienteII_{$intContCursosEquivalentesII}' name='imgEquivalenteII_{$intContCursosEquivalentesII}' src='{$strPathImgWarningGray}' style='cursor:normal' onmouseover='fntGetDetailUserAutorizaII({$intContCursosEquivalentesII},true,{$intCursoId},{$rTMP["CURSO"]});'>";
                                                                                    }
                                                                                    else{
                                                                                        print "<img id='imgPendienteII_{$intContCursosEquivalentesII}' name='imgEquivalenteII_{$intContCursosEquivalentesII}' src='{$strPathImgWarning}' style='cursor:normal' onmouseover='fntGetDetailUserAutorizaII({$intContCursosEquivalentesII},true,{$intCursoId},{$rTMP["CURSO"]});'>";
                                                                                      }
                                                                                }
                                                                                ?>
                                                                            </td>
                                                                            <td width="5%" align="center">
                                                                                <?php
                                                                                if($rTMP["ESTADO"] == 2){
                                                                                    if(isset($arrAccesoModificarCursosEqui[4])){
                                                                                        $strPathImgDel = strGetCoreImageWithPath("del.png");
                                                                                        print "<img id='imgSolicitudAnularEqII_{$intContCursosEquivalentesII}' name='imgSolicitudAnularEqII_{$intContCursosEquivalentesII}' src='{$strPathImgDel}' onclick='fntGetSolicitudAnulacion({$intCursoId},{$rTMP["CURSO"]},{$intContCursosEquivalentesII},false)' style='cursor:pointer;'>";
                                                                                    }
                                                                                }else{
                                                                                    print "&nbsp;";
                                                                                }
                                                                                ?>
                                                                            </td>
                                                                            <td width="5%">
                                                                                <?php
                                                                                if(isset($arrValorEstadosFinalesAutorizador[$rTMP["ESTADO"]]) && check_persona_access(637)){
                                                                                    $strPathImgUndo = strGetCoreImageWithPath("undo_green.png");
                                                                                    print "<img title='Nueva solicitud de autorizaci�n' id='imgAutorizadoII_{$intContCursosEquivalentesII}' name='imgEquivalenteII_{$intContCursosEquivalentesII}'  src='{$strPathImgUndo}' style='cursor: pointer;' onclick='fntUndoSolicitudCompuestaEquivalenciaGeneral({$intContCursosEquivalentesII},{$rTMP["CURSO"]},{$intCursoId});'>";
                                                                                }
                                                                                else{
                                                                                    print "&nbsp;";
                                                                                }
                                                                                ?>
                                                                            </td>
                                                                            <td >
                                                                                <?php 
                                                                                if( isset($arrEliminarEqGen[3]) ){
                                                                                    ?>
                                                                                    <img id="imgDeleteCursoEquivalente" src="<?php print strGetCoreImageWithPath("del.png"); ?>" style="cursor: pointer;" title="Eliminar equivalencia general" onclick="fntDeleteEquivalencia(<?php print $intCursoId; ?>,<?php print $rTMP["CURSO"]; ?>,<?php print $intContCursosEquivalentesII; ?>,false);" >                                                                        
                                                                                    <?php        
                                                                                }else{
                                                                                    print '&nbsp;';
                                                                                }
                                                                                ?>
                                                                            </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td class="<?php print $strClassEqui; ?>">
                                                                                <div id="divAnd_<?php print $intContCursosEquivalentesII; ?>_2" style="display: <?php print isset($rTMP["CODIGOEQUIVALENTE2"]) ? "" : "none";?>;">y</div>
                                                                            </td>
                                                                            <td class="<?php print $strClassEqui; ?>">
                                                                                <div id="divCodigoCursoEquivalenteII_<?php print $intContCursosEquivalentesII; ?>_2">
                                                                                    <a href="adm_academico_detalle_curso.php?curso=<?php print $rTMP["CURSO2"]?>&facultad=<?php print $intFacultad; ?>">
                                                                                        <?php print isset($rTMP["CODIGOEQUIVALENTE2"]) ? $rTMP["CODIGOEQUIVALENTE2"] : "";?>
                                                                                    </a>
                                                                                </div>
                                                                                <input type="text" class="field_textbox inputSizeComplete" id="txtCodigoCursoEquivalenteII_<?php print $intContCursosEquivalentesII?>_2" value="<?php print isset($rTMP["CODIGOEQUIVALENTE2"]) ? $rTMP["CODIGOEQUIVALENTE2"] : "";?>" style="display: none;">
                                                                            </td>
                                                                            <td class="<?php print $strClassEqui; ?>" align="left" id="TdEquivalente_<?php print $intContCursosEquivalentesII; ?>_2">
                                                                                <div id="divCursoEquivalenteII_<?php print $intContCursosEquivalentesII; ?>_2">
                                                                                    <a href="adm_academico_detalle_curso.php?curso=<?php print $rTMP["CURSO2"]?>&facultad=<?php print $intFacultad; ?>">
                                                                                        <?php print isset($rTMP["EQUIVALENTE2"]) ? $rTMP["EQUIVALENTE2"] : "&nbsp;";?>
                                                                                    </a>
                                                                                </div>
                                                                                <input type="text" class="field_textbox inputSizeComplete" id="txtCursoEquivalenteII_<?php print $intContCursosEquivalentesII?>_2" value="<?php print isset($rTMP["EQUIVALENTE2"]) ? $rTMP["EQUIVALENTE2"] : "";?>" style="display: none;">
                                                                                <input type="hidden" name="hidUpdateEquivalente_<?php print $intContCursosEquivalentesII; ?>_2" id="hidUpdateEquivalente_<?php print $intContCursosEquivalentesII; ?>_2" value="<?php print $rTMP["CURSO2"]; ?>">
                                                                            </td>
                                                                            <td class="<?php print $strClassEqui; ?>">
                                                                                <div id="divSobresee_<?php print $intContCursosEquivalentesII; ?>_2">
                                                                                    <?php
                                                                                    if(!empty($rTMP["SOBRESEE2"]) ){
                                                                                        print $arrCondicion[$rTMP["SOBRESEE2"]];
                                                                                    }else{
                                                                                        print "&nbsp;";
                                                                                    }
                                                                                    ?>
                                                                                </div>
                                                                                <select name="sltSobresee_<?php print $intContCursosEquivalentesII; ?>_2" class="field_selectbox inputSizeComplete" style="display: none;">
                                                                                    <option value=""></option>
                                                                                    <?php
                                                                                    reset($arrCondicion);
                                                                                    foreach( $arrCondicion as  $arrTMP['key'] => $arrTMP['value'])  {
                                                                                        $strSelected = ($arrTMP["key"] == $rTMP["SOBRESEE2"]) ? "selected" : "";
                                                                                        ?>
                                                                                        <option value="<?php print $arrTMP["key"]; ?>" <?php print $strSelected; ?>><?php print $arrTMP["value"]; ?></option>
                                                                                        <?php
                                                                                    }
                                                                                    ?>
                                                                                </select>
                                                                            </td>
                                                                            <td>&nbsp;</td>
                                                                            <td>&nbsp;</td>
                                                                            <td>
                                                                                &nbsp;
                                                                            </td>
                                                                            <!--<td class="<?php print $strClass; ?>" id="TdEquivalentes_<?php print $intContCursosEquivalentesII; ?>_2" style="display: <?php print !empty($rTMP["CURSO2"]) ? "none" : "block"; ?>">&nbsp;</td>-->
                                                                        </tr>
                                                                        <tr>
                                                                            <td class="<?php print $strClassEqui; ?>">
                                                                                <div id="divAnd_<?php print $intContCursosEquivalentesII; ?>_3" style="display: <?php print isset($rTMP["CODIGOEQUIVALENTE3"]) ? "" : "none";?>;">y</div>
                                                                            </td>
                                                                            <td class="<?php print $strClassEqui; ?>">
                                                                                <div id="divCodigoCursoEquivalenteII_<?php print $intContCursosEquivalentesII; ?>_3">
                                                                                    <a href="adm_academico_detalle_curso.php?curso=<?php print $rTMP["CURSO3"]?>&facultad=<?php print $intFacultad; ?>">
                                                                                        <?php print isset($rTMP["CODIGOEQUIVALENTE3"]) ? $rTMP["CODIGOEQUIVALENTE3"] : "";?>
                                                                                    </a>
                                                                                </div>
                                                                                <input type="text" class="field_textbox inputSizeComplete" id="txtCodigoCursoEquivalenteII_<?php print $intContCursosEquivalentesII?>_3" value="<?php print isset($rTMP["CODIGOEQUIVALENTE3"]) ? $rTMP["CODIGOEQUIVALENTE3"] : "";?>" style="display: none;">
                                                                            </td>
                                                                            <td class="<?php print $strClassEqui; ?>" align="left" id="TdEquivalente_<?php print $intContCursosEquivalentesII; ?>_3">
                                                                                <div id="divCursoEquivalenteII_<?php print $intContCursosEquivalentesII; ?>_3">
                                                                                    <a href="adm_academico_detalle_curso.php?curso=<?php print $rTMP["CURSO3"]?>&facultad=<?php print $intFacultad; ?>">
                                                                                        <?php print isset($rTMP["EQUIVALENTE3"]) ? $rTMP["EQUIVALENTE3"] : "&nbsp;";?>
                                                                                    </a>
                                                                                </div>
                                                                                <input type="text" class="field_textbox inputSizeComplete" id="txtCursoEquivalenteII_<?php print $intContCursosEquivalentesII?>_3" value="<?php print isset($rTMP["EQUIVALENTE3"]) ? $rTMP["EQUIVALENTE3"] : "";?>" style="display: none;">
                                                                                <input type="hidden" name="hidUpdateEquivalente_<?php print $intContCursosEquivalentesII; ?>_3" id="hidUpdateEquivalente_<?php print $intContCursosEquivalentesII; ?>_3" value="<?php print $rTMP["CURSO3"]; ?>" readonly="readonly">
                                                                            </td>
                                                                            <td class="<?php print $strClassEqui ; ?>">
                                                                                <div id="divSobresee_<?php print $intContCursosEquivalentesII; ?>_3">
                                                                                    <?php
                                                                                    if(!empty($rTMP["SOBRESEE3"]) ){
                                                                                        print $arrCondicion[$rTMP["SOBRESEE3"]];
                                                                                    }else{
                                                                                        print "&nbsp;";
                                                                                    }
                                                                                    ?>
                                                                                </div>
                                                                                <select name="sltSobresee_<?php print $intContCursosEquivalentesII; ?>_3" class="field_selectbox inputSizeComplete" style="display: none;">
                                                                                    <option value=""></option>
                                                                                    <?php
                                                                                    reset($arrCondicion);
                                                                                    foreach( $arrCondicion as  $arrTMP['key'] => $arrTMP['value'])  {
                                                                                        $strSelected = ($arrTMP["key"] == $rTMP["SOBRESEE3"]) ? "selected" : "";
                                                                                        ?>
                                                                                        <option value="<?php print $arrTMP["key"]; ?>" <?php print $strSelected; ?>><?php print $arrTMP["value"]; ?></option>
                                                                                        <?php
                                                                                    }
                                                                                    ?>
                                                                                </select>
                                                                            </td>
                                                                            <td class="<?php print $strClassEqui; ?>" >&nbsp;</td>
                                                                            <td class="<?php print $strClassEqui; ?>" >&nbsp;</td>
                                                                            <td class="<?php print $strClassEqui; ?>" align="left" id="rowsDeleteEquivalente_<?php print $intContCursosEquivalentesII; ?>">
                                                                                <?php
                                                                                if( empty($rTMP["ESTADO"]) ){
                                                                                    ?>
                                                                                    <img id="imgDeleteEquivalentesII" src="<?php print strGetCoreImageWithPath("del.png"); ?>" style="cursor: pointer; display: none" title="Eliminar" onclick="fntDeleteCursoEquivalenteII(<?php print $intContCursosEquivalentesII; ?>);">
                                                                                    <?php
                                                                                }else if($rTMP["ESTADO"] == 2){
                                                                                    print "<input type='hidden' id='hidNoCheckedCamposEquiII_{$intContCursosEquivalentesII}' value='Y' readonly=\"readonly\">&nbsp;";
                                                                                }else{
                                                                                    print "&nbsp;";
                                                                                }
                                                                                ?>
                                                                                <script type="text/javascript">
                                                                                for(var i = 1; i <=3; i++){
                                                                                    $("input[id='txtCursoEquivalenteII_<?php print $intContCursosEquivalentesII; ?>_"+ i +"']").autocomplete({
                                                                                       source : "<?php print $strAction; ?>?sendAutoCompleteCursos=true&facultad=<?php print $intFacultad; ?>",
                                                                                       minLength: 2,
                                                                                       select: function( event, ui ) {
                                                                                           var arrSplit = $(this).attr("id").split("_");
                                                                                           $("input[name='hidUpdateEquivalente_<?php print $intContCursosEquivalentesII; ?>_"+ arrSplit[2] +"']").val(ui.item.id);
                                                                                           $("input[id='txtCodigoCursoEquivalenteII_<?php print $intContCursosEquivalentesII; ?>_"+ arrSplit[2] +"']").val(ui.item.result);
                                                                                       }
                                                                                    });

                                                                                    $("input[id='txtCodigoCursoEquivalenteII_<?php print $intContCursosEquivalentesII; ?>_"+ i +"']").autocomplete({
                                                                                       source : "<?php print $strAction; ?>?sendAutoCompleteCursos=true&cod=true&facultad=<?php print $intFacultad; ?>",
                                                                                       minLength: 2,
                                                                                       select: function( event, ui ) {
                                                                                           var arrSplit = $(this).attr("id").split("_");
                                                                                           $("input[name='hidUpdateEquivalente_<?php print $intContCursosEquivalentesII; ?>_"+ arrSplit[2] +"']").val(ui.item.id);
                                                                                           $("input[id='txtCursoEquivalenteII_<?php print $intContCursosEquivalentesII; ?>_"+ arrSplit[2] +"']").val(ui.item.result);
                                                                                       }
                                                                                    });
                                                                                }
                                                                                </script>
                                                                                &nbsp;
                                                                            </td>
                                                                        </tr>
                                                                    </table>
                                                                    <div id="divOr" style="color: #278EAF; display: none;">O</div>
                                                                </td>
                                                            </tr>
                                                            <?php
                                                        }while($rTMP = db_fetch_assoc($qTMP));
                                                    }
                                                    else {
                                                        ?>
                                                        <tr id="rowSinEquivalencia">
                                                            <td colspan="8"><span id="NotaCursoEquivalente" name="NotaCursoEquivalente"  class="editTitlesLeft" style="font-style: italic;"> No existe ninguna equivalencia registrada para este curso. </span></td>
                                                        </tr>
                                                        <tr>
                                                            <td>
                                                                <table width="100%" cellpadding="0" cellspacing="0">
                                                                    <tr>
                                                                        <td width="2%" class="row0">&nbsp;</td>
                                                                        <td width="25%" class="row0">C�digo</td>
                                                                        <td width="30%" class="row0">Curso</td>
                                                                        <td width="28%" class="row0">Sobresee</td>
                                                                        <td width="10%" class="row0">&nbsp;</td>
                                                                        <td width="5%" class="row0">&nbsp;</td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                        <?php
                                                    }
                                                    ?>
                                                    <tr>
                                                        <td colspan="8" align="left">
                                                            <img src="<?php print strGetCoreImageWithPath("add.png"); ?>" style="cursor: pointer; display: none;" onclick="addRowCursoEquivalenteII();" id="imgAddEquivalentes" title="<?php print $lang["ADD"];?>">
                                                        </td>
                                                    </tr>
                                                </table>
                                                <script type="text/javascript">
                                                    var intContador2 = <?php print $intContCursosEquivalentesII; ?>;
                                                    var arrBubblePopop = Array();
                                                    var arrBubblePopopII = Array();
                                                    function fntGetSolicitudAnulacion(intCurso, intCursoEquivalente, intContadorCursoEquivalente, boolEquivalenteI){
                                                        $.ajax({
                                                            url: "<?php print $strAction;  ?>?facultad=<?php print $intFacultad; ?>",
                                                            data: {
                                                                BlurSolicitarAnulacion : true,
                                                                DeleteEquivalencia: false,
                                                                boolEquivalenteI:boolEquivalenteI,
                                                                curso : intCurso,
                                                                curso_equivalente : intCursoEquivalente,
                                                                intContadorCursoEquivalente : intContadorCursoEquivalente
                                                            },
                                                            dataType: "html",
                                                            type: "POST",
                                                            async: false,
                                                            beforeSend: function() {
                                                                intTop = ( $(window).height() * 1 ) / 2;
                                                                $("#divShowLoadingGeneralBig").css("top", intTop);
                                                                $("#divShowLoadingGeneralBig").css("left", 0);
                                                                $("#divShowLoadingGeneralBig").show();
                                                            },
                                                            success: function(data) {
                                                                $("#divShowLoadingGeneralBig").hide();
                                                                $("#ui-dialog-title-divSolicitarAnulacion").text('Solicitar anulaci�n equivalencia general');
                                                                $("#divSolicitarAnulacion").html(data);
                                                                $("#divSolicitarAnulacion").dialog('open');

                                                            }
                                                        });
                                                    }
                                                    
                                                    function fntDeleteEquivalencia(intCurso, intCursoEquivalente, intContadorCursoEquivalente, boolEquivalenteI){
                                                        $.ajax({
                                                            url: "<?php print $strAction;  ?>?facultad=<?php print $intFacultad; ?>",
                                                            data: {
                                                                BlurSolicitarAnulacion : true,
                                                                DeleteEquivalencia: true,
                                                                boolEquivalenteI:boolEquivalenteI,
                                                                curso : intCurso,
                                                                curso_equivalente : intCursoEquivalente,
                                                                intContadorCursoEquivalente : intContadorCursoEquivalente
                                                            },
                                                            dataType: "html",
                                                            type: "POST",
                                                            async: false,
                                                            beforeSend: function() {
                                                                intTop = ( $(window).height() * 1 ) / 2;
                                                                $("#divShowLoadingGeneralBig").css("top", intTop);
                                                                $("#divShowLoadingGeneralBig").css("left", 0);
                                                                $("#divShowLoadingGeneralBig").show();
                                                            },
                                                            success: function(data) {
                                                                $("#divShowLoadingGeneralBig").hide();
                                                                $("#ui-dialog-title-divSolicitarAnulacion").text('Eliminar equivalencia general');
                                                                $("#divSolicitarAnulacion").html(data);
                                                                $("#divSolicitarAnulacion").dialog('open');

                                                            }
                                                        });
                                                    }



                                                    function fntUndoSolicitudIndividualEquivalenciaGeneral(intIndex,intCursoEquivalente,intCurso){
                                                        $("#hdnEstadoFinal_"+intIndex).attr("value","Y");
                                                        $("#hdnSeccionI_"+intIndex).attr("value","Y");
                                                        fntEditarCursoAutorizacion();
                                                        checkForm();

                                                    }

                                                    function fntUndoSolicitudCompuestaEquivalenciaGeneral(intIndex,intCursoEquivalente,intCurso){
                                                        $("#hdnEstadoFinalCompuesto_"+intIndex).attr("value","Y");
                                                        $("#hdnSeccionII_"+intIndex).attr("value","Y");
                                                        fntEditarCursoAutorizacion();
                                                        checkForm();

                                                    }

                                                    function fntGetDetailUserAutoriza(intContadorCursoEquivalenteI,boolAutorizado, intCurso, intCursoEquivalente){

                                                        if( arrBubblePopop[intContadorCursoEquivalenteI] ) {
                                                            $("img[name='imgEquivalenteI_"+ intContadorCursoEquivalenteI +"']").ShowBubblePopup();
                                                        }
                                                        else {
                                                            $.ajax({
                                                                url: "<?php print $strAction;  ?>",
                                                                data: {
                                                                    getDetailUser : true,
                                                                    curso : intCurso,
                                                                    curso_equivalente : intCursoEquivalente
                                                                },
                                                                dataType: "html",
                                                                type: "POST",
                                                                async: false,
                                                                beforeSend: function() {
                                                                    intTop = ( $(window).height() * 1 ) / 2;
                                                                    $("#divShowLoadingGeneralBig").css("top", intTop);
                                                                    $("#divShowLoadingGeneralBig").css("left", 0);
                                                                    $("#divShowLoadingGeneralBig").show();
                                                                },
                                                                success: function(data) {
                                                                    $("#divShowLoadingGeneralBig").hide();
                                                                    $("img[name='imgEquivalenteI_"+ intContadorCursoEquivalenteI +"']").CreateBubblePopup({
                                                                            distance: "0px",
                                                                            width: "350",
                                                                            tail:{
                                                                                align:'center',
                                                                                hidden: false
                                                                            },
                                                                            innerHtml: data,
                                                                            themeName: 'all-black',
                                                                            themePath: 'core/jquery/bubblepopup/jquerybubblepopup-theme'
                                                                        });
                                                                    arrBubblePopop[intContadorCursoEquivalenteI] = 1;
                                                                }
                                                            });
                                                        }

                                                    }

                                                    function fntGetDetailUserAutorizaII(intContadorCursoEquivalenteII,boolAutorizado, intCurso, intCursoEquivalente){

                                                        if( arrBubblePopopII[intContadorCursoEquivalenteII] ) {
                                                            $("img[name='imgEquivalenteII_"+ intContadorCursoEquivalenteII +"']").ShowBubblePopup();
                                                        }
                                                        else {
                                                            $.ajax({
                                                                url: "<?php print $strAction;  ?>",
                                                                data: {
                                                                    getDetailUser : true,
                                                                    curso : intCurso,
                                                                    curso_equivalente : intCursoEquivalente
                                                                },
                                                                dataType: "html",
                                                                type: "POST",
                                                                async: false,
                                                                beforeSend: function() {
                                                                    intTop = ( $(window).height() * 1 ) / 2;
                                                                    $("#divShowLoadingGeneralBig").css("top", intTop);
                                                                    $("#divShowLoadingGeneralBig").css("left", 0);
                                                                    $("#divShowLoadingGeneralBig").show();
                                                                },
                                                                success: function(data) {
                                                                    $("#divShowLoadingGeneralBig").hide();
                                                                    $("img[name='imgEquivalenteII_"+ intContadorCursoEquivalenteII +"']").CreateBubblePopup({
                                                                            distance: "0px",
                                                                            width: "350",
                                                                            tail:{
                                                                                align:'center',
                                                                                hidden: false
                                                                            },
                                                                            innerHtml: data,
                                                                            themeName: 'all-black',
                                                                            themePath: 'core/jquery/bubblepopup/jquerybubblepopup-theme'
                                                                        });
                                                                    arrBubblePopopII[intContadorCursoEquivalenteII] = 1;
                                                                }
                                                            });
                                                        }

                                                    }
                                                </script>
                                            </td>
                                        </tr>
                                    </table>
                                    <div id="acordionPadreHijo">
                                        <h3><a href="#">Padre / hijo</a></h3>
                                        <div>
                                            <table width="95%" align="center" cellpadding="0" cellspacing="0">
                                                <!-- CURSO PADRE -->
                                                <tr>
                                                    <td class="Heading1" colspan="3"><?php print $lang["ACADEMICO_CURSOS_PENSUM_DETALLE_CURSO_PADRE"]; ?></td>
                                                </tr>
                                                <tr>
                                                    <td>&nbsp;</td>
                                                    <td>&nbsp;</td>
                                                    <td align="center">
                                                        <table width="100%" align="center" cellpadding="5" cellspacing="0" id="tblCursoPadre">
                                                            <tr>
                                                                <td width="10%" class="row0">C�digo</td>
                                                                <td width="40%" class="row0">Curso</td>
                                                                <td width="5%" class="row0">&nbsp;</td>
                                                                <td width="45%" >&nbsp;</td>
                                                            </tr>
                                                            <tr>
                                                                <td align="left" width="10%" nowrap>
                                                                    <?php
                                                                    $intCursoPadreId = intval(sqlGetValueFromKey("SELECT SUBORDINADO_DE FROM {$cfg["academico"]["schema"]}.CURSO WHERE CURSO = {$intCurso}"));
                                                                    $intContCursoPadre = intval(sqlGetValueFromKey("SELECT COUNT(SUBORDINADO_DE) FROM {$cfg["academico"]["schema"]}.CURSO WHERE CURSO = {$intCurso}"));
                                                                    //drawDebug($intContCursoPadre);
                                                                    if( empty($intCursoPadreId) )$intCursoPadreId = 0;
                                                                    $strCursoPadre = ($intCursoPadre == 0) ? "&nbsp;Ninguno" : sqlGetValueFromKey("SELECT nombre FROM curso WHERE curso = {$intCursoPadre}");
                                                                    $strCodigoPadre = ($intCursoPadre == 0) ? "&nbsp;Ninguno" : sqlGetValueFromKey("SELECT codigo FROM curso WHERE curso = {$intCursoPadre}");
                                                                    ?>
                                                                    <div  id="SubordinadoDe" class="editTitlesLeft">
                                                                        <?php
                                                                        if($intCursoPadre > 0){
                                                                            ?>
                                                                            <a href="adm_academico_detalle_curso.php?curso=<?php print $intCursoPadreId; ?>&facultad=<?php print $intFacultad; ?>">
                                                                                <?php print $strCodigoPadre; ?>
                                                                            </a>
                                                                            <?php
                                                                        }
                                                                        else {
                                                                            ?>
                                                                            <span style="font-style: italic;"><?php print $strCodigoPadre; ?></span>
                                                                            <?php
                                                                        }
                                                                        ?>
                                                                    </div>
                                                                    <input type="text" class="field_textbox inputSizeComplete" id="txtCodigoPadre" value="<?php print $strCodigoPadre;?>" style="display: none;">
                                                                    <input type="hidden" name="hidSubordinadoDe" id="hidSubordinadoDe" value="<?php print $intCursoPadre; ?>">
                                                                    <input type="hidden" name="hidDeletePadre" value="N">
                                                                </td>
                                                                <td>
                                                                    <input type="text" class="field_textbox inputSizeComplete" id="txtCursoPadre" value="<?php print $strCursoPadre; ?>" style="display: none;">
                                                                    <div  id="SubordinadoDe" class="editTitlesLeft">
                                                                        <?php
                                                                        if( $intCursoPadre > 0 ) {
                                                                            ?>
                                                                            <a href="adm_academico_detalle_curso.php?curso=<?php print $intCursoPadreId; ?>&facultad=<?php print $intFacultad; ?>">
                                                                                <?php print $strCursoPadre; ?>
                                                                            </a>
                                                                            <?php
                                                                        }
                                                                        else {
                                                                            ?>
                                                                            <span style="font-style: italic;"><?php print $strCursoPadre; ?></span>
                                                                            <?php
                                                                        }
                                                                        ?>
                                                                    </div>
                                                                </td>
                                                                <td align="center">
                                                                    <div id="DivDeleteCursoPadre" style="display: none;">
                                                                        <img id="ImgDeleteCursoPadre" src="<?php print strGetCoreImageWithPath("del.png"); ?>" style="cursor: pointer;" title="Eliminar" onclick="fntDeleteRowPadre();">
                                                                    </div>
                                                                    &nbsp;
                                                                </td>
                                                                <td>
                                                                    <script type="text/javascript">
                                                                        $("input[id='txtCursoPadre']").autocomplete({
                                                                           source : "<?php print $strAction; ?>?sendAutoCompleteCursos=true&facultad=<?php print $intFacultad; ?>",
                                                                           minLength: 2,
                                                                           select: function( event, ui ) {
                                                                               var arrSplit = $(this).attr("id").split("_");
                                                                               $("input[name='hidSubordinadoDe']").val(ui.item.id);
                                                                               $("#txtCodigoPadre").val(ui.item.result);
                                                                           }
                                                                        });
                                                                        $("input[id='txtCodigoPadre']").autocomplete({
                                                                           source : "<?php print $strAction; ?>?sendAutoCompleteCursos=true&cod=true&facultad=<?php print $intFacultad; ?>",
                                                                           minLength: 2,
                                                                           select: function( event, ui ) {
                                                                               var arrSplit = $(this).attr("id").split("_");
                                                                               $("input[name='hidSubordinadoDe']").val(ui.item.id);
                                                                               $("#txtCursoPadre").val(ui.item.result);
                                                                           }
                                                                        });
                                                                        $("input[id='txtCodigoPadre']").focus(function(){
                                                                           $(this).select();
                                                                        });
                                                                    </script>
                                                                    &nbsp;
                                                                </td>
                                                            </tr>
                                                            <!--<tr id="rowSubordinadoDe" style="display: none;">
                                                                <td colspan="2" style="display: none;">
                                                                    <img src="<?php print strGetCoreImageWithPath("add.png"); ?>" style="cursor: pointer; <?php print ($intCursoPadre > 0) ? "display:none" : "display:block";?>" onclick="addRowCursoPadre();" title="<?php print $lang["ADD"];?>">
                                                                </td>
                                                            </tr>-->
                                                        </table>
                                                    </td>
                                                </tr>
                                                <tr><td>&nbsp;</td></tr>
                                                <!-- DEPENDIENTES -->
                                                <tr>
                                                    <td class="Heading1" colspan="3"><?php print $lang["ACADEMICO_CURSOS_PENSUM_DETALLE_CURSOS_DEPENDIENTES"]; ?></td>
                                                </tr>
                                                <tr>
                                                    <td>&nbsp;</td>
                                                    <td>&nbsp;</td>
                                                    <td>
                                                        <table width="100%" align="center" cellpadding="5" cellspacing="0" id="tblCursosDependientes">

                                                            <tr>
                                                                <td width="10%" class="row0">C�digo</td>
                                                                <td width="40%" class="row0">Curso</td>
                                                                <td width="5%" class="row0">&nbsp;</td>
                                                                <td width="45%">&nbsp;</td>
                                                            </tr>
                                                            <?php
                                                            $intContCursosPendientes = 0;
                                                            $strClass = "row2";
                                                            $strQuery = "SELECT C.CURSO ID, C.CODIGO, C.NOMBRE
                                                                         FROM   {$cfg["academico"]["schema"]}.CURSO C,
                                                                                {$cfg["academico"]["schema"]}.AREA A
                                                                         WHERE  SUBORDINADO_DE = '{$intCursoId}'
                                                                         AND    C.AREA = A.AREA
                                                                         AND    A.FACULTAD = '{$intFacultad}'";
                                                            //debugQuery($strQuery);
                                                            $qTMP = db_query($strQuery);
                                                            if( $rTMP = db_fetch_assoc($qTMP) ) {

                                                                do {
                                                                    $intContCursosPendientes++;
                                                                    $strClass = ($strClass == "row2") ? "row1" : "row2";
                                                                    ?>
                                                                    <tr>
                                                                        <td class="<?php print $strClass; ?>">
                                                                            <div id="divCursoDependiente_<?php print $intContCursosPendientes; ?>">
                                                                                <a href="adm_academico_detalle_curso.php?curso=<?php print $rTMP["ID"]; ?>&facultad=<?php print $intFacultad; ?>">
                                                                                    <?php print $rTMP["CODIGO"]; ?>
                                                                                </a>
                                                                            </div>
                                                                            <input type="text" class="field_textbox inputSizeComplete" id="txtCodigoCursoDependiente_<?php print $intContCursosPendientes; ?>" value="<?php print $rTMP["CODIGO"]; ?>" style="display: none;">
                                                                        </td>
                                                                        <td class="<?php print $strClass; ?>">
                                                                            <input type="text" class="field_textbox inputSizeComplete" id="txtCursoDependiente_<?php print $intContCursosPendientes; ?>" value="<?php print $rTMP["NOMBRE"]; ?>" style="display: none;">
                                                                            <input type="hidden" value="<?php print $rTMP["ID"]; ?>" name="hidDependiente_<?php print $intContCursosPendientes; ?>"  id="hidDependiente_<?php print $intContCursosPendientes; ?>">
                                                                            <div id="divCursoDependiente_<?php print $intContCursosPendientes; ?>">
                                                                                <a href="adm_academico_detalle_curso.php?curso=<?php print $rTMP["ID"]; ?>&facultad=<?php print $intFacultad; ?>">
                                                                                    <?php print $rTMP["NOMBRE"]; ?>
                                                                                </a>
                                                                            </div>
                                                                        </td>
                                                                        <td class="<?php print $strClass; ?>" align="center" id="rowDependiente_<?php print $intContCursosPendientes; ?>" name="rowDependiente_<?php print $intContCursosPendientes; ?>">
                                                                            <img id="DeleteDepediente_<?php print $intContCursosPendientes; ?>" name="DeleteDepediente_<?php print $intContCursosPendientes; ?>" src="<?php print strGetCoreImageWithPath("del.png"); ?>" style="cursor: pointer; display: none" title="Eliminar" onclick="fntDeleteRowDependiente(<?php print $intContCursosPendientes; ?>);" >
                                                                        </td>
                                                                        <td>
                                                                        <script type="text/javascript">
                                                                            $("input[id='txtCursoDependiente_<?php print $intContCursosPendientes; ?>']").autocomplete({
                                                                               source : "<?php print $strAction; ?>?sendAutoCompleteCursos=true&facultad=<?php print $intFacultad; ?>",
                                                                               minLength: 2,
                                                                               select: function( event, ui ) {
                                                                                   $("input[name='hidDependiente_<?php print $intContCursosPendientes; ?>']").val(ui.item.id);
                                                                                   $("input[id='txtCodigoCursoDependiente_<?php print $intContCursosPendientes; ?>']").val(ui.item.result);
                                                                               }
                                                                            });

                                                                            $("input[id='txtCodigoCursoDependiente_<?php print $intContCursosPendientes; ?>']").autocomplete({
                                                                               source : "<?php print $strAction; ?>?sendAutoCompleteCursos=true&cod=true&facultad=<?php print $intFacultad; ?>",
                                                                               minLength: 2,
                                                                               select: function( event, ui ) {
                                                                                   $("input[name='hidDependiente_<?php print $intContCursosPendientes; ?>']").val(ui.item.id);
                                                                                   $("input[id='txtCursoDependiente_<?php print $intContCursosPendientes; ?>']").val(ui.item.result);
                                                                               }
                                                                            });
                                                                        </script>
                                                                        &nbsp;
                                                                        </td>
                                                                    </tr>
                                                                    <?php

                                                                }while($rTMP = db_fetch_assoc($qTMP));
                                                            }
                                                            else {
                                                                ?>
                                                                <tr id="rowNinguno" name="rowNinguno">
                                                                    <td width="10%" class="editTitlesLeft" style="font-style: italic;">Ninguno</td>
                                                                    <td width="40%" class="editTitlesLeft" style="font-style: italic;">Ninguno</td>
                                                                    <td width="5%">&nbsp;</td>
                                                                    <td width="45%">&nbsp;</td>
                                                                </tr>
                                                                <?php
                                                            }
                                                            ?>
                                                            <tr id="rowDependiente" style="display: none;">
                                                                <td colspan="4">
                                                                    <img src="<?php print strGetCoreImageWithPath("add.png"); ?>" style="cursor: pointer;" onclick="addRowCursoDependiente();" title="Agregar">
                                                                    <script type="text/javascript">
                                                                        var intContadorDependiente = <?php print $intContCursosPendientes; ?>;
                                                                    </script>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                    <script type="text/javascript">
                                        var strClass = "<?php print $strClass; ?>";

                                        $(function() {

                                            $("input[id='txtCursoPadre']").focus(function(){
                                              $(this).select();
                                           });
                                            $("input[id*='txtCursoEquivalenteI'], input[id*='txtCodigoCursoEquivalenteI']").focus(function(){
                                              $(this).select();
                                           });

                                            $("input[id*='txtCursoDependiente'], input[id*='txtCodigoCursoDependiente']").focus(function(){
                                              $(this).select();
                                           });
                                            $("#acordionPadreHijo").accordion({
                                                collapsible: true,
                                                autoHeight: false,
                                                navigation: false,
                                                active: -1
                                            });
                                        });

                                        function fntCargarSelectTipoCurso(intFila){
                                            var intPensum = $("#txtIdPensum_"+intFila).val();
                                            if(intPensum > 0){
                                                $.ajax({
                                                    url:"<?php print $strAction; ?>",
                                                    data:{
                                                       cargarSelectTipoCurso: true,
                                                       intFila:intFila
                                                    },
                                                    type:"POST",
                                                    beforeSend: function() {
                                                        $("#divShowLoadingGeneralBig").css("z-index", 1050);
                                                        $("#divShowLoadingGeneralBig").show();
                                                    },
                                                    success:function(data){
                                                        $("#divShowLoadingGeneralBig").hide();
                                                        $("#divSelectTipoCurso_"+intFila).html(data);
                                                    }
                                                });
                                            }
                                        }

                                        function fntCargarSelectTag(intFila){
                                            var intPensum = $("#txtIdPensum_"+intFila).val();
                                            if(intPensum > 0){
                                                $.ajax({
                                                    url:"<?php print $strAction; ?>",
                                                    data:{
                                                       cargarSelectTag: true,
                                                       intFila:intFila
                                                    },
                                                    type:"POST",
                                                    beforeSend: function() {
                                                        $("#divShowLoadingGeneralBig").css("z-index", 1050);
                                                        $("#divShowLoadingGeneralBig").show();
                                                    },
                                                    success:function(data){
                                                        $("#divShowLoadingGeneralBig").hide();
                                                        $("#divSelectTag_"+intFila).html(data);
                                                    }
                                                });
                                            }
                                        }

                                        function fntCargarSelectCiclo(intFila){
                                            var intPensum = $("#txtIdPensum_"+intFila).val();
                                            var intCurso = $("#hidGuardar").val();
                                            if(intPensum > 0 & intCurso >0){
                                                $.ajax({
                                                    url:"<?php print $strAction; ?>",
                                                    data:{
                                                       cargarSelectCiclo: true,
                                                       intPensum:intPensum,
                                                       intFila:intFila,
                                                       intCurso:intCurso
                                                    },
                                                    type:"POST",
                                                    beforeSend: function() {
                                                        $("#divShowLoadingGeneralBig").css("z-index", 1050);
                                                        $("#divShowLoadingGeneralBig").show();
                                                    },
                                                    success:function(data){
                                                        $("#divShowLoadingGeneralBig").hide();
                                                        $("#divSelectCiclo_"+intFila).html(data);
                                                    }
                                                });
                                            }
                                        }


                                        function fntCursoAutocomplete(obj){
                                            var arrSplit = $(obj).attr("id").split("_");
                                            var facultad = $("#hdnFacultad").val();
                                            var intCurso = $("#hidGuardar").val();
                                            $("#txtCodigoPensum_"+arrSplit[1]).autocomplete({
                                                source: function(request, response) {
                                                    $.ajax({
                                                        url: "<?php print $strAction; ?>?sendAutoCompletePensum=true",
                                                        dataType: "json",
                                                        beforeSend: function() {
                                                            $("#divShowLoadingGeneralBig").css("z-index", 1050);
                                                            $("#divShowLoadingGeneralBig").show();
                                                        },
                                                        data: {
                                                            term : request.term,
                                                            facultad: facultad,
                                                            intCurso:intCurso
                                                        },
                                                        success: function(data) {
                                                            response(data);
                                                            $("#divShowLoadingGeneralBig").hide();
                                                        }
                                                    });
                                                },
                                                minLength: 5,
                                                select: function( event, ui ) {
                                                    $("#hdnAddPensum").val('Y');
                                                    $("#txtIdPensum_"+arrSplit[1]).val(ui.item.id);
                                                    $("#txtCodigoPensum_"+arrSplit[1]).val(ui.item.value);
                                                    $("#lblNombrePensum_"+arrSplit[1]).text(ui.item.nombre);
                                                    $("#divSelectTipoCurso_"+arrSplit[1]).show();
                                                    $("#divSelectCiclo_"+arrSplit[1]).show();
                                                    $("#divSelectTag_"+arrSplit[1]).show();
                                                    fntCargarSelectCiclo(arrSplit[1]);
                                                    fntCargarSelectTipoCurso(arrSplit[1]);
                                                    fntCargarSelectTag(arrSplit[1]);
                                                }
                                            });
                                        }

                                        //Agregar fila para los agregar cursos equivalentes seccion I
                                        function addRowCursoEquivalenteI(){
                                            $("#rowNinguno").css("display","none");
                                            strClass = (strClass == "row2") ? "row1" : "row2";
                                            intContador1++;

                                            var arrElements = Array();
                                            var arrOptions = Array();
                                            arrOptions["rowLength"] = 1;

                                            var intCelda = 1;

                                            arrElements[intCelda] = new Array();
                                            arrElements[intCelda]["cellClass"] = strClass;
                                            arrElements[intCelda]["cellAlign"] = "left";
                                            arrElements[intCelda]["isFormElement"] = false;
                                            arrElements[intCelda]["html"] = "&nbsp;";

                                            intCelda++;

                                            arrElements[intCelda] = new Array();
                                            arrElements[intCelda]["cellClass"] = strClass;
                                            arrElements[intCelda]["cellAlign"] = "left";
                                            arrElements[intCelda]["isFormElement"] = false;
                                            arrElements[intCelda]["html"] = "<input type='text' id='txtCodigoCursoEquivalenteI_"+ intContador1 +"' class='field_textbox inputSizeComplete'>";

                                            intCelda++;

                                            arrElements[intCelda] = new Array();
                                            arrElements[intCelda]["cellClass"] = strClass;
                                            arrElements[intCelda]["cellAlign"] = "left";
                                            arrElements[intCelda]["isFormElement"] = false;
                                            arrElements[intCelda]["html"] = "<input type='text' id='txtCursoEquivalenteI_"+ intContador1 +"' class='field_textbox inputSizeComplete'>";
                                            arrElements[intCelda]["html"] += "<input type='hidden' name='hidNewCursoEquivalenteI_"+ intContador1 +"'>";

                                            intCelda++;

                                            arrElements[intCelda] = new Array();
                                            arrElements[intCelda]["cellClass"] = strClass;
                                            arrElements[intCelda]["cellAlign"] = "left";
                                            arrElements[intCelda]["isFormElement"] = false;
                                            arrElements[intCelda]["html"] = "<select name='sltCursoEquivalenteSobresee_"+ intContador1 +"' class='field_selectbox inputSizeComplete'>";
                                            arrElements[intCelda]["html"] +=    "<option value=''></option>";
                                                                                <?php
                                                                                reset($arrCondicion);
                                                                                foreach( $arrCondicion as  $arrTMP['key'] => $arrTMP['value'])  {
                                                                                    ?>
                                            arrElements[intCelda]["html"] +=        "<option value='<?php print $arrTMP["key"]; ?>'><?php print $arrTMP["value"]; ?></option>";
                                                                                    <?php
                                                                                }
                                                                                ?>
                                            arrElements[intCelda]["html"] += "</select>";

                                            intCelda++;

                                            arrElements[intCelda] = new Array();
                                            arrElements[intCelda]["cellClass"] = strClass;
                                            arrElements[intCelda]["cellAlign"] = "left";
                                            arrElements[intCelda]["isFormElement"] = false;
                                            arrElements[intCelda]["html"] = "&nbsp;";

                                            intCelda++;

                                            arrElements[intCelda] = new Array();
                                            arrElements[intCelda]["cellClass"] = strClass;
                                            arrElements[intCelda]["cellAlign"] = "left";
                                            arrElements[intCelda]["isFormElement"] = false;
                                            arrElements[intCelda]["html"] = "&nbsp;";

                                            intCelda++;

                                            arrElements[intCelda] = new Array();
                                            arrElements[intCelda]["cellClass"] = strClass;
                                            arrElements[intCelda]["cellAlign"] = "left";
                                            arrElements[intCelda]["isFormElement"] = false;
                                            arrElements[intCelda]["html"] = "<img src=\"<?php print strGetCoreImageWithPath("del.png"); ?>\" style=\"cursor: pointer;\" title='Eliminar' onclick=\"fntDeleteNewRowCursoEquivalenteI(this,true);\">";

                                            addDynamicRow("tblCursoEquivalenteI","frm_detalle",arrElements,arrOptions);

                                            $("input[id='txtCursoEquivalenteI_"+ intContador1 +"']").autocomplete({
                                               source : "<?php print $strAction; ?>?sendAutoCompleteCursos=true&facultad=<?php print $intFacultad; ?>",
                                               minLength: 2,
                                               select: function( event, ui ) {
                                                   var arrSplit = $(this).attr("id").split("_");
                                                   $("input[name='hidNewCursoEquivalenteI_"+ arrSplit[1] + "']").val(ui.item.id);
                                                   $("input[id='txtCodigoCursoEquivalenteI_"+ arrSplit[1] +"']").val(ui.item.result);
                                               }
                                            });

                                            $("input[id='txtCodigoCursoEquivalenteI_"+ intContador1 +"']").autocomplete({
                                               source : "<?php print $strAction; ?>?sendAutoCompleteCursos=true&cod=true&facultad=<?php print $intFacultad; ?>",
                                               minLength: 2,
                                               select: function( event, ui ) {
                                                   var arrSplit = $(this).attr("id").split("_");
                                                   $("input[name='hidNewCursoEquivalenteI_"+ arrSplit[1] + "']").val(ui.item.id);
                                                   $("input[id='txtCursoEquivalenteI_"+ arrSplit[1] +"']").val(ui.item.result);
                                               }
                                            });
                                        }

                                        function fntRowCursoEquivalenteI(intContadorLocal) {
                                            var objHidDelete = getDocumentLayer("hidDeleteCursoEquivalenteI_" + intContadorLocal);
                                            var objTr = objHidDelete.parentNode.parentNode;

                                            for( var i = 0; i < objTr.cells.length; i++ ) {
                                                objTr.cells[i].className = "rowdelete";
                                            }
                                            objHidDelete.value = "Y";
                                        }

                                        function fntDeleteNewRowCursoEquivalenteI(objImg, boolIsNuevo){
                                            var objRow = objImg.parentNode.parentNode;
                                            if( boolIsNuevo ) {
                                                getDocumentLayer("tblCursoEquivalenteI").deleteRow(objRow.rowIndex);
                                            }
                                        }

                                        function addRowCursoPadre(){
                                            strClass = (strClass == "row2") ? "row1": "row2";
                                            var intContadorCursoPadre = 1;
                                            var arrElements = Array();
                                            var arrOptions = Array();
                                            arrOptions["rowLength"] = 1;
                                            var intCelda = 1;

                                            arrElements[intCelda] = new Array();
                                            arrElements[intCelda]["cellClass"] = strClass;
                                            arrElements[intCelda]["cellAlign"] = "left";
                                            arrElements[intCelda]["isFormElement"] = false;
                                            arrElements[intCelda]["html"] = "<input type='text' id='txtCursoPadre' class='field_textbox inputSizeComplete'>";
                                            arrElements[intCelda]["html"] += "<input type='hidden' name='hidSubordinadoDe'>";

                                            addDynamicRow("tblCursoPadre","frm_detalle",arrElements,arrOptions);

                                            $("input[id='txtCursoPadre']").autocomplete({
                                               source : "<?php print $strAction; ?>?sendAutoCompleteCursos=true&facultad=<?php print $intFacultad; ?>",
                                               minLength: 2,
                                               select: function( event, ui ) {
                                                   var arrSplit = $(this).attr("id").split("_");
                                                   $("input[name='hidSubordinadoDe']").val(ui.item.id);
                                               }
                                            });

                                        }

                                        function addRowCursoDependiente(){
                                            $("#rowNinguno").css("display","none");
                                            strClass = (strClass == "row2") ? "row1": "row2";
                                            intContadorDependiente++;
                                            var arrElements = Array();
                                            var arrOptions = Array();
                                            arrOptions["rowLength"] = 1;
                                            var intCelda = 1;

                                            arrElements[intCelda] = new Array();
                                            arrElements[intCelda]["cellClass"] = strClass;
                                            arrElements[intCelda]["cellAlign"] = "left";
                                            arrElements[intCelda]["isFormElement"] = false;
                                            arrElements[intCelda]["html"] = "<input type='text' id='txtCodigoCursoDependiente_"+ intContadorDependiente +"' class='field_textbox inputSizeComplete'>";

                                            intCelda++;

                                            arrElements[intCelda] = new Array();
                                            arrElements[intCelda]["cellClass"] = strClass;
                                            arrElements[intCelda]["cellAlign"] = "left";
                                            arrElements[intCelda]["isFormElement"] = false;
                                            arrElements[intCelda]["html"] = "<input type='text' id='txtCursoDependiente_"+ intContadorDependiente +"' class='field_textbox inputSizeComplete'>";
                                            arrElements[intCelda]["html"] += "<input type='hidden' name='hidDependiente_"+ intContadorDependiente +"'>";

                                            intCelda++;

                                            arrElements[intCelda] = new Array();
                                            arrElements[intCelda]["cellClass"] = strClass;
                                            arrElements[intCelda]["cellAlign"] = "center";
                                            arrElements[intCelda]["isFormElement"] = false;
                                            arrElements[intCelda]["html"] = "<img src='<?php print strGetCoreImageWithPath("del.png"); ?>' style='cursor: pointer;' title='Eliminar' onclick='fntDeleteRowCursoDependiente(this,true);' >";

                                            intCelda++;

                                            arrElements[intCelda] = new Array();
                                            arrElements[intCelda]["isFormElement"] = false;
                                            arrElements[intCelda]["html"] = "&nbsp;";

                                            intCelda++

                                            addDynamicRow("tblCursosDependientes","frm_detalle",arrElements,arrOptions);

                                            $("input[id='txtCursoDependiente_"+ intContadorDependiente +"']").autocomplete({
                                               source : "<?php print $strAction; ?>?sendAutoCompleteCursos=true&facultad=<?php print $intFacultad; ?>",
                                               minLength: 2,
                                               select: function( event, ui ) {
                                                   var arrSplit = $(this).attr("id").split("_");
                                                   $("input[name='hidDependiente_"+ arrSplit[1] + "']").val(ui.item.id);
                                                   $("input[id='txtCodigoCursoDependiente_"+ arrSplit[1] +"']").val(ui.item.result);
                                               }
                                            });

                                            $("input[id='txtCodigoCursoDependiente_"+ intContadorDependiente +"']").autocomplete({
                                               source : "<?php print $strAction; ?>?sendAutoCompleteCursos=true&cod=true&facultad=<?php print $intFacultad; ?>",
                                               minLength: 2,
                                               select: function( event, ui ) {
                                                   var arrSplit = $(this).attr("id").split("_");
                                                   $("input[name='hidDependiente_"+ arrSplit[1] + "']").val(ui.item.id);
                                                   $("input[id='txtCursoDependiente_"+ arrSplit[1] +"']").val(ui.item.result);
                                               }
                                            });
                                        }

                                        function fntDeleteRowCursoDependiente(objImg, boolIsNuevo){
                                            var objRow = objImg.parentNode.parentNode;
                                            if( boolIsNuevo ) {
                                                getDocumentLayer("tblCursosDependientes").deleteRow(objRow.rowIndex);
                                            }
                                        }

                                        function fntDeleteRowPadre(){
                                            var objHidDelete = getDocumentLayer("hidDeletePadre");
                                            var objTr = objHidDelete.parentNode.parentNode;

                                            for( var i = 0; i < objTr.cells.length; i++ ) {
                                                objTr.cells[i].className = "rowdelete";
                                            }
                                            objHidDelete.value = "Y";
                                            $("input[name='hidSubordinadoDe']").val("");
                                        }

                                        function fntDeleteRowPadre(){
                                            var objHidDelete = getDocumentLayer("hidDeletePadre");
                                            var objTr = objHidDelete.parentNode.parentNode;

                                            for( var i = 0; i < objTr.cells.length; i++ ) {
                                                objTr.cells[i].className = "rowdelete";
                                            }
                                            objHidDelete.value = "Y";
                                            $("input[name='hidSubordinadoDe']").val("");
                                        }

                                        function fntDeleteRowDependiente(intContadorLocal){
                                            var objHidDelete = getDocumentLayer("hidDependiente_" + intContadorLocal);
                                            var objTr = objHidDelete.parentNode.parentNode;

                                            for( var i = 0; i < objTr.cells.length; i++ ) {
                                                objTr.cells[i].className = "rowdelete";
                                            }
                                            objHidDelete.value = "Y";
                                            $("input[name='hidDependiente_"+ intContadorLocal +"']").val("");
                                        }

                                        //Agregar fila para los cursos equivalente seccion II
                                        function addRowCursoEquivalenteII(){
                                            strClass = (strClass == "row2") ? "row1" : "row2";
                                            intContador2++;

                                            var arrElements = Array();
                                            var arrOptions = Array();
                                            arrOptions["rowLength"] = 1;
                                            arrOptions["rowID"] = "trRowEquivalente_"+ intContador2;

                                            var intCelda = 1;

                                            arrElements[intCelda] = new Array();
                                            arrElements[intCelda]["cellClass"] = strClass;
                                            arrElements[intCelda]["cellAlign"] = "left";
                                            arrElements[intCelda]["isFormElement"] = false;
                                            arrElements[intCelda]["html"] = "<table width='100%' cellpadding='0' cellspacing='0'>"
                                            for(var i = 1; i <=3; i++){
                                                arrElements[intCelda]["html"] +=    "<tr>";
                                                if(i!=1){
                                                    arrElements[intCelda]["html"] +=        "<td width='2%'>y</td>";
                                                }else{
                                                    arrElements[intCelda]["html"] +=        "<td width='2%'>&nbsp;</td>";
                                                }
                                                arrElements[intCelda]["html"] +=        "<td width='25%' class='rowEqui'>";
                                                arrElements[intCelda]["html"] +=            "<input type='text' id='txtCodigoCursoEquivalenteII_"+ intContador2 +"_"+ i +"' class='field_textbox inputSizeComplete'>";
                                                arrElements[intCelda]["html"] +=        "</td>";
                                                arrElements[intCelda]["html"] +=        "<td width='30%' class='rowEqui'>";
                                                arrElements[intCelda]["html"] +=            "<input type='text' id='txtCursoEquivalenteII_"+ intContador2 +"_"+ i +"' class='field_textbox inputSizeComplete'>";
                                                arrElements[intCelda]["html"] +=            "<input type='hidden' name='hidNewCursoEquivalenteII_"+ intContador2 +"_"+ i +"'>";
                                                arrElements[intCelda]["html"] +=        "</td>";
                                                arrElements[intCelda]["html"] +=        "<td width='28%' class='rowEqui'>";
                                                arrElements[intCelda]["html"] +=            "<select name='sltSobresee_"+ intContador2 +"_"+ i +"' class='field_textbox inputSizeComplete'>";
                                                arrElements[intCelda]["html"] +=                "<option value=''></option>";
                                                                                                <?php
                                                                                                reset($arrCondicion);
                                                                                                foreach( $arrCondicion as  $arrTMP['key'] => $arrTMP['value'])  {
                                                                                                    ?>
                                                arrElements[intCelda]["html"] +=                    "<option value='<?php print $arrTMP["key"]; ?>'><?php print $arrTMP["value"]; ?></option>";
                                                                                                <?php
                                                                                                }
                                                                                                ?>
                                                arrElements[intCelda]["html"] +=            "</select>";
                                                arrElements[intCelda]["html"] +=        "</td>";
                                                arrElements[intCelda]["html"] +=        "<td width='5%'>&nbsp;</td>";
                                                arrElements[intCelda]["html"] +=        "<td width='5%'>&nbsp;</td>";
                                                if(i == 3){
                                                    arrElements[intCelda]["html"] +=        "<td width='5%'>";
                                                    arrElements[intCelda]["html"] +=            "&nbsp;<img src=\"<?php print strGetCoreImageWithPath("del.png"); ?>\" style=\"cursor: pointer;\" title='Eliminar' onclick=\"fntDeleteRowCursoEquivalenteII("+intContador2+",true);\">";
                                                    arrElements[intCelda]["html"] +=        "</td>";
                                                }else{
                                                    arrElements[intCelda]["html"] +=        "<td width='5%'>";
                                                    arrElements[intCelda]["html"] +=            "&nbsp;";
                                                    arrElements[intCelda]["html"] +=        "</td>";
                                                }
                                                arrElements[intCelda]["html"] +=    "</tr>";
                                            }
                                            arrElements[intCelda]["html"] += "</table>";
                                            arrElements[intCelda]["html"] += "<div style=\"color: #278EAF;\">O</div>";

                                            addDynamicRow("tblCursoEquivalenteII","frm_detalle",arrElements,arrOptions);

                                            for(var i = 1; i <=3; i++){
                                                $("input[id='txtCursoEquivalenteII_"+ intContador2 +"_"+ i +"']").autocomplete({
                                                   source : "<?php print $strAction; ?>?sendAutoCompleteCursos=true&facultad=<?php print $intFacultad; ?>",
                                                   minLength: 2,
                                                   select: function( event, ui ) {
                                                      // console.log(ui);
                                                       var arrSplit = $(this).attr("id").split("_");
                                                       $("input[name='hidNewCursoEquivalenteII_"+ arrSplit[1] + "_"+ arrSplit[2] +"']").val(ui.item.id);
                                                       $("input[id='txtCodigoCursoEquivalenteII_"+ arrSplit[1] +"_"+ arrSplit[2] +"']").val(ui.item.result);
                                                   }
                                                });

                                                $("input[id='txtCodigoCursoEquivalenteII_"+ intContador2 +"_"+ i +"']").autocomplete({
                                                   source : "<?php print $strAction; ?>?sendAutoCompleteCursos=true&cod=true&facultad=<?php print $intFacultad; ?>",
                                                   minLength: 2,
                                                   select: function( event, ui ) {
                                                       var arrSplit = $(this).attr("id").split("_");
                                                       $("input[name='hidNewCursoEquivalenteII_"+ arrSplit[1] + "_"+ arrSplit[2] +"']").val(ui.item.id);
                                                       $("input[id='txtCursoEquivalenteII_"+ arrSplit[1] +"_"+ arrSplit[2] +"']").val(ui.item.result);
                                                   }
                                                });
                                            }
                                            $("#NotaCursoEquivalente").hide();
                                        }

                                        //Eliminar cursos equivalente seccion II
                                        function fntDeleteCursoEquivalenteII(intContadorLocal){
                                            var objHidDelete = getDocumentLayer("hidDeleteCursoEquivalenteII_" + intContadorLocal);
                                            var objTr = objHidDelete.parentNode.parentNode;

                                            $("#trRowEquivalente_"+intContadorLocal).addClass("rowdelete");

                                            objHidDelete.value = "Y";
                                        }

                                        function fntDeleteRowCursoEquivalenteII(intContadorLocal, boolIsNuevo){
                                            if( boolIsNuevo ) {
                                                $("#trRowEquivalente_"+intContadorLocal).remove();
                                            }
                                        }

                                        function fntDeleteFila(objImg, intIndex, boolIsNuevo){
                                            var boolIsNuevo = ( boolIsNuevo ) ? true : false;
                                            var objRow = objImg.parentNode.parentNode;
                                            if( boolIsNuevo )
                                                getDocumentLayer("tbPensum").deleteRow(objRow.rowIndex);
                                            else{
                                                getDocumentLayer("hdnDeleteAcaIdiomaDocs_"+intIndex).value = "Y";
                                                for( var i = 0; i < objRow.cells.length; i++) {
                                                    objRow.cells[i].className = "rowdelete";
                                                }
                                            }
                                        }

                                        var objTabCursosRelacionados = getDocumentLayer("TabCursosRelacionados");
                                        objTabCursosRelacionados.innerHTML = "Cursos equivalentes (<?php print $intContCursoPadre + $intContCursosPendientes + $intContadorCursosEquivalente1 + $intContCursosEquivalentesII ;  ?>)";

                                    </script>
                                </div>

                                <!--INFO PENSUM-->
                                <div id="tabInfoPenum">
                                    <table width="95%" align="center" cellpadding="3" cellspacing="0">
                                        <tr>
                                            <td>
                                                <?php
                                               


                                                $strQuery = "SELECT  CP.PENSUM, MAX(TCP.TIPO_CURSO_PENSUM) TIPO, MAX(CP.NUMERO_CICLO) NUMERO_CICLO, MAX(E.NOMBRE) ESPECIALIDAD
                                                             FROM    {$cfg["academico"]["schema"]}.CURSO_PENSUM CP,
                                                                     {$cfg["academico"]["schema"]}.TIPO_CURSO_PENSUM TCP,
                                                                     {$cfg["academico"]["schema"]}.TITULO E,
                                                                     {$cfg["academico"]["schema"]}.pensum P
                                                             WHERE   CURSO = '{$intCurso}'
                                                             AND     CP.TIPO_CURSO_PENSUM = TCP.TIPO_CURSO_PENSUM
                                                             AND     P.PENSUM = CP.PENSUM
                                                             GROUP BY CP.PENSUM
                                                             ORDER BY PENSUM DESC, NUMERO_CICLO";

                                                $strQuery = "SELECT pensum.codigo pensum, pensum.pensum pensumID, TCP.nombre tipo, CP.numero_ciclo, CP.grupo, especialidad.nombre especialidad,
                                                                    especialidad.carrera carrera, especialidad.titulo tituloespecialidad, pensum.fecha_inicio
                                                             FROM   ( {$cfg["academico"]["schema"]}.CURSO_PENSUM CP
                                                                        LEFT JOIN {$cfg["academico"]["schema"]}.TIPO_CURSO_pensum TCP
                                                                            ON CP.tipo_curso_pensum = TCP.tipo_curso_pensum),
                                                                     {$cfg["academico"]["schema"]}.TITULO especialidad,
                                                                     {$cfg["academico"]["schema"]}.pensum pensum
                                                             WHERE   pensum.pensum = CP.pensum
                                                             AND     pensum.titulo = especialidad.titulo
                                                             AND     CP.curso = '{$intCurso}'
                                                             ORDER   BY pensum.fecha_inicio DESC";

                                                $qTMP = db_query($strQuery);
                                                $strClass = "row1";
                                                $intContPensa = 0;

                                                $arrColores = getColores(true);

                                                ?>

                                                <form id="frmPensum" name="frmPensum" method="post">
                                                    <table align="center" width="100%" cellpadding="3" cellspacing="0" id="tbPensum">
                                                        <tr>
                                                            <td colspan="5" >
                                                                Este curso pertenece a los siguientes pensa
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td width="5%" class="row0">&nbsp;</td>
                                                            <td width="12%" class="row0" align="left" nowrap="nowrap"><?php print $lang["ACADEMICO_CURSOS_PENSUM_VISTA_CODIGO"]; ?></td>
                                                            <td width="40%" class="row0" align="left" nowrap="nowrap"><?php print $lang["ACADEMICO_CURSOS_PENSUM_PENSUM"]; ?></td>
                                                            <td width="15%" class="row0" align="left" nowrap="nowrap"><?php print $lang["ACADEMICO_CURSOS_PENSUM_DETALLE_TIPO_CURSO"]; ?></td>
                                                            <td width="10%" class="row0" align="center" nowrap="nowrap"><?php print $lang["ACADEMICO_CURSOS_PENSUM_CICLO"]; ?></td>
                                                            <td width="5%" class="row0" align="center" nowrap="nowrap">Tag</td>
                                                            <td width="3%" class="row0" align="center" nowrap="nowrap">&nbsp;</td>
                                                        </tr>
                                                        <?php

                                                        if( $rTMP = db_fetch_assoc($qTMP) ) {
                                                            $intRowCount=1;
                                                            do{
                                                                $intContPensa++;

                                                                $arrInfo = $rTMP;
                                                                if( $arrInfo["PENSUMID"] == $intPensum ) $strClass = "row1";
                                                                    /*$strInfoRequisitos = "";
                                                                    if( isset($arrRequisitoCursos[$arrInfo["PENSUMID"]]["1"]) ) $strInfoRequisitos .= $arrRequisitoCursos[$arrInfo["PENSUMID"]]["1"]."<br>";
                                                                    if( isset($arrRequisitoCursos[$arrInfo["PENSUMID"]]["2"]) ) $strInfoRequisitos .= $arrRequisitoCursos[$arrInfo["PENSUMID"]]["2"]."<br>";
                                                                    if( isset($arrRequisitoCursos[$arrInfo["PENSUMID"]]["3"]) ) $strInfoRequisitos .= $arrRequisitoCursos[$arrInfo["PENSUMID"]]["3"]."<br>";
                                                                    if( isset($arrRequisitoCursos[$arrInfo["PENSUMID"]]["4"]) ) $strInfoRequisitos .= $arrRequisitoCursos[$arrInfo["PENSUMID"]]["4"]."<br>";
                                                                      */
                                                                    ?>

                                                                    <tr>
                                                                        <td class="<?php print $strClass; ?>">&nbsp;</td>
                                                                        <td class="<?php print $strClass; ?>" align="left">
                                                                            <a href="adm_academico_rpt_cursos.php?facultad=<?php print $intFacultad; ?>&carrera=<?php print $rTMP["CARRERA"]; ?>&pensum=<?php print $arrInfo["PENSUMID"]; ?>&especialidad=<?php print $rTMP["TITULOESPECIALIDAD"];?>">
                                                                                <?php print isset($arrInfo["PENSUM"]) ? $arrInfo["PENSUM"] : "&nbsp;"; ?>
                                                                            </a>
                                                                        </td>
                                                                        <td class="<?php print $strClass; ?>" align="left"><?php print isset($arrInfo["ESPECIALIDAD"]) ? $arrInfo["ESPECIALIDAD"]: "&nbsp;"; ?></td>
                                                                        <td class="<?php print $strClass; ?>" align="left"><?php print isset($arrInfo["TIPO"]) ? $arrInfo["TIPO"] : "&nbsp;"; ?></td>
                                                                        <td class="<?php print $strClass; ?>" align="center"><?php print isset($arrInfo["NUMERO_CICLO"]) ? $arrInfo["NUMERO_CICLO"] : "&nbsp;"; ?></td>
                                                                        <td class="<?php print $strClass; ?>" align="center">
                                                                        <div id="DivNewUpdateColor_<?php print $intContPensa;?>" name="DivNewUpdateColor_<?php print $intContPensa;?>" class="divColores" style="background-color: <?php print isset($arrInfo["GRUPO"]) ? $arrInfo["GRUPO"] : "&nbsp;"; ?>">&nbsp;</div>
                                                                        </td>
                                                                        <td class="<?php print $strClass; ?>">&nbsp;</td>
                                                                    </tr>
                                                                    <?php
                                                                $strClass = ($strClass == "row1") ? "row2" : "row1";
                                                            }while( $rTMP = db_fetch_assoc($qTMP) );

                                                        }
                                                        else {

                                                            ?>
                                                            <tr>
                                                                <td colspan="6" width="5%" >No hay p�nsum asociados</td>
                                                            </tr>
                                                            <?php
                                                        }

                                                        ?>
                                                    <tr style="display: none;" id="trInicial">
                                                            <td class="row1">&nbsp;</td>
                                                            <td class="row1" align="left">
                                                                <input type="hidden" class="field_textbox inputSizeComplete" id="txtIdPensum_0" name="txtIdPensum_0" onchange="fntCargarSelectCiclo(this)">
                                                                <input type="text" class="field_textbox inputSizeComplete" id="txtCodigoPensum_0" name="txtCodigoPensum_0" onfocus="fntCursoAutocomplete(this)">
                                                            </td>
                                                            <td class="row1" align="left">
                                                                <label id="lblNombrePensum_0"></label>
                                                            </td>
                                                            <td class="row1" align="left">
                                                                 <div id="divSelectTipoCurso_0" style="display: none;">
                                                                    <?php drawSelectTipoCurso(); ?>
                                                                </div>
                                                            </td>
                                                            <td class="row1" align="left">
                                                                <div id="divSelectCiclo_0" style="display: none;">
                                                                    <?php drawSelectCiclos(); ?>
                                                                </div>
                                                            </td>
                                                            <td class="row1" align="center">
                                                                <div id="divSelectTag_0" style="display: none;">
                                                                    <?php drawSelectTag();?>
                                                                </div>
                                                            </td>
                                                            <td class="row1" align="center">
                                                                <div id="divButtonEliminar_0">
                                                                    <input type="hidden" name="hidDeletePensum_0<?php print $intCount; ?>" value="N">
                                                                    <img id="imgDeletePensum_0" name="imgDeletePensum_0" src="<?php print strGetCoreImageWithPath("del.png"); ?>"  style="cursor: pointer;"  title="Eliminar" onclick="fntDeletePensum(this);">
                                                                </div>
                                                            </td>
                                                    </tr>
                                                    </table>
                                                    <script type="text/javascript" language="javascript">
                                                        $( document ).ready(function() {
                                                            $("select[name*='UpdateColor']").each(function(){
                                                                var arrSplit = $(this).attr('name').split('_');
                                                                if( arrSplit[1] != 0 ){
                                                                    $('#UpdateColor_'+arrSplit[1]).colourPicker({
                                                                        ico: 'core/jquery/colourpicker/jquery.colourpicker.gif',
                                                                        title:    "Seleccione color"
                                                                    });
                                                                }
                                                            });
                                                        });


                                                        var objTabPensa = getDocumentLayer("TabPensa");
                                                        objTabPensa.innerHTML = "Pensa (<?php print $intContPensa; ?>)"

                                                        function fntDeletePensum(objImg){
                                                            $(objImg).parent().parent().parent().remove()
                                                        }



                                                        var intFilas = '<?php print $intContPensa; ?>';
                                                        function ftnAgregarFila(){
                                                            intFilas++;
                                                            $("#tbPensum  tr:last").prev('tr').after($('#trInicial').clone());
                                                            $("#tbPensum  tr:last").prev('tr').css('display','');
                                                            $("#tbPensum  tr:last").prev('tr').find('td').each(function(){

                                                                $(this).find("div").each(function(){
                                                                    arrExplode = $(this).attr('id').split('_');
                                                                    strNewName = arrExplode[0] + '_' + intFilas;
                                                                    $(this).attr('name',strNewName);
                                                                    $(this).attr('id',strNewName);
                                                                    $(this).css("background-color", "");
                                                                });

                                                                $(this).find("input").each(function(){
                                                                    arrExplode = $(this).attr('id').split('_');
                                                                    strNewName = arrExplode[0] + '_' + intFilas;
                                                                    $(this).attr('name',strNewName);
                                                                    $(this).attr('id',strNewName);
                                                                    $(this).attr('value','');
                                                                });

                                                                $(this).find("select").each(function(){
                                                                    arrExplode = $(this).attr('id').split('_');
                                                                    strNewName = arrExplode[0] + '_' + intFilas;
                                                                    $(this).attr('name',strNewName);
                                                                    $(this).attr('id',strNewName);
                                                                    $(this).hide();
                                                                    $('option', this).remove();
                                                                });

                                                                $(this).find("label").each(function(){
                                                                    arrExplode = $(this).attr('id').split('_');
                                                                    strNewName = arrExplode[0] + '_' + intFilas;
                                                                    $(this).attr('name',strNewName);
                                                                    $(this).attr('id',strNewName);
                                                                    $(this).text('');
                                                                });

                                                                $(this).find("img").each(function(){
                                                                    arrExplode = $(this).attr('id').split('_');
                                                                    strNewName = arrExplode[0] + '_' + intFilas;
                                                                    $(this).attr('name',strNewName);
                                                                    $(this).attr('id',strNewName);
                                                                });
                                                            });
                                                         }
                                                    </script>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                <?php
                                                if( isset($arrTiposAcceso[2])){
                                                    ?>
                                                    <div id="divButtonAgregar" style="display: none;">
                                                        <img id="imgAddPensum" src="<?php print strGetCoreImageWithPath("add.png"); ?>" title="Agregar" style="display: inline-block ;cursor: pointer;" onclick="ftnAgregarFila();">
                                                    </div>
                                                    <?php
                                                    }
                                                else{
                                                    print "&nbsp;";
                                                }?>
                                                </td>
                                            </tr>
                                        </table>
                                    </form>
                                </div>



                               
<!--SYLLABUS UA-->
<link href="/core/summernote/summernote-lite.min.css" charset="UTF-8" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"
    integrity="sha512-894YE6QWD5I59HgZOGReFYm4dnWc1Qt5NtvYSaNcOP+u1T9qYdvdihz0PPSiiqn/+/3e7Jo4EaG7TubfWGUrMQ=="
    crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://code.jquery.com/jquery-migrate-3.4.0.js"></script>
<script type="text/javascript" language="JavaScript" src="/core/summernote/summernote-lite.min.js"></script>
<script type="text/javascript">
var jQuerySummernote = jQuery.noConflict(true);
</script>
<style>
#wrapEditorDescInst.field-error .note-editor,
#wrapEditorAporte.field-error .note-editor,
#wrapEditorConocimientos.field-error .note-editor,
#wrapEditorMarco.field-error .note-editor,
[id^="wrapEditorBiblio_"].field-error .note-editor {
    border: 2px solid #cc0000 !important;
}
#wrapEditorDescInst.field-error .note-editable,
#wrapEditorAporte.field-error .note-editable,
#wrapEditorConocimientos.field-error .note-editable,
#wrapEditorMarco.field-error .note-editable,
[id^="wrapEditorBiblio_"].field-error .note-editable {
    background-color: #ffe5e5 !important;
}
</style>
<div id="tabSyllabusUA">
    
    <!-- Bot�n Editar -->
    <table cellpadding="3" cellspacing="0" width="95%" align="center">

        <?php
        // Cargar datos de Syllabus UA antes de renderizar la secci�n (bit�cora, campos, RA, biblio)
        $intSyllabusUA    = 0;
        $strDescInst      = '';
        $strAporte        = '';
        $strConocimientos = '';
        $strMarco         = '';
        $syllabusUA       = getSyllabusUA($intCurso);
        if ($syllabusUA) {
            $intSyllabusUA    = intval($syllabusUA['SYLLABUS_UA']);
            $strDescInst      = getTextoClob('DESCRIPCION_INSTITUCIONAL', 'SYLLABUS_UA', 'SYLLABUS_UA', $intSyllabusUA);
            $strAporte        = getTextoClob('APORTE_PLAN_ESTUDIOS', 'SYLLABUS_UA', 'SYLLABUS_UA', $intSyllabusUA);
            $strConocimientos = getTextoClob('CONOCIMIENTOS_PREVIOS', 'SYLLABUS_UA', 'SYLLABUS_UA', $intSyllabusUA);
            $strMarco         = getTextoClob('MARCO_NORMATIVO', 'SYLLABUS_UA', 'SYLLABUS_UA', $intSyllabusUA);
        }
        ?>

        
        <!-- Descripci�n Institucional -->
<tr>
    <td>
        <!--                    <div class="Heading1 WidthDiv" align="left"  style="display: inline-block; width: 90%;">
 -->
        <div class="heading1 WidthDiv" align="left">
            

            Descripci�n institucional del curso  




            
            <img style="cursor: pointer; vertical-align: middle;" src="images/info.png" width="18px" height="18px"
onclick="mostrarAyuda('Descripci�n institucional del curso', 'Describa el prop�sito y contenido general del curso desde una perspectiva institucional, incluyendo su relevancia acad�mica y el tipo de formaci�n que aporta.<br>Puede mencionar temas, conceptos o habilidades que se abordar�n.');"
>
           

            <img id="imgEditDescInst" 
             src="<?php print strGetCoreImageWithPath('edit.gif'); ?>" 
             style="cursor: pointer; display: none; vertical-align: middle;" 
             title="Editar" 
             onclick="fntEditSyllabusField('DescInst');">


                             <?php if($intSyllabusUA > 0) { ?>
        <img src="<?php print strGetCoreImageWithPath('acad_bitacora_orange.png'); ?>" width="20px" height="20px"
             style="cursor: pointer; margin-left: 10px; vertical-align: middle;" 
             title="Ver bit�cora de cambios"
             onclick="fntMostrarBitacoraCampo(<?php print $intSyllabusUA; ?>, 'DESCRIPCION_INSTITUCIONAL');">
    <?php } ?>

    

        </div>
       
    </td>
</tr>

        <tr><td>&nbsp;</td></tr>


        <tr>
            <td align="left">
                <input type="hidden" name="hidEditedDescInst" id="hidEditedDescInst" value="N">
<input type="hidden" name="hidUltimoValidadoDescInst" id="hidUltimoValidadoDescInst" value="">
<input type="hidden" name="hidEstadoLLMDescInst" id="hidEstadoLLMDescInst" value="">
<input type="hidden" name="hidValorAceptadoDescInst" id="hidValorAceptadoDescInst" value="">
<input type="hidden" name="hidAceptadoManualDescInst" id="hidAceptadoManualDescInst" value="">

<span id="spanDescInst" style="font-family: Verdana, Geneva, Arial, Helvetica, sans-serif; font-size: 11px;"><?php print !empty($strDescInst) ? $strDescInst : '<em>Sin informaci�n</em>'; ?></span>
<div id="wrapEditorDescInst" style="display:none;">
    <textarea id="txtDescInst" style="width:100%;"><?php print htmlspecialchars($strDescInst, ENT_QUOTES); ?></textarea>
    <div id="divResultadoLLMDescInst" style="display: none; margin: 10px 0;"></div>
</div>
<textarea name="frm_sylUA_txtDescInst" id="frm_sylUA_txtDescInst" style="display:none;"></textarea>
            </td>
        </tr>


        <tr><td>&nbsp;</td></tr>        
        
        <!-- Aporte al Plan de Estudios -->
<tr>
    <td>
        <div class="heading1 WidthDiv" align="left">
            Aportes al plan de estudios/perfil de egreso.

            <img style="cursor: pointer; vertical-align: middle;" 
            src="images/info.png" 
            width="18px" 
            height="18px"
            onclick="mostrarAyuda('Aporte al plan de estudios y perfil de egreso', 'Explique c�mo este curso contribuye a la formaci�n del estudiante dentro del programa acad�mico y al desarrollo del perfil de egreso.<br>Describa su relaci�n con el plan de estudios, otras �reas o cursos, y su relevancia en la formaci�n profesional.<br>Puede mencionar las competencias, habilidades, conocimientos o perspectivas que fortalece.');"
            >
            <img id="imgEditAporte" 
             src="<?php print strGetCoreImageWithPath('edit.gif'); ?>" 
             style="cursor: pointer; display: none; vertical-align: middle;" 
             title="Editar" 
             onclick="fntEditSyllabusField('Aporte');">

                 <?php if($intSyllabusUA > 0) { ?>
        <img src="<?php print strGetCoreImageWithPath('acad_bitacora_orange.png'); ?>" width="20px" height="20px"
             style="cursor: pointer; margin-left: 10px; vertical-align: middle;" 
             title="Ver bit�cora de cambios de este campo"
             onclick="fntMostrarBitacoraCampo(<?php print $intSyllabusUA; ?>, 'APORTE_PLAN_ESTUDIOS');">
    <?php } ?>

        </div>

    </td>
</tr>
        <tr><td>&nbsp;</td></tr>
        <tr>
            <td align="left">
                <input type="hidden" name="hidEditedAporte" id="hidEditedAporte" value="N">
                <input type="hidden" name="hidUltimoValidadoAporte" id="hidUltimoValidadoAporte" value="">
                <input type="hidden" name="hidEstadoLLMAporte" id="hidEstadoLLMAporte" value="">
                <input type="hidden" name="hidValorAceptadoAporte" id="hidValorAceptadoAporte" value="">
                <input type="hidden" name="hidAceptadoManualAporte" id="hidAceptadoManualAporte" value="">

                <span id="spanAporte" style="font-family: Verdana, Geneva, Arial, Helvetica, sans-serif; font-size: 11px;"><?php print !empty($strAporte) ? $strAporte : '<em>Sin informaci�n</em>'; ?></span>
                <div id="wrapEditorAporte" style="display:none;">
                    <textarea id="txtAporte" style="width:100%;"><?php print htmlspecialchars($strAporte, ENT_QUOTES); ?></textarea>
                    <div id="divResultadoLLMAporte" style="display: none; margin: 10px 0;"></div>
                </div>
                <textarea name="frm_sylUA_txtAporte" id="frm_sylUA_txtAporte" style="display:none;"></textarea>
            </td>
        </tr>

        <tr><td>&nbsp;</td></tr>

                <!-- Resultados de Aprendizaje -->
     <tr>

<?php

    // Construir contenido din�mico para el modal de RA
$strContenidoModalRA = "Los resultados de aprendizaje (RA) describen lo que el estudiante ser� capaz de hacer al finalizar el curso.<br>";
$strContenidoModalRA .= "� Deben ser claros, espec�ficos y medibles.<br>";
$strContenidoModalRA .= "� Cada resultado debe contener un solo verbo principal.<br>";
$strContenidoModalRA .= "� Deben ser evaluables mediante evidencias (ex�menes, proyectos, actividades, etc.).<br>";
$strContenidoModalRA .= "<div style='text-align: center; margin-top: 15px;'>";
$strContenidoModalRA .= "<strong>Uso de la taxonom�a de Bloom:</strong><br><br>";
$strContenidoModalRA .= "<img src='https://intranet.ufm.edu/reportesai/taxonomia_bloom.png' style='max-width: 50%; height: auto;' alt='Taxonom�a de Bloom'>";
$strContenidoModalRA .= "</div>";
// verbos recomendados
$strContenidoModalRA .= "<div style='text-align: center; margin-top: 15px;'><strong>Verbos recomendados por nivel:</strong></div>";
$strContenidoModalRA .= "<div style='margin-left: 15px; line-height: 1.8;'>";

// Obtener niveles y verbos de la base de datos
$qNivelesBloom = db_query("SELECT bn.BLOOM_NIVEL, bn.NOMBRE
                          FROM {$cfg['academico']['schema']}.BLOOM_NIVEL bn
                          ORDER BY bn.BLOOM_NIVEL DESC");

while($rNivel = db_fetch_array($qNivelesBloom)) {
    // Obtener verbos del nivel
    $qVerbos = db_query("SELECT VERBO 
                        FROM {$cfg['academico']['schema']}.BLOOM_VERBO 
                        WHERE BLOOM_NIVEL = {$rNivel['BLOOM_NIVEL']} 
                        ORDER BY VERBO");
    
    $arrVerbos = array();
    while($rVerbo = db_fetch_array($qVerbos)) {
        $arrVerbos[] = htmlspecialchars($rVerbo['VERBO']);
    }
    
    if(!empty($arrVerbos)) {
        $strContenidoModalRA .= "<strong>" . htmlspecialchars($rNivel['NOMBRE']) . ":</strong> ";
        $strContenidoModalRA .= implode(', ', $arrVerbos) . ".<br>";
    }
}

$strContenidoModalRA .= "</div>";

// Escapar comillas para JavaScript
$strContenidoModalRA = str_replace("'", "\\'", $strContenidoModalRA);
?>

    <td>
        <div class="heading1 WidthDiv" align="left">

            Resultados de aprendizaje del curso

            <img style="cursor: pointer; vertical-align: middle;" 
                 src="images/info.png" 
                 width="18px" 
                 height="18px"
                 onclick="mostrarAyuda('Resultados de aprendizaje del curso', '<?php print $strContenidoModalRA; ?>');">


                     <?php if($intSyllabusUA > 0) { ?>
    <img src="<?php print strGetCoreImageWithPath('acad_bitacora_orange.png'); ?>" 
         width="20px" 
         height="20px"
         style="cursor: pointer; margin-left: 10px; vertical-align: middle;" 
         title="Ver bit�cora general de resultados de aprendizaje"
         onclick="fntMostrarBitacoraTodosRA(<?php print $intSyllabusUA; ?>);">
    <?php } ?>


        </div>
    </td>
</tr>

        <tr><td>&nbsp;</td></tr>

        <tr>
    <td align="left">

        
        <table class="table1" width="100%" cellpadding="3" cellspacing="1" id="tblResultadosAprendizaje">
            <tr>
                <td width="15%" class="row0" align="center">Nivel bloom</td>
                <td width="65%" class="row0" align="center">Descripci�n del resultado de aprendizaje (RA)</td>
                <td width="5%" class="row0" align="center"></td>
            </tr>
            <?php
            // Cargar RA existentes
            $strClassRA = "row1";
            $intContadorRA = 0;

            // Consultar bibliograf�as existentes
            $intContadorBiblio = 0;
            $strClassBiblio = "row2";
            $arrBibliografias = array();

            if($intSyllabusUA > 0) {
                $strQuery = "SELECT SYLLABUS_UA_BIBLIO
                            FROM {$cfg['academico']['schema']}.SYLLABUS_UA_BIBLIO
                            WHERE SYLLABUS_UA = {$intSyllabusUA}
                            ORDER BY SYLLABUS_UA_BIBLIO";
                
                $qBiblio = db_query($strQuery);
                while($rBiblio = db_fetch_array($qBiblio)) {
                    $rBiblio['REFERENCIA_COMPLETA'] = getReferenciaBiblio($rBiblio['SYLLABUS_UA_BIBLIO']);
                    $arrBibliografias[] = $rBiblio;
                }
            }
            
            if($intSyllabusUA > 0) {
                $strQuery = "SELECT ra.SYLLABUS_UA_RA,
                                    ra.BLOOM_NIVEL,
                                    n.NOMBRE AS NIVEL_NOMBRE,
                                    ra.DESCRIPCION_RA
                            FROM {$cfg['academico']['schema']}.SYLLABUS_UA_RA ra
                            JOIN {$cfg['academico']['schema']}.BLOOM_NIVEL n 
                                ON ra.BLOOM_NIVEL = n.BLOOM_NIVEL
                            WHERE ra.SYLLABUS_UA = {$intSyllabusUA}
                            ORDER BY ra.SYLLABUS_UA_RA";
                
               // error_log("Query RA: " . $strQuery);
                $qRA = db_query($strQuery);
                //error_log("Num rows: " . db_num_rows($qRA));
                
                    while($rRA = db_fetch_array($qRA)) {
                        $intContadorRA++;
                        $strClassRA = ($strClassRA == "row1") ? "row2" : "row1";
                                        ?>
                <tr id="trRA_<?php print $intContadorRA; ?>">
                    <!-- Columna Nivel Bloom -->
                    <td class="<?php print $strClassRA; ?>">
                        <!-- Select oculto inicialmente -->
                        <select name="sltNivelBloom_<?php print $intContadorRA; ?>" 
                                id="sltNivelBloom_<?php print $intContadorRA; ?>" 
                                class="field_selectbox"
                                style="width: 100%; display: none;"
                                disabled>
                            <option value="">Seleccione el nivel de Bloom</option>
                            <?php
$qNiveles = db_query("SELECT BLOOM_NIVEL, NOMBRE, DESCRIPCION
                    FROM {$cfg['academico']['schema']}.BLOOM_NIVEL 
                    ORDER BY BLOOM_NIVEL DESC");
while($rNivel = db_fetch_array($qNiveles)) {
    $selected = ($rNivel['BLOOM_NIVEL'] == $rRA['BLOOM_NIVEL']) ? 'selected' : '';
    $descripcion = !empty($rNivel['DESCRIPCION']) ? htmlspecialchars($rNivel['DESCRIPCION']) : '';
    
    // Obtener verbos del nivel
    $qVerbos = db_query("SELECT VERBO 
                        FROM {$cfg['academico']['schema']}.BLOOM_VERBO 
                        WHERE BLOOM_NIVEL = {$rNivel['BLOOM_NIVEL']} 
                        ORDER BY VERBO");
    $arrVerbos = array();
    while($rVerbo = db_fetch_array($qVerbos)) {
        $arrVerbos[] = $rVerbo['VERBO'];
    }
    $verbos = !empty($arrVerbos) ? implode(', ', $arrVerbos) : '';
    ?>
    <option value="<?php print $rNivel['BLOOM_NIVEL']; ?>" 
            title="<?php print $descripcion; ?>"
            data-verbos="<?php print htmlspecialchars($verbos); ?>"
            <?php print $selected; ?>>
        <?php print $rNivel['NOMBRE']; ?>
    </option>
                                <?php
                            }
                            ?>
                        </select>
                        <!-- Texto visible inicialmente -->

                        <span id="spanNivelBloom_<?php print $intContadorRA; ?>" style="display: block; text-align: center;">
                            <?php print htmlspecialchars($rRA['NIVEL_NOMBRE']); ?>
                         </span>

                    </td>
                    
                    <!-- Columna Descripci�n -->
                    <td class="<?php print $strClassRA; ?>">
                        <!-- Textarea oculto inicialmente -->
                        <textarea name="txtDescripcionRA_<?php print $intContadorRA; ?>" 
                                id="txtDescripcionRA_<?php print $intContadorRA; ?>"
                                class="field_textarea" 
                                style="width: 100%; display: none;"
                                rows="3"
                                readonly><?php print htmlspecialchars($rRA['DESCRIPCION_RA']); ?></textarea>
                        <!-- Texto visible inicialmente -->
                        <span id="spanDescripcionRA_<?php print $intContadorRA; ?>" style="display: block; text-align: center;">
                            <?php print nl2br(htmlspecialchars($rRA['DESCRIPCION_RA'])); ?> 
                        </span>


                             <div id="divResultadoLLM_<?php print $intContadorRA; ?>" style="display: none; margin-top: 15px; padding: 15px; border-left: 4px solid #ccc; background-color: #f9f9f9; font-size: 12px; line-height: 1.6;"></div>

                    </td>

                    
                    
                    <!-- Columna Eliminar -->
<td class="<?php print $strClassRA; ?>" align="center">


    <!-- Bot�n Editar -->
    <img src="<?php //print strGetCoreImageWithPath('edit.png'); 
      print strGetCoreImageWithPath("edit.gif") ?>" 
         style="cursor: pointer; display: none;" 
         id="imgEditRA_<?php print $intContadorRA; ?>"
         title="Editar" 
         onclick="fntEditRA(<?php print $intContadorRA; ?>);">
    
    <!-- Bot�n Eliminar -->
    <img src="<?php print strGetCoreImageWithPath('del.png'); ?>" 
         style="cursor: pointer; display: none;" 
         id="imgDeleteRA_<?php print $intContadorRA; ?>"
         title="Eliminar" 
         onclick="fntDeleteRA(<?php print $intContadorRA; ?>);">
    
    <input type="hidden" name="hidDeleteRA_<?php print $intContadorRA; ?>" 
           id="hidDeleteRA_<?php print $intContadorRA; ?>" value="N">
    <input type="hidden" name="hidUpdateRA_<?php print $intContadorRA; ?>" 
           value="<?php print $rRA['SYLLABUS_UA_RA']; ?>">
    <!-- Nuevo campo para indicar si esta fila fue editada -->
    <input type="hidden" name="hidEditedRA_<?php print $intContadorRA; ?>" 
           id="hidEditedRA_<?php print $intContadorRA; ?>" value="N">

               <input type="hidden" name="hidEstadoLLM_<?php print $intContadorRA; ?>" 
           id="hidEstadoLLM_<?php print $intContadorRA; ?>" value="">
</td>
                </tr>
                        <?php
                   // }
                }// 
            } 
            ?>

                        <tr style="display: none;" id="trRAInicial">
                <td class="row1">
                    <select name="sltNivelBloom_0" 
                            id="sltNivelBloom_0" 
                            class="field_selectbox"
                            style="width: 100%;">
                        <option value="">Seleccione el nivel de Bloom</option>
                        <?php
                        $qNiveles = db_query("SELECT BLOOM_NIVEL, NOMBRE, DESCRIPCION
                                            FROM {$cfg['academico']['schema']}.BLOOM_NIVEL 
                                            ORDER BY BLOOM_NIVEL DESC");
                        while($rNivel = db_fetch_array($qNiveles)) {
                            $selected = '';
                            $descripcion = !empty($rNivel['DESCRIPCION']) ? htmlspecialchars($rNivel['DESCRIPCION']) : '';
                            
                            // Obtener verbos del nivel
                            $qVerbos = db_query("SELECT VERBO 
                                                FROM {$cfg['academico']['schema']}.BLOOM_VERBO 
                                                WHERE BLOOM_NIVEL = {$rNivel['BLOOM_NIVEL']} 
                                                ORDER BY VERBO");
                            $arrVerbos = array();
                            while($rVerbo = db_fetch_array($qVerbos)) {
                                $arrVerbos[] = $rVerbo['VERBO'];
                            }
                            $verbos = !empty($arrVerbos) ? implode(', ', $arrVerbos) : '';
                            ?>
                            <option value="<?php print $rNivel['BLOOM_NIVEL']; ?>" 
                                    title="<?php print $descripcion; ?>"
                                    data-verbos="<?php print htmlspecialchars($verbos); ?>"
                                    <?php print $selected; ?>>
                                <?php print $rNivel['NOMBRE']; ?>
                            </option>
                            <?php
                        }
                        ?>
                    </select>
                </td>
                <td class="row1">
                    <textarea name="txtDescripcionRA_0" 
                              id="txtDescripcionRA_0"
                              class="field_textarea" 
                              style="width: 100%;"
                              rows="3"></textarea>


                                 <div id="divResultadoLLM_0" style="display: none; margin-top: 15px; padding: 15px; border-left: 4px solid #ccc; background-color: #f9f9f9; font-size: 12px; line-height: 1.6;"></div>




                </td>
                <td class="row1" align="center">
                    <img id="imgDeleteRA_0" 
                         src="<?php print strGetCoreImageWithPath('del.png'); ?>" 
                         style="cursor: pointer;" 
                         title="Eliminar" 
                         onclick="fntDeleteRA(0);">
                    <input type="hidden" name="hidDeleteRA_0" id="hidDeleteRA_0" value="N">
                    <input type="hidden" name="hidNewRA_0" id="hidNewRA_0" value="1">
                    <input type="hidden" name="hidEstadoLLM_0" id="hidEstadoLLM_0" value="">

                </td>
            </tr>


            <tr>
                <td colspan="4" align="left" style="padding: 10px;">
                              <div id="divButtonAgregarRA" style="display: none;">
                    <img src="<?php print strGetCoreImageWithPath('add.png'); ?>" 
                         style="cursor: pointer;" 
                         onclick="addRowRA();" 
                         title="Agregar Resultado de Aprendizaje">
                    <span style="margin-left: 5px;"> <!-- Agregar Resultado de Aprendizaje --> </span>
                            </div>
                </td>
            </tr>
        </table>
        
        <script type="text/javascript">
            var strClassRA = "<?php print $strClassRA; ?>";
            var intContadorRA = <?php print $intContadorRA; ?>;
        </script>


        <script type="text/javascript">

var arrSyllabusSummernoteCampos = ['DescInst', 'Aporte', 'Conocimientos', 'Marco'];
var summernoteDescInstInit = false;
var summernoteAporteInit = false;
var summernoteConocimientosInit = false;
var summernoteMarcoInit = false;

function fntGetSyllabusSummernoteCfg(fieldName) {
    return {
        spanId: 'span' + fieldName,
        wrapEditorId: 'wrapEditor' + fieldName,
        textareaId: 'txt' + fieldName,
        initFlag: 'summernote' + fieldName + 'Init',
        postFieldId: 'frm_sylUA_txt' + fieldName,
        editedId: 'hidEdited' + fieldName,
        estadoId: 'hidEstadoLLM' + fieldName,
        ultimoValidadoId: 'hidUltimoValidado' + fieldName,
        aceptadoId: 'hidAceptadoManual' + fieldName,
        divResultadoId: 'divResultadoLLM' + fieldName
    };
}

function fntIsSummernoteCampoInit(fieldName) {
    return window['summernote' + fieldName + 'Init'] === true;
}

function textoPlanoDesdeHtml(html) {
    if (!html) return '';
    var div = document.createElement('div');
    div.innerHTML = html;
    return (div.textContent || div.innerText || '').replace(/\s+/g, ' ').trim();
}

function escapeHtmlTexto(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function fntIniciarSummernoteSyllabusField(fieldName) {
    var cfg = fntGetSyllabusSummernoteCfg(fieldName);
    if (typeof jQuerySummernote === 'undefined' || typeof jQuerySummernote.fn.summernote !== 'function') {
        console.error('Summernote no est� disponible. Verifique jQuery 3.6 y summernote-lite.');
        return;
    }
    if (fntIsSummernoteCampoInit(fieldName)) {
        jQuerySummernote('#' + cfg.wrapEditorId + ' .note-editor').show();
        return;
    }
    jQuerySummernote('#' + cfg.textareaId).summernote({
        height: 220,
        toolbar: [
            ['style', ['style']],
            ['font', ['bold', 'italic', 'underline']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['insert', ['link']]
        ]
    });
    jQuerySummernote('#' + cfg.wrapEditorId + ' .note-editable').css({'background-color': 'white'});
    window[cfg.initFlag] = true;
}

function fntGetHtmlCampoSummernote(fieldName) {
    var cfg = fntGetSyllabusSummernoteCfg(fieldName);
    if (fntIsSummernoteCampoInit(fieldName) && typeof jQuerySummernote !== 'undefined' && typeof jQuerySummernote.fn.summernote === 'function') {
        return jQuerySummernote('#' + cfg.textareaId).summernote('code') || '';
    }
    var textarea = document.getElementById(cfg.textareaId);
    return textarea ? textarea.value : '';
}

function fntSyncCampoSummernoteToPost(fieldName) {
    if ($('#hidEdited' + fieldName).val() !== 'Y') {
        return;
    }
    $('#frm_sylUA_txt' + fieldName).val(fntGetHtmlCampoSummernote(fieldName));
}

function fntSyncTodosCamposSummernoteToPost() {
    for (var i = 0; i < arrSyllabusSummernoteCampos.length; i++) {
        fntSyncCampoSummernoteToPost(arrSyllabusSummernoteCampos[i]);
    }
}

function fntIsSummernoteBiblioInit(intIndex) {
    return window['summernoteBiblioInit_' + intIndex] === true;
}

function fntIniciarSummernoteBiblio(intIndex) {
    var textareaId = 'txtReferenciaBiblio_' + intIndex;
    var wrapEditorId = 'wrapEditorBiblio_' + intIndex;
    if (typeof jQuerySummernote === 'undefined' || typeof jQuerySummernote.fn.summernote !== 'function') {
        console.error('Summernote no est� disponible. Verifique jQuery 3.6 y summernote-lite.');
        return;
    }
    if (fntIsSummernoteBiblioInit(intIndex)) {
        jQuerySummernote('#' + wrapEditorId + ' .note-editor').show();
        return;
    }
    jQuerySummernote('#' + textareaId).summernote({
        height: 120,
        toolbar: [
            ['font', ['italic']]
                ]
    });
    jQuerySummernote('#' + wrapEditorId + ' .note-editable').css({'background-color': 'white'});
    window['summernoteBiblioInit_' + intIndex] = true;
}

function fntGetHtmlBiblio(intIndex) {
    var textareaId = 'txtReferenciaBiblio_' + intIndex;
    if (fntIsSummernoteBiblioInit(intIndex) && typeof jQuerySummernote !== 'undefined' && typeof jQuerySummernote.fn.summernote === 'function') {
        return jQuerySummernote('#' + textareaId).summernote('code') || '';
    }
    var textarea = document.getElementById(textareaId);
    return textarea ? textarea.value : '';
}

function fntSetHtmlBiblio(intIndex, html) {
    if (fntIsSummernoteBiblioInit(intIndex) && typeof jQuerySummernote !== 'undefined' && typeof jQuerySummernote.fn.summernote === 'function') {
        jQuerySummernote('#txtReferenciaBiblio_' + intIndex).summernote('code', html);
    } else {
        $('#txtReferenciaBiblio_' + intIndex).val(html);
    }
}

function fntSyncBiblioToPost(intIndex) {
    if ($('#hidEditedBiblio_' + intIndex).val() !== 'Y' && $('#hidNewBiblio_' + intIndex).val() !== '1') {
        return;
    }
    $('#txtReferenciaBiblio_' + intIndex).val(fntGetHtmlBiblio(intIndex));
}

function fntSyncTodasBibliografiasToPost() {
    $("textarea[name*='txtReferenciaBiblio_']").each(function(){
        var strName = $(this).attr('name') || '';
        var arrSplit = strName.split('_');
        var intIndex = parseInt(arrSplit[arrSplit.length - 1], 10);
        if (!isNaN(intIndex) && intIndex > 0) {
            fntSyncBiblioToPost(intIndex);
        }
    });
}

function fntSubmitFormDetalleCurso() {
    fntSyncTodosCamposSummernoteToPost();
    fntSyncTodasBibliografiasToPost();
    document.getElementById('frm_detalle').submit();
}

function fntBiblioEnModoEdicion(intIndex) {
    var wrapEditor = $('#wrapEditorBiblio_' + intIndex);
    return wrapEditor.length > 0 && wrapEditor.css('display') !== 'none';
}

function addRowRA() {
    $("#rowNingunoRA").css("display", "none");
    strClassRA = (strClassRA == "row2") ? "row1" : "row2";
    intContadorRA++;
    
    // Clonar fila template
    $("#tblResultadosAprendizaje tr:last").prev('tr').after($('#trRAInicial').clone());
    $("#tblResultadosAprendizaje tr:last").prev('tr').css('display','');
    $("#tblResultadosAprendizaje tr:last").prev('tr').attr('id', 'trRA_' + intContadorRA);
    
    // Actualizar IDs y nombres de todos los elementos
    $("#tblResultadosAprendizaje tr:last").prev('tr').find('td').each(function(){
        
        // Actualizar clase de fila
        $(this).removeClass('row1 row2').addClass(strClassRA);
        
        // Actualizar SELECTs
        $(this).find("select").each(function(){
            var arrExplode = $(this).attr('id').split('_');
            var strNewName = arrExplode[0] + '_' + intContadorRA;
            $(this).attr('name', strNewName);
            $(this).attr('id', strNewName);
        });
        
        // Actualizar TEXTAREAs
        $(this).find("textarea").each(function(){
            var arrExplode = $(this).attr('id').split('_');
            var strNewName = arrExplode[0] + '_' + intContadorRA;
            $(this).attr('name', strNewName);
            $(this).attr('id', strNewName);
            $(this).val('');
        });


        
        // Actualizar INPUTs hidden
        $(this).find("input[type='hidden']").each(function(){
            var arrExplode = $(this).attr('id').split('_');
            var strNewName = arrExplode[0] + '_' + intContadorRA;
            $(this).attr('name', strNewName);
            $(this).attr('id', strNewName);
        });
        
        // Actualizar IMGs

    $(this).find("img").each(function(){
    var arrExplode = $(this).attr('id').split('_');
    var strNewName = arrExplode[0] + '_' + intContadorRA;
    $(this).attr('id', strNewName);
    // Asignar evento con JavaScript puro
    var contador = intContadorRA; // Capturar el valor
    this.onclick = function() { 
        fntDeleteRA(contador); 
    };
});

// Actualizar DIVs (para resultado LLM)
$(this).find("div[id^='divResultadoLLM_']").each(function(){
    var arrExplode = $(this).attr('id').split('_');
    var strNewName = arrExplode[0] + '_' + intContadorRA;
    $(this).attr('id', strNewName);
});


    // Asignar evento change al select de nivel Bloom
    var selectBloom = document.getElementById('sltNivelBloom_' + intContadorRA);
    var textareaDesc = document.getElementById('txtDescripcionRA_' + intContadorRA);
    selectBloom.onchange = function() {
        var selectedOption = this.options[this.selectedIndex];
        var nivel = selectedOption.text;
        var descripcion = selectedOption.title;
        var verbos = selectedOption.getAttribute('data-verbos');
        //var placeholder = 'Nivel de bloom: ' + nivel + '.';

       /* var placeholder = 'Sugerencia para redactar RA.\n';
        placeholder += 'Nivel de bloom: ' + nivel + '.';

        

        if(descripcion) placeholder += '\nDefinici�n: ' + descripcion+ '.';
        if(verbos) placeholder += '\nVerbos para describir este resultado: ' + verbos +'.';*/

        var placeholder = 'Sugerencia para redactar RA.\n';

    if (descripcion) placeholder += nivel + ': ' + descripcion + '.';
    if (verbos) placeholder += '\nVerbos para describir este resultado: ' + verbos + '.';

        textareaDesc.placeholder = placeholder;
    };

    


        
    });
}


window.onload = function() {
    var selects = document.querySelectorAll('[id^="sltNivelBloom_"]');
    for(var i = 0; i < selects.length; i++) {
        var select = selects[i];
        var contador = select.id.split('_')[1];
        var textarea = document.getElementById('txtDescripcionRA_' + contador);
        
        select.onchange = function() {
                var id = this.id.split('_')[1];
                var txt = document.getElementById('txtDescripcionRA_' + id);
                var selectedOption = this.options[this.selectedIndex];
                var nivel = selectedOption.text;
                var descripcion = selectedOption.title;
                var verbos = selectedOption.getAttribute('data-verbos');

                var placeholder = 'Sugerencia para redactar RA.\n';
                //placeholder += 'Nivel de bloom: ' + nivel + '.';

                //var placeholder = 'Nivel de bloom: ' + nivel+ '.';
                //if(descripcion) placeholder += '\nDefinici�n: ' + descripcion +'.';
                //if(verbos) placeholder += '\nVerbos para describir este resultado: ' + verbos +'.';

                if (descripcion) placeholder += nivel + ': ' + descripcion + '.';
                if (verbos) placeholder += '\nVerbos para describir este resultado: ' + verbos + '.';

                txt.placeholder = placeholder;

            };
    }
};




function validarTodosCamposDescripcionConLLM() {
    var hayErrores = false;
    var campos = ['DescInst', 'Aporte', 'Conocimientos', 'Marco'];
    
    for(var i = 0; i < campos.length; i++) {
        var nombreCampo = campos[i];
        
        // Solo validar si fue editado
        if($('#hidEdited' + nombreCampo).val() !== 'Y') {
            continue;
        }
        
        
        // Obtener el HTML del editor Summernote
        var textoHTML = $.trim(fntGetHtmlCampoSummernote(nombreCampo));
        
        // Si est� vac�o, saltar
        var textoPlano = $('<div>').html(textoHTML).text().trim();
        if (textoPlano === '') {
            continue;
        }
        
        // Verificar si ya fue validado
        var ultimoValidado = $('#hidUltimoValidado' + nombreCampo).val();
        if (ultimoValidado === textoHTML) {
            var estadoAnterior = $('#hidEstadoLLM' + nombreCampo).val();
            if(estadoAnterior === 'incorrecto') {
                hayErrores = true;
            }
            continue;
        }
        
        // Mostrar indicador de carga
        $('#divResultadoLLM' + nombreCampo).html(
            '<div style="padding: 10px; background-color: #e7f3ff; border-radius: 4px;">' +
            '<strong>Validando texto...</strong></div>'
        ).show();
        
        // Validar con LLM (s�ncrono)
        var resultado = null;
        
        $.ajax({
            url: "<?php print $strAction; ?>",
            type: 'POST',
            data: {
                validarDescripcion: true,
                textoHTML: textoHTML,
                nombreCampo: nombreCampo
            },
            async: false,
            dataType: 'json',
            success: function(data) {
                resultado = data;
            },
            error: function() {
                resultado = {error: 'Error al conectar con el servidor'};
            }
        });
        
        if (resultado && !resultado.error) {
            // Guardar resultado
            $('#hidUltimoValidado' + nombreCampo).val(textoHTML);
            $('#hidEstadoLLM' + nombreCampo).val(resultado.estado);
            
            // Mostrar resultado
            mostrarResultadoValidacionDescripcion(nombreCampo, resultado);
            
            if(resultado.estado === 'incorrecto') {
                hayErrores = true;
            }
        } else if (resultado && resultado.error) {
            $('#divResultadoLLM' + nombreCampo).html(
                '<div style="padding: 10px; background-color: #f8d7da; color: #721c24; border-radius: 4px;">' +
                '<strong>Error:</strong> ' + resultado.error + '</div>'
            ).show();
            hayErrores = true;
        }
    }
    
    return !hayErrores;
}

function validarTodosRAsConLLM() {
    var hayErrores = false;
    var totalValidaciones = 0;
    var validacionesNuevas = 0;
    
    // NO limpiar resultados anteriores para mantener validaciones previas
    
    // Recorrer todos los selects de nivel Bloom
    $("select[name*='sltNivelBloom_']").each(function(){
        var arrSplit = $(this).attr('name').split('_');
        var intIndex = arrSplit[1];
        
        var txtDescripcion = $("#txtDescripcionRA_" + intIndex);
        
        // Verificar si el textarea est� en modo edici�n (visible y no readonly)
        var estaVisible = txtDescripcion.css('display') != 'none';
        var esReadonly = txtDescripcion.attr('readonly') == 'readonly';
        var estaEnModoEdicion = txtDescripcion.length > 0 && estaVisible && !esReadonly;
        
        var estaEliminado = $("#hidDeleteRA_" + intIndex).val() == "Y";
        
        // Solo validar si est� en modo edici�n y NO est� eliminado
        if(estaEnModoEdicion && !estaEliminado) {
            var nivelBloom = $(this).find('option:selected').text();
            var descripcion = $.trim(txtDescripcion.val());
            
            // Solo validar si tiene contenido
            if(descripcion !== "" && nivelBloom !== "Seleccione el nivel de Bloom") {
                totalValidaciones++;
                
                // Obtener el �ltimo valor validado
                var ultimoValidado = txtDescripcion.attr('data-ultimo-validado') || '';
                var valorActual = nivelBloom + '|' + descripcion;

                // Solo llamar al LLM si el valor cambi� Y no fue aceptado manualmente
                var fueAceptadoManual = txtDescripcion.attr('data-aceptado-manual') === 'true';
                
                // Solo llamar al LLM si el valor cambi�
                if(valorActual !== ultimoValidado && !fueAceptadoManual) {
                    validacionesNuevas++;
                    var resultado = validarRAconLLMSincrono(nivelBloom, descripcion);
                    mostrarResultadoEnFila(intIndex, resultado, true);
                    
                    // Guardar el valor validado
                    txtDescripcion.attr('data-ultimo-validado', valorActual);
                    
                    // Si es incorrecto, marcar error
                    if(resultado && resultado.estado === 'incorrecto') {
                        hayErrores = true;
                    }
                } else {
                    // Mostrar resultado anterior (sin llamar al LLM)
                    mostrarResultadoEnFila(intIndex, null, false);
                    
                    // Verificar si el resultado anterior era incorrecto
                    var divResultado = $('#divResultadoLLM_' + intIndex);
                    var estadoAnterior = divResultado.attr('data-estado');

                             // Actualizar el campo hidden con el estado anterior
                    $('#hidEstadoLLM_' + intIndex).val(estadoAnterior);

                    if(divResultado.attr('data-estado') === 'incorrecto') {
                        hayErrores = true;
                    }
                }
            }
        }
    });
    
    // Mostrar resumen de validaciones
   /* if(totalValidaciones > 0) {
        console.log('Total RAs validados: ' + totalValidaciones);
        console.log('Llamadas al LLM: ' + validacionesNuevas);
        console.log('Resultados reutilizados: ' + (totalValidaciones - validacionesNuevas));
        console.log('-------------------------------------');
        console.log('--------------------------------------');

    }*/
    
    return !hayErrores;
}





function validarTodasBibliografiasConLLM() {
    var hayErrores = false;
    var totalValidaciones = 0;
    var validacionesNuevas = 0;
    
    $("textarea[name*='txtReferenciaBiblio_']").each(function(){
        var arrSplit = $(this).attr('name').split('_');
        var intIndex = arrSplit[1];
        
        if(intIndex == 0) return true;
        
        var estaEnModoEdicion = fntBiblioEnModoEdicion(intIndex);
        var estaEliminado = $("#hidDeleteBiblio_" + intIndex).val() == "Y";
        
        if(estaEnModoEdicion && !estaEliminado) {
            var referenciaHtml = fntGetHtmlBiblio(intIndex);
            
            if(textoPlanoDesdeHtml(referenciaHtml) !== "") {
                totalValidaciones++;

                var txtReferencia = $('#txtReferenciaBiblio_' + intIndex);
                var ultimoValidado = txtReferencia.attr('data-ultimo-validado') || '';
                var valorActual = referenciaHtml;

                var fueAceptadoManual = txtReferencia.attr('data-aceptado-manual') === 'true';
                
               if(valorActual !== ultimoValidado && !fueAceptadoManual) {
                    validacionesNuevas++;
                    var resultado = validarBiblioConLLMSincrono(referenciaHtml);
                    mostrarResultadoBiblioEnFila(intIndex, resultado, true);
                    
                    txtReferencia.attr('data-ultimo-validado', valorActual);
                    
                    if(resultado && resultado.estado === 'incorrecto') {
                        hayErrores = true;
                    }

                } else {
                    mostrarResultadoBiblioEnFila(intIndex, null, false);
                    
                    var divResultado = $('#divResultadoLLMBiblio_' + intIndex);
                    var estadoAnterior = divResultado.attr('data-estado');
                    $('#hidEstadoLLMBiblio_' + intIndex).val(estadoAnterior);
                    
                    if(divResultado.attr('data-estado') === 'incorrecto') {
                        hayErrores = true;
                    }
                }
            }
        }
    });
    
    return !hayErrores;
}

function validarRAconLLMSincrono(nivelBloom, descripcion) {
    var resultado = null;


    // Obtener Descripci�n Institucional si existe
    var descripcionInst = textoPlanoDesdeHtml(fntGetHtmlCampoSummernote('DescInst'));
    if (descripcionInst === '') {
        descripcionInst = textoPlanoDesdeHtml($('#spanDescInst').html() || '');
    }
    
    
    $.ajax({
        url: "<?php print $strAction; ?>",
        type: 'POST',
        data: {
            validarRA: true,
            nivelBloom: nivelBloom,
            descripcionRA: descripcion,
            descripcionInstitucional: descripcionInst  
        },
        async: false,
        dataType: 'json',
        success: function(data) {
            resultado = data;
        },
        error: function() {
            resultado = {error: 'Error al conectar con el servidor'};
        }
    });
    
    return resultado;
}

function validarBiblioConLLMSincrono(referencia) {
    var resultado = null;
    
    $.ajax({
        url: "<?php print $strAction; ?>",
        type: 'POST',
        data: {
            validarBibliografia: true,
            referenciaBibliografica: referencia
        },
        async: false,
        dataType: 'json',
        success: function(data) {
            resultado = data;
        },
        error: function() {
            resultado = {error: 'Error al conectar con el servidor'};
        }
    });
    
    return resultado;
}



function mostrarResultadoValidacionDescripcion(nombreCampo, resultado) {
    var divResultado = $('#divResultadoLLM' + nombreCampo);
    var html = '';

       
    // AGREGAR ESTA L�NEA:
    var ICONO_IA_URL = 'https://intranet.ufm.edu/reportesai/icon_ia_sparkle.png';
    
    if (resultado.estado === 'correcto') {
        // AGREGAR EL ICONO AL INICIO:
        html = '<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">' +
               '<img src="' + ICONO_IA_URL + '" alt="IA" style="height: 25px;">' +
               '<span style="font-size: 12px; color: #666;">An�lisis generado con IA</span>' +
               '</div>';
        
        html += '<div style="color: #155724; background-color: #d4edda; padding: 10px; border-radius: 4px; border-left: 4px solid #28a745;">';
        html += '<strong>Texto correcto</strong><br>';
        html += resultado.explicacion;
        html += '</div>';
    }  else {
        var color = resultado.estado === 'incorrecto' ? '#721c24' : '#856404';
        var bg = resultado.estado === 'incorrecto' ? '#f8d7da' : '#fff3cd';
        var borderColor = resultado.estado === 'incorrecto' ? '#dc3545' : '#ffc107';
        var titulo = resultado.estado === 'incorrecto' ? 'Texto incorrecto' : 'Puede mejorarse';
        
        // AGREGAR EL ICONO AL INICIO:
        html = '<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">' +
               '<img src="' + ICONO_IA_URL + '" alt="IA" style="height: 25px;">' +
               '<span style="font-size: 12px; color: #666;">An�lisis generado con IA</span>' +
               '</div>';
        
        html += '<div style="color: ' + color + '; background-color: ' + bg + '; padding: 10px; border-radius: 4px; border-left: 4px solid ' + borderColor + ';">';
        html += '<strong>' + titulo + '</strong><br>';
        html += resultado.explicacion;
        
        if (resultado.sugerencias && resultado.sugerencias.length > 0) {
            html += '<br><br><strong>Sugerencias:</strong><ul style="margin: 5px 0; padding-left: 20px;">';
            resultado.sugerencias.forEach(function(sug) {
                html += '<li>' + sug + '</li>';
            });
            html += '</ul>';
        }
 
        if (resultado.html_corregido && resultado.html_corregido.trim() !== '') {
            html += '<br><br><strong>Vista previa del texto corregido:</strong>';
            html += '<div style="margin: 10px 0; padding: 10px; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px;">';
            html += resultado.html_corregido;
            html += '</div>';
             
            
            var htmlEscapado = encodeURIComponent(resultado.html_corregido);
            
            // AGREGAR BOTONES EN CONTENEDOR FLEX
            html += '<div style="margin-top: 10px; display: flex; gap: 8px;">';
            
            var labelAplicarDesc = (resultado.estado === 'incorrecto') ? 'Aplicar correcci�n' : 'Aplicar sugerencia';
            var colorAplicarDesc = (resultado.estado === 'incorrecto') ? '#4CAF50' : '#2196F3';
            html += '<button type="button" onclick="aplicarCorreccionDescripcion(\'' + nombreCampo + '\', decodeURIComponent(\'' + htmlEscapado + '\')); return false;" ';
            html += 'style="padding: 5px 15px; background-color: ' + colorAplicarDesc + '; color: white; border: none; ';
            html += 'border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 12px;">';
            html += labelAplicarDesc + '</button>';
            
            if (resultado.estado !== 'incorrecto') {
                html += '<button type="button" onclick="aceptarComoCorrectoDescripcion(\'' + nombreCampo + '\'); return false;" ';
                html += 'style="padding: 5px 15px; background-color: #4CAF50; color: white; border: none; ';
                html += 'border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 12px;">';
                html += 'Aceptar texto actual como correcto</button>';
            }
            
            html += '</div>';
        }
        
        html += '</div>';
    }
    
    divResultado.html(html).show();
}



function aceptarComoCorrectoDescripcion(nombreCampo) {
    var cfg = fntGetSyllabusSummernoteCfg(nombreCampo);
    var divResultado = $('#divResultadoLLM' + nombreCampo);
    
    $('#hidEstadoLLM' + nombreCampo).val('correcto');
    divResultado.hide();
    
    var wrapEditor = document.getElementById(cfg.wrapEditorId);
    if (wrapEditor) {
        var noteEditor = wrapEditor.querySelector('.note-editor');
        if (noteEditor) {
            noteEditor.style.border = '2px solid #28a745';
        }
    }
}



function aplicarCorreccionDescripcion(nombreCampo, htmlCorregido) {
    var cfg = fntGetSyllabusSummernoteCfg(nombreCampo);

    if (fntIsSummernoteCampoInit(nombreCampo) && typeof jQuerySummernote !== 'undefined' && typeof jQuerySummernote.fn.summernote === 'function') {
        jQuerySummernote('#' + cfg.textareaId).summernote('code', htmlCorregido);
    } else {
        document.getElementById(cfg.textareaId).value = htmlCorregido;
    }
    
    $('#hidEdited' + nombreCampo).val('Y');
    $('#hidUltimoValidado' + nombreCampo).val('');
    $('#hidEstadoLLM' + nombreCampo).val('');
    $('#divResultadoLLM' + nombreCampo).html(
        '<div style="padding: 10px; background-color: #d4edda; color: #155724; border-radius: 4px;">' +
        '<strong>Sugerencia/correcci�n aplicada exitosamente</strong><br>' +
        '</div>'
    ).show();
}


function fntCargarVerbosBloom(intContador) {
    var intNivel = $("#sltNivelBloom_" + intContador).val();
    var selectVerboId = "sltVerboBloom_" + intContador;
    var objSelectVerbo = document.getElementById(selectVerboId);
    
    if(!objSelectVerbo) {
        console.error("No se encontr� el select: " + selectVerboId);
        return;
    }
    
    // Limpiar select
    objSelectVerbo.innerHTML = "<option value=''>Seleccione</option>";
    
    if(intNivel == "" || intNivel == null) {
        objSelectVerbo.disabled = true;
        return;
    }
    
    // AJAX
    $.ajax({
        url: "<?php print $strAction; ?>",
        type: "POST",
        data: {
            sendVerbosBloom: true,
            nivelBloom: intNivel
        },
        dataType: "json",
        success: function(response) {
            if(response.success && response.verbos) {
                response.verbos.forEach(function(verbo) {
                    var option = document.createElement("option");
                    option.value = verbo.BLOOM_VERBO;
                    option.text = verbo.VERBO;
                    objSelectVerbo.add(option);
                });
                objSelectVerbo.disabled = false;
            }
        },
        error: function(xhr, status, error) {
            console.error("Error AJAX:", error);
        }
    });
}

var DEBUG_LLM = false;
function mostrarResultadoEnFila(intIndex, resultado, llamoLLM) {
    var divResultado = $('#divResultadoLLM_' + intIndex);

    // Si fue aceptado manualmente, no mostrar nada
    var txtDescripcion = $('#txtDescripcionRA_' + intIndex);
    if(txtDescripcion.attr('data-aceptado-manual') === 'true') {
        return;
    }
    


     if(!resultado && !llamoLLM) {
        if(DEBUG_LLM) {
            // Obtener el HTML actual y cambiar el mensaje
            var htmlActual = divResultado.html();
            if(htmlActual !== '') {
                // Reemplazar "(validado con LLM)" por "(resultado anterior)"
                htmlActual = htmlActual.replace('(validado con LLM)', '(resultado anterior)');
                divResultado.html(htmlActual);
            }
        }




        divResultado.show();
        return;
    }
    
    if(!resultado) {
        return;
    }
    
    var color = '';
    var icono = '';
    
    if(resultado.estado === 'correcto') {
        color = '#4CAF50';
        icono = '';
    } else if(resultado.estado === 'puede_mejorarse') {
        color = '#FF9800';
        icono = '';
    } else if(resultado.estado === 'incorrecto') {
        color = '#F44336';
        icono = '';
    }


    var txtDescripcion = $('#txtDescripcionRA_' + intIndex);
    txtDescripcion.css('border', '2px solid ' + color);


    if(resultado.estado === 'incorrecto') {
        txtDescripcion.css('background-color', '#ffebee');
    } else {
        txtDescripcion.css('background-color', '');
    }


    var ICONO_IA_URL = 'https://intranet.ufm.edu/reportesai/icon_ia_sparkle.png'; // URL de tu icono


    
    //var html = '<strong style="color: ' + color + ';">' + ' ' + resultado.estado.toUpperCase() + '</strong><br>';


    var mensajeEstado = '';
if(resultado.estado === 'correcto') {
    mensajeEstado = 'Correcto';
} else if(resultado.estado === 'puede_mejorarse') {
    mensajeEstado = 'Puede mejorarse';
} else if(resultado.estado === 'incorrecto') {
    mensajeEstado = 'Incorrecto';
}





var html = '<div style="font-size: 11px;"><div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">' +
       '<img src="' + ICONO_IA_URL + '" alt="IA" style="height: 25px;">' +
       '<span style="font-size: 12px; color: #666;">An�lisis generado con IA</span>' +
       '</div>' +
       '<strong style="color: ' + color + ';">' + mensajeEstado + '</strong><br>';

    
    // Indicar si llam� al LLM
    if(DEBUG_LLM) {
    if(llamoLLM) {
        html += ' <span style="font-size: 10px; color: #666;">(validado con LLM)</span>';
    } else {
        html += ' <span style="font-size: 10px; color: #666;">(resultado anterior)</span>';
    }

    html += '<br>';
    }
    
    
    
    // Mostrar Retroalimentaci�n primero
    if(resultado.justificacion && resultado.justificacion !== '') {
        html += '<strong>Retroalimentaci�n:</strong> ' + resultado.justificacion + '<br>';
    }
    

if(resultado.sugerencia && resultado.sugerencia !== '') {
    var labelAplicarRA = (resultado.estado === 'incorrecto') ? 'Aplicar correcci�n' : 'Aplicar sugerencia';
    var colorAplicarRA = (resultado.estado === 'incorrecto') ? '#4CAF50' : '#2196F3';
    var labelTextoRA = (resultado.estado === 'incorrecto') ? 'Correcci�n:' : 'Sugerencia:';
    html += '<div style="margin-top: 8px;"><strong>' + labelTextoRA + '</strong> ' + resultado.sugerencia + '</div>';
    html += '<div style="margin-top: 8px;"><button type="button" onclick="aplicarSugerencia(' + intIndex + ')" data-sugerencia="' + resultado.sugerencia.replace(/"/g, '&quot;') + '" ';
    html += 'style="padding: 5px 12px; font-size: 12px; font-weight: bold; cursor: pointer; background-color: ' + colorAplicarRA + '; color: white; border: none; border-radius: 4px;">';
    html += labelAplicarRA + '</button>';
    if (resultado.estado !== 'incorrecto') {
        html += '<button type="button" onclick="aceptarComoCorrectaRA(' + intIndex + ')" ';
        html += 'style="padding: 5px 12px; font-size: 12px; font-weight: bold; cursor: pointer; background-color: #4CAF50; color: white; border: none; border-radius: 4px; margin-left: 8px;">';
        html += 'Aceptar texto actual como correcto</button>';
    }
    html += '</div>';
}

    
    if(resultado.error) {
        html = '<strong style="color: red;">Error:</strong> ' + resultado.error;
    }
    
    html += '</div>'; // Cerrar div con font-size
    divResultado.html(html);
    divResultado.css('border-left-color', color);
    divResultado.attr('data-estado', resultado.estado);
    divResultado.show();

    // Guardar estado en campo hidden
    $('#hidEstadoLLM_' + intIndex).val(resultado.estado);
}


function aplicarSugerencia(intIndex) {

    var sugerencia = event.target.getAttribute('data-sugerencia');

    var txtDescripcion = $('#txtDescripcionRA_' + intIndex);
    
    // Reemplazar el contenido del textarea con la sugerencia
    txtDescripcion.val(sugerencia);
    
    // Limpiar el atributo data-ultimo-validado para forzar nueva validaci�n
    txtDescripcion.attr('data-ultimo-validado', '');
    
    // Limpiar el estado LLM para que requiera nueva validaci�n
    $('#hidEstadoLLM_' + intIndex).val('');
    
    // Limpiar el resultado visual
    $('#divResultadoLLM_' + intIndex).hide().html('');
    
    // Limpiar estilos del textarea
    txtDescripcion.css('border', '');
    txtDescripcion.css('background-color', '');
    
    // Mensaje opcional
    //console.log('Sugerencia aplicada al RA #' + intIndex);
}

function aceptarComoCorrectaRA(intIndex) {
    var txtDescripcion = $('#txtDescripcionRA_' + intIndex);
    var divResultado = $('#divResultadoLLM_' + intIndex);

    // Guardar el valor actual para detectar cambios
    txtDescripcion.attr('data-valor-aceptado', txtDescripcion.val());
    txtDescripcion.attr('data-ultimo-validado', $('#sltNivelBloom_' + intIndex).find('option:selected').text() + '|' + txtDescripcion.val());

    // Marcar como aceptado manualmente
    txtDescripcion.attr('data-aceptado-manual', 'true');
    
    // Establecer estado como correcto ANTES de ocultar
    $('#hidEstadoLLM_' + intIndex).val('correcto');
    divResultado.attr('data-estado', 'correcto');
    
    // Ocultar todo el bloque
    divResultado.hide();
    
    // Poner el input en verde
    txtDescripcion.css('border', '2px solid #28a745');
        txtDescripcion.css('background-color', '');

}



function mostrarResultadoBiblioEnFila(intIndex, resultado, llamoLLM) {
    var divResultado = $('#divResultadoLLMBiblio_' + intIndex);

    if($('#txtReferenciaBiblio_' + intIndex).attr('data-aceptado-manual') === 'true') {
        return;
    }
    
    if(!resultado && !llamoLLM) {
        divResultado.show();
        return;
    }
    
    if(!resultado) {
        return;
    }
    
    var color = '';
    if(resultado.estado === 'correcto') {
        color = '#4CAF50';
    } else if(resultado.estado === 'puede_mejorarse') {
        color = '#FF9800';
    } else if(resultado.estado === 'incorrecto') {
        color = '#F44336';
    }
    
    var txtReferencia = $('#txtReferenciaBiblio_' + intIndex);
    var wrapEditor = $('#wrapEditorBiblio_' + intIndex);
    wrapEditor.removeClass('field-error');
    if (typeof jQuerySummernote !== 'undefined') {
        jQuerySummernote('#wrapEditorBiblio_' + intIndex + ' .note-editor').css('border', '2px solid ' + color);
    }
    
    if(resultado.estado === 'incorrecto') {
        if (typeof jQuerySummernote !== 'undefined') {
            jQuerySummernote('#wrapEditorBiblio_' + intIndex + ' .note-editable').css('background-color', '#ffebee');
        }
    } else {
        if (typeof jQuerySummernote !== 'undefined') {
            jQuerySummernote('#wrapEditorBiblio_' + intIndex + ' .note-editable').css('background-color', 'white');
        }
    }
    
    var ICONO_IA_URL = 'https://intranet.ufm.edu/reportesai/icon_ia_sparkle.png';
    
    var mensajeEstado = '';
    if(resultado.estado === 'correcto') {
        mensajeEstado = 'Estado: Bibliograf�a correcta seg�n formato Chicago';
    } else if(resultado.estado === 'puede_mejorarse') {
        mensajeEstado = 'Estado: Bibliograf�a puede mejorarse (formato Chicago)';
    } else if(resultado.estado === 'incorrecto') {
        mensajeEstado = 'Estado: Bibliograf�a incorrecta seg�n formato Chicago';
    }
    
    var html = '<div style="font-size: 11px;"><div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">' +
               '<img src="' + ICONO_IA_URL + '" alt="IA" style="height: 25px;">' +
               '<span style="font-size: 12px; color: #666;">An�lisis generado con IA</span>' +
               '</div>' +
               '<strong style="color: ' + color + ';">' + mensajeEstado + '</strong><br>';
    
    if(resultado.justificacion && resultado.justificacion !== '') {
        //html += '<div style="margin-top: 8px;"><strong>Retroalimentaci�n:</strong> ' + resultado.justificacion + '</div>';
        html += '<div style="margin-top: 8px;"><strong>Retroalimentaci�n:</strong> ' + escapeHtmlTexto(resultado.justificacion) + '</div>';
    }
    

        if(resultado.sugerencia && resultado.sugerencia !== '') {
 // html += '<div style="margin-top: 8px;" id="divSugerenciaBiblio_' + intIndex + '"><strong>Sugerencia:</strong> ' + resultado.sugerencia + '</div>';
    var labelAplicarBiblio = (resultado.estado === 'incorrecto') ? 'Aplicar correcci�n' : 'Aplicar sugerencia';
    var colorAplicarBiblio = (resultado.estado === 'incorrecto') ? '#4CAF50' : '#2196F3';
    var labelTextoBiblio = (resultado.estado === 'incorrecto') ? 'Correcci�n:' : 'Sugerencia:';
    html += '<div style="margin-top: 8px;" id="divSugerenciaBiblio_' + intIndex + '"><strong>' + labelTextoBiblio + '</strong> ' + '<span class="biblio-sugerencia-html">' + resultado.sugerencia + '</span></div>';

    html += '<div style="margin-top: 8px; display: flex; gap: 8px;">';
    html += '<button type="button" onclick="aplicarSugerenciaBiblio(' + intIndex + ')" data-sugerencia="' + resultado.sugerencia.replace(/"/g, '&quot;') + '" ';
    html += 'style="padding: 5px 12px; font-size: 12px; font-weight: bold; cursor: pointer; background-color: ' + colorAplicarBiblio + '; color: white; border: none; border-radius: 4px;">';
    html += labelAplicarBiblio + '</button>';
    if (resultado.estado !== 'incorrecto') {
        html += '<button type="button" onclick="aceptarComoCorrectaBiblio(' + intIndex + ')" ';
        html += 'style="padding: 5px 12px; font-size: 12px; font-weight: bold; cursor: pointer; background-color: #4CAF50; color: white; border: none; border-radius: 4px;">';
        html += 'Aceptar actual como correcta</button>';
    }
    html += '</div>';
}
    
    if(resultado.error) {
        html = '<strong style="color: red;">Error:</strong> ' + resultado.error;
    }
    
    html += '</div>'; // Cerrar div con font-size
    divResultado.html(html);
    divResultado.css('border-left-color', color);
    divResultado.attr('data-estado', resultado.estado);
    divResultado.show();
    
    $('#hidEstadoLLMBiblio_' + intIndex).val(resultado.estado);
}


function aplicarSugerenciaBiblio(intIndex) {

    var sugerencia = event.target.getAttribute('data-sugerencia');

    var txtReferencia = $('#txtReferenciaBiblio_' + intIndex);

    fntSetHtmlBiblio(intIndex, sugerencia);
    txtReferencia.attr('data-ultimo-validado', '');
    $('#hidEstadoLLMBiblio_' + intIndex).val('');
    $('#divResultadoLLMBiblio_' + intIndex).hide().html('');
    
    if (typeof jQuerySummernote !== 'undefined') {
        jQuerySummernote('#wrapEditorBiblio_' + intIndex + ' .note-editor').css('border', '');
        jQuerySummernote('#wrapEditorBiblio_' + intIndex + ' .note-editable').css('background-color', 'white');
    }
}




function aceptarComoCorrectaBiblio(intIndex) {
    var txtReferencia = $('#txtReferenciaBiblio_' + intIndex);
    var divResultado = $('#divResultadoLLMBiblio_' + intIndex);
    var valorActual = fntGetHtmlBiblio(intIndex);

    txtReferencia.attr('data-valor-aceptado', valorActual);
    //txtReferencia.attr('data-ultimo-validado', textoPlanoDesdeHtml(valorActual));
    txtReferencia.attr('data-ultimo-validado', valorActual);

    txtReferencia.attr('data-aceptado-manual', 'true');
    
    $('#hidEstadoLLMBiblio_' + intIndex).val('correcto');
    divResultado.attr('data-estado', 'correcto');
    
    divResultado.hide();
    
    if (typeof jQuerySummernote !== 'undefined') {
        jQuerySummernote('#wrapEditorBiblio_' + intIndex + ' .note-editor').css('border', '2px solid #28a745');
        jQuerySummernote('#wrapEditorBiblio_' + intIndex + ' .note-editable').css('background-color', 'white');
    }
}



function fntDeleteRA(intContador) {
    var objHidNew = $("#hidNewRA_" + intContador);
    var objHidDelete = $("#hidDeleteRA_" + intContador);
    var objTr = document.getElementById("trRA_" + intContador);
    
    // Si es nuevo (no guardado), eliminar del DOM
    if(objHidNew.length > 0 && objHidNew.val() == "1") {
        $("#trRA_" + intContador).remove();
    } else {
        // Si es existente, marcar para eliminar 
        for(var i = 0; i < objTr.cells.length; i++) {
            objTr.cells[i].className = "rowdelete";
        }
        objHidDelete.val("Y");
    }
}


function fntEditRA(intContador) {
    // Ocultar spans y mostrar campos editables solo de esta fila
    $("#spanNivelBloom_" + intContador).hide();
    $("#spanDescripcionRA_" + intContador).hide();
    $("#sltNivelBloom_" + intContador).show().removeAttr('disabled');
    $("#txtDescripcionRA_" + intContador).show().removeAttr('readonly');
    
    // Marcar que esta fila fue editada
    $("#hidEditedRA_" + intContador).val("Y");
    
   // $("#imgEditRA_" + intContador).hide();
}

function fntEditSyllabusField(fieldName) {
    var cfg = fntGetSyllabusSummernoteCfg(fieldName);
    document.getElementById(cfg.spanId).style.display = 'none';
    document.getElementById(cfg.wrapEditorId).style.display = 'block';
    document.getElementById(cfg.editedId).value = 'Y';
    $('#' + cfg.estadoId).val('');
    $('#' + cfg.ultimoValidadoId).val('');
    $('#' + cfg.aceptadoId).val('');
    $('#' + cfg.divResultadoId).hide().html('');
    $('#' + cfg.wrapEditorId).removeClass('field-error');
    if (typeof jQuerySummernote !== 'undefined') {
        jQuerySummernote('#' + cfg.wrapEditorId + ' .note-editor').css('border', '');
    }
    fntIniciarSummernoteSyllabusField(fieldName);
}


function addRowBiblio() {
    $("#rowNingunoBiblio").css("display", "none");
    strClassBiblio = (strClassBiblio == "row2") ? "row1" : "row2";
    intContadorBiblio++;
    
    $("#tblBibliografia tr:last").prev('tr').after($('#trBiblioInicial').clone());
    var $newRow = $("#tblBibliografia tr:last").prev('tr');
    $newRow.css('display','');
    $newRow.attr('id', 'trBiblio_' + intContadorBiblio);
    
    $newRow.find('td').each(function(){
        $(this).removeClass('row1 row2').addClass(strClassBiblio);
        
        $(this).find("div[id^='spanReferenciaBiblio_']").each(function(){
            var strNewId = 'spanReferenciaBiblio_' + intContadorBiblio;
            $(this).attr('id', strNewId);
            $(this).html('');
            $(this).hide();
        });

        $(this).find("div[id^='wrapEditorBiblio_']").each(function(){
            var strNewId = 'wrapEditorBiblio_' + intContadorBiblio;
            $(this).attr('id', strNewId);
            $(this).show();
            $(this).removeClass('field-error');
        });
        
        $(this).find("textarea").each(function(){
            var arrExplode = $(this).attr('id').split('_');
            var strNewName = arrExplode[0] + '_' + intContadorBiblio;
            $(this).attr('name', strNewName);
            $(this).attr('id', strNewName);
            $(this).val('');
        });
        
        $(this).find("input[type='hidden']").each(function(){
            var arrExplode = $(this).attr('id').split('_');
            var strNewName = arrExplode[0] + '_' + intContadorBiblio;
            $(this).attr('name', strNewName);
            $(this).attr('id', strNewName);
        });
        
        $(this).find("img").each(function(){
            var arrExplode = $(this).attr('id').split('_');
            var strNewName = arrExplode[0] + '_' + intContadorBiblio;
            $(this).attr('id', strNewName);
            var contador = intContadorBiblio;
            this.onclick = function() { 
                fntDeleteBiblio(contador); 
            };
        });
        
        $(this).find("div[id^='divResultadoLLMBiblio_']").each(function(){
            var arrExplode = $(this).attr('id').split('_');
            var strNewName = arrExplode[0] + '_' + intContadorBiblio;
            $(this).attr('id', strNewName);
            $(this).hide().html('');
        });
    });

    window['summernoteBiblioInit_' + intContadorBiblio] = false;
    fntIniciarSummernoteBiblio(intContadorBiblio);
    $("#hidEditedBiblio_" + intContadorBiblio).val("Y");
}

function fntDeleteBiblio(intContador) {
    var objHidNew = $("#hidNewBiblio_" + intContador);
    var objHidDelete = $("#hidDeleteBiblio_" + intContador);
    var objTr = document.getElementById("trBiblio_" + intContador);
    
    if(objHidNew.length > 0 && objHidNew.val() == "1") {
        $("#trBiblio_" + intContador).remove();
    } else {
        for(var i = 0; i < objTr.cells.length; i++) {
            objTr.cells[i].className = "rowdelete";
        }
        objHidDelete.val("Y");
    }
}

function fntEditBiblio(intContador) {
    $("#spanReferenciaBiblio_" + intContador).hide();
    $("#wrapEditorBiblio_" + intContador).show();
    $("#hidEditedBiblio_" + intContador).val("Y");

    $("#txtReferenciaBiblio_" + intContador).attr('data-ultimo-validado', '');
    $("#hidEstadoLLMBiblio_" + intContador).val('');
    $("#divResultadoLLMBiblio_" + intContador).hide().html('');
    $("#wrapEditorBiblio_" + intContador).removeClass('field-error');
    if (typeof jQuerySummernote !== 'undefined') {
        jQuerySummernote('#wrapEditorBiblio_' + intContador + ' .note-editor').css('border', '');
        jQuerySummernote('#wrapEditorBiblio_' + intContador + ' .note-editable').css('background-color', 'white');
    }
    fntIniciarSummernoteBiblio(intContador);
}


        </script>

    </td>
</tr>


        
        <tr><td>&nbsp;</td></tr>


        
        <!-- Conocimientos Previos -->
<tr>
    <td>
        <div class="heading1 WidthDiv" align="left"  style="">
            Conocimientos previos esperados

    <img style="cursor: pointer; vertical-align: middle;" 
     src="images/info.png" 
     width="18px" 
     height="18px"
     onclick="mostrarAyuda('Conocimientos previos esperados', 'Indique los conocimientos, habilidades o bases acad�micas que el estudiante debe poseer antes de cursar esta asignatura.<br>Puede incluir conceptos, cursos previos o �reas de conocimiento recomendadas. No se refiere a prerrequisitos formales, sino al nivel esperado para un adecuado aprovechamiento del curso. Si no se requieren conocimientos previos, puede indicarlo expl�citamente.');"
     >

            <img id="imgEditConocimientos" 
             src="<?php print strGetCoreImageWithPath('edit.gif'); ?>" 
             style="cursor: pointer; display: none; vertical-align: middle;" 
             title="Editar" 
             onclick="fntEditSyllabusField('Conocimientos');">


                 <?php if($intSyllabusUA > 0) { ?>
        <img src="<?php print strGetCoreImageWithPath('acad_bitacora_orange.png'); ?>" width="20px" height="20px"
             style="cursor: pointer; margin-left: 10px; vertical-align: middle;" 
             title="Ver bit�cora de cambios"
             onclick="fntMostrarBitacoraCampo(<?php print $intSyllabusUA; ?>, 'CONOCIMIENTOS_PREVIOS');">
    <?php } ?>


        </div>

    </td>
</tr>

        <tr><td>&nbsp;</td></tr>
        <tr>
            <td align="left">
                <input type="hidden" name="hidEditedConocimientos" id="hidEditedConocimientos" value="N">
                <input type="hidden" name="hidUltimoValidadoConocimientos" id="hidUltimoValidadoConocimientos" value="">
                <input type="hidden" name="hidEstadoLLMConocimientos" id="hidEstadoLLMConocimientos" value="">
                <input type="hidden" name="hidValorAceptadoConocimientos" id="hidValorAceptadoConocimientos" value="">
                <input type="hidden" name="hidAceptadoManualConocimientos" id="hidAceptadoManualConocimientos" value="">
                <span id="spanConocimientos" style="font-family: Verdana, Geneva, Arial, Helvetica, sans-serif; font-size: 11px;"><?php print !empty($strConocimientos) ? $strConocimientos : '<em>Sin informaci�n</em>'; ?></span>
                <div id="wrapEditorConocimientos" style="display:none;">
                    <textarea id="txtConocimientos" style="width:100%;"><?php print htmlspecialchars($strConocimientos, ENT_QUOTES); ?></textarea>
                    <div id="divResultadoLLMConocimientos" style="display: none; margin: 10px 0;"></div>
                </div>
                <textarea name="frm_sylUA_txtConocimientos" id="frm_sylUA_txtConocimientos" style="display:none;"></textarea>
            </td>
        </tr>
        <tr><td>&nbsp;</td></tr>



        

        

        
        <?php
// Construir contenido din�mico para el modal de Bibliograf�a

/*$strContenidoModalBiblio = "Liste las fuentes bibliogr�ficas fundamentales del curso definidas por la unidad acad�mica.<br>";
$strContenidoModalBiblio .= "Estas constituyen la base m�nima de referencia para el desarrollo de los contenidos y el logro de los resultados de aprendizaje.";
$strContenidoModalBiblio .= "<br><hr style='border: 1px solid #ccc; margin: 15px 0;'>";
$strContenidoModalBiblio .= "<strong>Formato bibliogr�fico requerido: Chicago</strong><br><br>";
$strContenidoModalBiblio .= "Autor. T�tulo. Ciudad: Editorial, A�o.<br>";
$strContenidoModalBiblio .= "<em>Ejemplo: L�pez, Mar�a. Estad�stica Aplicada. Madrid: Springer, 2018.</em><br><br>";
$strContenidoModalBiblio .= "<em>La validaci�n autom�tica con IA eval�a las referencias �nicamente contra el formato Chicago (Bibliography).</em>";



// Escapar comillas para JavaScript
$strContenidoModalBiblio = str_replace("'", "\\'", $strContenidoModalBiblio);
//$strContenidoModalBiblio = str_replace('"', '\\"', $strContenidoModalBiblio);
$strContenidoModalBiblio = str_replace('"', '&quot;', $strContenidoModalBiblio);*/



// Construir contenido din�mico para el modal de Bibliograf�a (chicago_info.html)
$strContenidoModalBiblio = "<p>Liste las fuentes bibliogr�ficas fundamentales del curso definidas por la unidad acad�mica. ";
$strContenidoModalBiblio .= "Estas constituyen la base m�nima de referencia para el desarrollo de los contenidos y el logro de los resultados de aprendizaje.</p>";
$strContenidoModalBiblio .= "<hr>";
$strContenidoModalBiblio .= "<p><strong>Formato bibliogr�fico requerido: Chicago (Bibliograf�a)</strong></p>";
$strContenidoModalBiblio .= "<p>La validaci�n autom�tica con IA eval�a las referencias �nicamente contra el formato Chicago para ";
$strContenidoModalBiblio .= "<strong>bibliograf�a</strong> (no el de notas al pie/finales, ni el de autor-fecha).</p>";
$strContenidoModalBiblio .= "<hr>";
$strContenidoModalBiblio .= "<p><strong>Estructura general (libro completo, el caso m�s frecuente):</strong></p>";
$strContenidoModalBiblio .= "<p>Autor. <em>T�tulo del libro</em>. Ciudad: Editorial, A�o.</p>";
$strContenidoModalBiblio .= "<p><em>Ejemplo:</em> L�pez, Mar�a. <em>Estad�stica aplicada</em>. Madrid: Springer, 2018.</p>";
$strContenidoModalBiblio .= "<ul>";
$strContenidoModalBiblio .= "<li>El apellido del autor va primero, seguido de coma y el nombre (solo se invierte el primer autor si hay varios).</li>";
$strContenidoModalBiblio .= "<li>El t�tulo del libro siempre va en <em>cursiva</em>.</li>";
$strContenidoModalBiblio .= "<li>Los datos de publicaci�n no van entre par�ntesis .</li>";
$strContenidoModalBiblio .= "<li>Para libros, solo se indica el a�o de publicaci�n, sin mes ni d�a.</li>";
$strContenidoModalBiblio .= "</ul>";
$strContenidoModalBiblio .= "<hr>";
$strContenidoModalBiblio .= "<p><strong>Otros tipos de fuente (menos frecuentes):</strong></p>";
$strContenidoModalBiblio .= "<p><strong>Art�culo de revista:</strong><br>";
$strContenidoModalBiblio .= "Autor. \"T�tulo del art�culo\". <em>Nombre de la Revista</em> Volumen, n.� N�mero (A�o): p�ginas.</p>";
$strContenidoModalBiblio .= "<p style=\"margin-left: 1em;\"><em>Ejemplo:</em> P�rez, Juan. \"El cambio clim�tico en Am�rica Latina\". <em>Revista de Ecolog�a</em> 12, n.� 3 (2020): 45-60.</p>";
$strContenidoModalBiblio .= "<p><strong>Cap�tulo de libro:</strong><br>";
$strContenidoModalBiblio .= "Autor del cap�tulo. \"T�tulo del cap�tulo\". En <em>T�tulo del libro</em>, editado por Nombre del Editor, p�ginas. Ciudad: Editorial, A�o.</p>";
$strContenidoModalBiblio .= "<hr>";
$strContenidoModalBiblio .= "<p style=\"font-style: italic; color: #666;\">Consejo: si la fuente es un libro completo (el caso m�s com�n), basta con seguir el ejemplo de arriba: ";
$strContenidoModalBiblio .= "Autor. <em>T�tulo</em>. Ciudad: Editorial, A�o.</p>";

// Escapar comillas para JavaScript
$strContenidoModalBiblio = str_replace("'", "\\'", $strContenidoModalBiblio);
$strContenidoModalBiblio = str_replace('"', '&quot;', $strContenidoModalBiblio);


?>

        <!-- Bibliograf�a de Base -->
      <tr>
    <td><div class="heading1 WidthDiv" align="left">Bibliograf�a base m�nima
        <img style="cursor: pointer; vertical-align: middle;" 
             src="images/info.png" 
             width="18px" 
             height="18px"
             onclick="mostrarAyuda('Bibliograf�a base m�nima', '<?php print $strContenidoModalBiblio; ?>');">

             
    <?php if($intSyllabusUA > 0) { ?>
    <img src="<?php print strGetCoreImageWithPath('acad_bitacora_orange.png'); ?>"
         width="20px"
         height="20px"
         style="cursor: pointer; margin-left: 10px; vertical-align: middle;"
         title="Ver bit�cora general de bibliograf�as"
         onclick="fntMostrarBitacoraTodosBiblio(<?php print $intSyllabusUA; ?>);">
    <?php } ?>

    </div></td>
</tr>
<tr><td>&nbsp;</td></tr>
<tr>
    <td align="left">
        <table id="tblBibliografia" class="table1" width="100%" cellpadding="3" cellspacing="1">
            <tr>
                            <th class="row0" width="95%" style="text-align: left; padding-left: 15px;">Bibliograf�a completa</th>
               <!-- <th class="row0" width="5%"></th> -->
                                <th width="5%" class="row0" align="center"></td>

            </tr>


            
            <?php
            if(empty($arrBibliografias)) {
            ?>
            <!--
            <tr id="rowNingunoBiblio">
                <td class="row1" colspan="2" align="left"><em>No hay bibliograf�a registrada</em></td>
            </tr>
            -->
            <?php
            } else {
               /* foreach($arrBibliografias as $rBiblio) {
                    $intContadorBiblio++;
                    $strClassBiblio = ($strClassBiblio == "row1") ? "row2" : "row1";*/
                        $numVisual = 0; // Contador para numeraci�n visual
    foreach($arrBibliografias as $rBiblio) {
        $intContadorBiblio++;
        $numVisual++; // Incrementar numeraci�n visual
        $strClassBiblio = ($strClassBiblio == "row1") ? "row2" : "row1";
            ?>
            <tr id="trBiblio_<?php print $intContadorBiblio; ?>">
<td class="<?php print $strClassBiblio; ?>" style="padding: 3px 15px;">
    <div id="spanReferenciaBiblio_<?php print $intContadorBiblio; ?>" 
         style="display: block; text-align: left; padding: 5px 10px; line-height: 1.4; font-family: Verdana, Geneva, Arial, Helvetica, sans-serif; font-size: 11px;">
        <strong><?php print $numVisual; ?>.</strong>
        <?php
        $strRefBiblio = $rBiblio['REFERENCIA_COMPLETA'];
        print renderReferenciaBiblioVista($strRefBiblio);
        ?>
    </div>

    <div id="wrapEditorBiblio_<?php print $intContadorBiblio; ?>" style="display: none;">
        <textarea name="txtReferenciaBiblio_<?php print $intContadorBiblio; ?>" 
                  id="txtReferenciaBiblio_<?php print $intContadorBiblio; ?>"
                  style="width: 95%;"><?php print htmlspecialchars($rBiblio['REFERENCIA_COMPLETA'], ENT_QUOTES); ?></textarea>
    </div>
    
    <div id="divResultadoLLMBiblio_<?php print $intContadorBiblio; ?>" 
         style="display: none; margin: 15px 10px; padding: 15px; border-left: 4px solid #ccc; 
                background-color: #f9f9f9; font-size: 12px; line-height: 1.6; text-align: left;">
    </div>

</td>
                
                <td class="<?php print $strClassBiblio; ?>" align="center">
                    <img src="<?php print strGetCoreImageWithPath('edit.gif'); ?>" 
                         style="cursor: pointer; display: none;" 
                         id="imgEditBiblio_<?php print $intContadorBiblio; ?>"
                         title="Editar" 
                         onclick="fntEditBiblio(<?php print $intContadorBiblio; ?>);">
                    
                    <img src="<?php print strGetCoreImageWithPath('del.png'); ?>" 
                         style="cursor: pointer; display: none;" 
                         id="imgDeleteBiblio_<?php print $intContadorBiblio; ?>"
                         title="Eliminar" 
                         onclick="fntDeleteBiblio(<?php print $intContadorBiblio; ?>);">
                    
                    <input type="hidden" name="hidDeleteBiblio_<?php print $intContadorBiblio; ?>" 
                           id="hidDeleteBiblio_<?php print $intContadorBiblio; ?>" value="N">
                    <input type="hidden" name="hidUpdateBiblio_<?php print $intContadorBiblio; ?>" 
                           value="<?php print $rBiblio['SYLLABUS_UA_BIBLIO']; ?>">
                    <input type="hidden" name="hidEditedBiblio_<?php print $intContadorBiblio; ?>" 
                           id="hidEditedBiblio_<?php print $intContadorBiblio; ?>" value="N">
                    <input type="hidden" name="hidEstadoLLMBiblio_<?php print $intContadorBiblio; ?>" 
           id="hidEstadoLLMBiblio_<?php print $intContadorBiblio; ?>" value="">
                </td>
            </tr>
            <?php
                }
            }
            ?>
            
            <!-- Fila Template (oculta) -->
            <tr style="display: none;" id="trBiblioInicial">
    <td class="row1" style="padding: 15px;">
        <div id="spanReferenciaBiblio_0" style="display: none;"></div>
        <div id="wrapEditorBiblio_0">
            <textarea id="txtReferenciaBiblio_0"
                      style="width: 95%;"
                      placeholder="Ingrese la bibliograf�a completa (formato Chicago)"></textarea>
        </div>

        <div id="divResultadoLLMBiblio_0" 
             style="display: none; margin: 15px 10px; padding: 15px; border-left: 4px solid #ccc; 
                    background-color: #f9f9f9; font-size: 12px; line-height: 1.6; text-align: left;">
        </div>

                </td>
                <td class="row1" align="center">
                    <img id="imgDeleteBiblio_0" 
                         src="<?php print strGetCoreImageWithPath('del.png'); ?>" 
                         style="cursor: pointer;" 
                         title="Eliminar" 
                         onclick="fntDeleteBiblio(0);">
                    <input type="hidden" id="hidDeleteBiblio_0" value="N">
                    <input type="hidden" id="hidNewBiblio_0" value="1">
                    <input type="hidden" id="hidEditedBiblio_0" value="N">
                    <input type="hidden" id="hidEstadoLLMBiblio_0" value="">

                </td>
            </tr>
            
            <tr>
                <td colspan="2" align="left" style="padding: 10px;">
                    <div id="divButtonAgregarBiblio" style="display: none;">
                        <img src="<?php print strGetCoreImageWithPath('add.png'); ?>" 
                             style="cursor: pointer;" 
                             onclick="addRowBiblio();" 
                             title="Agregar Bibliograf�a">
                    </div>
                </td>
            </tr>
        </table>
        
        <script type="text/javascript">
            var strClassBiblio = "<?php print $strClassBiblio; ?>";
            var intContadorBiblio = <?php print $intContadorBiblio; ?>;
        </script>
    </td>
</tr>


        <tr><td>&nbsp;</td></tr>

        
        
        <!-- Marco Normativo -->
<tr>
    <td>
        <div class="heading1 WidthDiv" align="left"  style="">
            Marco normativo institucional

            <img style="cursor: pointer; vertical-align: middle;" 
     src="images/info.png" 
     width="18px" 
     height="18px"
     onclick="mostrarAyuda('Marco normativo institucional', 'Describa las normas y lineamientos aplicables al desarrollo del curso. Incluya aspectos como integridad acad�mica, disciplina, comportamiento, asistencia o uso de recursos. Puede incorporar reglas espec�ficas del curso y referencias a reglamentos institucionales. Estas normas deben ser respetadas durante el desarrollo del curso.');"
>

            <img id="imgEditMarco" 
             src="<?php print strGetCoreImageWithPath('edit.gif'); ?>" 
             style="cursor: pointer; display: none; vertical-align: middle;" 
             title="Editar" 
             onclick="fntEditSyllabusField('Marco');">

                 <?php if($intSyllabusUA > 0) { ?>
        <img src="<?php print strGetCoreImageWithPath('acad_bitacora_orange.png'); ?>" width="20px" height="20px"
            style="cursor: pointer; margin-left: 10px; vertical-align: middle;" 
             title="Ver bit�cora de cambios"
             onclick="fntMostrarBitacoraCampo(<?php print $intSyllabusUA; ?>, 'MARCO_NORMATIVO');">
    <?php } ?>

        </div>

    </td>
</tr>

        <tr><td>&nbsp;</td></tr>
        <tr>
            <td align="left">
                <input type="hidden" name="hidEditedMarco" id="hidEditedMarco" value="N">
                <input type="hidden" name="hidUltimoValidadoMarco" id="hidUltimoValidadoMarco" value="">
                <input type="hidden" name="hidEstadoLLMMarco" id="hidEstadoLLMMarco" value="">
                <input type="hidden" name="hidValorAceptadoMarco" id="hidValorAceptadoMarco" value="">
                <input type="hidden" name="hidAceptadoManualMarco" id="hidAceptadoManualMarco" value="">
                <span id="spanMarco" style="font-family: Verdana, Geneva, Arial, Helvetica, sans-serif; font-size: 11px;"><?php print !empty($strMarco) ? $strMarco : '<em>Sin informaci�n</em>'; ?></span>
                <div id="wrapEditorMarco" style="display:none;">
                    <textarea id="txtMarco" style="width:100%;"><?php print htmlspecialchars($strMarco, ENT_QUOTES); ?></textarea>
                    <div id="divResultadoLLMMarco" style="display: none; margin: 10px 0;"></div>
                </div>
                <textarea name="frm_sylUA_txtMarco" id="frm_sylUA_txtMarco" style="display:none;"></textarea>
            </td>
        </tr>


        
    </table>
</div>

<!--PREREQUISITO-->
                                <div id="tabCurso">
                                    <?php
                                    $objPreRequisito->drawContenidoCursos(false);
                                    ?>
                                </div>
                               <div id="tabCursosImpartidos">
                                    <div id="divCursosImpartidos"></div>
                                    <script type="text/javascript" language="javascript">
                                        getDocumentLayer("TabCursosImpartidos").innerHTML = "Cursos impartidos (<?php print getTotalCursosImpartidos($intCurso); ?>)"
                                    </script>
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>
            </form>

            <?php
        theme_draw_content_close(true);

    theme_draw_layer_close();


        ?>


        <?php
            drawBlurEdicionAutorizacion();
            drawBlurVersionControl();
            drawBlurs();
        theme_draw_centerbox_close();
        draw_footer();

    function updateClob($strKeyMatch, $tempcfg, $strProcedureName) {

        global $cfg;

        //drawDebug($strKeyMatch);
        //drawDebug($tempcfg);
        //drawDebug($strProcedureName);

        $intCountToUpdate = 0;
        if( empty($tempcfg)) $tempcfg = " ";
        while( $intCountToUpdate <= strlen($tempcfg) ) {
            $strTMPTexto = substr($tempcfg,$intCountToUpdate,4000);
            $ret = db_query("call {$cfg["academico"]["schema"]}.{$strProcedureName}( '{$strKeyMatch}','{$strTMPTexto}')");
            $intCountToUpdate += 4000;
        }
    }

    function localSelectClob($strCampo, $strTabla, $strCampoId, $strKeyMatch) {

        if( empty($strCampo) ) return "";
        $strQuery = "SELECT MAX(dbms_lob.getlength({$strCampo}) ) FROM {$strTabla} WHERE {$strCampoId} = '{$strKeyMatch}'";
        $intCountPartes = sqlGetValueFromKey($strQuery);
        $strCampos = "";
        $intCount = 1;
        $intParte = 0;
        while( $intCount <= $intCountPartes ) {
            $intParte++;
            $strCampos .= (empty($strCampos)) ? "" : ",";
            $strCampos .= "DBMS_LOB.substr({$strCampo}, 4000, {$intCount}) PARTE{$intParte}";
            $intCount += 4000;
        }

        if( empty($strCampos) ) return "";
        $strQuery = "SELECT {$strCampoId}, {$strCampos} FROM {$strTabla} where {$strCampoId}='{$strKeyMatch}'";
        $ret = sqlGetValueFromKey($strQuery);

        $strTextoClob = "";
        for( $i = 1; $i <= $intParte; $i++ ) {
            if( !is_null($ret["PARTE{$i}"]) && !empty($ret["PARTE{$i}"]) )
                $strTextoClob .= $ret["PARTE{$i}"];
        }
        return $strTextoClob;
    }

    function drawTablaBannerAutorizacion($intCurso){
        $arrAutorizacion = getSiCarreraEstaAutorizada($intCurso);
        $boolAutorizacion = isset($arrAutorizacion['AUTORIZADO'])?$arrAutorizacion['AUTORIZADO']:false;
        $strMensajeAutorizado = isset($arrAutorizacion['DESCRIPCION_ESTADO'])?$arrAutorizacion['DESCRIPCION_ESTADO']:'';
        $strFecha = isset($arrAutorizacion['FECHA'])?show_date($arrAutorizacion["FECHA"],true,true,true,true):'';
        $strNombrePersona = isset($arrAutorizacion['NOMBRE_PERSONA'])?$arrAutorizacion["NOMBRE_PERSONA"]:'';
        $strColorDiv = "#FF2B00";
        $strHTML = "<div id='divBoubleAutorizacion'>";
        if($boolAutorizacion == true){
            $strColorDiv = "#04B816";
            $strMensajeAutorizado = "Autorizado";
        }
        $strHTML = "<table width='100%' cellspacing='2' cellpadding='2'>";
        $strHTML = $strHTML."<tr><td class='editTitles'>{$strMensajeAutorizado}</td><td>&nbsp;</td><td>".$strNombrePersona."</tr></td>";
        $strHTML = $strHTML."<tr><td class='editTitles'>Fecha</td><td>&nbsp;</td><td>".$strFecha."</tr></td>";
        $strHTML = $strHTML."</table>";
        $strHTML = $strHTML."</div>";
        ?>
        <script type="text/javascript" language="JavaScript">
            $(function(){
                $("#divAutorizacion").CreateBubblePopup({
                    distance: "10px",
                    width: "350",
                    tail:{
                        align:'center',
                        hidden: false
                    },
                    align: 'left',
                    innerHtml: "<?php print $strHTML?>",
                    themeName: 'all-black',
                    themePath: 'core/jquery/bubblepopup/jquerybubblepopup-theme',
                    divStyle: {position:'absolute;z-index:1050'},
                    themeMargins: {difference: '4px'}
                });
            });
        </script>
        <div id="divAutorizacion" style="color:<?php print $strColorDiv?>;font-size:14px;width: auto;">
            <?php print $strMensajeAutorizado;?>
        </div>
        <?php
        return $boolAutorizacion;
    }

    function getSiCarreraEstaAutorizada($intCurso){
        $arrResultado = array();
        $strQuery = "SELECT  aut_caso.aut_caso,
                        aut_caso_estado.aut_caso_estado,
                        persona.persona,
                        aut_caso.add_fecha fecha,
                        aut_estado_caso_tabla.campo_update,
                        aut_estado_caso_tabla.valor,
                        aut_estado_caso.descripcion,
                        persona.nombre1,
                        persona.nombre2,
                        persona.apellido1,
                        persona.apellido2,
                        persona.apellido_casada,
                        'PERSONA_INICIA' tipo,
                        aut_caso_llave.valor,
                        aut_estado_caso_tabla.valor estado
                FROM aut_caso_llave
                    INNER JOIN aut_caso
                        ON AUT_CASO_LLAVE.AUT_CASO = aut_caso.aut_caso
                    INNER JOIN aut_caso_estado
                        ON aut_caso.aut_caso = aut_caso_estado.aut_caso
                    INNER JOIN aut_llave
                        ON aut_caso_llave.aut_llave = aut_llave.aut_llave
                    INNER JOIN aut_tabla
                        ON aut_llave.aut_tabla =  aut_tabla.aut_tabla
                    INNER JOIN persona
                        ON  aut_caso.persona_inicia = persona.persona
                    INNER JOIN aut_estado_caso
                        ON aut_caso_estado.aut_estado_caso = aut_estado_caso.aut_estado_caso
                    INNER JOIN aut_estado_caso_tabla
                        ON aut_caso_estado.aut_estado_caso = aut_estado_caso_tabla.aut_estado_caso
                WHERE aut_caso_estado.activo = 'Y'
                AND aut_llave.campo = 'CURSO'
                AND aut_tabla.nombre_aut_tabla = 'CURSO'
                AND aut_caso_llave.valor = {$intCurso}
                AND aut_caso.aut_tipo_caso = 14
                AND aut_estado_caso.orden = 1

                UNION

               SELECT  aut_caso.aut_caso,
                        aut_caso_estado.aut_caso_estado,
                        persona.persona,
                        aut_persona_caso_estado.fecha,
                        aut_estado_caso_tabla.campo_update,
                        aut_estado_caso_tabla.valor,
                        aut_estado_caso.descripcion,
                        persona.nombre1,
                        persona.nombre2,
                        persona.apellido1,
                        persona.apellido2,
                        persona.apellido_casada,
                        'PERSONA_AUTORIZA' tipo,
                        aut_caso_llave.valor,
                        aut_estado_caso_tabla.valor estado
                FROM aut_caso_llave
                    INNER JOIN aut_caso
                        ON AUT_CASO_LLAVE.AUT_CASO = aut_caso.aut_caso
                    INNER JOIN aut_caso_estado
                        ON aut_caso.aut_caso = aut_caso_estado.aut_caso
                    INNER JOIN aut_llave
                        ON aut_caso_llave.aut_llave = aut_llave.aut_llave
                    INNER JOIN aut_tabla
                        ON aut_llave.aut_tabla =  aut_tabla.aut_tabla
                    INNER JOIN aut_estado_caso
                        ON aut_caso_estado.aut_estado_caso = aut_estado_caso.aut_estado_caso
                    INNER JOIN aut_transicion_estado_caso
                       ON  aut_caso_estado.aut_estado_caso = aut_transicion_estado_caso.aut_estado_caso_siguiente
                    INNER JOIN aut_caso_estado aut_caso_estado2
                        ON aut_transicion_estado_caso.aut_estado_caso = aut_caso_estado2.aut_estado_caso
                        AND aut_caso_estado2.aut_caso = aut_caso.aut_caso
                    INNER JOIN aut_persona_caso_estado
                            ON  aut_persona_caso_estado.aut_caso_estado = aut_caso_estado2.aut_caso_estado
                    INNER JOIN persona
                        ON aut_persona_caso_estado.persona = persona.persona
                    INNER JOIN aut_estado_caso_tabla
                        ON aut_caso_estado.aut_estado_caso = aut_estado_caso_tabla.aut_estado_caso
                WHERE aut_caso_estado.activo = 'Y'
                AND aut_llave.campo = 'CURSO'
                AND aut_tabla.nombre_aut_tabla = 'CURSO'
                AND aut_caso_llave.valor = {$intCurso}
                AND aut_caso.aut_tipo_caso = 14";
        $qTMP = db_query($strQuery);
        while($rTMP = db_fetch_assoc($qTMP)){
            $arrResultado['NOMBRE_PERSONA'] = academico_getPersonaNombre($rTMP["NOMBRE1"],$rTMP["NOMBRE2"],$rTMP["APELLIDO1"],$rTMP["APELLIDO2"],$rTMP["APELLIDO_CASADA"]);
            $arrResultado['DESCRIPCION_ESTADO'] = $rTMP['DESCRIPCION'];
            $arrResultado['FECHA'] = $rTMP['FECHA'];
            $arrResultado['AUTORIZADO'] = ($rTMP['ESTADO'] == 2)?true:false;
        };
        if( count($arrResultado) == 0 ){
            $strQuery = "SELECT estado_autorizador
                         FROM   curso
                         WHERE curso  = {$intCurso}
                         ";
            $qTMP = db_query($strQuery);
            while($rTMP = db_fetch_assoc($qTMP)){
                $arrResultado['AUTORIZADO'] = ($rTMP['ESTADO_AUTORIZADOR'] == 2)?true:false;
                if($arrResultado['AUTORIZADO']){
                    $arrResultado['DESCRIPCION_ESTADO'] = 'Autorizado';
                }
                else{
                    $arrResultado['DESCRIPCION_ESTADO'] = 'Pendiente';
                }
            };
            db_free_result($qTMP);
        }

        return $arrResultado;
    }

    function drawBotonAutorizacion(){
        global $cfg;
        global $strAction;
        global $arrModificarAutorizado;
        DrawJQueryButton("buttonSolicitarAutorizacion", "Autorizaci�n", "save");
        ?>
        <script type="text/javascript" language="JavaScript">
            OnclickJQueryButton("buttonSolicitarAutorizacion", "fntSetAutorizacion();");

            function fntSetAutorizacion(){
                $.ajax({
                    url:"<?php print $strAction; ?>",
                    data: {
                        setAutorizacion: true,
                        curso:'<?php print isset($_GET["curso"])?$_GET["curso"]:0;?>'
                    },
                    type:"post",
                    dataType:"html",
                    beforeSend: function() {
                        intTop = ( $(window).height() * 1 ) / 2;
                        $("#divShowLoadingGeneralBig").css("top", intTop);
                        $("#divShowLoadingGeneralBig").css("left", 0);
                        $("#divShowLoadingGeneralBig").show();
                    },
                    success: function(data){
                        DisableJQueryButton("buttonSolicitarAutorizacion", true);

                        if(data == "Y"){
                            boolAutorizacionCurso = true;
                            fntSetBouble();
                            <?php
                            if(isset($arrModificarAutorizado[1])){ //permiso pendiente
                                ?>
                                DisableJQueryButton("butEditDetalleCurso", false);
                                <?php
                            }else{
                                ?>
                                DisableJQueryButton("butEditDetalleCurso", true);
                                <?php
                            }
                            ?>
                            addAlertOnFooter("update","Autorizaci�n realizada");
                        }else{
                            addAlertOnFooter("error","No se realizo la autorizaci�n");
                        }
                        $("#divShowLoadingGeneralBig").hide();
                    }
                });
            }

            function fntSetBouble(){
                $.ajax({
                    url:"<?php print $strAction; ?>",
                    data: {
                        setBouble: true,
                        curso:'<?php print isset($_GET["curso"])?$_GET["curso"]:0;?>'
                    },
                    type:"post",
                    dataType:"html",
                    beforeSend: function() {
                        intTop = ( $(window).height() * 1 ) / 2;
                        $("#divShowLoadingGeneralBig").css("top", intTop);
                        $("#divShowLoadingGeneralBig").css("left", 0);
                        $("#divShowLoadingGeneralBig").show();
                    },
                    success: function(data){
                        $(function(){
                            $("#divAutorizacion").CreateBubblePopup({
                                distance: "10px",
                                width: "350",
                                tail:{
                                    align:'center',
                                    hidden: false
                                },
                                align: 'left',
                                innerHtml: data,
                                themeName: 'all-black',
                                themePath: 'core/jquery/bubblepopup/jquerybubblepopup-theme',
                                divStyle: {position:'absolute;z-index:1050'},
                                themeMargins: {difference: '4px'}
                            });
                        });
                        $("#divAutorizacion").html("Autorizado");
                        $("#divAutorizacion").css("color","#04B816");
                        $("#divShowLoadingGeneralBig").hide();
                    }
                });
            }
        </script>
        <?php
    }

    function UpdateAutorizacion($intCurso){
        $intCurso = intval($intCurso);
        $intAddUser = ( isset($_SESSION["wt"]["originalUserToTest"]) ? $_SESSION["wt"]["originalUserToTest"] : $_SESSION["wt"]["uid"] );
        if($intCurso > 0 && $intAddUser > 0){
            $strQuery = "UPDATE curso
                         SET    autoriza       = 'Y',
                                autoriza_fecha = NOW(),
                                autoriza_user  = {$intAddUser}
                         WHERE  curso = {$intCurso}";
            db_query($strQuery);
            return true;
        }else{
            return false;
        }
    }

    function tablaBuble($intCurso){
        $arrAutorizacion = getSiCarreraEstaAutorizada($intCurso);
        if($arrAutorizacion["AUTORIZADO"] == true){
            ?>
            <table width="100%">
                <tr>
                    <td class="editTitles">Autorizado</td>
                    <td>&nbsp;</td>
                    <td><?php print $arrAutorizacion["NOMBRE_PERSONA"];?></td>
                </tr>
                <tr>
                    <td class="editTitles">Fecha</td>
                    <td>&nbsp;</td>
                    <td><?php print show_date($arrAutorizacion["FECHA"],true,true,true,true);?></td>
                </tr>
            </table>
            <?php
        }
    }

    function drawBlurEdicionAutorizacion(){
        ?>
        <div id="divBlurEditAutorizacion" style="display: none;" title="Edici�n de curso con autorizaci�n realizada">
              <table width="100%">
            <tr>
                <td>
                    Editar este curso deshabilitar� las asignaciones y cualquier uso relacionado.
                </td>
            </tr>
            <tr>
                <td>
                    �Desea continuar con la edici�n?
                </td>
            </tr>
        </table>
        </div>
        <script type="text/javascript" language="JavaScript">
        $(function(){

            $("#divBlurEditAutorizacion").dialog({
                autoOpen: false,
                show: "explode",
                hide: "explode",
                modal: "true",
                resizable: false,
                draggable: false,
                width: 400,
                buttons: {
                    "Aceptar": function(){
                        fntEditDetalle();
                        $( this ).dialog( "close" );
                    },
                    "Cancelar": function(){
                        $( this ).dialog( "close" );
                    }
                }

            });
        });
        </script>
        <?php
    }


            function drawBlurVersionControl(){
        ?>

<div id="divBlurVersionControl" style="display: none;" title="Control de versiones del syllabus">
    <div style="line-height: 1.6;">
        
        <p style="margin-bottom: 20px;">
            El sistema mantiene un <strong>historial de versiones</strong> del syllabus. 
            Seleccione c�mo desea guardar los cambios realizados:
        </p>
        
        <div style="border: 1px solid #4CAF50; padding: 15px; margin-bottom: 15px; border-radius: 4px; background-color: #f1f8f4;">
            <div style="font-weight: bold; margin-bottom: 8px; color: #2e7d32;">
                Guardar (versi�n actual)
            </div>
            <div style="font-size: 12px; color: #333;">
                Al presionar el bot�n <strong>"Guardar (versi�n actual)"</strong>, se actualizar� la versi�n actual (activa) con los cambios realizados.
               <!-- Esta versi�n es la que se muestra a los catedr�ticos cuando inician su syllabus. -->
            </div>
        </div>
        
        <div style="border: 1px solid #2196F3; padding: 15px; border-radius: 4px; background-color: #e3f2fd;">
            <div style="font-weight: bold; margin-bottom: 8px; color: #1565c0;">
                Crear nueva versi�n
            </div>
            <div style="font-size: 12px; color: #333;">
                Al presionar el bot�n <strong>"Crear nueva versi�n"</strong>, se cerrar� la versi�n actual y se crear� una nueva versi�n activa. La versi�n anterior queda archivada y puede consultarse en "Ver versiones".
            </div>
        </div>
        
    </div>
</div>

        <script type="text/javascript" language="JavaScript">
        $(function(){
            $("#divBlurVersionControl").dialog({
                autoOpen: false,
                show: "explode",
                hide: "explode",
                modal: true,
                resizable: true,
                draggable: true,
                width: 800,
                maxHeight: 700,
                open: function() {
                    $(this).css({
                        'font-family': 'Verdana,Geneva,Arial,Helvetica,sans-serif',
                        'font-size': '11px',
                        'line-height': '1.6',
                        'padding': '15px'
                    });
                    $(this).find('*').not('table, table *').css({
                        'font-family': 'Verdana,Geneva,Arial,Helvetica,sans-serif',
                        'font-size': '11px'
                    });
                },
                buttons: {
                    "Guardar (versi�n actual)": function(){                        
                        $(this).dialog("close");
                        fntSubmitFormDetalleCurso();
                    },
                    "Crear nueva versi�n": function(){
                        var hidTipoGuardado = document.createElement("input");
                        hidTipoGuardado.type = "hidden";
                        hidTipoGuardado.name = "hidTipoGuardado";
                        hidTipoGuardado.value = "nueva_version";
                        document.getElementById("frm_detalle").appendChild(hidTipoGuardado);
                        
                        $(this).dialog("close");
                        fntSubmitFormDetalleCurso();
                    }
                }
            });


            
        });
        </script>
        <?php
    }

    function drawBlurs(){
        ?>
        <div id="divBlurValidarPreRequisitos" style="display: none;" title="Validador de prerrequisitos"></div>
        <div id="divBlurListadoPensum" style="display: none;" title="Listado de pensum"></div>
        <div id="divSolicitarAnulacion" name="divSolicitarAnulacion"  style="display: none;" title=""></div>

        <div id="divBlurVersionesSyllabus" style="display: none;" title="Versiones del syllabus (unidad acad�mica)"></div>
        <div id="divBlurDetalleSyllabusVersion" style="display: none;" title="Detalle de versi�n"></div>


        <div id="divBlurBitacoraCampo" style="display: none;" title="Bit�cora de cambios"></div>

        <div id="divBlurDetalleLogCampo" style="display: none;" title="Ver contenido completo"></div>
        <div id="divBlurValorActualCampo" style="display: none;" title="Valor actual del campo"></div>



        <div id="divBlurBitacoraRA" style="display: none;" title="Bit�cora de resultado de aprendizaje"></div>
        <div id="divBlurBitacoraTodosRA" style="display: none;" title="Bit�cora de resultados de aprendizaje"></div>

        <div id="divBlurBitacoraRAEliminado" style="display: none;" title="Bit�cora de resultado de aprendizaje"></div>

        <div id="divBlurBitacoraBiblio" style="display: none;" title="Bit�cora de bibliograf�a"></div>
        <div id="divBlurBitacoraTodosBiblio" style="display: none;" title="Bit�cora de bibliograf�as"></div>
        <div id="divBlurBitacoraBiblioEliminado" style="display: none;" title="Bit�cora de bibliograf�a eliminada"></div>

        




        <div id="divModalAyuda" style="display: none;">
                <div id="divModalAyudaContenido" style="padding: 15px; line-height: 1.6; font-size: 13px;">
                <!-- El contenido se cargar� din�micamente -->
            </div>
        </div>


        <script type="text/javascript">
            $(function(){
                $("#divModalAyuda").dialog({
                    autoOpen: false,
                    show: "explode",
                    hide: "explode",
                    modal: true,
                    resizable: false,
                    draggable: true,
                    width: 1000,
                    buttons: {
                        "Cerrar": function(){
                            $(this).dialog("close");
                        }
                    }
                });
            });

            // Funci�n reutilizable para mostrar ayuda
            function mostrarAyuda(titulo, contenido) {
                $("#divModalAyuda").dialog("option", "title", titulo);
                $("#divModalAyudaContenido").html(contenido);
                $("#divModalAyuda").dialog("open");
            }
        </script>


        
        <?php
    }

    function drawBlurValidarPreRequisitos(){
        global $strAction;

        $intFacultad = isset($_GET["facultad"]) ? intval($_GET["facultad"]) : 0;
        ?>
        <table width="100%" cellpadding="5" cellspacing="0">
            <tr>
                <td width="20%">Alumno</td>
                <td width="80%">
                    <input type="text" class="field_textbox_stand_by inputSizeComplete" id="txtNombreAlumno" value="B�squeda de alumno">
                </td>
            </tr>
            <tr>
                <td>Carrera y p�nsum</td>
                <td id="tdCarrerasPensumAlumno">
                    &nbsp;
                </td>
            </tr>
        </table>
        <br>
        <div id="divBannerPersona"></div>
        <script type="text/javascript">
            $("#txtNombreAlumno").focus(function(){
                $(this).removeClass("field_textbox_stand_by inputSizeComplete");
                $(this).addClass("field_textbox inputSizeComplete");
                if( this.value == this.defaultValue ){
                    $(this).val("");
                }
                else{
                    $(this).select();
                }
            });

            $("#txtNombreAlumno").blur(function(){
                if( this.value.length > 0 ){
                    $(this).val();
                    $(this).removeClass("field_textbox_stand_by inputSizeComplete");
                    $(this).addClass("field_textbox inputSizeComplete");
                }
                else{
                    $(this).val("B�squeda de alumno");
                    $(this).addClass("field_textbox_stand_by inputSizeComplete");
                }
            });

            $("#txtNombreAlumno").autocomplete ({
                source: '<?php print $strAction; ?>'+"?sendAutoCompletePersona=true&facultad=<?php print $intFacultad; ?>",
                minLength: 2,
                select: function( event, ui ) {
                    fntGetPensumAlumno(ui.item.id,ui.item.carne);
                }
            });

            function fntGetPensumAlumno(intPersona,strCarne){
                $.ajax({
                    url:"<?php print $strAction; ?>",
                    data:{
                        getPensumAlumno : true,
                        intPersona : intPersona,
                        strCarne:strCarne
                    },
                    type: "post",
                    dataType: "html",
                    beforeSend: function() {
                       intTop = ( $(window).height() * 1 ) / 2;
                       $("#divShowLoadingGeneralBig").css("top", intTop);
                       $("#divShowLoadingGeneralBig").css("left", 0);
                       $("#divShowLoadingGeneralBig").css("z-index","2500");
                       $("#divShowLoadingGeneralBig").show();
                   },
                   success: function(data){
                       $("#tdCarrerasPensumAlumno").html(data);
                       if(!data){
                           $("#divBannerPersona").html("");
                       }
                       $("#divShowLoadingGeneralBig").hide();
                   }

                });
            }

            function fntGetDetailPersona(intPersona, intPensum, strCarne){
                var intCurso = $("#hidGuardar").val();
                $.ajax({
                    url:"<?php print $strAction; ?>",
                    data:{
                        getDetailPersona : true,
                        intPersona : intPersona,
                        intCurso : intCurso,
                        intPensum : intPensum,
                        strCarne : strCarne
                    },
                    type: "post",
                    dataType: "html",
                    beforeSend: function() {
                       intTop = ( $(window).height() * 1 ) / 2;
                       $("#divShowLoadingGeneralBig").css("top", intTop);
                       $("#divShowLoadingGeneralBig").css("left", 0);
                       $("#divShowLoadingGeneralBig").css("z-index","1500");
                       $("#divShowLoadingGeneralBig").show();
                   },
                   success: function(data){
                       $("#divBannerPersona").html(data);
                       $("#divShowLoadingGeneralBig").hide();
                   }

                });
            }
        </script>

        <?php
    }


   function drawBlurVersionesSyllabus($intCurso) {
    global $cfg, $strAction;
    
    $intCurso = intval($intCurso);
    if($intCurso <= 0) {
        echo "<p>Curso inv�lido</p>";
        return;
    }
    
    // Obtener todas las versiones del syllabus
    $strQuery = "SELECT s.SYLLABUS_UA, 
                    TO_CHAR(s.FECHA_INICIO, 'DD/MM/YYYY HH24:MI:SS') as FECHA_INICIO_FORMAT,
                    TO_CHAR(s.FECHA_FIN, 'DD/MM/YYYY HH24:MI:SS') as FECHA_FIN_FORMAT,
                    s.FECHA_INICIO,
                    s.FECHA_FIN,
                    s.MOD_USER,
                    p.USUARIO as USUARIO_ARCHIVADOR
             FROM {$cfg['academico']['schema']}.SYLLABUS_UA s
             LEFT JOIN {$cfg['academico']['schema']}.PERSONA p 
                ON s.MOD_USER = p.PERSONA
             WHERE s.CURSO = {$intCurso}
             ORDER BY s.FECHA_INICIO DESC";
    
    $qVersiones = db_query($strQuery);
    $intContador = 0;
    $strClass = "row2";
    
    ?>
    <table width="100%" cellpadding="5" cellspacing="1" class="table1">
        <tr>
            <th class="row0" width="20%">Fecha Inicio</th>
            <th class="row0" width="20%">Fecha Fin</th>
            <th class="row0" width="15%">Estado</th>
            <th class="row0" width="25%">Archivado por</th>
            <th class="row0" width="20%">Ver detalle</th>
        </tr>
        <?php
        $boolHayRegistros = false;
        while($rVersion = db_fetch_array($qVersiones)) {
            $boolHayRegistros = true;
            $intContador++;
            $strClass = ($strClass == "row1") ? "row2" : "row1";
            
            $intSyllabusUA = $rVersion['SYLLABUS_UA'];
            $strFechaInicio = $rVersion['FECHA_INICIO_FORMAT'];
            $strFechaFin = !empty($rVersion['FECHA_FIN']) ? $rVersion['FECHA_FIN_FORMAT'] : "Vigente";
            $strEstado = empty($rVersion['FECHA_FIN']) ? "Actual" : "Anterior";
            $strEstadoClass = empty($rVersion['FECHA_FIN']) ? "style='color: green; font-weight: bold;'" : "";

            // Determinar usuario que archiv�
            $strUsuarioArchivador = "-";
            if(!empty($rVersion['FECHA_FIN'])) {
                // Solo mostrar si la versi�n est� cerrada
                if(!empty($rVersion['USUARIO_ARCHIVADOR'])) {
                    $strUsuarioArchivador = $rVersion['USUARIO_ARCHIVADOR'];
                } else if(!empty($rVersion['MOD_USER'])) {
                    $strUsuarioArchivador = "ID: " . $rVersion['MOD_USER'];
                }
            }
            
            ?>
                
            
                <tr>
                    <td class="<?php print $strClass; ?>" align="center">
                        <?php print $strFechaInicio; ?>
                    </td>
                    <td class="<?php print $strClass; ?>" align="center">
                        <?php print $strFechaFin; ?>
                    </td>
                    <td class="<?php print $strClass; ?>" align="center" <?php print $strEstadoClass; ?>>
                        <?php print $strEstado; ?>
                    </td>
                    <td class="<?php print $strClass; ?>" align="center">
                        <?php print $strUsuarioArchivador; ?>
                    </td>
                    <td class="<?php print $strClass; ?>" align="center">
                        <img src="<?php print strGetCoreImageWithPath('vistaPreviaDoc.png'); ?>" 
                             style="cursor: pointer; width: 24px;"
                             title="Ver detalle" 
                             onclick="fntVerDetalleSyllabusVersion(<?php print $intSyllabusUA; ?>);">
                    </td>
                </tr>
                <?php
            }
        
        db_free_result($qVersiones);
        ?>
    </table>
    
    <script type="text/javascript">
    function fntVerDetalleSyllabusVersion(intSyllabusUA) {
        $.ajax({
            url: "<?php print $strAction; ?>",
            data: {
                drawBlurDetalleSyllabusVersion: true,
                syllabusUA: intSyllabusUA
            },
            type: 'POST',
            dataType: 'html',
            beforeSend: function() {
                intTop = ( $(window).height() * 1 ) / 2;
                $("#divShowLoadingGeneralSmall").css("top", intTop);
                $("#divShowLoadingGeneralSmall").css("left", 0);
                $("#divShowLoadingGeneralSmall").show();
            },
            success: function(data) {
                $("#divBlurDetalleSyllabusVersion").html(data);
                $("#divBlurDetalleSyllabusVersion").dialog("open");
                $("#divShowLoadingGeneralSmall").hide();
            },
            error: function() {
                $("#divShowLoadingGeneralSmall").hide();
                //alert("Error al cargar el detalle");
            }
        });
        
        /*
        $("#divBlurDetalleSyllabusVersion").dialog({
            autoOpen: false,
            show: "explode",
            hide: "explode",
            modal: "true",
            resizable: false,
            width: 1000,
            height: 600,
            buttons: {
                "Cerrar": function() {
                    $(this).dialog("close");
                }
            }
        });*/

$("#divBlurDetalleSyllabusVersion").dialog({
    autoOpen: false,
    show: "explode",
    hide: "explode",
    modal: true,
    resizable: true,
    draggable: true,
    width: 1000,
    height: 600,
        open: function() {
        $(this).css({
            'font-family': 'Verdana,Geneva,Arial,Helvetica,sans-serif',
            'font-size': '11px',
            'line-height': '1.6',
            'padding': '15px'
        });
        $(this).find('*').not('table, table *').css({
            'font-family': 'Verdana,Geneva,Arial,Helvetica,sans-serif',
            'font-size': '11px'
        });

                // Alineaci�n para tablas de RA
        $(this).find('table').each(function() {
            var $table = $(this);
            var hasNivelBloom = $table.find('th:contains("Nivel Bloom")').length > 0;
            var hasBibliografia = $table.find('th:contains("Bibliograf�a completa")').length > 0;
            
            if (hasNivelBloom) {
                // Tabla de RA: centrar contenido
                $table.find('td').css('text-align', 'center');
            } else if (hasBibliografia) {
                // Tabla de Bibliograf�a: alinear a la izquierda
                $table.find('td').css('text-align', 'left');
            }
        });

    },
    buttons: {
        "Cerrar": function() {
            $(this).dialog("close");
        }
    }
});


    }



    </script>
    <?php
}





function drawBlurDetalleSyllabusVersion($intSyllabusUA) {
    global $cfg;
    
    $intSyllabusUA = intval($intSyllabusUA);
    if($intSyllabusUA <= 0) {
        echo "<p>Versi�n inv�lida</p>";
        return;
    }
    
    // Obtener datos de la versi�n
    $strQuery = "SELECT SYLLABUS_UA, CURSO,
                        TO_CHAR(FECHA_INICIO, 'DD/MM/YYYY HH24:MI:SS') as FECHA_INICIO_FORMAT,
                        TO_CHAR(FECHA_FIN, 'DD/MM/YYYY HH24:MI:SS') as FECHA_FIN_FORMAT
                 FROM {$cfg['academico']['schema']}.SYLLABUS_UA
                 WHERE SYLLABUS_UA = {$intSyllabusUA}";
    
    $qVersion = db_query($strQuery);
    $rVersion = db_fetch_array($qVersion);
    
    if(!$rVersion) {
        echo "<p>Versi�n no encontrada</p>";
        return;
    }
    
    // Obtener campos CLOB
    $strDescInst = getTextoClob('DESCRIPCION_INSTITUCIONAL', 'SYLLABUS_UA', 'SYLLABUS_UA', $intSyllabusUA);
    $strAporte = getTextoClob('APORTE_PLAN_ESTUDIOS', 'SYLLABUS_UA', 'SYLLABUS_UA', $intSyllabusUA);
    $strConocimientos = getTextoClob('CONOCIMIENTOS_PREVIOS', 'SYLLABUS_UA', 'SYLLABUS_UA', $intSyllabusUA);
    $strMarco = getTextoClob('MARCO_NORMATIVO', 'SYLLABUS_UA', 'SYLLABUS_UA', $intSyllabusUA);
    
    ?>
    <div style="padding: 10px;">
 
        <div class="heading1 WidthDiv" align="left">
           <b>    Informaci�n de la Versi�n </b>
        </div>
        <br>

        <div style="margin-bottom: 25px;">
            <strong>Fecha Inicio:</strong> <?php print $rVersion['FECHA_INICIO_FORMAT']; ?> &nbsp;&nbsp;&nbsp;
            <strong>Fecha Fin:</strong> <?php print !empty($rVersion['FECHA_FIN_FORMAT']) ? $rVersion['FECHA_FIN_FORMAT'] : "Vigente"; ?>
        </div>


        <div class="heading1 WidthDiv" align="left">
           <b> Descripci�n institucional del curso </b>

              <img src="<?php print strGetCoreImageWithPath('acad_bitacora_orange.png'); ?>" width="20px" height="20px"
        style="cursor: pointer; margin-left: 5px; vertical-align: middle;" 
        title="Ver bit�cora de cambios"
        onclick="fntMostrarBitacoraCampo(<?php print $intSyllabusUA; ?>, 'DESCRIPCION_INSTITUCIONAL');">

        </div>
        <br>

        <div style="border: 1px solid #ccc; padding: 10px; background: #f9f9f9; margin-bottom: 25px;">
            <?php print !empty($strDescInst) ? $strDescInst : "<em>Sin contenido</em>"; ?>
        </div>
        
        <!-- 2. Aportes al plan de estudios/perfil de egreso -->
        <div class="heading1 WidthDiv" align="left">
           <b> Aportes al plan de estudios/perfil de egreso</b>

              <img src="<?php print strGetCoreImageWithPath('acad_bitacora_orange.png'); ?>" width="20px" height="20px"
        style="cursor: pointer; margin-left: 5px; vertical-align: middle;" 
        title="Ver bit�cora de cambios"
        onclick="fntMostrarBitacoraCampo(<?php print $intSyllabusUA; ?>, 'APORTE_PLAN_ESTUDIOS');">
        </div>
        <br>
        <div style="border: 1px solid #ccc; padding: 10px; background: #f9f9f9; margin-bottom: 25px;">
            <?php print !empty($strAporte) ? $strAporte : "<em>Sin contenido</em>"; ?>
        </div>
        
        <!-- 3. Resultados de aprendizaje del curso (medibles) -->
                <div class="heading1 WidthDiv" align="left">
           <b>   Resultados de aprendizaje del curso  </b>
              <img src="<?php print strGetCoreImageWithPath('acad_bitacora_orange.png'); ?>" width="20px" height="20px"
        style="cursor: pointer; margin-left: 5px; vertical-align: middle;"
        title="Ver bit�cora de cambios"
        onclick="fntMostrarBitacoraTodosRA(<?php print $intSyllabusUA; ?>);">
        </div>
        <br>

        
        <?php
        // Obtener RA
        $strQueryRA = "SELECT ra.SYLLABUS_UA_RA,
                              ra.DESCRIPCION_RA,
                              n.NOMBRE as NIVEL_NOMBRE
                       FROM {$cfg['academico']['schema']}.SYLLABUS_UA_RA ra
                       LEFT JOIN {$cfg['academico']['schema']}.BLOOM_NIVEL n 
                           ON ra.BLOOM_NIVEL = n.BLOOM_NIVEL
                       WHERE ra.SYLLABUS_UA = {$intSyllabusUA}
                       ORDER BY ra.SYLLABUS_UA_RA";
        $qRA = db_query($strQueryRA);

        $boolHayRegistrosRA = false;
        $strClass = "row2";
        ?>
        <table width="100%" cellpadding="5" cellspacing="1" class="table1" style="margin-bottom: 25px;">
            <tr>
                <th class="row0" width="20%">Nivel Bloom</th>
                <th class="row0" width="80%">Descripci�n</th>
            </tr>
            <?php
            while($rRA = db_fetch_array($qRA)) {
                $boolHayRegistrosRA = true;
                $strClass = ($strClass == "row1") ? "row2" : "row1";
                ?>
                <tr>
                    <td class="<?php print $strClass; ?>">
                        <?php print !empty($rRA['NIVEL_NOMBRE']) ? $rRA['NIVEL_NOMBRE'] : "N/A"; ?>
                    </td>
                    <td class="<?php print $strClass; ?>">
                        <?php print $rRA['DESCRIPCION_RA']; ?>
                    </td>
                </tr>
                <?php
            }
            
            if(!$boolHayRegistrosRA) {
                ?>
                <tr>
                    <td colspan="2" class="row2"><em>No hay resultados de aprendizaje registrados</em></td>
                </tr>
                <?php
            }
            ?>
        </table>
        <?php
        db_free_result($qRA);
        ?>
        
        <!-- 4. Conocimientos previos esperados -->

        <div class="heading1 WidthDiv" align="left">
           <b> Conocimientos previos esperados </b>

              <img src="<?php print strGetCoreImageWithPath('acad_bitacora_orange.png'); ?>" width="20px" height="20px"
        style="cursor: pointer; margin-left: 5px; vertical-align: middle;" 
        title="Ver bit�cora de cambios"
        onclick="fntMostrarBitacoraCampo(<?php print $intSyllabusUA; ?>, 'CONOCIMIENTOS_PREVIOS');">

        </div>
        <br>

        
        <div style="border: 1px solid #ccc; padding: 10px; background: #f9f9f9; margin-bottom: 25px;">
            <?php print !empty($strConocimientos) ? $strConocimientos : "<em>Sin contenido</em>"; ?>
        </div>
        
        <!-- 5. Bibliograf�a base m�nima -->
        <div class="heading1 WidthDiv" align="left">
           <b> Bibliograf�a base m�nima </b>

              <img src="<?php print strGetCoreImageWithPath('acad_bitacora_orange.png'); ?>" width="20px" height="20px"
        style="cursor: pointer; margin-left: 5px; vertical-align: middle;"
        title="Ver bit�cora de cambios"
        onclick="fntMostrarBitacoraTodosBiblio(<?php print $intSyllabusUA; ?>);">

        </div>
        <br>
        <?php
        // Obtener Bibliograf�a
        $strQueryBiblio = "SELECT SYLLABUS_UA_BIBLIO
                           FROM {$cfg['academico']['schema']}.SYLLABUS_UA_BIBLIO
                           WHERE SYLLABUS_UA = {$intSyllabusUA}
                           ORDER BY SYLLABUS_UA_BIBLIO";

        $qBiblio = db_query($strQueryBiblio);

        $boolHayRegistrosBiblio = false;
        $strClass = "row2";
        $intNum = 0;
        ?>
        <table width="100%" cellpadding="5" cellspacing="1" class="table1" style="margin-bottom: 25px;">
            <tr>
                <th class="row0" width="100%">Bibliograf�a completa</th>
            </tr>
            <?php
            while($rBiblio = db_fetch_array($qBiblio)) {
                $boolHayRegistrosBiblio = true;
                $intNum++;
                $strClass = ($strClass == "row1") ? "row2" : "row1";
                $strReferencia = getReferenciaBiblio($rBiblio['SYLLABUS_UA_BIBLIO']);
                ?>
                <tr>
                    <td class="<?php print $strClass; ?>">
                        <strong><?php print $intNum; ?>.</strong>
                        <?php print renderReferenciaBiblioVista($strReferencia); ?>
                    </td>
                </tr>
                <?php
            }
            
            if(!$boolHayRegistrosBiblio) {
                ?>
                <tr>
                    <td class="row2"><em>No hay bibliograf�a registrada</em></td>
                </tr>
                <?php
            }
            ?>
        </table>
        <?php
        db_free_result($qBiblio);
        ?>
        
        <!-- 6. Marco normativo institucional -->
        <div class="heading1 WidthDiv" align="left">
           <b> Marco normativo institucional</b>

              <img src="<?php print strGetCoreImageWithPath('acad_bitacora_orange.png'); ?>" width="20px" height="20px"
        style="cursor: pointer; margin-left: 5px; vertical-align: middle;" 
        title="Ver bit�cora de cambios"
        onclick="fntMostrarBitacoraCampo(<?php print $intSyllabusUA; ?>, 'MARCO_NORMATIVO');">

        </div>
        <br>

        <div style="border: 1px solid #ccc; padding: 10px; background: #f9f9f9;">
            <?php print !empty($strMarco) ? $strMarco : "<em>Sin contenido</em>"; ?>
        </div>
    </div>
    <?php
}


function drawBlurBitacoraCampoOld($intSyllabusUA, $strCampo) {
    global $cfg;
    
    $intSyllabusUA = intval($intSyllabusUA);
    
    // Validar campo
    $arrCamposValidos = array(
        'DESCRIPCION_INSTITUCIONAL' => 'Descripci�n Institucional',
        'APORTE_PLAN_ESTUDIOS' => 'Aporte al Plan de Estudios',
        'CONOCIMIENTOS_PREVIOS' => 'Conocimientos Previos',
        'MARCO_NORMATIVO' => 'Marco Normativo'
    );
    
    if(!isset($arrCamposValidos[$strCampo])) {
        echo "<p>Campo inv�lido</p>";
        return;
    }
    
    $strNombreCampo = $arrCamposValidos[$strCampo];
    
    // Obtener log del campo
    $arrLog = getLogCampoSyllabus($intSyllabusUA, $strCampo);
    
    ?>
<div style="padding: 10px;">
    <div class="heading1 WidthDiv" align="left">
        <b>Bit�cora de cambios del campo: <?php print $strNombreCampo; ?></b>
    </div>
    <br>
    
    <?php 
    if(empty($arrLog)) { ?>
        <p align="center"><em>No hay cambios registrados para este campo.</em></p>
    <?php } else { ?>
        <table width="100%" cellpadding="5" cellspacing="0" class="table1">
            <tr class="heading2">
                <th class="row0" width="30%">Fecha</th>
                <th class="row0" width="30%">Vigente desde</th>
                <th class="row0" width="40%">Realizado por</th>
                <th class="row0" width="30%">Ver contenido completo</th>
            </tr>
            <?php
            $intFila = 1;
            $strClass = "row1";
            foreach($arrLog as $rLog) {
                $strUsuario = $rLog['USUARIO'];
                ?>
                <tr>
                    <td class="<?php print $strClass; ?>" align="center"><?php print date('d/m/Y H:i:s', strtotime($rLog['ADD_FECHA_LOG'])); ?></td>
                    <td class="<?php print $strClass; ?>" align="center"><?php print $strUsuario; ?></td>
                    <td class="<?php print $strClass; ?>" align="center">
                        <img src="<?php print strGetCoreImageWithPath('vistaPreviaDoc.png'); ?>" 
                            style="cursor: pointer; width: 24px;"
                            title="Ver contenido completo" 
                            onclick="fntVerDetalleLogCampo(<?php print $rLog['LOG_ID']; ?>, '<?php print $strCampo; ?>', '<?php print $strNombreCampo; ?>');">
                    </td>
                </tr>
                <?php
                $strClass = ($strClass == "row1") ? "row2" : "row1";
                $intFila++;
            }
            ?>
        </table>
    <?php } ?>
</div>
    <?php
}

function drawBlurBitacoraCampo($intSyllabusUA, $strCampo) {
    global $cfg;

    $intSyllabusUA = intval($intSyllabusUA);

    $arrCamposValidos = array(
        'DESCRIPCION_INSTITUCIONAL' => 'Descripci�n Institucional',
        'APORTE_PLAN_ESTUDIOS'      => 'Aporte al Plan de Estudios',
        'CONOCIMIENTOS_PREVIOS'     => 'Conocimientos Previos',
        'MARCO_NORMATIVO'           => 'Marco Normativo'
    );

    if(!isset($arrCamposValidos[$strCampo])) {
        echo "<p>Campo inv�lido</p>";
        return;
    }

    $strNombreCampo = $arrCamposValidos[$strCampo];

    // Metadatos del valor actual (qui�n y cu�ndo, sin traer el CLOB)
    //TODO:
    $strQueryActual = "SELECT
                          TO_CHAR(NVL(s.MOD_FECHA, s.ADD_FECHA), 'DD/MM/YYYY HH24:MI:SS') as FECHA_ACTUAL,
                          NVL(p2.USUARIO, p1.USUARIO) as USUARIO_ACTUAL,
                          TO_CHAR(s.ADD_FECHA, 'DD/MM/YYYY HH24:MI:SS') as FECHA_CREACION,
                          p1.USUARIO as USUARIO_CREACION
                       FROM {$cfg['academico']['schema']}.SYLLABUS_UA s
                       LEFT JOIN {$cfg['academico']['schema']}.PERSONA p1
                           ON s.ADD_USER = p1.PERSONA
                       LEFT JOIN {$cfg['academico']['schema']}.PERSONA p2
                           ON s.MOD_USER = p2.PERSONA
                       WHERE s.SYLLABUS_UA = {$intSyllabusUA}";


   $strQueryActual = "SELECT
                          TO_CHAR(s.ADD_FECHA, 'DD/MM/YYYY HH24:MI:SS') as FECHA_CREACION,
                          p1.USUARIO as USUARIO_CREACION,
                          TO_CHAR(
                              NVL(
                                  (SELECT MAX(L.ADD_FECHA_LOG)
                                   FROM {$cfg['academico']['schema']}.SYLLABUS_UA_LOG L
                                   WHERE L.SYLLABUS_UA = s.SYLLABUS_UA
                                     AND L.TIPO_OPERACION = 'U'
                                     AND L.{$strCampo} IS NOT NULL
                                     AND (
                                         s.{$strCampo} IS NULL
                                         OR DBMS_LOB.COMPARE(L.{$strCampo}, s.{$strCampo}) != 0
                                     )),
                                  s.ADD_FECHA
                              ),
                              'DD/MM/YYYY HH24:MI:SS'
                          ) as FECHA_ACTUAL,
                          NVL(
                              (SELECT p.USUARIO
                               FROM {$cfg['academico']['schema']}.SYLLABUS_UA_LOG L
                               LEFT JOIN {$cfg['academico']['schema']}.PERSONA p
                                   ON NVL(L.ADD_USER_LOG, L.ADD_USER) = p.PERSONA
                               WHERE L.SYLLABUS_UA = s.SYLLABUS_UA
                                 AND L.TIPO_OPERACION = 'U'
                                 AND L.{$strCampo} IS NOT NULL
                                 AND (
                                     s.{$strCampo} IS NULL
                                     OR DBMS_LOB.COMPARE(L.{$strCampo}, s.{$strCampo}) != 0
                                 )
                                 AND L.ADD_FECHA_LOG = (
                                     SELECT MAX(L2.ADD_FECHA_LOG)
                                     FROM {$cfg['academico']['schema']}.SYLLABUS_UA_LOG L2
                                     WHERE L2.SYLLABUS_UA = s.SYLLABUS_UA
                                       AND L2.TIPO_OPERACION = 'U'
                                       AND L2.{$strCampo} IS NOT NULL
                                       AND (
                                           s.{$strCampo} IS NULL
                                           OR DBMS_LOB.COMPARE(L2.{$strCampo}, s.{$strCampo}) != 0
                                       )
                                 )
                                 AND ROWNUM = 1),
                              p1.USUARIO
                          ) as USUARIO_ACTUAL
                       FROM {$cfg['academico']['schema']}.SYLLABUS_UA s
                       LEFT JOIN {$cfg['academico']['schema']}.PERSONA p1
                           ON s.ADD_USER = p1.PERSONA
                       WHERE s.SYLLABUS_UA = {$intSyllabusUA}";

    $qActual = db_query($strQueryActual);
    $rActual = db_fetch_assoc($qActual);
    db_free_result($qActual);

    // Obtener historial de cambios del campo
    $arrLog = getLogCampoSyllabus($intSyllabusUA, $strCampo);

    ?>
<div style="padding: 10px;">
    <div class="heading1 WidthDiv" align="left">
        <b>Bit�cora de cambios del campo: <?php print $strNombreCampo; ?></b>
    </div>
    <br>

    <div style="margin-bottom: 7px;">
        <strong>Creado por:</strong> <?php print $rActual['USUARIO_CREACION']; ?><br>
        <strong>Fecha de creaci�n:</strong> <?php print $rActual['FECHA_CREACION']; ?>
    </div>
    <br>

    <!-- Valor Actual -->
    <div style="margin-bottom: 20px;">
        <div class="heading1 WidthDiv" align="left">
            <b>Valor Actual</b>
        </div>
        <br>
        <table width="100%" cellpadding="5" cellspacing="0" class="table1">
            <tr class="heading2">
                <th class="row0" width="30%">Vigente desde </th>
                <!--<th class="row0" width="30%">Fecha</th>-->
                <th class="row0" width="40%">Realizado por</th>
                <th class="row0" width="30%">Ver contenido completo</th>
            </tr>
            <tr>
                <td class="row1" align="center"><?php print $rActual['FECHA_ACTUAL']; ?></td>
                <td class="row1" align="center"><?php print $rActual['USUARIO_ACTUAL']; ?></td>
                <td class="row1" align="center">
                    <img src="<?php print strGetCoreImageWithPath('vistaPreviaDoc.png'); ?>"
                         style="cursor: pointer; width: 24px;"
                         title="Ver contenido actual"
                         onclick="fntVerValorActualCampo(<?php print $intSyllabusUA; ?>, '<?php print $strCampo; ?>', '<?php print $strNombreCampo; ?>');">
                </td>
            </tr>
        </table>
    </div>

    <!-- Historial de Modificaciones -->
    <div class="heading1 WidthDiv" align="left">
        <b>Historial de modificaciones</b>
    </div>
    <br>

    <?php if(empty($arrLog)) { ?>
        <p align="center"><em>No hay cambios registrados para este campo.</em></p>
    <?php } else { ?>
        <table width="100%" cellpadding="5" cellspacing="0" class="table1">
            <tr class="heading2">
                <th class="row0" width="30%">Fecha del cambio</th>
                <th class="row0" width="40%">Realizado por</th>
                <th class="row0" width="30%">Ver contenido completo</th>
            </tr>
            <?php
            $strClass = "row1";
            foreach($arrLog as $rLog) {
                ?>
                <tr>
                    <td class="<?php print $strClass; ?>" align="center"><?php print date('d/m/Y H:i:s', strtotime($rLog['ADD_FECHA_LOG'])); ?></td>
                    <td class="<?php print $strClass; ?>" align="center"><?php print $rLog['USUARIO']; ?></td>
                    <td class="<?php print $strClass; ?>" align="center">
                        <img src="<?php print strGetCoreImageWithPath('vistaPreviaDoc.png'); ?>"
                             style="cursor: pointer; width: 24px;"
                             title="Ver contenido completo"
                             onclick="fntVerDetalleLogCampo(<?php print $rLog['LOG_ID']; ?>, '<?php print $strCampo; ?>', '<?php print $strNombreCampo; ?>');">
                    </td>
                </tr>
                <?php
                $strClass = ($strClass == "row1") ? "row2" : "row1";
            }
            ?>
        </table>
    <?php } ?>
</div>
    <?php
}


function drawBlurDetalleLogCampo($intLogID, $strCampo, $strNombreCampo) {
    global $cfg;
    
    $intLogID = intval($intLogID);
    
    // Validar campo
    $arrCamposValidos = array(
        'DESCRIPCION_INSTITUCIONAL' => 'Descripci�n Institucional',
        'APORTE_PLAN_ESTUDIOS' => 'Aporte al Plan de Estudios',
        'CONOCIMIENTOS_PREVIOS' => 'Conocimientos Previos',
        'MARCO_NORMATIVO' => 'Marco Normativo'
    );
    
    if(!isset($arrCamposValidos[$strCampo])) {
        echo "<p>Campo inv�lido</p>";
        return;
    }

    $strNombreCampo = $arrCamposValidos[$strCampo];

    
    // Obtener datos del log CON el usuario que realizo el cambio.
 
                 $strQuery = "SELECT l.LOG_ID, 
                    l.ADD_FECHA_LOG,
                    l.ADD_USER_LOG,
                    p.USUARIO
             FROM {$cfg['academico']['schema']}.SYLLABUS_UA_LOG l
             LEFT JOIN {$cfg['academico']['schema']}.PERSONA p
                ON NVL(l.ADD_USER_LOG, l.ADD_USER) = p.PERSONA
             WHERE l.LOG_ID = {$intLogID}";

    
    $qLog = db_query($strQuery);
    $rLog = db_fetch_assoc($qLog);
    
    if(!$rLog) {
        echo "<p>Registro no encontrado</p>";
        return;
    }
    
    // Obtener contenido del CLOB
    $strContenido = getTextoClob($strCampo, 'SYLLABUS_UA_LOG', 'LOG_ID', $intLogID);
    
    // Usuario ya viene del query
    $strUsuario = $rLog['USUARIO'];
    
    ?>
    <div style="padding: 10px;">

        <div style="margin-bottom: 7px;">
            <strong>Fecha del cambio:</strong> <?php print date('d/m/Y H:i:s', strtotime($rLog['ADD_FECHA_LOG'])); ?><br>
            <strong>Realizado por:</strong> <?php print $strUsuario; ?>
        </div>

        <div class="heading1 WidthDiv" align="left">
            <b><?php print $strNombreCampo; ?></b>
        </div>
        <br>
        
 
        
        
<div style="border: 1px solid #ccc; padding: 10px; background: #f9f9f9; margin-bottom: 25px;">
    <?php print !empty($strContenido) ? $strContenido : "<em>Sin contenido</em>"; ?>
</div>

    </div>
    <?php
}

function drawBlurValorActualCampo($intSyllabusUA, $strCampo, $strNombreCampo) {
    global $cfg;

    $intSyllabusUA = intval($intSyllabusUA);

    $arrCamposValidos = array(
        'DESCRIPCION_INSTITUCIONAL' => 'Descripci�n Institucional',
        'APORTE_PLAN_ESTUDIOS'      => 'Aporte al Plan de Estudios',
        'CONOCIMIENTOS_PREVIOS'     => 'Conocimientos Previos',
        'MARCO_NORMATIVO'           => 'Marco Normativo'
    );

    if(!isset($arrCamposValidos[$strCampo])) {
        echo "<p>Campo inv�lido</p>";
        return;
    }

    $strNombreCampo = $arrCamposValidos[$strCampo];

    // Obtener metadatos: qui�n y cu�ndo fue la �ltima modificaci�n (o creaci�n)
    $strQuery = "SELECT
                    TO_CHAR(NVL(s.MOD_FECHA, s.ADD_FECHA), 'DD/MM/YYYY HH24:MI:SS') as FECHA_ACTUAL,
                    NVL(p2.USUARIO, p1.USUARIO) as USUARIO_ACTUAL
                 FROM {$cfg['academico']['schema']}.SYLLABUS_UA s
                 LEFT JOIN {$cfg['academico']['schema']}.PERSONA p1
                     ON s.ADD_USER = p1.PERSONA
                 LEFT JOIN {$cfg['academico']['schema']}.PERSONA p2
                     ON s.MOD_USER = p2.PERSONA
                 WHERE s.SYLLABUS_UA = {$intSyllabusUA}";

                 // TODO:

                     $strQuery = "SELECT
                    TO_CHAR(
                        NVL(
                            (SELECT MAX(L.ADD_FECHA_LOG)
                             FROM {$cfg['academico']['schema']}.SYLLABUS_UA_LOG L
                             WHERE L.SYLLABUS_UA = s.SYLLABUS_UA
                               AND L.TIPO_OPERACION = 'U'
                               AND L.{$strCampo} IS NOT NULL
                               AND (
                                   s.{$strCampo} IS NULL
                                   OR DBMS_LOB.COMPARE(L.{$strCampo}, s.{$strCampo}) != 0
                               )),
                            s.ADD_FECHA
                        ),
                        'DD/MM/YYYY HH24:MI:SS'
                    ) as FECHA_ACTUAL,
                    NVL(
                        (SELECT p.USUARIO
                         FROM {$cfg['academico']['schema']}.SYLLABUS_UA_LOG L
                         LEFT JOIN {$cfg['academico']['schema']}.PERSONA p
                             ON NVL(L.ADD_USER_LOG, L.ADD_USER) = p.PERSONA
                         WHERE L.SYLLABUS_UA = s.SYLLABUS_UA
                           AND L.TIPO_OPERACION = 'U'
                           AND L.{$strCampo} IS NOT NULL
                           AND (
                               s.{$strCampo} IS NULL
                               OR DBMS_LOB.COMPARE(L.{$strCampo}, s.{$strCampo}) != 0
                           )
                           AND L.ADD_FECHA_LOG = (
                               SELECT MAX(L2.ADD_FECHA_LOG)
                               FROM {$cfg['academico']['schema']}.SYLLABUS_UA_LOG L2
                               WHERE L2.SYLLABUS_UA = s.SYLLABUS_UA
                                 AND L2.TIPO_OPERACION = 'U'
                                 AND L2.{$strCampo} IS NOT NULL
                                 AND (
                                     s.{$strCampo} IS NULL
                                     OR DBMS_LOB.COMPARE(L2.{$strCampo}, s.{$strCampo}) != 0
                                 )
                           )
                           AND ROWNUM = 1),
                        p1.USUARIO
                    ) as USUARIO_ACTUAL
                 FROM {$cfg['academico']['schema']}.SYLLABUS_UA s
                 LEFT JOIN {$cfg['academico']['schema']}.PERSONA p1
                     ON s.ADD_USER = p1.PERSONA
                 WHERE s.SYLLABUS_UA = {$intSyllabusUA}";

    $qMeta = db_query($strQuery);
    $rMeta = db_fetch_assoc($qMeta);
    db_free_result($qMeta);

    // Obtener el contenido CLOB actual
    $strContenido = getTextoClob($strCampo, 'SYLLABUS_UA', 'SYLLABUS_UA', $intSyllabusUA);
    ?>
    <div style="padding: 10px;">

        <div style="margin-bottom: 7px;">
            <strong>Vigente desde:</strong> <?php print $rMeta['FECHA_ACTUAL']; ?><br>
            <strong>Realizado por:</strong> <?php print $rMeta['USUARIO_ACTUAL']; ?>
        </div>

        <div class="heading1 WidthDiv" align="left">
            <b><?php print $strNombreCampo; ?> � Valor Actual</b>
        </div>
        <br>

        <div style="border: 1px solid #ccc; padding: 10px; background: #f9f9f9; margin-bottom: 25px;">
            <?php print !empty($strContenido) ? $strContenido : "<em>Sin contenido</em>"; ?>
        </div>

    </div>
    <?php
}


function drawBlurBitacoraRA($intSyllabusUARA) {
    global $cfg;
    
    $intSyllabusUARA = intval($intSyllabusUARA);
    
    // Obtener informaci�n actual del RA
                   $strQueryRA = "SELECT ra.DESCRIPCION_RA, 
                      bn.NOMBRE as BLOOM_NOMBRE,
                      TO_CHAR(NVL(ra.MOD_FECHA, ra.ADD_FECHA), 'DD/MM/YYYY HH24:MI:SS') as FECHA_ACTUAL,
                      NVL(p2.USUARIO, p1.USUARIO) as USUARIO_ACTUAL
               FROM {$cfg['academico']['schema']}.SYLLABUS_UA_RA ra
               LEFT JOIN {$cfg['academico']['schema']}.BLOOM_NIVEL bn 
                   ON ra.BLOOM_NIVEL = bn.BLOOM_NIVEL
               LEFT JOIN {$cfg['academico']['schema']}.PERSONA p1 
                   ON ra.ADD_USER = p1.PERSONA
               LEFT JOIN {$cfg['academico']['schema']}.PERSONA p2 
                   ON ra.MOD_USER = p2.PERSONA
               WHERE ra.SYLLABUS_UA_RA = {$intSyllabusUARA}";

    $qRA = db_query($strQueryRA);
    $rRA = db_fetch_array($qRA);
    db_free_result($qRA);

    



                 $strQueryInfo = "SELECT 
                    TO_CHAR(ra.ADD_FECHA, 'DD/MM/YYYY HH24:MI:SS') as FECHA_CREACION,
                    p1.USUARIO as USUARIO_CREACION,
                    TO_CHAR(ra.MOD_FECHA, 'DD/MM/YYYY HH24:MI:SS') as FECHA_MODIFICACION,
                    p2.USUARIO as USUARIO_MODIFICACION
                 FROM {$cfg['academico']['schema']}.SYLLABUS_UA_RA ra
                 LEFT JOIN {$cfg['academico']['schema']}.PERSONA p1 
                     ON ra.ADD_USER = p1.PERSONA
                 LEFT JOIN {$cfg['academico']['schema']}.PERSONA p2 
                     ON ra.MOD_USER = p2.PERSONA
                 WHERE ra.SYLLABUS_UA_RA = {$intSyllabusUARA}";

$qInfo = db_query($strQueryInfo);
$rInfo = db_fetch_array($qInfo);
db_free_result($qInfo);




    
    ?>

    <div style="margin-bottom: 7px;">
    <strong>Creado por:</strong> <?php print $rInfo['USUARIO_CREACION']; ?><br>
    <strong>Fecha de creaci�n:</strong> <?php print $rInfo['FECHA_CREACION']; ?>
</div>

<br>
        
<?php if($rRA) { ?>
<div style="margin-bottom: 20px;">
    <div class="heading1 WidthDiv" align="left">
        <b>Valor Actual</b>
    </div>
    <br>
    <table width="100%" cellpadding="5" cellspacing="1" class="table1">
        <tr>
            <th class="row0" width="18%" align="center">Fecha</th>
            <th class="row0" width="12%" align="center">Realizado por</th>
            <th class="row0" width="10%" align="center">Nivel Bloom</th>
            <th class="row0" width="60%" align="center">Descripci�n</th>
        </tr>
        <tr>
            <td class="row1" align="center"><?php print $rRA['FECHA_ACTUAL']; ?></td>
            <td class="row1" align="center"><?php print $rRA['USUARIO_ACTUAL']; ?></td>
            <td class="row1" align="center"><?php print $rRA['BLOOM_NOMBRE']; ?></td>
            <td class="row1" align="center"><?php print htmlspecialchars($rRA['DESCRIPCION_RA']); ?></td>
        </tr>
    </table>
</div>

<div class="heading1 WidthDiv" align="left">
    <b>Historial de modificaciones</b>
</div>
<br>
<?php } ?>

        
        <?php
        $arrLog = getLogRA($intSyllabusUARA);
        
        if(count($arrLog) > 0) {
            ?>
            <table width="100%" cellpadding="5" cellspacing="1" class="table1">
                <tr>
                    <th class="row0" width="18%" align="center">Fecha</th>
                    <th class="row0" width="12%" align="center">Realizado por</th>
                    <th class="row0" width="10%" align="center">Nivel Bloom</th>
                    <th class="row0" width="60%" align="center">Descripci�n</th>
                </tr>
                <?php
                $strClass = "row2";
                foreach($arrLog as $rLog) {
                    $strClass = ($strClass == "row1") ? "row2" : "row1";

                        // Solo mostrar la descripci�n actual (el "Ahora")
                    $strDescripcion = htmlspecialchars($rLog['DESCRIPCION_RA']);

                    ?>
                    <tr>
        <td class="<?php print $strClass; ?>" align="center"><?php print $rLog['FECHA_LOG']; ?></td>
        <td class="<?php print $strClass; ?>" align="center"><?php print $rLog['USUARIO']; ?></td>
        <td class="<?php print $strClass; ?>" align="center"><?php print $rLog['BLOOM_NOMBRE']; ?></td>
        <td class="<?php print $strClass; ?>" align="center"><?php print $strDescripcion; ?></td>
                    </tr>
                    <?php
                }
                ?>
            </table>
            <?php
        } else {
            ?>
            <p align="center"><em>No hay cambios registrados para este resultado de aprendizaje.</em></p>
            <?php
        }
        ?>
    </div>
    <?php
}

function drawBlurBitacoraRAEliminado($intSyllabusUARA) {
    global $cfg;
    
    $intSyllabusUARA = intval($intSyllabusUARA);
    
    // Obtener informaci�n del RA eliminado (�ltima versi�n antes de eliminar)
    $strQueryRA = "SELECT 
                      L.DESCRIPCION_RA, 
                      bn.NOMBRE as BLOOM_NOMBRE,
                      TO_CHAR(L.ADD_FECHA, 'DD/MM/YYYY HH24:MI:SS') as FECHA_CREACION,
                      p1.USUARIO as USUARIO_CREACION,
                      TO_CHAR(L.ADD_FECHA_LOG, 'DD/MM/YYYY HH24:MI:SS') as FECHA_ELIMINACION,
                      p2.USUARIO as USUARIO_ELIMINACION
                   FROM {$cfg['academico']['schema']}.SYLLABUS_UA_RA_LOG L
                   LEFT JOIN {$cfg['academico']['schema']}.BLOOM_NIVEL bn 
                       ON L.BLOOM_NIVEL = bn.BLOOM_NIVEL
                   LEFT JOIN {$cfg['academico']['schema']}.PERSONA p1 
                       ON L.ADD_USER = p1.PERSONA
                   LEFT JOIN {$cfg['academico']['schema']}.PERSONA p2 
                       ON L.ADD_USER_LOG = p2.PERSONA
                   WHERE L.SYLLABUS_UA_RA = {$intSyllabusUARA}
                     AND L.TIPO_OPERACION = 'D'
                   ORDER BY L.ADD_FECHA_LOG DESC
                   FETCH FIRST 1 ROW ONLY";
    $qRA = db_query($strQueryRA);
    $rRA = db_fetch_array($qRA);
    db_free_result($qRA);
    
    ?>
    <div style="padding: 10px;">
        <div class="heading1 WidthDiv" align="left">
            <b>Bit�cora de Resultado de Aprendizaje (Eliminado)</b>
        </div>
        <br>
        
        <?php if($rRA) { ?>
        <div style="margin-bottom: 7px;">
            <strong>Creado por:</strong> <?php print $rRA['USUARIO_CREACION']; ?><br>
            <strong>Fecha:</strong> <?php print $rRA['FECHA_CREACION']; ?>
        </div>
        <br>
        
        <div style="margin-bottom: 7px; padding: 10px; background: #ffe6e6; border: 1px solid #ff4444; border-radius: 4px;">
            <strong>Eliminado por:</strong> <?php print $rRA['USUARIO_ELIMINACION']; ?><br>
            <strong>Fecha de eliminaci�n:</strong> <?php print $rRA['FECHA_ELIMINACION']; ?>
        </div>
        <br>
        
        <div style="margin-bottom: 20px;">
            <div class="heading1 WidthDiv" align="left">
                <b>�ltima Versi�n (antes de eliminar)</b>
            </div>
            <br>
            <table width="100%" cellpadding="5" cellspacing="1" class="table1">
                <tr>
                    <th class="row0" width="15%" align="center">Nivel Bloom</th>
                    <th class="row0" width="85%" align="center">Descripci�n</th>
                </tr>
                <tr>
                    <td class="row1" align="center"><?php print $rRA['BLOOM_NOMBRE']; ?></td>
                    <td class="row1"><?php print htmlspecialchars($rRA['DESCRIPCION_RA']); ?></td>
                </tr>
            </table>
        </div>
        <?php } ?>
        
        <?php
        // Obtener historial de modificaciones (antes de eliminar)
        $strQueryLog = "
            SELECT 
                L.LOG_ID,
                L.SYLLABUS_UA_RA,
                L.DESCRIPCION_RA,
                L.BLOOM_NIVEL,
                bn.NOMBRE as BLOOM_NOMBRE,
                L.TIPO_OPERACION,
                TO_CHAR(NVL(L.MOD_FECHA, L.ADD_FECHA), 'DD/MM/YYYY HH24:MI:SS') as FECHA_LOG,
                L.ADD_USER_LOG,
                p.USUARIO
            FROM {$cfg['academico']['schema']}.SYLLABUS_UA_RA_LOG L
            LEFT JOIN {$cfg['academico']['schema']}.PERSONA p 
                ON L.ADD_USER_LOG = p.PERSONA
            LEFT JOIN {$cfg['academico']['schema']}.BLOOM_NIVEL bn 
                ON L.BLOOM_NIVEL = bn.BLOOM_NIVEL
            WHERE L.SYLLABUS_UA_RA = {$intSyllabusUARA}
              AND L.TIPO_OPERACION = 'U'
            ORDER BY L.ADD_FECHA_LOG DESC, L.LOG_ID DESC
        ";
        
        $arrLog = array();
        $qLog = db_query($strQueryLog);
        
        while($rLog = db_fetch_assoc($qLog)) {
            $arrLog[] = $rLog;
        }
        
        db_free_result($qLog);
        
        if(count($arrLog) > 0) {
            ?>
            <div class="heading1 WidthDiv" align="left">
                <b>Historial de modificaciones</b>
            </div>
            <br>
            <table width="100%" cellpadding="5" cellspacing="1" class="table1">
                <tr>
                    <th class="row0" width="18%" align="center">Fecha</th>
                    <th class="row0" width="12%" align="center">Realizado por</th>
                    <th class="row0" width="10%" align="center">Nivel Bloom</th>
                    <th class="row0" width="60%" align="center">Descripci�n</th>
                </tr>
                <?php
                $strClass = "row2";
                foreach($arrLog as $rLog) {
                    $strClass = ($strClass == "row1") ? "row2" : "row1";
                    $strDescripcion = htmlspecialchars($rLog['DESCRIPCION_RA']);
                    ?>
                    <tr>
                        <td class="<?php print $strClass; ?>" align="center"><?php print $rLog['FECHA_LOG']; ?></td>
                        <td class="<?php print $strClass; ?>" align="center"><?php print $rLog['USUARIO']; ?></td>
                        <td class="<?php print $strClass; ?>" align="center"><?php print $rLog['BLOOM_NOMBRE']; ?></td>
                        <td class="<?php print $strClass; ?>" align="center"><?php print $strDescripcion; ?></td>
                    </tr>
                    <?php
                }
                ?>
            </table>
            <?php
        } else {
            ?>
            <p align="center"><em>No hay modificaciones registradas antes de la eliminaci�n.</em></p>
            <?php
        }
        ?>
    </div>
    <?php
}





function drawBlurBitacoraTodosRA($intSyllabusUA) {
    global $cfg;
    
    $intSyllabusUA = intval($intSyllabusUA);
    
    // Obtener RA activos
    $arrRAActivos = getRAActivos($intSyllabusUA);
    
    // Obtener RA eliminados
    $arrRAEliminados = getRAEliminados($intSyllabusUA);
    
    ?>
    <div style="padding: 10px;">
        
        <?php
        // Tabla de RA Activos
        if(count($arrRAActivos) > 0) {
            ?>
            <div class="heading1 WidthDiv" align="left">
                <b>Resultados de aprendizaje actuales</b>
            </div>
            <br>
            <table width="100%" cellpadding="5" cellspacing="1" class="table1">
                <tr>
        <th class="row0" width="15%" align="center">Fecha creaci�n</th>
        <th class="row0" width="12%" align="center">Usuario</th>
        <th class="row0" width="13%" align="center">Nivel bloom</th>
        <th class="row0" width="55%" align="center">Descripci�n</th>
        <th class="row0" width="5%"></th>
                </tr>
                <?php
                $strClass = "row2";
                foreach($arrRAActivos as $rRA) {
                    $strClass = ($strClass == "row1") ? "row2" : "row1";
                    ?>
        <tr>
            <td class="<?php print $strClass; ?>" align="center"><?php print $rRA['FECHA_CREACION']; ?></td>
            <td class="<?php print $strClass; ?>" align="center"><?php print $rRA['USUARIO_CREACION']; ?></td>
            <td class="<?php print $strClass; ?>" align="center"><?php print $rRA['BLOOM_NOMBRE']; ?></td>
            <td class="<?php print $strClass; ?>" align="center"><?php print htmlspecialchars($rRA['DESCRIPCION_RA']); ?></td>
            <td class="<?php print $strClass; ?>" align="center">
                <img src="<?php print strGetCoreImageWithPath('acad_bitacora_orange.png'); ?>" 
                     width="18px" 
                     height="18px"
                     style="cursor: pointer;" 
                     title="Ver bit�cora de este RA"
                     onclick="fntMostrarBitacoraRA(<?php print $rRA['SYLLABUS_UA_RA']; ?>);">
            </td>
        </tr>
                    <?php
                }
                ?>
            </table>
            <?php
        } else {
            ?>
            <p align="center"><em>No hay resultados de aprendizaje registrados.</em></p>
            <?php
        }
        
        // Tabla de RA Eliminados (solo si hay)
        if(count($arrRAEliminados) > 0) {
            ?>
            <br><br>
            <div class="heading1 WidthDiv" align="left">
                <b>Resultados de aprendizaje eliminados</b>
            </div>
            <br>
            <table width="100%" cellpadding="5" cellspacing="1" class="table1">
                <tr>
                    <th class="row0" width="15%" align="center">Fecha eliminaci�n</th>
                    <th class="row0" width="12%" align="center">Usuario</th>
                    <th class="row0" width="13%" align="center">Nivel bloom</th>
                    <th class="row0" width="55%" align="center">Descripci�n</th>
                    <th class="row0" width="5%"></th>
                </tr>
                <?php
                $strClass = "row2";
                foreach($arrRAEliminados as $rRA) {
                    $strClass = ($strClass == "row1") ? "row2" : "row1";
                    ?>
                    <tr>
                        <td class="<?php print $strClass; ?>" align="center"><?php print $rRA['FECHA_ELIMINACION']; ?></td>
                        <td class="<?php print $strClass; ?>" align="center"><?php print $rRA['USUARIO']; ?></td>
                        <td class="<?php print $strClass; ?>" align="center"><?php print $rRA['BLOOM_NOMBRE']; ?></td>
                        <td class="<?php print $strClass; ?>" align="center"><?php print htmlspecialchars($rRA['DESCRIPCION_RA']); ?></td>
                        <td class="<?php print $strClass; ?>" align="center">
                            <img src="<?php print strGetCoreImageWithPath('acad_bitacora_orange.png'); ?>" 
                                 width="18px" 
                                 height="18px"
                                 style="cursor: pointer;" 
                                 title="Ver bit�cora de este RA eliminado"
                                 onclick="fntMostrarBitacoraRAEliminado(<?php print $rRA['SYLLABUS_UA_RA']; ?>);">
                        </td>
                    </tr>
                    <?php
                }
                ?>
            </table>
            <?php
        }
        ?>
    </div>
    <?php
}

function drawBlurBitacoraBiblio($intSyllabusUABiblio) {
    global $cfg;

    $intSyllabusUABiblio = intval($intSyllabusUABiblio);

    // Valor actual de la bibliograf�a
    $strQueryBiblio = "SELECT
                          b.SYLLABUS_UA_BIBLIO,
                          TO_CHAR(NVL(b.MOD_FECHA, b.ADD_FECHA), 'DD/MM/YYYY HH24:MI:SS') as FECHA_ACTUAL,
                          NVL(p2.USUARIO, p1.USUARIO) as USUARIO_ACTUAL
                       FROM {$cfg['academico']['schema']}.SYLLABUS_UA_BIBLIO b
                       LEFT JOIN {$cfg['academico']['schema']}.PERSONA p1
                           ON b.ADD_USER = p1.PERSONA
                       LEFT JOIN {$cfg['academico']['schema']}.PERSONA p2
                           ON b.MOD_USER = p2.PERSONA
                       WHERE b.SYLLABUS_UA_BIBLIO = {$intSyllabusUABiblio}";
    $qBiblio = db_query($strQueryBiblio);
    $rBiblio = db_fetch_array($qBiblio);
    db_free_result($qBiblio);
    if ($rBiblio) {
        $rBiblio['REFERENCIA_COMPLETA'] = getReferenciaBiblio($rBiblio['SYLLABUS_UA_BIBLIO']);
    }

    // Informaci�n de creaci�n
    $strQueryInfo = "SELECT
                        TO_CHAR(b.ADD_FECHA, 'DD/MM/YYYY HH24:MI:SS') as FECHA_CREACION,
                        p1.USUARIO as USUARIO_CREACION,
                        TO_CHAR(b.MOD_FECHA, 'DD/MM/YYYY HH24:MI:SS') as FECHA_MODIFICACION,
                        p2.USUARIO as USUARIO_MODIFICACION
                     FROM {$cfg['academico']['schema']}.SYLLABUS_UA_BIBLIO b
                     LEFT JOIN {$cfg['academico']['schema']}.PERSONA p1
                         ON b.ADD_USER = p1.PERSONA
                     LEFT JOIN {$cfg['academico']['schema']}.PERSONA p2
                         ON b.MOD_USER = p2.PERSONA
                     WHERE b.SYLLABUS_UA_BIBLIO = {$intSyllabusUABiblio}";
    $qInfo = db_query($strQueryInfo);
    $rInfo = db_fetch_array($qInfo);
    db_free_result($qInfo);
    ?>

    <div style="margin-bottom: 7px;">
        <strong>Creado por:</strong> <?php print $rInfo['USUARIO_CREACION']; ?><br>
        <strong>Fecha de creaci�n:</strong> <?php print $rInfo['FECHA_CREACION']; ?>
    </div>
    <br>

    <?php if($rBiblio) { ?>
    <div style="margin-bottom: 20px;">
        <div class="heading1 WidthDiv" align="left">
            <b>Valor Actual</b>
        </div>
        <br>
        <table width="100%" cellpadding="5" cellspacing="1" class="table1">
            <tr>
                <th class="row0" width="20%" align="center">Fecha</th>
                <th class="row0" width="20%" align="center">Realizado por</th>
                <th class="row0" width="60%" align="center">Bibliograf�a</th>
            </tr>
            <tr>
                <td class="row1" align="center"><?php print $rBiblio['FECHA_ACTUAL']; ?></td>
                <td class="row1" align="center"><?php print $rBiblio['USUARIO_ACTUAL']; ?></td>
                <td class="row1" style="text-align: left;"><?php print renderReferenciaBiblioVista($rBiblio['REFERENCIA_COMPLETA']); ?></td>
            </tr>
        </table>
    </div>

    <div class="heading1 WidthDiv" align="left">
        <b>Historial de modificaciones</b>
    </div>
    <br>
    <?php } ?>

    <?php
    $arrLog = getLogBiblio($intSyllabusUABiblio);

    if(count($arrLog) > 0) {
        ?>
        <table width="100%" cellpadding="5" cellspacing="1" class="table1">
            <tr>
                <th class="row0" width="20%" align="center">Fecha</th>
                <th class="row0" width="20%" align="center">Realizado por</th>
                <th class="row0" width="60%" align="center">Bibliograf�a</th>
            </tr>
            <?php
            $strClass = "row2";
            foreach($arrLog as $rLog) {
                $strClass = ($strClass == "row1") ? "row2" : "row1";
                ?>
                <tr>
                    <td class="<?php print $strClass; ?>" align="center"><?php print $rLog['FECHA_LOG']; ?></td>
                    <td class="<?php print $strClass; ?>" align="center"><?php print $rLog['USUARIO']; ?></td>
                    <td class="<?php print $strClass; ?>" style="text-align: left;"><?php print renderReferenciaBiblioVista($rLog['REFERENCIA_COMPLETA']); ?></td>
                </tr>
                <?php
            }
            ?>
        </table>
        <?php
    } else {
        ?>
        <p align="center"><em>No hay cambios registrados para esta bibliograf�a.</em></p>
        <?php
    }
    ?>
    </div>
    <?php
}


function drawBlurBitacoraBiblioEliminado($intSyllabusUABiblio) {
    global $cfg;

    $intSyllabusUABiblio = intval($intSyllabusUABiblio);

    $strQueryBiblio = "SELECT
                          L.LOG_ID,
                          TO_CHAR(L.ADD_FECHA, 'DD/MM/YYYY HH24:MI:SS') as FECHA_CREACION,
                          p1.USUARIO as USUARIO_CREACION,
                          TO_CHAR(L.ADD_FECHA_LOG, 'DD/MM/YYYY HH24:MI:SS') as FECHA_ELIMINACION,
                          p2.USUARIO as USUARIO_ELIMINACION
                       FROM {$cfg['academico']['schema']}.SYLLABUS_UA_BIBLIO_LOG L
                       LEFT JOIN {$cfg['academico']['schema']}.PERSONA p1
                           ON L.ADD_USER = p1.PERSONA
                       LEFT JOIN {$cfg['academico']['schema']}.PERSONA p2
                           ON L.ADD_USER_LOG = p2.PERSONA
                       WHERE L.SYLLABUS_UA_BIBLIO = {$intSyllabusUABiblio}
                         AND L.TIPO_OPERACION = 'D'
                       ORDER BY L.ADD_FECHA_LOG DESC
                       FETCH FIRST 1 ROW ONLY";
    $qBiblio = db_query($strQueryBiblio);
    $rBiblio = db_fetch_array($qBiblio);
    db_free_result($qBiblio);
    if ($rBiblio) {
        $rBiblio['REFERENCIA_COMPLETA'] = getReferenciaBiblioLog($rBiblio['LOG_ID']);
    }
    ?>
    <div style="padding: 10px;">
        <div class="heading1 WidthDiv" align="left">
            <b>Bit�cora de Bibliograf�a (Eliminada)</b>
        </div>
        <br>

        <?php if($rBiblio) { ?>
        <div style="margin-bottom: 7px;">
            <strong>Creado por:</strong> <?php print $rBiblio['USUARIO_CREACION']; ?><br>
            <strong>Fecha:</strong> <?php print $rBiblio['FECHA_CREACION']; ?>
        </div>
        <br>

        <div style="margin-bottom: 7px; padding: 10px; background: #ffe6e6; border: 1px solid #ff4444; border-radius: 4px;">
            <strong>Eliminado por:</strong> <?php print $rBiblio['USUARIO_ELIMINACION']; ?><br>
            <strong>Fecha de eliminaci�n:</strong> <?php print $rBiblio['FECHA_ELIMINACION']; ?>
        </div>
        <br>

        <div style="margin-bottom: 20px;">
            <div class="heading1 WidthDiv" align="left">
                <b>�ltima versi�n (antes de eliminar)</b>
            </div>
            <br>
            <table width="100%" cellpadding="5" cellspacing="1" class="table1">
                <tr>
                    <th class="row0" width="100%" align="center">Bibliograf�a</th>
                </tr>
                <tr>
                    <td class="row1" style="text-align: left;"><?php print renderReferenciaBiblioVista($rBiblio['REFERENCIA_COMPLETA']); ?></td>
                </tr>
            </table>
        </div>
        <?php } ?>

        <?php
        $strQueryLog = "
            SELECT
                L.LOG_ID,
                L.TIPO_OPERACION,
                TO_CHAR(NVL(L.MOD_FECHA, L.ADD_FECHA), 'DD/MM/YYYY HH24:MI:SS') as FECHA_LOG,
                p.USUARIO
            FROM {$cfg['academico']['schema']}.SYLLABUS_UA_BIBLIO_LOG L
            LEFT JOIN {$cfg['academico']['schema']}.PERSONA p
                ON L.ADD_USER_LOG = p.PERSONA
            WHERE L.SYLLABUS_UA_BIBLIO = {$intSyllabusUABiblio}
              AND L.TIPO_OPERACION = 'U'
            ORDER BY L.ADD_FECHA_LOG DESC, L.LOG_ID DESC
        ";
        $arrLog = array();
        $qLog = db_query($strQueryLog);
        while($rLog = db_fetch_assoc($qLog)) {
            $rLog['REFERENCIA_COMPLETA'] = getReferenciaBiblioLog($rLog['LOG_ID']);
            $arrLog[] = $rLog;
        }
        db_free_result($qLog);

        if(count($arrLog) > 0) {
            ?>
            <div class="heading1 WidthDiv" align="left">
                <b>Historial de modificaciones</b>
            </div>
            <br>
            <table width="100%" cellpadding="5" cellspacing="1" class="table1">
                <tr>
                    <th class="row0" width="20%" align="center">Fecha</th>
                    <th class="row0" width="20%" align="center">Realizado por</th>
                    <th class="row0" width="60%" align="center">Bibliograf�a</th>
                </tr>
                <?php
                $strClass = "row2";
                foreach($arrLog as $rLog) {
                    $strClass = ($strClass == "row1") ? "row2" : "row1";
                    ?>
                    <tr>
                        <td class="<?php print $strClass; ?>" align="center"><?php print $rLog['FECHA_LOG']; ?></td>
                        <td class="<?php print $strClass; ?>" align="center"><?php print $rLog['USUARIO']; ?></td>
                        <td class="<?php print $strClass; ?>" style="text-align: left;"><?php print renderReferenciaBiblioVista($rLog['REFERENCIA_COMPLETA']); ?></td>
                    </tr>
                    <?php
                }
                ?>
            </table>
            <?php
        } else {
            ?>
            <p align="center"><em>No hay modificaciones registradas antes de la eliminaci�n.</em></p>
            <?php
        }
        ?>
    </div>
    <?php
}


function drawBlurBitacoraTodosBiblio($intSyllabusUA) {
    global $cfg;

    $intSyllabusUA = intval($intSyllabusUA);

    $arrBiblioActivos    = getBiblioActivos($intSyllabusUA);
    $arrBiblioEliminados = getBiblioEliminados($intSyllabusUA);
    ?>
    <div style="padding: 10px;">

        <?php
        if(count($arrBiblioActivos) > 0) {
            ?>
            <div class="heading1 WidthDiv" align="left">
                <b>Bibliograf�as actuales</b>
            </div>
            <br>
            <table width="100%" cellpadding="5" cellspacing="1" class="table1">
                <tr>
                    <th class="row0" width="15%" align="center">Fecha creaci�n</th>
                    <th class="row0" width="15%" align="center">Usuario</th>
                    <th class="row0" width="65%" align="center">Bibliograf�a</th>
                    <th class="row0" width="5%"></th>
                </tr>
                <?php
                $strClass = "row2";
                foreach($arrBiblioActivos as $rBiblio) {
                    $strClass = ($strClass == "row1") ? "row2" : "row1";
                    ?>
                    <tr>
                        <td class="<?php print $strClass; ?>" align="center"><?php print $rBiblio['FECHA_CREACION']; ?></td>
                        <td class="<?php print $strClass; ?>" align="center"><?php print $rBiblio['USUARIO_CREACION']; ?></td>
                        <td class="<?php print $strClass; ?>" style="text-align: left;"><?php print renderReferenciaBiblioVista($rBiblio['REFERENCIA_COMPLETA']); ?></td>
                        <td class="<?php print $strClass; ?>" align="center">
                            <img src="<?php print strGetCoreImageWithPath('acad_bitacora_orange.png'); ?>"
                                 width="18px"
                                 height="18px"
                                 style="cursor: pointer;"
                                 title="Ver bit�cora de esta bibliograf�a"
                                 onclick="fntMostrarBitacoraBiblio(<?php print $rBiblio['SYLLABUS_UA_BIBLIO']; ?>);">
                        </td>
                    </tr>
                    <?php
                }
                ?>
            </table>
            <?php
        } else {
            ?>
            <p align="center"><em>No hay bibliograf�as registradas.</em></p>
            <?php
        }

        if(count($arrBiblioEliminados) > 0) {
            ?>
            <br><br>
            <div class="heading1 WidthDiv" align="left">
                <b>Bibliograf�as eliminadas</b>
            </div>
            <br>
            <table width="100%" cellpadding="5" cellspacing="1" class="table1">
                <tr>
                    <th class="row0" width="15%" align="center">Fecha eliminaci�n</th>
                    <th class="row0" width="15%" align="center">Usuario</th>
                    <th class="row0" width="65%" align="center">Bibliograf�a</th>
                    <th class="row0" width="5%"></th>
                </tr>
                <?php
                $strClass = "row2";
                foreach($arrBiblioEliminados as $rBiblio) {
                    $strClass = ($strClass == "row1") ? "row2" : "row1";
                    ?>
                    <tr>
                        <td class="<?php print $strClass; ?>" align="center"><?php print $rBiblio['FECHA_ELIMINACION']; ?></td>
                        <td class="<?php print $strClass; ?>" align="center"><?php print $rBiblio['USUARIO']; ?></td>
                        <td class="<?php print $strClass; ?>" style="text-align: left;"><?php print renderReferenciaBiblioVista($rBiblio['REFERENCIA_COMPLETA']); ?></td>
                        <td class="<?php print $strClass; ?>" align="center">
                            <img src="<?php print strGetCoreImageWithPath('acad_bitacora_orange.png'); ?>"
                                 width="18px"
                                 height="18px"
                                 style="cursor: pointer;"
                                 title="Ver bit�cora de esta bibliograf�a eliminada"
                                 onclick="fntMostrarBitacoraBiblioEliminado(<?php print $rBiblio['SYLLABUS_UA_BIBLIO']; ?>);">
                        </td>
                    </tr>
                    <?php
                }
                ?>
            </table>
            <?php
        }
        ?>
    </div>
    <?php
}


    

    function UpdateMultipleEquivalencias($intCursoId,$intCurso_equivalente,$intCurso_equivalente2,$intCurso_equivalente3,$strCursoSobresee1I,$strCursoSobresee2I,$strCursoSobresee3I,$intCursoModificar,$intIdEquivalenciaBuscar,$intAddUser){
            // intCurso_equivalente a intCurso_equivalente3 son los ids para cada Curso y asi mismo $strCursoSobresee1I a $strCursoSobresee3I
            //aqui actualizo multiples curos_equivalentes en un Solo Curso

        if ($intCurso_equivalente==$intCursoId) {
            if ($intCurso_equivalente3 > 0 ){
                $intCurso_equivalente = $intCurso_equivalente2;
                $intCurso_equivalente2 = $intCurso_equivalente3;
                $intCurso_equivalente3 = 0;
                $strCursoSobresee1I = $strCursoSobresee2I;
                $strCursoSobresee2I = $strCursoSobresee3I;
                $strCursoSobresee3I = NULL;
            }else{
                $intCurso_equivalente = $intCurso_equivalente2;
                $intCurso_equivalente2 = 0;
                $intCurso_equivalente3 = 0;
                $strCursoSobresee1I = $strCursoSobresee2I;
                $strCursoSobresee2I = NULL;
                $strCursoSobresee3I = NULL;
            }
        }
        if($intCurso_equivalente2==$intCursoId  ) {

            if ($strCursoSobresee3I == "NULL") {
                $strCursoSobresee2I = NULL;
                $strCursoSobresee3I = NULL;
            }else{
                $strCursoSobresee2I = $strCursoSobresee3I;
                $strCursoSobresee3I = NULL;
            }
            $intCurso_equivalente2 = $intCurso_equivalente3;
            $intCurso_equivalente3 = 0;

        }
        elseif($intCurso_equivalente3==$intCursoId) {
            $intCurso_equivalente3 = 0;
            $strCursoSobresee3I = NULL;
        }
        $strCurso_equivalente = ($intCurso_equivalente != 0) ? $intCurso_equivalente : NULL;
        $strCurso_equivalente2 = ($intCurso_equivalente2 != 0) ? $intCurso_equivalente2 : NULL;
        $strCurso_equivalente3 = ($intCurso_equivalente3 != 0) ? $intCurso_equivalente3 : NULL;

        $strQuery = "call SP_UPDATE_CURSO_EQUIVALENT_MUL({$intCursoModificar},'{$strCurso_equivalente}','{$strCurso_equivalente2}','{$strCurso_equivalente3}','{$strCursoSobresee1I}',NULL,'{$strCursoSobresee2I}','{$strCursoSobresee3I}',{$intAddUser},{$intCursoModificar},{$intIdEquivalenciaBuscar})";
       // drawDebug($strQuery);
        db_query($strQuery);
    }

    function getDetailPersona($intPersona){
        $arrDetailPersona = Array();
        $strQuery = "SELECT p.persona,
                            p.nombre1,
                            p.nombre2,
                            p.apellido1,
                            p.apellido2,
                            p.identificacion,
                            p.fecha_nacimiento,
                            ( CASE WHEN(fc.tipo_forma_contacto = 1) THEN fc.descripcion ELSE '' END ) telefono,
                            ( CASE WHEN(fc.tipo_forma_contacto = 2) THEN fc.descripcion ELSE '' END ) email,
                            ( CASE WHEN(fc.tipo_forma_contacto = 2) THEN INSTR(fc.descripcion,'ufm.edu') ELSE 0 END ) order_email,
                            c.carne
                     FROM   persona p
                            LEFT JOIN forma_contacto fc
                                ON  p.persona = fc.persona_cuenta
                                AND fc.activo = 'Y'
                            LEFT JOIN carne c
                                ON  p.persona = c.persona AND c.actual = 'Y'
                     WHERE  p.persona = {$intPersona}
                     ORDER  BY order_email";
        $qTMP = db_query($strQuery);

        while($rTMP = db_fetch_assoc($qTMP)){
            $arrDetailPersona[$rTMP["PERSONA"]]["NOMBRE"] = $rTMP["NOMBRE1"]." ".$rTMP["NOMBRE2"]." ".$rTMP["APELLIDO1"]." ".$rTMP["APELLIDO2"];
            $arrDetailPersona[$rTMP["PERSONA"]]["CARNE"] = $rTMP["CARNE"];
            $arrDetailPersona[$rTMP["PERSONA"]]["IDENTIFICACION"] = $rTMP["IDENTIFICACION"];
            $arrDetailPersona[$rTMP["PERSONA"]]["FECHA_NACIMIENTO"] = $rTMP["FECHA_NACIMIENTO"];
            if(isset($rTMP["TELEFONO"]) && !empty($rTMP["TELEFONO"]) )
                $arrDetailPersona[$rTMP["PERSONA"]]["TELEFONO"] = $rTMP["TELEFONO"];
            if(isset($rTMP["EMAIL"]) && !empty($rTMP["EMAIL"]) )
                $arrDetailPersona[$rTMP["PERSONA"]]["EMAIL"] = $rTMP["EMAIL"];
        }
        db_free_result($qTMP);

        return $arrDetailPersona;
    }

    function drawDetallePersona($intPersona,$strCarne,$intCurso, $intPensum){
        global $globalConnection;
        $arrDetailAlumno = getDetailPersona($intPersona);
        $intRespuesta1 = 0;
        $strRespuesta1 = "";
        $intRespuesta2 = 0;
        $strRespuesta2 = "";
        $intRespuesta3 = 0;
        $strRespuesta3 = "";

        $sinUmasAprobadasFacultad = getUmasPorFacultad($intPensum,$strCarne,0);
        $arrPreRequisitosPendientes = validarPreRequisitos($intCurso, $sinUmasAprobadasFacultad, $strCarne, true);

        /*$arrPreRequisitoCurso = validarPreRequisitoCursos($intCurso, $strCarne, true);
        drawDebug($arrPreRequisitoCurso,'$arrPreRequisitoCurso');
        $arrPreRequisitoHistorial = validarPreRequisitoHistorial($intCurso,$strCarne,$sinUmasAprobadasFacultad,true);
        drawDebug($arrPreRequisitoHistorial,'$arrPreRequisitoHistorial');*/

        //$strQuery = "CALL SP_ACA_VERIFICAR_PRERREQUISITO({$intPersona}, {$intCurso}, {$intPensum}, :intRespuesta1, :strRespuesta1, :intRespuesta2, :strRespuesta2, :intRespuesta3, :strRespuesta3)";
        //$strQuery = "CALL SP_VERIFICAR_PRERREQUISITO_TXT('{$strCarne}', {$intCurso}, {$intPensum}, :intRespuesta1, :strRespuesta1, :intRespuesta2, :strRespuesta2, :intRespuesta3, :strRespuesta3)";
        /*$qTMP = oci_parse($globalConnection, $strQuery);
        oci_bind_by_name($qTMP,":intRespuesta1",$intRespuesta1);
        oci_bind_by_name($qTMP,":strRespuesta1",$strRespuesta1,4000);
        oci_bind_by_name($qTMP,":intRespuesta2",$intRespuesta2);
        oci_bind_by_name($qTMP,":strRespuesta2",$strRespuesta2,4000);
        oci_bind_by_name($qTMP,":intRespuesta3",$intRespuesta3);
        oci_bind_by_name($qTMP,":strRespuesta3",$strRespuesta3,4000);
        oci_execute($qTMP);
        oci_free_statement($qTMP);


        $strRespuesta3 = trim($strRespuesta3);
        $strRespuesta2 = trim($strRespuesta2);
        $strRespuesta1 = trim($strRespuesta1);*/

        //$strRespuesta1 = !empty($strRespuesta1)?($strRespuesta1.( !empty($strRespuesta2)? ", ".$strRespuesta2 : "").( !empty($strRespuesta3)? ", ".$strRespuesta3 : "")) :(!empty($strRespuesta3)?$strRespuesta3.( !empty($strRespuesta2)? ", ".$strRespuesta2 : ""):( !empty($strRespuesta2)?$strRespuesta2 : ""));

        //drawdebug($strRespuesta1,"line #".__LINE__);
        //$arrExplodeResp1 = explode(",",$strRespuesta1);

        ?>
        <table width="100%" cellpadding="2" cellspacing="2" border="0" bgcolor="#EEEEEE">
            <tr>
                <td rowspan="3" width="15%" align="center"><?php academico_draw_avatar_persona($intPersona); ?></td>
                <td rowspan="3" width="5%">&nbsp;</td>
                <td colspan="4" class="bannerH1">
                    <?php print isset($arrDetailAlumno[$intPersona]["NOMBRE"]) ? $arrDetailAlumno[$intPersona]["NOMBRE"] : "&nbsp;"; ?>
                </td>
            </tr>
            <tr>
                <td width="8%">
                    <img src="<?php print strGetCoreImageWithPath("birthday.png"); ?>">
                </td>
                <td width="22%">
                    <?php print isset($arrDetailAlumno[$intPersona]["FECHA_NACIMIENTO"]) ? show_date($arrDetailAlumno[$intPersona]["FECHA_NACIMIENTO"], false) : "&nbsp;"; ?>
                </td>
                <td width="8%">
                    <img src="<?php print strGetCoreImageWithPath("e-mail.png"); ?>">
                </td>
                <td width="42%">
                    <?php print isset($arrDetailAlumno[$intPersona]["EMAIL"]) ? $arrDetailAlumno[$intPersona]["EMAIL"] : "&nbsp;"; ?>
                </td>
            </tr>
            <tr>
                <td>
                    <img src="<?php print strGetCoreImageWithPath("carne.png"); ?>">
                </td>
                <td>
                    <?php print isset($arrDetailAlumno[$intPersona]["CARNE"]) ? $arrDetailAlumno[$intPersona]["CARNE"] : "&nbsp;"; ?>
                </td>
                <td>
                    <img src="<?php print strGetCoreImageWithPath("telefono.png"); ?>">
                </td>
                <td>
                    <?php print isset($arrDetailAlumno[$intPersona]["TELEFONO"]) ? getFormatoTelefono($arrDetailAlumno[$intPersona]["TELEFONO"]) : "&nbsp;"; ?>
                </td>
            </tr>
        </table>
        <br>
        <div style="border: 1px solid #EEEEEE;">
            <table width="95%" cellpadding="0" cellspacing="0" align="center">
                <?php
                if( count($arrPreRequisitosPendientes) == 0 ){
                    ?>
                    <tr>
                        <td class="bannerH1" align="center">
                            "El alumno ha cumplido los pre-requisitos de este curso"
                        </td>
                    </tr>
                    <?php
                }
                else{
                    ?>
                    <tr>
                        <td class="heading1">"El alumno no ha cumplido con los siguientes pre-requisitos de este curso:" </td>
                    </tr>
                    <tr>
                        <td>
                            <table width="100%" cellpadding="0" cellspacing="0" id="tblDetallePreRequisitosNoCumplidos">
                                <?php
                                $strClass = "row1";
                                if( isset($arrPreRequisitosPendientes['CURSOS']) ){
                                    ?>
                                    <tr>
                                        <td>
                                            <b>Cursos:</b>
                                        </td>
                                    </tr>
                                    <?php
                                    reset($arrPreRequisitosPendientes['CURSOS']);
                                    foreach( $arrPreRequisitosPendientes['CURSOS'] as  $arrTMP['key'] => $arrTMP['value'])  {
                                        $strClass = ($strClass == "row1") ? "row2" : "row1";
                                        ?>
                                        <tr>
                                            <td class="<?php print $strClass; ?>"><?php print $arrTMP['value']; ?></td>
                                        </tr>
                                        <?php
                                    }
                                }

                                if( isset($arrPreRequisitosPendientes['HISTORIAL']) ){
                                    ?>
                                    <tr>
                                        <td>
                                            <b>Historial:</b><br>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="<?php print $strClass; ?>"><?php print $arrPreRequisitosPendientes['HISTORIAL']; ?></td>
                                    </tr>
                                    <?php
                                }
                                ?>
                            </table>
                        </td>
                    </tr>
                    <?php
                }
                ?>
            </table>
            <br>
        </div>
        <?php
    }

    function drawTabCursosImpartido($intCurso,$intFacultad){
        global $strAction;
        $arrCursosImpartidos = getCursosImpartidos($intCurso);
        ?>
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td class="row0">Subciclo</td>
                <td class="row0">CIMP</td>
                <td class="row0" align="center">Secci�n</td>
                <td class="row0" align="center">Cupo</td>
                <td class="row0">Catedr�tico principal</td>
            </tr>
            <?php
            $strClass = 'row1';
            $intRow = 0;
            foreach( $arrCursosImpartidos as  $arrTMP['key'] => $arrTMP['value'])  {
                ?>
                <tr>
                    <td class="<?php print $strClass;?>">
                        <a target="_blank" href="adm_academico_programa.php?facultad=<?php print $intFacultad; ?>&intPrograma=<?php print md5($arrTMP['value']['CICLO_FECHA']);?>">
                            <?php print isset($arrTMP['value']['CICLO_FECHA_CODIGO']) ? $arrTMP['value']['CICLO_FECHA_CODIGO']: "&nbsp;"; ?>
                        </a>
                    </td>
                    <td class="<?php print $strClass;?>">
                        <a target="_blank" href="adm_academico_curso_impartido_detalle.php?cursoImpartido=<?php print md5($arrTMP['key']); ?>&intCiclo=<?php print md5($arrTMP['value']['CICLO']); ?>&intPrograma=<?php print md5($arrTMP['value']['CICLO_FECHA']); ?>&Facultad=<?php print $intFacultad ?>">
                            <?php print isset($arrTMP['key'])? $arrTMP['key'] : "&nbsp;";?>
                        </a>
                    </td>
                    <td class="<?php print $strClass;?>" align="center">
                        <?php print $arrTMP['value']['SECCION'] ;?>
                    </td>
                    <td class="<?php print $strClass;?>" align="center">
                        <?php print $arrTMP['value']['CUPO'] ;?>
                    </td>
                    <td class="<?php print $strClass;?>">
                        <?php
                        if( isset($arrTMP['value']['PERSONA']) && intval($arrTMP['value']['PERSONA']) > 0 ){;
                            ?>
                            <a  id="cat_<?php print $intRow ;?>" target="_blank" href="adm_admisiones_persona.php?persona=<?php print $arrTMP['value']['PERSONA']; ?>&strTab=hrefCatedratico" onmouseover="fntGetDetailCatedratico(<?php print $arrTMP['value']['PERSONA']; ?>,<?php print $intRow; ?>)" style="vertical-align: bottom;"  >
                                <?php print isset($arrTMP['value']['NOMBRE_CAT']) ? $arrTMP['value']['NOMBRE_CAT']: "&nbsp;";?>
                            </a>
                            <?php
                        }else{
                            print '&nbsp;';
                        }
                        ?>

                    </td>
                </tr>
                <?php
                $intRow++;
            }
            ?>
        </table>
        <script type="text/javascript" language="javascript">
            getDocumentLayer("TabCursosImpartidos").innerHTML = "Cursos impartidos (<?php print count($arrCursosImpartidos); ?>)"
            function fntGetDetailCatedratico(intCatedratico,intFila){
                if( intCatedratico > 0 ) {
                    if( arrBubblePopop[intFila] ) {
                        $("#cat_"+ intFila).ShowBubblePopup();
                    }
                    else {

                        $.ajax({
                            url: "<?php print $strAction;  ?>?facultad=<?php print $intFacultad; ?>",
                            data: "getDetailCatedratico=true&intCatedratico="+ intCatedratico,
                            dataType: "html",
                            type: "POST",
                            async: false,
                            beforeSend: function() {
                                intTop = ( $(window).height() * 1 ) / 2;
                                $("#divShowLoadingGeneralBig").css("top", intTop);
                                $("#divShowLoadingGeneralBig").css("left", 0);
                                $("#divShowLoadingGeneralBig").show();
                            },
                            success: function(data) {
                                $("#divShowLoadingGeneralBig").hide();
                                $("#cat_"+ intFila).CreateBubblePopup({
                                    distance: "0px",
                                    width: "350",
                                    tail:{
                                        align:'center',
                                        hidden: false
                                    },
                                    innerHtml: data,
                                    themeName: 'all-black',
                                    themePath: 'core/jquery/bubblepopup/jquerybubblepopup-theme',
                                    themeMargins: {difference: '4px'},
                                });
                                arrBubblePopop[intFila] = 1;
                            }

                        });

                    }
                }

            }
        </script>

        <!-- Indicador de an�lisis con IA -->
<div id="divAnalizandoIA" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); 
     background-color: rgba(255, 255, 255, 0.95); padding: 20px 30px; border-radius: 8px; 
     box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 9999; border-left: 4px solid #4CAF50;">
    <div style="display: flex; align-items: center; gap: 12px;">
        <img src="https://intranet.ufm.edu/reportesai/icon_ia_sparkle.png" alt="IA" style="height: 28px; animation: pulse 1.5s infinite;">
        <span style="font-size: 14px; font-weight: 500; color: #333;">Analizando con IA...</span>
    </div>
</div>

<style>
@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.7; transform: scale(1.1); }
}
</style>

        <?php
    }

    function getCursosImpartidos($intCurso){
        $arrCursosImpartidos = Array();
        $strQuery ="SELECT     ci.maximo_alumnos,
                                ci.seccion,
                                ci.curso_impartido,
                                cf.codigo ciclo_fecha_codigo,
                                cf.ciclo_fecha,
                                cf.ciclo,
                                cis.persona,
                                persona.nombre1,
                                persona.nombre2,
                                persona.apellido1,
                                persona.apellido2,
                                persona.apellido_casada
                    FROM curso_impartido ci
                    INNER JOIN ciclo_fecha cf
                    ON ci.ciclo_fecha = cf.ciclo_fecha
                    LEFT JOIN curso_impartido_staff cis
                    ON ci.curso_impartido = cis.curso_impartido
                    AND cis.es_titular = 'Y'
                    LEFT JOIN persona
                    on cis.persona = persona.persona
                    WHERE ci.curso = {$intCurso}
                    ORDER BY cf.fecha_inicio,cf.fecha_fin,ci.curso_impartido";
        $qTMP = db_query($strQuery);
        while($rTMP = db_fetch_assoc($qTMP)){
          $arrCursosImpartidos[$rTMP['CURSO_IMPARTIDO']]['CUPO'] = $rTMP['MAXIMO_ALUMNOS'];
          $arrCursosImpartidos[$rTMP['CURSO_IMPARTIDO']]['SECCION'] = $rTMP['SECCION'];
          $arrCursosImpartidos[$rTMP['CURSO_IMPARTIDO']]['CICLO'] = $rTMP['CICLO'];
          $arrCursosImpartidos[$rTMP['CURSO_IMPARTIDO']]['CICLO_FECHA'] = $rTMP['CICLO_FECHA'];
          $arrCursosImpartidos[$rTMP['CURSO_IMPARTIDO']]['CICLO_FECHA_CODIGO'] = $rTMP['CICLO_FECHA_CODIGO'];
          $arrCursosImpartidos[$rTMP['CURSO_IMPARTIDO']]['PERSONA'] = $rTMP['PERSONA'];
          $arrCursosImpartidos[$rTMP['CURSO_IMPARTIDO']]['NOMBRE_CAT'] = $rTMP['NOMBRE1']." ".$rTMP['NOMBRE2']." ".$rTMP['APELLIDO1']." ".$rTMP['APELLIDO2'];
          if (isset($rTMP['APELLIDO_CASADA']) && !empty ($rTMP['APELLIDO_CASADA']) ){
              $arrCursosImpartidos[$rTMP['CURSO_IMPARTIDO']]['NOMBRE_CAT'] .= ' de '.$rTMP['APELLIDO_CASADA'];
          }
        }
        db_free_result($qTMP);
        //drawDebug($arrCursosImpartidos);
        return $arrCursosImpartidos;
    }

    function getTotalCursosImpartidos($intCurso){
        $strQuery ="SELECT     count(ci.curso_impartido)
                    FROM curso_impartido ci
                    INNER JOIN ciclo_fecha cf
                    ON ci.ciclo_fecha = cf.ciclo_fecha
                    LEFT JOIN curso_impartido_staff cis
                    ON ci.curso_impartido = cis.curso_impartido
                    AND cis.es_titular = 'Y'
                    LEFT JOIN persona
                    on cis.persona = persona.persona
                    WHERE ci.curso = {$intCurso}
                    ORDER BY cf.fecha_inicio,cf.fecha_fin,ci.curso_impartido";
        $intTotalCursosImpartidos = sqlGetValueFromKey($strQuery);
        return $intTotalCursosImpartidos;
    }

    function getDetailCatedraticos($intCatedratico){
        $arrDetailCatedraticos = Array();
        $strQuery = "SELECT p.persona,
                            p.nombre1,
                            p.nombre2,
                            p.apellido1,
                            p.apellido2,
                            p.identificacion,
                            p.fecha_nacimiento,
                            ( CASE WHEN(fc.tipo_forma_contacto = 1) THEN fc.descripcion ELSE '' END ) telefono,
                            ( CASE WHEN(fc.tipo_forma_contacto = 2) THEN fc.descripcion ELSE '' END ) email,
                            ( CASE WHEN(fc.tipo_forma_contacto = 2) THEN INSTR(fc.descripcion,'ufm.edu') ELSE 0 END ) order_email,
                            c.carne
                     FROM   persona p
                            LEFT JOIN forma_contacto fc
                                ON  p.persona = fc.persona_cuenta
                                AND fc.activo = 'Y'
                            LEFT JOIN carne c
                                ON  p.persona = c.persona AND c.actual = 'Y'
                     WHERE  p.persona = {$intCatedratico}
                     ORDER  BY order_email";
        $qTMP = db_query($strQuery);
       // drawDebug($strQuery);
        while($rTMP = db_fetch_assoc($qTMP)){
            $arrDetailCatedraticos[$rTMP["PERSONA"]]["NOMBRE"] = $rTMP["NOMBRE1"]." ".$rTMP["NOMBRE2"]." ".$rTMP["APELLIDO1"]." ".$rTMP["APELLIDO2"];
            $arrDetailCatedraticos[$rTMP["PERSONA"]]["CARNE"] = $rTMP["CARNE"];
            $arrDetailCatedraticos[$rTMP["PERSONA"]]["IDENTIFICACION"] = $rTMP["IDENTIFICACION"];
            $arrDetailCatedraticos[$rTMP["PERSONA"]]["FECHA_NACIMIENTO"] = $rTMP["FECHA_NACIMIENTO"];
            if(isset($rTMP["TELEFONO"]) && !empty($rTMP["TELEFONO"]) )
                $arrDetailCatedraticos[$rTMP["PERSONA"]]["TELEFONO"] = $rTMP["TELEFONO"];
            if(isset($rTMP["EMAIL"]) && !empty($rTMP["EMAIL"]) )
                $arrDetailCatedraticos[$rTMP["PERSONA"]]["EMAIL"] = $rTMP["EMAIL"];
        }
        db_free_result($qTMP);

        return $arrDetailCatedraticos;
    }

    function getValoresTipoCasoFinal(){
        $arrResult = array();
        $strQuery = "SELECT  aut_estado_caso_tabla.valor
                           FROM     aut_estado_caso
                                        INNER JOIN aut_estado_caso_tabla
                                            ON aut_estado_caso.aut_estado_caso = aut_estado_caso_tabla.aut_estado_caso
                           WHERE   aut_tipo_caso = 3
                           AND      aut_estado_caso.estado_final = 'Y'";
        $qTMP = db_query($strQuery);
        $intContador = 0;
        while($rTMP = db_fetch_assoc($qTMP)){
            $arrResult[$rTMP['VALOR']] = $rTMP['VALOR'];
        }
        db_free_result($qTMP);
        return $arrResult;
    }



?>
