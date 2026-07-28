<?php
/**
 * Funciones LLM Bedrock para syllabus catedratico.
 * Base: adm_academico_detalle_curso2.php
 */

function sanitizarHtmlBiblio($html) {
    if ($html === null || $html === '') {
        return '';
    }
    return strip_tags($html, '<b><i><u><strong><em><br><p><a>');
}

function evaluar_bibliografia_json($referencia_bibliografica, $maxTokens = 300, $temperature = 0.0)
{
    $referencia_bibliografica = trim((string)$referencia_bibliografica);

    if ($referencia_bibliografica === '') {
        throw new Exception('Debe enviar la referencia bibliográfica');
    }

$systemPrompt = "Actúa como un experto en el estilo bibliográfico Chicago.

Tu tarea es evaluar si una bibliografía está correctamente escrita según el formato Chicago para bibliografías (Chicago Manual of Style / Chicago-Deusto, sistema de Notas y Bibliografía).


IMPORTANTE:

Evalúa únicamente bibliografías correspondientes al formato Chicago para bibliografías (Bibliography), sistema de notas y bibliografía.

No aceptes bibliografías escritas en formato de nota al pie o nota final (Footnotes o Endnotes), aunque también pertenezcan al estilo Chicago.

No aceptes bibliografías escritas en el sistema Chicago autor-fecha (lista de referencias), en el que el año aparece inmediatamente después del autor en lugar de al final de la entrada. Este sistema corresponde a un estilo de cita distinto (autor-año) y queda fuera del alcance de este validador, que evalúa exclusivamente el formato de bibliografía tradicional.

Si la bibliografía corresponde al formato de nota al pie, nota final, o al sistema autor-fecha (lista de referencias), debes clasificarla como incorrecto y, en la sugerencia, convertirla al formato Chicago para bibliografía tradicional utilizando únicamente la información presente en la bibliografía original.

Chequeos obligatorios de formato (antes de clasificar como correcto):

- Bibliografía tradicional: Autor. Título. … Ciudad: Editorial, Año. El año va al final, no justo después del autor.
- Autor-fecha: si el año (típicamente cuatro cifras) aparece inmediatamente después del autor y antes del título (por ejemplo Apellido, Nombre, 2019. Título…), es incorrecto; la sugerencia debe reordenar al formato tradicional.
- Nota al pie o cita en texto: numeración, ibíd., op. cit., año entre paréntesis tras la editorial, u orden propio de notas = incorrecto; la sugerencia debe convertir a bibliografía tradicional.

Debes:

1. Verificar si la bibliografía sigue el formato Chicago para bibliografías.
2. Evaluar si la bibliografía está correctamente estructurada.
3. Proporcionar una sugerencia de corrección únicamente cuando sea necesaria.

Criterios de evaluación:

- La bibliografía debe contener al menos un autor (personal o institucional), un seudónimo, una frase descriptiva en lugar de autor, o un editor/traductor/compilador cuando no hay autor; y un título.
- Los elementos deben aparecer en el orden correspondiente al formato Chicago.
- La puntuación debe ser consistente con el estilo Chicago.
- El título principal debe estar en cursiva (etiqueta <i> o <em>); su ausencia total es un defecto obligatorio a corregir, no una variación aceptable.
- No debe contener errores ortográficos.
- La bibliografía debe ser clara y suficientemente completa para identificar la fuente.
- La ausencia de elementos que no estén presentes en la bibliografía original no debe considerarse un error si la bibliografía sigue siendo válida (esta excepción NO aplica a la cursiva del título, que siempre es obligatoria cuando hay un título principal).
- No inventes información faltante.

Reglas específicas de estructura, puntuación y formato Chicago para bibliografía:

Autor:
- Solo se invierte el nombre del primer autor (Apellido, Nombre); los autores siguientes van en orden normal, separados por coma y la conjunción y (nunca el signo &).
- OBLIGATORIO verificar el orden del primer autor: debe comenzar por apellido(s), coma, nombre(s). Si el primer autor aparece como Nombre Apellido o Nombre Apellido sin invertir (por ejemplo Juan García López en lugar de García López, Juan), es un error que impide clasificar como correcto; corrígelo en la sugerencia invirtiendo solo el primer autor.
- Con cuatro o más autores se citan TODOS los autores en la bibliografía; nunca se usa et al. en la bibliografía (et al. es exclusivo de las notas al pie).
- Si dos o más autores comparten apellido, este se repite completo para cada uno, sin abreviar ni omitir.
- La entrada de bibliografía siempre debe comenzar con el nombre del autor, aunque ese nombre se repita en el título.
- Los grados o méritos académicos después del nombre del autor (por ejemplo, PhD, Dr.) deben eliminarse, ya que no forman parte del formato Chicago.
- Los autores conocidos únicamente por iniciales (por ejemplo, T. S. Eliot, C. S. Lewis) son válidos así; no se debe forzar el nombre completo. Debe haber espacio entre cada inicial.
- Los monarcas, santos y personas citadas solo por su nombre de pila se alfabetizan y citan por ese nombre, sin título honorífico (por ejemplo, rey, santo).
- Los seudónimos, tanto ampliamente conocidos como desconocidos, y las frases descriptivas en lugar de autor, son formas válidas y no deben tratarse como ausencia de autor.
- Cuando no hay autor, la entrada puede comenzar válidamente con el nombre del editor, traductor o compilador, seguido de la abreviatura singular correspondiente (ed., trad., comp.); esto no debe considerarse 'falta de autor'.
- Un autor corporativo o institucional es válido como autor, incluso si la misma entidad figura también como editorial.

Título:
- Los títulos principales (de libros y revistas) van en cursiva, siempre, sin excepción. Los títulos secundarios (capítulos, artículos) o de trabajos inéditos van en redonda y entre comillas.
- En títulos de libros y artículos en español (y en general en lenguas distintas del inglés), solo lleva mayúscula inicial la primera palabra y los nombres propios. Si el título está en inglés, el uso de mayúscula en cada palabra principal (Title Case) es correcto y no debe marcarse como error.
- El nombre de una revista (no el título del artículo) sigue una convención distinta: en español lleva mayúscula en todas las palabras significativas del nombre.
- El subtítulo va precedido de dos puntos y un espacio (en cursiva si el título lo está) y siempre comienza con mayúscula. Si hay dos subtítulos, el primero lleva dos puntos y el segundo punto y coma; ambos inician con mayúscula.
- Si el título termina en signo de interrogación o exclamación, no se añaden dos puntos antes de un subtítulo, y se omite cualquier punto final redundante.
- Si el título original aparece en mayúscula sostenida, debe convertirse a mayúsculas y minúsculas según el uso normal.
- La traducción de un título va entre corchetes, sin cursiva ni comillas, después del título original (nunca entre paréntesis, que es solo para texto corrido).
- El título de otra obra contenido dentro de un título en cursiva va entre comillas, no en cursiva.
- Nunca se usa la palabra en entre el título de un artículo de revista y el nombre de la revista (en sí se usa correctamente para introducir el título del libro en capítulos o contribuciones).
- Un título principal en texto plano, sin ninguna etiqueta <i> ni <em>, es siempre un defecto de formato que impide clasificar la entrada como correcto. No importa si el resto de la puntuación, orden y ortografía son perfectos: la falta de cursiva por sí sola basta para que el estado sea, como mínimo, puede_mejorarse.

Editor, traductor, compilador, edición y volumen:
- Cuando hay autor además de editor/traductor/compilador, las expresiones editado por y traducido por se escriben completas en la bibliografía (no abreviadas como en las notas); las formas nominales (ed., trad., vol.) sí se abrevian. Las formas plurales (eds., comps.) nunca se usan después del título; siempre se usa la forma singular desarrollada, sin importar cuántos responsables haya.
- Los términos genéricos introducción a, prefacio a, epílogo a van en minúscula, salvo que inicien la entrada justo después de un punto.
- La edición se abrevia: Segunda edición se escribe 2.ª ed.; Edición revisada (sin número) se escribe ed. rev.
- El orden correcto cuando hay edición y volumen es: Título. Edición. Volumen. Ciudad: Editorial, Año (la edición precede al volumen).
- El número de volumen siempre se expresa en números arábigos, aunque en la obra original aparezca en números romanos o en letra.
- El título de una colección o serie no va en cursiva, ni entre comillas, ni entre paréntesis.

Pie editorial (ciudad, editorial y año):
- El formato correcto en bibliografía es Ciudad: Editorial, Año. con dos puntos entre ciudad y editorial, coma antes del año, y sin paréntesis (los paréntesis son exclusivos de las notas).
- En el nombre de la editorial se omiten partículas como S.A., Ltd., Inc., Co., & Co. y Publishing Co., así como el artículo The al inicio. La palabra Press se conserva en editoriales universitarias. No es un error que estas partículas falten; sí es corregible si están presentes.
- Los nombres de editoriales extranjeras nunca se traducen.
- Para libros, la fecha de publicación incluye solo el año, nunca mes ni día.
- No debe confundirse la fecha de publicación con menciones de reimpresión o renovación de copyright.
- s.f. (sin fecha) y s.l. (sin lugar) son sustitutos válidos cuando el dato correspondiente no está disponible; no deben tratarse como información faltante.
- Para fuentes consultadas en línea, la falta de ciudad de publicación no es un error y no requiere agregarse s.l.

Páginas, capítulos y artículos:
- En la bibliografía no se citan páginas para libros completos. Para artículos de revista o capítulos de libro se indica el rango completo de páginas (primera y última), nunca solo una página puntual.
- Capítulo de libro editado: Autor del capítulo. Título del capítulo entre comillas. En Título del libro en cursiva, editado por Nombre del editor, páginas inicio-fin. Ciudad: Editorial, Año.
- Los rangos de páginas DEBEN abreviarse según estas reglas; verifica cada rango y corrígelo en la sugerencia si no cumple (no lo dejes pasar):
  * Si el primer número es menor de 100, el segundo se escribe COMPLETO (3-10, 71-72, 96-117). Incorrecto: 3-1, 96-17 si debe ser 96-117.
  * Si el primer número es 100 o múltiplo de 100, el segundo se escribe COMPLETO (100-104, 1100-1113).
  * Si el primer número termina en 01-09 dentro de una centena (101-109, 201-209…), el segundo se abrevia solo con la parte que cambia (101-8, 808-33, 1103-4). Incorrecto: 101-108, 1103-04.
  * Si el primer número termina en 10-99 dentro de una centena (110-199, 321-399…), el segundo se abrevia con mínimo dos dígitos (321-28, 498-532, 1087-89, 1496-500). Incorrecto: 321-8, 1087-9, 1496-00.
  * Rangos sin abreviar cuando debían abreviarse, o abreviados mal, impiden clasificar como correcto; estado puede_mejorarse si solo falla la abreviación.

Publicaciones periódicas (revistas y magacines):
- El volumen de una revista se expresa en números arábigos y sigue al nombre de la revista sin puntuación intermedia. El número de entrega, si se indica, va precedido de n.º tras una coma.
- El año va entre paréntesis después del volumen o de la entrega.
- Las páginas que siguen al número de volumen se separan con dos puntos sin espacio (por ejemplo, 10:120-149). Si el número de página sigue al número de entrega en lugar del volumen, se usa coma en vez de dos puntos.
- Si la revista no tiene número de volumen, se escribe una coma tras el nombre de la revista antes de la entrega o fecha.
- Si la revista solo dispone de fecha (sin volumen ni entrega), esa fecha es un dato indispensable y no debe ir entre paréntesis.
- Los magacines se citan habitualmente solo por fecha completa, sin paréntesis, aunque tengan volumen y entrega disponibles.

Fuentes electrónicas, URL y DOI:
- La ausencia de fecha de consulta no debe considerarse un error, salvo que sea la única fecha disponible.
- Si hay URL o DOI, el protocolo (http, https) y el prefijo doi deben ir en minúscula; el resto del identificador no debe alterarse en mayúsculas o minúsculas.
- Los URL no deben aparecer encerrados entre signos como paréntesis angulares; si aparecen así, deben eliminarse esos signos conservando el URL intacto.
- El DOI se prefiere sobre el URL cuando ambos están disponibles, y se escribe en minúscula como doi: seguido de dos puntos sin espacio.
- El localizador electrónico final de un libro (URL, DOI, o indicación de formato como edición para Kindle o CD-ROM) debe ir en la última posición de la entrada bibliográfica.

Formato enriquecido:

- La bibliografía puede contener texto enriquecido.
- El título principal DEBE llevar cursiva (italic); esto no es opcional. Los enlaces (links) se incluyen cuando existan URL o DOI en la fuente original.
- La cursiva puede llegar representada en el texto original mediante la etiqueta <i>...</i> o mediante la etiqueta <em>...</em>. Debes tratar ambas como equivalentes: cualquiera de las dos cuenta como cursiva ya aplicada al evaluar si un título cumple el requisito de formato Chicago.
- Si el título no tiene NINGUNA de las dos etiquetas (texto completamente plano), esto se trata como cursiva faltante y debe corregirse, nunca como una variante aceptable.
- Sin importar si la cursiva original venía en <i> o en <em>, cuando definas la sugerencia debes normalizar siempre a la etiqueta <i>...</i>. Nunca uses <em> en la sugerencia.
- Debes conservar todo el formato enriquecido válido existente.
- Debes conservar todos los enlaces válidos existentes.
- No elimines etiquetas de formato enriquecido válidas.
- No agregues información nueva mediante el formato.
- Cuando el título no tenga cursiva, debes agregarla en la sugerencia usando <i>...</i>. Esto es obligatorio, no opcional.
- Si la bibliografía cambia de orden durante la corrección, conserva el formato enriquecido asociado a cada elemento.

Clasificación:

1. correcto
   La bibliografía cumple el formato Chicago para bibliografías tradicional (año al final, no autor-fecha), el primer autor está en orden Apellido, Nombre, no tiene errores ortográficos, es estructuralmente correcta, los rangos de páginas cumplen las reglas de abreviación si existen, Y el título principal está envuelto en <i> o <em> de principio a fin. Si falta la cursiva del título, el año va justo después del autor, o el primer autor no está invertido, el estado NUNCA puede ser correcto, sin excepción.

2. puede_mejorarse
   La bibliografía sigue el estilo Chicago para bibliografías pero presenta pequeños errores de formato, puntuación, estructura o formato enriquecido que pueden corregirse sin agregar información nueva. El ejemplo más común y obligatorio de este caso es un título principal en texto plano, sin cursiva: si ese es el único problema de la entrada, el estado correcto es puede_mejorarse.

3. incorrecto
   La bibliografía debe clasificarse como incorrecta si:

- no sigue el formato Chicago para bibliografías;
- corresponde al formato Chicago de nota al pie o nota final (Footnote o Endnote) en lugar del formato Chicago para bibliografía;
- corresponde al sistema Chicago autor-fecha (lista de referencias) en lugar del formato Chicago para bibliografía tradicional;
- le faltan elementos esenciales (autor, entidad responsable, seudónimo, frase descriptiva, editor/traductor/compilador en ausencia de autor, o título);
- tiene errores graves de estructura;
- contiene errores ortográficos;
- no permite identificar adecuadamente la fuente.

Reglas obligatorias de salida:

- Devuelve exactamente tres campos: estado, sugerencia y justificacion.
- No agregues campos adicionales.
- No uses emojis.
- No uses markdown.
- No uses listas.
- No uses asteriscos ni numeración.
- No agregues texto fuera del JSON.

Reglas de contenido:

- El campo sugerencia debe contener únicamente la bibliografía corregida.

- No agregues información que no exista en la bibliografía original.

- No inventes autor, año, ciudad, editorial, DOI, URL, volumen, número ni páginas.

- Solo puedes corregir el orden, la puntuación, el formato bibliográfico, la ortografía y el formato enriquecido (italic y links) utilizando exclusivamente la información proporcionada.

- Conserva toda la información existente.

- Conserva los enlaces y el formato enriquecido válidos.

- Si el título principal no tiene cursiva (ni <i> ni <em>), debes agregarla obligatoriamente usando <i>...</i> en la sugerencia, sin modificar el contenido textual. Esta corrección es obligatoria siempre que falte, no una mejora opcional.

- Si la cursiva original viene marcada con <em>...</em>, sustitúyela por <i>...</i> en la sugerencia, conservando el texto interior sin cambios.

- La justificacion debe ser una sola oración corta.

- Si el estado es correcto:

  - sugerencia debe ser cadena vacía.
  - justificacion debe ser cadena vacía.

- Si el estado es puede_mejorarse o incorrecto:

  - la sugerencia debe ser diferente de la bibliografía original.
  - la justificacion debe explicar brevemente el problema detectado.
  - la corrección mencionada en la justificacion debe verse reflejada en la sugerencia.

- PROHIBIDO devolver sugerencia idéntica a la entrada original: compara el texto completo incluyendo etiquetas HTML. Si el estado es puede_mejorarse o incorrecto, la sugerencia DEBE contener al menos un cambio visible (orden del autor, posición del año, cursiva <i>, abreviación de páginas, puntuación, conversión desde autor-fecha o nota, etc.). Si no puedes mejorar nada, el estado debe ser correcto con sugerencia vacía; nunca puede_mejorarse ni incorrecto con sugerencia igual o vacía.

Antes de clasificar, verifica si existen errores ortográficos. Si detectas alguno, el estado debe ser incorrecto.

Antes de clasificar, verifica el formato: si el año va justo después del autor (autor-fecha) o es nota/cita, estado incorrecto y sugerencia en bibliografía tradicional.

Antes de clasificar, verifica el primer autor: debe ser Apellido, Nombre; si no, corrígelo en la sugerencia.

Antes de clasificar, si hay rangos de páginas, verifica la abreviación según las reglas; corrige en la sugerencia si no cumplen.

Antes de clasificar, ejecuta el chequeo de cursiva descrito al inicio de este prompt: identifica el título principal y confirma si está envuelto por <i> o <em> de principio a fin. Si no lo está, el estado no puede ser correcto bajo ninguna circunstancia, incluso si el resto de la entrada es perfecta.

Verificación final:

- Si la sugerencia es idéntica a la bibliografía original (incluyendo HTML), no clasifiques como puede_mejorarse ni incorrecto; reclasifica como correcto o aplica un cambio real en la sugerencia.
- Si el estado es puede_mejorarse o incorrecto, la sugerencia no puede estar vacía ni ser igual a la original.
- Si la bibliografía cumple el formato Chicago, no tiene errores ortográficos, no requiere cambios visibles, Y el título principal está en cursiva, clasifica como correcto.
- Si el título principal no está en cursiva, el estado nunca es correcto, incluso si esta fue la única razón por la que dudaste en clasificarla como tal. En ese caso el estado correcto es puede_mejorarse.

IMPORTANTE PARA EVITAR ERRORES JSON:

- Devuelve únicamente un objeto JSON válido.
- En los campos sugerencia y justificacion no uses comillas dobles para citar texto. Utiliza comillas simples cuando sea necesario.

Devuelve únicamente el JSON.
";


$userPrompt = "Bibliografía a evaluar: {$referencia_bibliografica}";

$schema = [
    'type' => 'object',
    'properties' => [
        'estado' => [
            'type' => 'string',
            'enum' => ['correcto', 'puede_mejorarse', 'incorrecto'],
            'description' => 'Estado de la bibliografía según el formato Chicago para bibliografías'
        ],
        'sugerencia' => [
            'type' => 'string',
            'description' => 'Bibliografía corregida en formato Chicago. Debe conservar el hipertexto permitido (italic y links) cuando corresponda. Cadena vacía si estado es correcto. No usar comillas dobles; usar comillas simples.'
        ],
        'justificacion' => [
            'type' => 'string',
            'description' => 'Explicación breve del problema detectado. Cadena vacía si estado es correcto. No usar comillas dobles; usar comillas simples.'
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


//Descripción institucional del campo
case 'DescInst':
    return "
VALIDACIÓN ESPECÍFICA DEL CAMPO

Campo evaluado: Descripción institucional del curso

Evalúa que el texto funcione como una descripción institucional del curso.

REGLA DE PRIORIDAD:
Antes de validar si corresponde a una descripción institucional, verifica ortografía, gramática y coherencia general.
Si el texto tiene al menos una falta ortográfica, error gramatical o incoherencia lógica, el estado debe ser incorrecto.
Esta regla tiene prioridad sobre cualquier otra clasificación.
No uses puede_mejorarse cuando exista una falta ortográfica, error gramatical o incoherencia.

Una descripción institucional válida puede:
Presentar el propósito general del curso.
Explicar de qué trata el curso.
Mencionar temas, conceptos, herramientas, preguntas guía o habilidades que se abordarán.
Incluir contexto académico, profesional, histórico, social, jurídico, económico, empresarial, técnico o disciplinar relacionado con el curso.
Explicar qué aprenderá, analizará, desarrollará o comprenderá el estudiante.
Usar preguntas introductorias o retóricas si ayudan a presentar el contenido del curso.
Mencionar brevemente la relación del curso con otros cursos, semestres o áreas de formación.

Debe verificar que:
El texto esté relacionado claramente con un curso académico.
El texto tenga tono académico, formal o institucional.
El texto sea comprensible para estudiantes, docentes y autoridades académicas.
El texto describa el contenido, propósito, enfoque o valor formativo del curso.
El texto no sea únicamente una lista aislada de temas sin redacción explicativa.
El texto no sea únicamente una explicación de evaluación, asistencia, tareas, calendario, normas de clase o metodología operativa.
El texto no sea demasiado informal, promocional, personal o ambiguo.
El texto no trate sobre un tema general sin vincularlo con un curso.

No seas excesivamente estricto:
Acepta descripciones breves si explican razonablemente el propósito o contenido del curso.
Acepta descripciones que combinen propósito, temas, habilidades y contexto.
Acepta textos que no usen literalmente la frase 'el curso', siempre que sea claro que describen una asignatura o experiencia académica.

REGLA SOBRE CORRECCIÓN:
Si el estado es incorrecto porque el texto tiene faltas ortográficas, errores gramaticales o problemas de redacción, html_corregido debe incluir el HTML corregido.

Si el estado es incorrecto porque el texto está bien escrito pero no corresponde a una descripción institucional del curso, html_corregido también debe incluir una versión sugerida del texto, manteniendo exactamente la misma estructura HTML, orientada a convertirlo en una descripción institucional válida.

La sugerencia debe conservar el tema central cuando sea posible, pero vincularlo explícitamente con un curso académico, su propósito, contenido o valor formativo.

Si no existe información suficiente para convertirlo en una descripción institucional específica sin inventar datos, redacta una versión genérica y prudente sin agregar nombres de curso, facultad, evaluaciones, bibliografía ni contenidos demasiado específicos.
";

case 'Aporte':
    return "
VALIDACIÓN ESPECÍFICA DEL CAMPO

Campo evaluado: Aportes al plan de estudios/perfil de egreso

Evalúa que el texto explique el aporte del curso al plan de estudios, a la carrera o al perfil de egreso.

REGLA DE PRIORIDAD:
Antes de validar si corresponde al campo, verifica ortografía, gramática y coherencia general.
Si el texto tiene al menos una falta ortográfica, error gramatical o incoherencia lógica, el estado debe ser incorrecto.
Esta regla tiene prioridad sobre cualquier otra clasificación.
No uses puede_mejorarse cuando exista una falta ortográfica, error gramatical o incoherencia.

Una respuesta válida puede:
Explicar por qué el curso es relevante dentro de la formación del estudiante.
Mostrar cómo el curso se conecta con otros cursos, áreas o etapas del plan de estudios.
Describir qué conocimientos, habilidades, criterios, competencias o perspectivas aporta al futuro egresado.
Justificar la importancia académica, profesional o formativa del curso.
Mencionar vínculos con el ejercicio profesional, el contexto laboral, social, disciplinar o institucional.
Relacionar el curso con objetivos del programa, competencias de carrera o perfil de egreso.
Explicar cómo el curso sirve como base, complemento, integración o aplicación de otros aprendizajes.

Debe verificar que:
El texto esté relacionado claramente con el aporte del curso dentro de un programa académico.
El texto explique la relevancia formativa del curso, no solo su contenido.
El texto conecte el curso con la carrera, el plan de estudios, el perfil de egreso o el desempeño profesional futuro.
El texto tenga tono académico, formal o institucional.
El texto sea comprensible para estudiantes, docentes y autoridades académicas.

No debe considerarse válido si:
El texto solo describe de qué trata el curso sin explicar su aporte al plan de estudios o perfil de egreso.
El texto solo enumera temas sin explicar su valor formativo.
El texto solo habla de metodología, evaluación, tareas, asistencia o normas de clase.
El texto trata sobre un tema general sin vincularlo con la formación del estudiante o la carrera.
El texto es demasiado informal, promocional, personal o ambiguo.

No seas excesivamente estricto:
Acepta textos que llamen a este campo 'justificación', siempre que expliquen la importancia formativa del curso.
Acepta textos que respondan a la pregunta '¿en qué manera este curso conecta con el resto de los cursos de la carrera?'.
Acepta textos breves si explican razonablemente el aporte del curso al programa o al futuro egresado.
Acepta textos que mencionen cursos relacionados, competencias, PLOs, objetivos del programa o aplicaciones profesionales.

REGLA SOBRE CORRECCIÓN:
Si el estado es incorrecto porque el texto tiene faltas ortográficas, errores gramaticales o problemas de redacción, html_corregido debe incluir el HTML corregido.

Si el estado es incorrecto porque el texto está bien escrito pero no corresponde al campo Aportes al plan de estudios/perfil de egreso., html_corregido también debe incluir una versión sugerida del texto, manteniendo exactamente la misma estructura HTML, orientada a explicar el aporte del curso al plan de estudios, la carrera o el perfil de egreso.

La sugerencia debe conservar el tema central cuando sea posible, pero vincularlo explícitamente con la formación del estudiante, el plan de estudios, la carrera, el perfil de egreso o el desempeño profesional futuro.

Si no existe información suficiente para redactar un aporte específico sin inventar datos, redacta una versión genérica y prudente sin agregar nombres de carrera, facultad, cursos específicos, competencias oficiales o PLOs que no estén presentes en el texto original.
";


case 'Conocimientos':
    return "
VALIDACIÓN ESPECÍFICA DEL CAMPO

Campo evaluado: conocimientos previos

Evalúa que el texto describa los conocimientos previos esperados para cursar la asignatura.

REGLA DE PRIORIDAD:
Antes de validar si corresponde al campo, verifica ortografía, gramática y coherencia general.
Si el texto tiene al menos una falta ortográfica, error gramatical o incoherencia lógica, el estado debe ser incorrecto.
Esta regla tiene prioridad sobre cualquier otra clasificación.
No uses puede_mejorarse cuando exista una falta ortográfica, error gramatical o incoherencia.

Una respuesta válida puede:
Indicar conocimientos, habilidades o bases académicas necesarias antes de cursar el curso.
Mencionar conceptos, áreas, materias o herramientas que el estudiante debería dominar previamente.
Presentarse en forma de lista o en texto continuo.
Ser breve o extensa, dependiendo del curso.
Indicar explícitamente que no se requieren conocimientos previos, usando expresiones como 'Ninguno' o equivalentes.
Incluir recomendaciones o expectativas generales de preparación académica.

Debe verificar que:
El texto esté relacionado con conocimientos previos del estudiante.
El texto tenga sentido como preparación académica para el curso.
El texto sea claro y comprensible para estudiantes y docentes.
El texto tenga un tono académico o neutro.

No debe considerarse válido si:
El texto describe el contenido del curso en lugar de conocimientos previos.
El texto habla de metodología, evaluación, tareas o normas de clase.
El texto describe objetivos del curso o resultados de aprendizaje.
El texto trata sobre un tema general sin vincularlo con preparación previa del estudiante.
El texto es demasiado informal, promocional o ambiguo.

No seas excesivamente estricto:
Acepta respuestas muy breves como 'Ninguno'.
Acepta listas de conocimientos sin redacción extensa.
Acepta textos que incluyan recomendaciones generales de preparación.
Acepta textos que mencionen cursos previos, habilidades o áreas de conocimiento.

REGLA SOBRE CORRECCIÓN:
Si el estado es incorrecto porque el texto tiene faltas ortográficas, errores gramaticales o problemas de redacción, html_corregido debe incluir el HTML corregido.

Si el estado es incorrecto porque el texto está bien escrito pero no corresponde al campo conocimientos previos, html_corregido también debe incluir una versión sugerida del texto, manteniendo exactamente la misma estructura HTML, orientada a describir los conocimientos previos esperados.

La sugerencia debe mantener el nivel de especificidad del texto original cuando sea posible.

Si no existe información suficiente para redactar conocimientos previos específicos sin inventar datos, redacta una versión genérica y prudente como 'No se requieren conocimientos previos específicos para cursar esta asignatura'.
";


case 'Marco':
    return "
VALIDACIÓN ESPECÍFICA DEL CAMPO

Campo evaluado: Marco normativo institucional

Evalúa que el texto describa las normas, reglas o lineamientos aplicables al desarrollo del curso.

REGLA DE PRIORIDAD:
Antes de validar si corresponde al campo, verifica ortografía, gramática y coherencia general.
Si el texto tiene al menos una falta ortográfica, error gramatical o incoherencia lógica, el estado debe ser incorrecto.
Esta regla tiene prioridad sobre cualquier otra clasificación.
No uses puede_mejorarse cuando exista una falta ortográfica, error gramatical o incoherencia.

Una respuesta válida puede:
Describir normas de conducta, disciplina o comportamiento dentro del curso.
Incluir reglas de asistencia, puntualidad, participación o uso de dispositivos.
Mencionar políticas de honestidad académica, plagio o integridad.
Referirse a reglamentos institucionales de la universidad o facultad.
Estar redactada en forma de lista, numeración o texto continuo.
Incluir consecuencias o sanciones relacionadas con el incumplimiento de normas.
Combinar normas institucionales generales con reglas específicas del curso.

Debe verificar que:
El texto esté relacionado con normas, reglas o lineamientos del curso.
El texto tenga un enfoque normativo o regulatorio, no académico.
El texto sea claro y comprensible para estudiantes y docentes.
El texto tenga tono formal o institucional.

No debe considerarse válido si:
El texto describe el contenido del curso.
El texto explica objetivos, competencias o resultados de aprendizaje.
El texto describe metodología de enseñanza sin relación con normas.
El texto trata sobre temas generales sin relación con reglas o lineamientos.
El texto es demasiado informal, promocional o ambiguo.

No seas excesivamente estricto:
Acepta textos extensos o detallados con múltiples reglas.
Acepta listas, numeraciones o párrafos largos.
Acepta referencias a reglamentos institucionales sin explicarlos completamente.
Acepta redacción mixta entre normas institucionales y reglas del curso.

REGLA SOBRE CORRECCIÓN:
Si el estado es incorrecto porque el texto tiene faltas ortográficas, errores gramaticales o problemas de redacción, html_corregido debe incluir el HTML corregido.

Si el estado es incorrecto porque el texto está bien escrito pero no corresponde al campo Marco normativo institucional, html_corregido también debe incluir una versión sugerida del texto, manteniendo exactamente la misma estructura HTML, orientada a describir normas institucionales o reglas del curso.

La sugerencia debe mantener el nivel de formalidad del texto original.

Si no existe información suficiente para redactar normas específicas sin inventar datos, redacta una versión genérica y prudente que incluya lineamientos básicos como respeto, integridad académica y cumplimiento de reglamentos institucionales.
";


///Normas y reglas operativas
/*
case 'Normas':
    return "
VALIDACIÓN ESPECÍFICA DEL CAMPO

Campo evaluado: Normas y reglas operativas del curso

Evalúa que el texto describa las reglas operativas que aplican al desarrollo del curso, tales como asistencia, entregas, puntualidad, participación, conducta, uso de dispositivos, reposiciones, evaluaciones, comunicación, honestidad académica y otras disposiciones definidas por el catedrático.

REGLA DE PRIORIDAD:
Antes de validar si corresponde al campo, verifica ortografía, gramática y coherencia general.
Si el texto tiene al menos una falta ortográfica, error gramatical o incoherencia lógica, el estado debe ser incorrecto.
Esta regla tiene prioridad sobre cualquier otra clasificación.
No uses puede_mejorarse cuando exista una falta ortográfica, error gramatical o incoherencia.

Una respuesta válida puede:
Describir políticas de asistencia, puntualidad o participación.
Incluir reglas para entrega de tareas, proyectos, laboratorios o actividades.
Indicar penalizaciones por retrasos o incumplimientos.
Definir normas de conducta y convivencia dentro o fuera del aula.
Regular el uso de dispositivos electrónicos durante el curso.
Establecer criterios para reposiciones de exámenes, actividades o evaluaciones.
Mencionar expectativas de comunicación con el catedrático.
Incluir lineamientos de honestidad académica, plagio o uso adecuado de recursos.
Estar redactada en forma de lista, numeración o texto continuo.
Combinar varias reglas operativas dentro de un mismo texto.

Debe verificar que:
El texto esté relacionado con normas o reglas aplicables al funcionamiento diario del curso.
El texto tenga un enfoque operativo y práctico para estudiantes.
El texto sea claro y comprensible.
El texto tenga tono formal, académico o institucional.

No debe considerarse válido si:
El texto describe únicamente contenidos temáticos del curso.
El texto explica objetivos, competencias o resultados de aprendizaje.
El texto describe metodología de enseñanza sin establecer reglas concretas.
El texto trata temas generales sin indicar normas o lineamientos de comportamiento o funcionamiento.
El texto es demasiado informal, ambiguo o promocional.
El texto contiene únicamente información administrativa sin establecer reglas operativas.

No seas excesivamente estricto:
Acepta textos breves si establecen claramente una o más reglas.
Acepta textos extensos con múltiples lineamientos.
Acepta listas, numeraciones o párrafos largos.
Acepta combinaciones de normas académicas, operativas y de conducta.
Acepta referencias a reglamentos institucionales cuando se relacionen con el funcionamiento del curso.
No exijas que aparezcan todos los elementos posibles; basta con que el texto describa una o más reglas operativas de forma clara.

REGLA SOBRE CORRECCIÓN:
Si el estado es incorrecto porque el texto tiene faltas ortográficas, errores gramaticales o problemas de redacción, html_corregido debe incluir el HTML corregido.

Si el estado es incorrecto porque el texto está bien escrito pero no corresponde al campo Normas y reglas operativas del curso, html_corregido también debe incluir una versión sugerida del texto, manteniendo exactamente la misma estructura HTML, orientada a describir reglas operativas del curso.

La sugerencia puede incluir aspectos como asistencia, puntualidad, entregas, participación, conducta, uso de dispositivos, reposiciones y honestidad académica.

La sugerencia debe mantener el nivel de formalidad del texto original.

Si no existe información suficiente para redactar reglas específicas sin inventar datos, redacta una versión genérica y prudente que incluya lineamientos básicos sobre asistencia, respeto, cumplimiento de actividades académicas, honestidad académica y seguimiento de instrucciones del curso.
";*/

case 'Normas':
    return "
VALIDACIÓN ESPECÍFICA DEL CAMPO

Campo evaluado: Normas y reglas operativas del curso

Evalúa que el texto describa reglas o lineamientos aplicables al funcionamiento del curso.

REGLA DE PRIORIDAD:
Antes de validar si corresponde al campo, verifica ortografía, gramática y coherencia general.
Si el texto tiene al menos una falta ortográfica, error gramatical o incoherencia lógica, el estado debe ser incorrecto.
Esta regla tiene prioridad sobre cualquier otra clasificación.
No uses puede_mejorarse cuando exista una falta ortográfica, error gramatical o incoherencia.

Una respuesta válida puede:
Describir reglas de asistencia, puntualidad o participación.
Incluir normas sobre entregas, evaluaciones o reposiciones.
Definir expectativas de conducta y respeto.
Regular el uso de dispositivos electrónicos.
Mencionar honestidad académica o prevención del plagio.
Estar redactada como lista, numeración o texto continuo.

Debe verificar que:
El texto describa normas, reglas o lineamientos aplicables al curso.
El texto tenga un enfoque práctico u operativo.
El texto sea claro y comprensible.

No debe considerarse válido si:
El texto describe únicamente contenidos del curso.
El texto explica objetivos, competencias o resultados de aprendizaje.
El texto desarrolla metodología sin establecer reglas concretas.
El texto no contiene normas ni lineamientos para los estudiantes.

No seas excesivamente estricto:
Acepta textos breves.
Acepta que se mencione una sola regla operativa.
No exijas que aparezcan todos los temas posibles.
Acepta combinaciones de normas académicas, operativas y de conducta.

REGLA SOBRE CORRECCIÓN:
Si el estado es incorrecto porque el texto tiene faltas ortográficas, errores gramaticales o problemas de redacción, html_corregido debe incluir el HTML corregido.

Si el estado es incorrecto porque el texto está bien escrito pero no corresponde al campo, html_corregido también debe incluir una versión sugerida del texto, manteniendo exactamente la misma estructura HTML, orientada a describir normas o reglas operativas del curso.

La sugerencia debe mantener el nivel de formalidad del texto original.

Si no existe información suficiente para redactar reglas específicas sin inventar datos, redacta una versión genérica y prudente que incluya asistencia, respeto, cumplimiento de actividades académicas y honestidad académica.
";

case 'UsoIA':
    return "
VALIDACIÓN ESPECÍFICA DEL CAMPO

Campo evaluado: Uso de IA en el curso

Evalúa que el texto describa de forma clara las políticas del catedrático sobre el uso de inteligencia artificial en el curso, incluyendo cuando aplique: si está permitido o prohibido, condiciones de uso, citación o declaración de uso, y límites o restricciones.

REGLA DE PRIORIDAD:
Antes de validar si corresponde al campo, verifica ortografía, gramática y coherencia general.
Si el texto tiene al menos una falta ortográfica, error gramatical o incoherencia lógica, el estado debe ser incorrecto.
Esta regla tiene prioridad sobre cualquier otra clasificación.
No uses puede_mejorarse cuando exista una falta ortográfica, error gramatical o incoherencia.

Una respuesta válida puede:
Indicar si el uso de IA está permitido, prohibido o permitido con condiciones.
Establecer límites sobre tareas, exámenes, proyectos, ensayos o actividades evaluables.
Exigir citación, declaración o transparencia cuando se haya usado IA.
Diferenciar usos permitidos y no permitidos dentro del mismo curso.
Mencionar herramientas, tipos de asistencia o contextos específicos.
Estar redactada como lista, numeración o texto continuo.

Debe verificar que:
El texto esté relacionado con el uso de inteligencia artificial en el curso.
El texto sea claro y comprensible para estudiantes y docentes.
El texto tenga tono formal, académico o institucional.
El texto establezca lineamientos prácticos para el estudiante.

No debe considerarse válido si:
El texto describe únicamente contenidos del curso sin mencionar IA.
El texto explica metodología general sin establecer políticas sobre IA.
El texto trata sobre tecnología en general sin vincularlo con el uso de IA en el curso.
El texto no contiene lineamientos, restricciones o condiciones sobre IA.

No seas excesivamente estricto:
Acepta textos breves si establecen claramente la política de uso de IA.
No exijas que aparezcan todos los elementos posibles; basta con que el texto indique con claridad la postura del curso respecto a la IA.
Acepta políticas restrictivas, permisivas o mixtas.

REGLA SOBRE CORRECCIÓN:
Si el estado es incorrecto porque el texto tiene faltas ortográficas, errores gramaticales o problemas de redacción, html_corregido debe incluir el HTML corregido.

Si el estado es incorrecto porque el texto está bien escrito pero no corresponde al campo, html_corregido también debe incluir una versión sugerida del texto, manteniendo exactamente la misma estructura HTML, orientada a describir la política de uso de IA en el curso.

La sugerencia puede incluir permisos, prohibiciones, citación, límites y condiciones de uso.

Si no existe información suficiente para redactar una política específica sin inventar datos, redacta una versión genérica y prudente que indique que el estudiante debe consultar al catedrático y cumplir con integridad académica al usar herramientas de IA.
";

case 'PensamientoCritico':
    return "
VALIDACIÓN ESPECÍFICA DEL CAMPO

Campo evaluado: Desarrollo del pensamiento crítico

Evalúa que el texto describa cómo el catedrático promoverá el pensamiento crítico en el curso, incluyendo estrategias pedagógicas y evidencias o formas de demostrar ese desarrollo.

REGLA DE PRIORIDAD:
Antes de validar si corresponde al campo, verifica ortografía, gramática y coherencia general.
Si el texto tiene al menos una falta ortográfica, error gramatical o incoherencia lógica, el estado debe ser incorrecto.
Esta regla tiene prioridad sobre cualquier otra clasificación.
No uses puede_mejorarse cuando exista una falta ortográfica, error gramatical o incoherencia.

Una respuesta válida puede:
Describir estrategias para fomentar análisis, argumentación, reflexión o evaluación de información.
Mencionar actividades, metodologías, debates, casos, proyectos o evaluaciones que promuevan pensamiento crítico.
Indicar evidencias o productos que demuestren el desarrollo del pensamiento crítico del estudiante.
Relacionar el desarrollo del pensamiento crítico con objetivos, competencias o aprendizajes del curso.
Estar redactada como lista, numeración o texto continuo.

Debe verificar que:
El texto esté relacionado con el desarrollo del pensamiento crítico en el curso.
El texto describa estrategias, acciones o evidencias, no solo un concepto general.
El texto sea claro y comprensible para estudiantes y docentes.
El texto tenga tono académico o institucional.

No debe considerarse válido si:
El texto describe únicamente contenidos temáticos del curso sin estrategias de pensamiento crítico.
El texto habla solo de evaluación, asistencia o normas sin vincularlas al pensamiento crítico.
El texto es demasiado genérico y no explica cómo se desarrollará el pensamiento crítico en el curso.
El texto no contiene estrategias ni evidencias relacionadas con pensamiento crítico.

No seas excesivamente estricto:
Acepta textos breves si explican razonablemente estrategias y evidencias.
No exijas que aparezcan todos los elementos posibles; basta con que el texto describa con claridad cómo se promoverá el pensamiento crítico.
Acepta combinaciones de estrategias pedagógicas y evidencias de aprendizaje.

REGLA SOBRE CORRECCIÓN:
Si el estado es incorrecto porque el texto tiene faltas ortográficas, errores gramaticales o problemas de redacción, html_corregido debe incluir el HTML corregido.

Si el estado es incorrecto porque el texto está bien escrito pero no corresponde al campo, html_corregido también debe incluir una versión sugerida del texto, manteniendo exactamente la misma estructura HTML, orientada a describir estrategias y evidencias para el desarrollo del pensamiento crítico.

Si no existe información suficiente para redactar estrategias específicas sin inventar datos, redacta una versión genérica y prudente que mencione análisis, reflexión, argumentación y evaluación de información como ejes del pensamiento crítico en el curso.
";

        default:
            return "
VALIDACIÓN ESPECÍFICA DEL CAMPO

Campo evaluado: {$nombreCampo}

No hay reglas específicas adicionales para este campo.
";


    }
}





/**
 * Valida coherencia y ortografía de un texto HTML usando LLM
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
        throw new Exception('El texto está vacío');
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



// Este es el que funciona en intranet html4 , pero para el de MiU con summernote envia html5, por tanto este prompt no se usa pero lo  dejo por documentacion
$systemPromptBaseOldHTML4 = "Actúa como un experto en redacción académica y corrección de textos.

Recibirás un texto en formato HTML OBSOLETO (HTML4). Tu tarea es:

1. Evaluar la coherencia del texto (que tenga sentido lógico y esté bien estructurado)
2. Detectar errores ortográficos y gramaticales
3. Si hay correcciones necesarias, devolver el HTML corregido

REGLAS CRÍTICAS DE COMPATIBILIDAD HTML

IMPORTANTE: El HTML usa etiquetas OBSOLETAS de HTML4. NO las modernices.

1. PRESERVAR ETIQUETAS OBSOLETAS (aunque sean antiguas):
   Mantén <div> exactamente como está (NO cambiar a <p>)
   Mantén <b> exactamente como está (NO cambiar a <strong>)
   Mantén <i> exactamente como está (NO cambiar a <em>)
   Mantén <u> exactamente como está (NO eliminar)
   Mantén <font color=\"...\"> exactamente (NO cambiar a style)
   Mantén <font face=\"...\"> exactamente (NO cambiar a style)
   Mantén <font size=\"...\"> exactamente (NO cambiar a style)
   Mantén <blockquote> exactamente (NO eliminar)
   Mantén <ol>, <ul>, <li> exactamente
   Mantén <table>, <tr>, <td> con todos sus atributos
   Mantén <a href=\"...\"> exactamente
   Mantén <br> y <hr> exactamente

2. PRESERVAR ATRIBUTOS OBSOLETOS:
   Mantén align=\"left|center|right|justify\" (NO cambiar a style)
   Mantén color=\"#...\" en <font>
   Mantén face=\"...\" en <font>
   Mantén size=\"1-7\" en <font>
   Mantén valign, width, cellspacing, cellpadding, border
   Mantén TODOS los atributos exactamente como están

3. PRESERVAR ESTRUCTURA EXACTA:
   Mantén el anidamiento exacto de todas las etiquetas
   Mantén <div><br></div> para líneas vacías (NO eliminar)
   Mantén <div><b><br></b></div> o similares (NO simplificar)
   Mantén &nbsp; exactamente donde están
   Mantén todas las entidades HTML (&nbsp;, &amp;, &lt;, &gt;, etc.)
   NO agregues ni elimines etiquetas
   NO cambies el orden de anidamiento
   NO elimines <div> vacíos o con solo <br>

4. SOLO CORRIGE EL TEXTO VISIBLE:
   Corrige ortografía dentro del contenido textual
   Corrige gramática y puntuación
   Mejora redacción sin cambiar significado
   Mejora coherencia del texto
   Corrige acentos y tildes
   Corrige mayúsculas y minúsculas cuando sea necesario

5. PROHIBIDO HACER:
   NO modernices el HTML (NO usar <p>, <strong>, <em>, <span>)
   NO agregues atributos style=\"...\" donde no existan
   NO cambies etiquetas obsoletas por modernas
   NO elimines etiquetas vacías como <div><br></div>
   NO simplifiques la estructura HTML
   NO cambies el formato visual del texto
   NO agregues clases CSS
   NO elimines espacios &nbsp;
   NO cambies <b> por <strong>, <i> por <em>, <u> por <span>
   NO cambies <font> por <span style>
   NO cambies align por style

6. VALIDACIÓN DE SALIDA:
   El HTML corregido debe tener EXACTAMENTE las mismas etiquetas que el original
   Solo el texto dentro de las etiquetas debe cambiar
   La cantidad de <div>, <b>, <i>, <u>, <font>, etc. debe ser idéntica
   Los atributos obsoletos deben mantenerse idénticos
   El anidamiento debe ser idéntico
   Las líneas vacías <div><br></div> deben mantenerse

CRITERIOS DE EVALUACIÓN

Clasifica el texto en:
correcto: El texto es coherente, tiene sentido lógico y NO tiene errores ortográficos
puede_mejorarse: Solo usar si el texto es válido pero la redacción podría ser más clara o precisa, SIN errores ortográficos
incorrecto: Debe marcarse como incorrecto si tiene al menos un error ortográfico, errores gramaticales o es incoherente


REGLAS ESTRICTAS DE CLASIFICACIÓN:

Si el texto contiene al menos un error ortográfico:
  El estado DEBE ser incorrecto
  NO puede ser puede_mejorarse
  NO puede ser correcto

Si el texto es incoherente o no tiene sentido lógico:
  El estado DEBE ser incorrecto

Si el texto tiene errores gramaticales graves:
  El estado DEBE ser incorrecto

Solo usa puede_mejorarse cuando:
  El texto NO tiene errores ortográficos
  El texto es coherente
  Pero la redacción podría mejorar en claridad o precisión

REGLAS DE SALIDA

Debes devolver exactamente tres campos: estado, explicacion y html_corregido
No agregues campos adicionales
No uses emojis
No uses markdown
No uses listas en la explicación
No uses asteriscos ni numeración

Reglas de contenido:

El campo explicacion debe ser una o dos oraciones cortas y claras
La explicacion debe ser objetiva y directa
No uses comillas dobles dentro de la explicacion, usa comillas simples si es necesario

El campo html_corregido debe contener el HTML corregido completo
Si no hay correcciones, html_corregido debe ser cadena vacía

Si el estado es correcto:
  html_corregido debe ser cadena vacía
  explicacion debe ser cadena vacía

Si el estado es puede_mejorarse o incorrecto:
  html_corregido debe contener el HTML con las correcciones aplicadas
  explicacion debe explicar brevemente los problemas encontrados
  Las correcciones mencionadas en la explicacion deben verse reflejadas en html_corregido

IMPORTANTE PARA EVITAR ERRORES JSON:
Escapa correctamente las comillas dobles dentro del HTML: usa \\\" 
No uses saltos de línea literales en el JSON, usa \\n si es necesario
No uses caracteres especiales sin escapar
El HTML debe ser una cadena JSON válida

IMPORTANTE PARA EVITAR ERRORES JSON - REGLAS ESTRICTAS:

1. ESCAPAR COMILLAS EN HTML:
   Dentro del campo html_corregido, TODAS las comillas dobles deben escaparse con barra invertida
   Ejemplo CORRECTO en JSON: \"<div>texto con \\\"comillas\\\" aquí</div>\"
   Ejemplo INCORRECTO: \"<div>texto con \"comillas\" aquí</div>\"

2. NO USAR COMILLAS DOBLES EN EXPLICACION:
   En el campo explicacion, NO uses comillas dobles para citar palabras
   Usa comillas simples en su lugar
   Ejemplo CORRECTO: Errores ortográficos: 'intelectualess', 'formacción'

3. CARACTERES ESPECIALES:
   Escapa barras invertidas: \\\\
   No uses saltos de línea literales, usa \\n
   No uses caracteres especiales sin escapar

4. VALIDACIÓN ANTES DE RESPONDER:
   Verifica mentalmente que todas las comillas dobles dentro de strings estén escapadas con \\\"
   Verifica que no haya comillas dobles sin escapar en explicacion
   Verifica que el JSON sea sintácticamente válido

Verificación final antes de responder:

Compara el HTML original con el html_corregido que planeas devolver
Verifica que TODAS las etiquetas HTML sean idénticas
Verifica que TODOS los atributos sean idénticos
Verifica que solo el texto dentro de las etiquetas haya cambiado
Si agregaste, eliminaste o modificaste alguna etiqueta HTML, corrígelo antes de responder

RECUERDA: Tu objetivo es corregir el TEXTO, no el CÓDIGO HTML. Actúa como un corrector ortográfico que trabaja dentro del HTML sin tocar las etiquetas.

Devuelve únicamente el JSON.";



//Este el prompt actual
$systemPromptBase = "Actúa como un experto en redacción académica y corrección de textos.

Recibirás un texto en formato HTML. Tu tarea es:

1. Evaluar la coherencia del texto (que tenga sentido lógico y esté bien estructurado)
2. Detectar errores ortográficos y gramaticales
3. Si hay correcciones necesarias, devolver el HTML corregido

REGLAS CRÍTICAS DE PRESERVACIÓN HTML

IMPORTANTE: El HTML debe conservar su estructura y formato visual. NO lo modernices ni lo transformes.

1. PRESERVAR ETIQUETAS Y ESTRUCTURA:
   Mantén exactamente las mismas etiquetas HTML que existan en el original
   Mantén el anidamiento exacto de todas las etiquetas
   No agregues ni elimines etiquetas
   No cambies el orden de anidamiento
   No cambies el formato visual del texto

2. PRESERVAR ATRIBUTOS:
   Mantén todos los atributos exactamente como están
   No agregues atributos style=\"...\" donde no existan
   No elimines atributos existentes
   No cambies valores de atributos
   No agregues clases CSS

3. PRESERVAR CONTENIDO NO TEXTUAL:
   Mantén todas las entidades HTML (&nbsp;, &amp;, &lt;, &gt;, etc.) exactamente como están
   No elimines espacios &nbsp;
   No elimines etiquetas vacías si forman parte de la estructura original
   No simplifiques la estructura HTML

4. SOLO CORRIGE EL TEXTO VISIBLE:
   Corrige ortografía dentro del contenido textual
   Corrige gramática y puntuación
   Mejora redacción sin cambiar significado
   Mejora coherencia del texto
   Corrige acentos y tildes
   Corrige mayúsculas y minúsculas cuando sea necesario

5. PROHIBIDO HACER:
   No modernices el HTML
   No cambies etiquetas por otras distintas
   No elimines ni reestructures contenedores
   No cambies el formato visual del texto
   No cambies el estilo de maquetación existente
   No alteres el contenido HTML fuera del texto visible

6. VALIDACIÓN DE SALIDA:
   El HTML corregido debe conservar la misma estructura del original
   Solo el texto dentro de las etiquetas debe cambiar
   La cantidad de etiquetas debe mantenerse
   Los atributos deben mantenerse
   El anidamiento debe ser idéntico

CRITERIOS DE EVALUACIÓN

Clasifica el texto en:
correcto: El texto es coherente, tiene sentido lógico y NO tiene errores ortográficos
puede_mejorarse: Solo usar si el texto es válido pero la redacción podría ser más clara o precisa, SIN errores ortográficos
incorrecto: Debe marcarse como incorrecto si tiene al menos un error ortográfico, errores gramaticales o es incoherente

REGLAS ESTRICTAS DE CLASIFICACIÓN:

Si el texto contiene al menos un error ortográfico:
  El estado DEBE ser incorrecto
  NO puede ser puede_mejorarse
  NO puede ser correcto

Si el texto es incoherente o no tiene sentido lógico:
  El estado DEBE ser incorrecto

Si el texto tiene errores gramaticales graves:
  El estado DEBE ser incorrecto

Solo usa puede_mejorarse cuando:
  El texto NO tiene errores ortográficos
  El texto es coherente
  Pero la redacción podría mejorar en claridad o precisión

REGLAS DE SALIDA

Debes devolver exactamente tres campos: estado, explicacion y html_corregido
No agregues campos adicionales
No uses emojis
No uses markdown
No uses listas en la explicación
No uses asteriscos ni numeración

Reglas de contenido:

El campo explicacion debe ser una o dos oraciones cortas y claras
La explicacion debe ser objetiva y directa
No uses comillas dobles dentro de la explicacion, usa comillas simples si es necesario

El campo html_corregido debe contener el HTML corregido completo
Si no hay correcciones, html_corregido debe ser cadena vacía

Si el estado es correcto:
  html_corregido debe ser cadena vacía
  explicacion debe ser cadena vacía

Si el estado es puede_mejorarse o incorrecto:
  html_corregido debe contener el HTML con las correcciones aplicadas
  explicacion debe explicar brevemente los problemas encontrados
  Las correcciones mencionadas en la explicacion deben verse reflejadas en html_corregido

IMPORTANTE PARA EVITAR ERRORES JSON:
Escapa correctamente las comillas dobles dentro del HTML: usa \\\" 
No uses saltos de línea literales en el JSON, usa \\n si es necesario
No uses caracteres especiales sin escapar
El HTML debe ser una cadena JSON válida

IMPORTANTE PARA EVITAR ERRORES JSON - REGLAS ESTRICTAS:

1. ESCAPAR COMILLAS EN HTML:
   Dentro del campo html_corregido, TODAS las comillas dobles deben escaparse con barra invertida
   Ejemplo CORRECTO en JSON: \"<div>texto con \\\"comillas\\\" aquí</div>\"
   Ejemplo INCORRECTO: \"<div>texto con \"comillas\" aquí</div>\"

2. NO USAR COMILLAS DOBLES EN EXPLICACION:
   En el campo explicacion, NO uses comillas dobles para citar palabras
   Usa comillas simples en su lugar
   Ejemplo CORRECTO: Errores ortográficos: 'intelectualess', 'formacción'

3. CARACTERES ESPECIALES:
   Escapa barras invertidas: \\\\
   No uses saltos de línea literales, usa \\n
   No uses caracteres especiales sin escapar

4. VALIDACIÓN ANTES DE RESPONDER:
   Verifica mentalmente que todas las comillas dobles dentro de strings estén escapadas con \\\" 
   Verifica que no haya comillas dobles sin escapar en explicacion
   Verifica que el JSON sea sintácticamente válido

Verificación final antes de responder:

Compara el HTML original con el html_corregido que planeas devolver
Verifica que TODAS las etiquetas HTML sean idénticas
Verifica que TODOS los atributos sean idénticos
Verifica que solo el texto dentro de las etiquetas haya cambiado
Si agregaste, eliminaste o modificaste alguna etiqueta HTML, corrígelo antes de responder

RECUERDA: Tu objetivo es corregir el TEXTO, no el CÓDIGO HTML. Actúa como un corrector ortográfico que trabaja dentro del HTML sin tocar las etiquetas.

Devuelve únicamente el JSON.";

    $systemPromptCampo = obtener_prompt_validacion_campo($nombreCampo);
    $systemPrompt = $systemPromptBase . "\n\n" . $systemPromptCampo;


    
    $userPrompt = "HTML a evaluar:\n\n{$textoHTML}";


$schema = [
    'type' => 'object',
    'properties' => [
        'estado' => [
            'type' => 'string',
            'enum' => ['correcto', 'puede_mejorarse', 'incorrecto'],
            'description' => 'Estado de la validación'
        ],
        'explicacion' => [
            'type' => 'string',
            'description' => 'Explicación breve del problema. Cadena vacía si estado es correcto. No usar comillas dobles.'
        ],
        'html_corregido' => [
            'type' => 'string',
            'description' => 'HTML corregido preservando exactamente la estructura original. Cadena vacía si estado es correcto. Escapar comillas dobles con \\\"'
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



function formatear_resultados_aprendizaje_para_prompt($arrRA)
{
    if (!is_array($arrRA) || count($arrRA) === 0) {
        return 'No hay resultados de aprendizaje definidos por la unidad academica.';
    }

    $arrLineas = [];
    $intNum = 1;
    foreach ($arrRA as $ra) {
        if (!is_array($ra)) {
            continue;
        }
        $strBloom = trim((string)($ra['BLOOM'] ?? $ra['bloom'] ?? ''));
        $strDesc  = trim((string)($ra['DESC'] ?? $ra['desc'] ?? $ra['DESCRIPCION_RA'] ?? ''));
        if ($strDesc === '') {
            continue;
        }
        $strLinea = $intNum . '. ';
        if ($strBloom !== '') {
            $strLinea .= '[' . $strBloom . '] ';
        }
        $strLinea .= $strDesc;
        $arrLineas[] = $strLinea;
        $intNum++;
    }

    if (count($arrLineas) === 0) {
        return 'No hay resultados de aprendizaje definidos por la unidad academica.';
    }

    return implode("\n", $arrLineas);
}


function evaluar_experiencia_principal_json($descripcion, $arrRA, $maxTokens = 400, $temperature = 0.0)
{
    $descripcion = trim((string) $descripcion);

    if ($descripcion === '') {
        throw new Exception('Debe enviar la descripcion de la experiencia');
    }

    $strListaRA = formatear_resultados_aprendizaje_para_prompt($arrRA);

    $systemPrompt = "Actua como experto en diseno instruccional universitario en espanol.

Tu tarea es evaluar si una experiencia principal del syllabus del catedratico responde adecuadamente la pregunta:
Que hara el estudiante para lograr los Resultados de aprendizaje del curso?

Contexto:
- Los Resultados de aprendizaje fueron definidos por la unidad academica y no pueden cambiarse en esta seccion.
- La experiencia debe describir acciones, actividades o experiencias que realizara el estudiante.
- Debe ser coherente con al menos uno de los Resultados de aprendizaje proporcionados, cuando existan.
- Debe redactarse en espanol academico, con ortografia correcta.

Criterios de evaluacion:
1) Pertinencia: el texto describe que hara el estudiante, no solo que aprendera el docente ni contenidos abstractos sin accion estudiantil.
2) Coherencia general: en conjunto, la experiencia debe poder relacionarse de forma razonable con los Resultados de aprendizaje del curso. No es necesario identificar cuales ni cuantos; solo verificar vinculacion general cuando la lista no esta vacia.
3) Claridad: debe ser comprensible, concreta y util como experiencia principal del curso.
4) Ortografia y redaccion: detecta errores ortograficos, tildes y redaccion confusa.

Clasificacion:
1. correcto
La experiencia describe con claridad acciones del estudiante, es coherente con al menos un Resultado de aprendizaje de la lista (si hay lista), no tiene errores ortograficos y responde la pregunta guia.

2. puede_mejorarse
La experiencia es valida en general pero puede ser mas especifica, vincularse mejor con los Resultados de aprendizaje, o mejorar redaccion sin errores ortograficos graves.

3. incorrecto
Marcar como incorrecto si:
- no describe acciones del estudiante
- no responde la pregunta guia
- es incoherente, ambigua o demasiado generica para ser una experiencia principal
- no se relaciona razonablemente con ningun Resultado de aprendizaje cuando la lista no esta vacia
- contiene al menos un error ortografico
- describe solo evaluacion, normas, contenidos del docente o temas sin accion estudiantil

Reglas obligatorias de salida:
- Debes devolver exactamente tres campos: estado, sugerencia y justificacion
- No agregues campos adicionales
- No indiques que Resultados de aprendizaje especificos estan vinculados
- No uses emojis, markdown, listas, asteriscos ni numeracion fuera del JSON
- sugerencia: solo el texto corregido o mejorado de la experiencia. Cadena vacia si estado es correcto.
- justificacion: una oracion corta explicando el problema. Cadena vacia si estado es correcto.
- No inventes Resultados de aprendizaje que no esten en la lista proporcionada.
- No uses comillas dobles dentro de los valores; usa comillas simples si necesitas citar.

Antes de clasificar, revisa ortografia. Si hay al menos un error ortografico, clasifica como incorrecto.

Devuelve unicamente el JSON.";

    $userPrompt = "Resultados de aprendizaje del curso (definidos por la unidad academica):\n"
        . $strListaRA
        . "\n\nExperiencia principal a evaluar:\n"
        . $descripcion;

    $schema = [
        'type' => 'object',
        'properties' => [
            'estado' => [
                'type' => 'string',
                'enum' => ['correcto', 'puede_mejorarse', 'incorrecto'],
                'description' => 'Estado de la experiencia: correcto, puede_mejorarse o incorrecto'
            ],
            'sugerencia' => [
                'type' => 'string',
                'description' => 'Experiencia corregida o mejorada. Cadena vacia si estado es correcto.'
            ],
            'justificacion' => [
                'type' => 'string',
                'description' => 'Explicacion breve del problema. Cadena vacia si estado es correcto.'
            ],
        ],
        'required' => ['estado', 'sugerencia', 'justificacion'],
        'additionalProperties' => false,
    ];

    return AWSConfigBedrock::llm_generate_json_cached(
        $systemPrompt,
        $userPrompt,
        $schema,
        (int) $maxTokens,
        (float) $temperature
    );
}


function validar_ortografia_texto_plano($texto, $contextoCampo = 'Actividad de aprendizaje', $maxTokens = 150, $temperature = 0.0)
{
    $texto = trim((string) $texto);
    if ($texto === '') {
        throw new Exception('Debe enviar el texto a validar');
    }

    $systemPrompt = "Actúa como corrector de textos académicos breves en español.

Tu tarea es evaluar si el valor del campo es adecuado como NOMBRE de una actividad o rúbrica de evaluación de un curso, y además detectar errores ortográficos.

Campo evaluado: {$contextoCampo}

Criterios de evaluación:
1) Ortografía: detecta errores de ortografía, tildes, mayúsculas, signos y separación correcta de palabras.
2) Coherencia nominal: verifica que el texto tenga sentido como nombre breve de una actividad o rúbrica de evaluación.
3) No evalúes redacción extensa, estilo literario ni pertinencia pedagógica profunda.
4) Si el texto es incoherente, ambiguo, demasiado largo, no corresponde a un nombre de rúbrica o actividad, o mezcla conceptos extraños, marca estado incorrecto aunque esté bien escrito.
5) Si el texto contiene una frase nominal breve y coherente, y no tiene errores ortográficos, marca estado correcto.

Sugerencias:
- Solo proporciona sugerencia si existe una corrección clara, concreta y útil.
- La sugerencia debe contener únicamente el texto corregido.
- No incluyas explicaciones, etiquetas, comillas ni texto adicional dentro de sugerencia.
- Si no es posible dar una sugerencia válida usando únicamente la información disponible, deja sugerencia como cadena vacía.
- Si el estado es correcto, sugerencia debe ser cadena vacía.

Reglas de salida:
- Devuelve únicamente un objeto JSON válido.
- No agregues texto fuera del JSON.
- No uses markdown.
- No uses listas.
- No uses comentarios.
- No uses comillas dobles dentro de los valores de texto si puedes evitarlo; usa comillas simples cuando necesites citar algo dentro de justificacion.
- El JSON debe ser válido y parseable.
- Usa exactamente los campos solicitados.
- Si estado = correcto, justificacion debe ser cadena vacía.
- Si estado = incorrecto, justificacion debe ser breve y concreta.

Importante:
- Si hay al menos un error ortográfico, marca estado incorrecto.
- Si el texto no funciona como nombre breve de una actividad o rúbrica, marca estado incorrecto.
- Si no hay una corrección clara que valga la pena, deja sugerencia vacía.
- Si no puedes garantizar una sugerencia correcta y visible, no la inventes.";

    $userPrompt = "Texto a evaluar: {$texto}";

    $schema = [
        'type' => 'object',
        'properties' => [
            'estado' => [
                'type' => 'string',
                'enum' => ['correcto', 'incorrecto']
            ],
            'sugerencia' => [
                'type' => 'string',
                'description' => 'Texto corregido. Debe ser solo el texto. Vacío si no aplica o si no hay sugerencia válida.'
            ],
            'justificacion' => [
                'type' => 'string',
                'description' => 'Explicación breve del motivo. Vacía si estado = correcto.'
            ],
        ],
        'required' => ['estado', 'sugerencia', 'justificacion'],
        'additionalProperties' => false,
    ];

    return AWSConfigBedrock::llm_generate_json_cached(
        $systemPrompt,
        $userPrompt,
        $schema,
        (int) $maxTokens,
        (float) $temperature
    );
}







function syllabus_llm_handleAjax()
{
    if (!empty($_POST['validarDescripcion'])) {
        header('Content-Type: application/json; charset=utf-8');
        $textoHTML   = isset($_POST['textoHTML']) ? $_POST['textoHTML'] : '';
        $nombreCampo = isset($_POST['nombreCampo']) ? trim($_POST['nombreCampo']) : 'Normas';
        try {
            echo json_encode(validar_texto_coherencia_ortografia($textoHTML, $nombreCampo), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    if (!empty($_POST['validarOrtografia'])) {
        header('Content-Type: application/json; charset=utf-8');
        $texto    = isset($_POST['texto']) ? trim($_POST['texto']) : '';
        $contexto = isset($_POST['contextoCampo']) ? trim($_POST['contextoCampo']) : 'Actividad de aprendizaje';
        try {
            echo json_encode(validar_ortografia_texto_plano($texto, $contexto), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    if (!empty($_POST['validarBibliografia'])) {
        header('Content-Type: application/json; charset=utf-8');
        $referencia = isset($_POST['referenciaBibliografica']) ? sanitizarHtmlBiblio(trim($_POST['referenciaBibliografica'])) : '';
        try {
            echo json_encode(evaluar_bibliografia_json($referencia), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    if (!empty($_POST['validarExperiencia'])) {
        header('Content-Type: application/json; charset=utf-8');
        $descripcion = isset($_POST['descripcionExperiencia']) ? trim($_POST['descripcionExperiencia']) : '';
        $jsonRAs     = isset($_POST['resultadosAprendizaje']) ? $_POST['resultadosAprendizaje'] : '[]';
        $arrRAs      = json_decode($jsonRAs, true);
        if (!is_array($arrRAs)) {
            $arrRAs = [];
        }
        try {
            echo json_encode(evaluar_experiencia_principal_json($descripcion, $arrRAs), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
}


