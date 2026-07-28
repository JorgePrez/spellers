<?php
/**
 * Funciones LLM Bedrock para syllabus catedratico.
 * Base: adm_academico_detalle_curso2.php
 */

function evaluar_bibliografia_json($referencia_bibliografica, $maxTokens = 300, $temperature = 0.0)
{
    $referencia_bibliografica = trim((string)$referencia_bibliografica);

    if ($referencia_bibliografica === '') {
        throw new Exception('Debe enviar la referencia bibliográfica');
    }

$systemPrompt = "Actúa como un experto en referencias bibliográficas académicas.

Tu tarea es evaluar si una referencia bibliográfica está correctamente escrita según los estándares académicos más comunes (APA, MLA, Chicago, Harvard, Vancouver, etc.).

Debes:
1. Identificar el formato bibliográfico más probable que intentó usar el usuario, solo si puede determinarse con claridad
2. Evaluar si la referencia es válida según ese formato
3. Proporcionar retroalimentación clara y una sugerencia de corrección si es necesario

Formatos bibliográficos comunes:

Nota general:
- Las referencias pueden corresponder a libros, documentos institucionales u otras fuentes académicas
- Cada formato puede incluir, si aplica, información adicional relevante según el tipo de documento, pero su ausencia no implica necesariamente que la referencia sea incorrecta

APA (American Psychological Association):
- Estructura general: Autor, A. A. (Año). Título. Fuente.

MLA (Modern Language Association):
- Estructura general: Autor. Título. Fuente, Año.

Chicago:
- Estructura general: Autor. Título. Ciudad: Editorial, Año.

Harvard:
- Estructura general: Autor, A. A., Año. Título. Fuente.

Vancouver:
- Estructura general: Autor AA. Título. Fuente. Año.
- Puede incluir, si aplica: volumen(número), páginas u otros datos adicionales según el tipo de documento.


Criterios de evaluación:

- La referencia debe contener al menos un autor (personal o institucional) o una entidad responsable, y un título
- Debe seguir un formato bibliográfico reconocible
- No debe tener errores ortográficos
- Debe ser clara y suficientemente completa para identificar la fuente
- Los elementos deben estar en el orden correcto según el formato
- La ausencia de elementos adicionales u opcionales no debe considerarse un error si la referencia sigue siendo clara, comprensible y estructuralmente válida
- No todos los formatos requieren los mismos elementos, por lo que la falta de ciertos datos no implica automáticamente que la referencia sea incorrecta o deba mejorarse

Clasificación:

1. correcto
La referencia está bien formateada, es suficientemente completa y sigue un formato bibliográfico estándar.
También debe clasificarse como correcto si es clara, coherente, no tiene errores ortográficos, sigue un formato reconocible y la única ausencia corresponde a elementos no esenciales.


2. puede_mejorarse
La referencia es válida pero presenta pequeñas inconsistencias de formato o detalles menores que conviene corregir

3. incorrecto
Debe marcarse como incorrecto si:
- no sigue ningún formato bibliográfico reconocible
- le faltan elementos esenciales (autor, entidad responsable o título)
- tiene errores graves de formato
- está incompleta o no tiene sentido
- contiene al menos un error ortográfico

- No clasifiques como puede_mejorarse si la única observación es la ausencia de elementos no esenciales

Regla general de clasificación:
- Si la referencia es clara, coherente, sin errores ortográficos y sigue un formato reconocible, debe clasificarse como correcto aunque no incluya todos los posibles elementos

Reglas obligatorias de salida:

- Debes devolver exactamente cuatro campos: formato_mas_probable, estado, sugerencia y justificacion
- No agregues campos adicionales
- No uses emojis
- No uses markdown
- No uses listas
- No uses asteriscos ni numeración
- No agregues texto fuera del JSON

Reglas de contenido:

- El campo formato_mas_probable debe contener el nombre del formato (APA, MLA, Chicago, Vancouver, Harvard, etc.) o cadena vacía si no se identifica ninguno
- Solo debes indicar un formato si la estructura de la referencia coincide de forma clara con ese estilo
- Si la referencia es híbrida, ambigua, genérica, incompleta o mezcla rasgos de varios estilos, usa cadena vacía en formato_mas_probable
- No adivines un formato por parecido superficial

- El campo sugerencia debe contener solo la referencia corregida
- No incluyas explicaciones en la sugerencia

- No inventes información que no esté presente en la referencia original
- No agregues datos como año, editorial, ciudad, revista, volumen o páginas si no aparecen en el texto original
- Solo debes reorganizar, corregir formato, puntuación y ortografía usando únicamente la información disponible
- No elimines información que esté presente en la referencia original
- Todos los elementos presentes en la referencia original deben conservarse en la sugerencia, aunque el formato no sea perfecto
- No deduzcas ni infieras información estructural que no esté explícitamente indicada
- Si un dato como volumen, número o páginas ya está presente, debes respetar exactamente su forma original y no modificar su estructura

- El campo justificacion debe ser una sola oración corta
- La justificacion debe ser clara, objetiva y directa

- Si el estado es correcto:
  - sugerencia debe ser cadena vacía
  - justificacion debe ser cadena vacía

- Si el estado es puede_mejorarse o incorrecto:
  - sugerencia debe proponer una referencia corregida que sea concreta, visible y diferente de la referencia original
  - justificacion debe explicar brevemente el problema
  - la corrección mencionada en la justificacion debe verse reflejada en la sugerencia

Antes de clasificar el resultado, revisa explícitamente si contiene errores ortográficos. Si detectas al menos uno, debes clasificarlo como incorrecto.

Verificación final antes de responder:

- Compara la referencia original con la sugerencia final que planeas devolver
- Si la sugerencia final es igual a la referencia original, no clasifiques como puede_mejorarse
- Si la sugerencia final es igual a la referencia original y no hay errores ortográficos ni faltan elementos esenciales, clasifica como correcto
- No marques una referencia como puede_mejorarse si no puedes proponer una corrección concreta, visible y diferente usando únicamente la información disponible

IMPORTANTE PARA EVITAR ERRORES JSON:
En los campos sugerencia y justificacion, NO uses comillas dobles para citar texto
Usa comillas simples en su lugar
Ejemplo CORRECTO: La referencia tiene errores en 'autor' y 'año'

Devuelve únicamente el JSON.";

    $userPrompt = "Referencia a evaluar: {$referencia_bibliografica}";

    //Esquema sin descripcion

   /* $schema = [
        'type' => 'object',
        'properties' => [
            'formato_mas_probable' => [
                'type' => 'string'
            ],
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
        'required' => ['formato_mas_probable', 'estado', 'sugerencia', 'justificacion'],
        'additionalProperties' => false
    ];*/

    $schema = [
    'type' => 'object',
    'properties' => [
        'formato_mas_probable' => [
            'type' => 'string',
            'description' => 'Nombre del formato bibliográfico (APA, MLA, Chicago, Harvard, Vancouver) o cadena vacía si no se identifica'
        ],
        'estado' => [
            'type' => 'string',
            'enum' => ['correcto', 'puede_mejorarse', 'incorrecto'],
            'description' => 'Estado de la referencia: correcto, puede_mejorarse o incorrecto'
        ],
        'sugerencia' => [
            'type' => 'string',
            'description' => 'Referencia corregida. Cadena vacía si estado es correcto. No usar comillas dobles, usar comillas simples'
        ],
        'justificacion' => [
            'type' => 'string',
            'description' => 'Explicación breve del problema. Cadena vacía si estado es correcto. No usar comillas dobles, usar comillas simples'
        ]
    ],
    'required' => ['formato_mas_probable', 'estado', 'sugerencia', 'justificacion'],
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



case 'Normas':
    return "
VALIDACIÓN ESPECÍFICA DEL CAMPO

Campo evaluado: Normas y reglas operativas del curso

Evalúa que el texto describa las reglas operativas del curso: asistencia, entregas, conducta,
reposiciones, participación, uso de dispositivos, políticas de honestidad académica, plazos,
consecuencias por incumplimiento, etc.

REGLA DE PRIORIDAD:
Antes de validar pertinencia del campo, verifica ortografía, gramática y coherencia general.
Si el texto tiene al menos una falta ortográfica, error gramatical o incoherencia lógica,
el estado debe ser incorrecto.

Una respuesta válida puede describir normas negociables y no negociables, asistencia, entregas,
conducta e integridad académica, en lista o párrafos.

No debe considerarse válido si solo describe contenidos, objetivos o metodología sin normas.

REGLA SOBRE CORRECCIÓN:
Si el estado es incorrecto por ortografía/gramática, html_corregido debe incluir el HTML corregido
manteniendo la misma estructura de etiquetas.
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



$systemPromptBase = "Actúa como un experto en redacción académica y corrección de textos.

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
            'description' => 'HTML corregido manteniendo estructura exacta. Cadena vacía si estado es correcto. Escapar comillas dobles con \\\"'
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


function validar_ortografia_texto_plano($texto, $contextoCampo = 'Actividad de aprendizaje', $maxTokens = 150, $temperature = 0.0)
{
    $texto = trim((string) $texto);
    if ($texto === '') {
        throw new Exception('Debe enviar el texto a validar');
    }

    $systemPrompt = "Actúa como corrector ortográfico en español para textos académicos breves.
Tu ÚNICA tarea es detectar errores ortográficos.
Campo evaluado: {$contextoCampo}
Evalúa SOLO ortografía. NO evalúes pertinencia ni redacción.
Si no hay errores ortográficos: estado correcto.
Si hay al menos un error ortográfico: estado incorrecto.
NO uses puede_mejorarse.
Si correcto: sugerencia y justificacion vacías.
Si incorrecto: sugerencia con texto corregido, justificacion breve.
No uses comillas dobles en sugerencia ni justificacion.
Devuelve únicamente el JSON.";

    $userPrompt = "Texto a evaluar: {$texto}";

    $schema = [
        'type' => 'object',
        'properties' => [
            'estado' => ['type' => 'string', 'enum' => ['correcto', 'incorrecto']],
            'sugerencia' => ['type' => 'string'],
            'justificacion' => ['type' => 'string'],
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
    if (isset($_POST['validarDescripcion']) && $_POST['validarDescripcion'] == true) {
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

    if (isset($_POST['validarOrtografia']) && $_POST['validarOrtografia'] == true) {
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

    if (isset($_POST['validarBibliografia']) && $_POST['validarBibliografia'] == true) {
        header('Content-Type: application/json; charset=utf-8');
        $referencia = isset($_POST['referenciaBibliografica']) ? trim($_POST['referenciaBibliografica']) : '';
        try {
            echo json_encode(evaluar_bibliografia_json($referencia), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
}


