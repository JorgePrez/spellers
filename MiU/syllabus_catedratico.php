<?php

require_once("core/main.php");
require_once("core/forms.php");
require_once("syllabus_catedratico_bitacora.php");
require_once __DIR__ . '/syllabus_catedratico_versiones.php';
require_once __DIR__ . '/syllabus_catedratico_spellcheck.php';
require_once __DIR__ . '/core/aws-php-sdk/aws_config_bedrock.php';
require_once __DIR__ . '/syllabus_catedratico_llm_validacion.php';











global $arrConfigSite;
$globalConnection = $arrConfigSite["db"]["database_resource"];

$intCursoImpartido = isset($_POST['cimp']) ? intval($_POST['cimp']) : 0;

uac_bitacora_handleAjax($globalConnection);
syllabus_llm_handleAjax();

header('Content-Type: text/html; charset=windows-1252');

$strActionLLM = basename(__FILE__);

//
//$intCurso         =  17830; //  17830;//33167;  //17830; //24085;  // TODO: recibir por parametro
//17830 biblioteca de archivos
//24085 Proceso Economico I 
//33578 Libertad en accion test
//curso que no tiene 33167


//Este campo se va a obtener desde el post//
// curso impartido biblioteca

//$intCursoImpartido = 144901;  // TODO: usar en fase 2 para datos del impartido

$intCurso          = 0; // se obtiene desde la query del impartido, NO por parametro


// ============================================================
// FUNCIONES AUXILIARES
// ============================================================

function getSyllabusUA($intCurso) {
    global $globalConnection;
    $intCurso = intval($intCurso);
    if ($intCurso <= 0) return null;

    $strQuery = "SELECT SYLLABUS_UA, CURSO, ADD_USER, ADD_FECHA, FECHA_INICIO, FECHA_FIN
                 FROM SYLLABUS_UA
                 WHERE CURSO = :curso
                   AND FECHA_FIN IS NULL";
     //importante debugear

    $stid = oci_parse($globalConnection, $strQuery);
    oci_bind_by_name($stid, ':curso', $intCurso, -1, SQLT_INT);
    oci_execute($stid);
    $row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stid);
    return $row ? $row : null;
}

function getClobsSyllabusUA($intSyllabusUA) {
    global $globalConnection;
    $intSyllabusUA = intval($intSyllabusUA);
    if ($intSyllabusUA <= 0) return [];

    $strQuery = "SELECT DESCRIPCION_INSTITUCIONAL,
                        APORTE_PLAN_ESTUDIOS,
                        CONOCIMIENTOS_PREVIOS,
                        MARCO_NORMATIVO
                 FROM SYLLABUS_UA
                 WHERE SYLLABUS_UA = :id";

    $stid = oci_parse($globalConnection, $strQuery);
    oci_bind_by_name($stid, ':id', $intSyllabusUA, -1, SQLT_INT);
    oci_execute($stid);
    // OCI_RETURN_LOBS convierte automaticamente CLOB a string PHP
    $row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS + OCI_RETURN_LOBS);
    oci_free_statement($stid);
    return $row ? $row : [];
}

function getRAsSyllabusUA($intSyllabusUA) {
    global $globalConnection;
    $intSyllabusUA = intval($intSyllabusUA);
    if ($intSyllabusUA <= 0) return [];

    $strQuery = "SELECT ra.SYLLABUS_UA_RA,
                        ra.DESCRIPCION_RA,
                        bn.NOMBRE AS BLOOM_NOMBRE
                 FROM SYLLABUS_UA_RA ra
                 LEFT JOIN BLOOM_NIVEL bn
                     ON ra.BLOOM_NIVEL = bn.BLOOM_NIVEL
                 WHERE ra.SYLLABUS_UA = :id
                 ORDER BY ra.SYLLABUS_UA_RA";

    $stid = oci_parse($globalConnection, $strQuery);
    oci_bind_by_name($stid, ':id', $intSyllabusUA, -1, SQLT_INT);
    oci_execute($stid);
    $arrRA = [];
    while ($row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS)) {
        $arrRA[] = [
            'BLOOM' => $row['BLOOM_NOMBRE'] ?? '',
            'DESC'  => $row['DESCRIPCION_RA'] ?? '',
        ];
    }
    oci_free_statement($stid);
    return $arrRA;
}

function syl_convertirUtf8($valor)
{
    if ($valor === null) {
        return '';
    }
    $str = (string) $valor;
    if ($str === '') {
        return '';
    }
    if (function_exists('mb_check_encoding') && mb_check_encoding($str, 'UTF-8')) {
        return $str;
    }
    $strEncoding = 'Windows-1252';
    if (function_exists('mb_detect_encoding')) {
        $detected = mb_detect_encoding($str, 'UTF-8, Windows-1252, ISO-8859-1, ISO-8859-15', true);
        if ($detected && $detected !== 'UTF-8') {
            $strEncoding = $detected;
        }
    }
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($str, 'UTF-8', $strEncoding);
    }
    return utf8_encode($str);
}

function syl_jsonParaJs($data)
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    if ($json !== false) {
        return $json;
    }

    if (!is_array($data)) {
        return 'null';
    }

    $arrUtf8 = [];
    foreach ($data as $item) {
        if (!is_array($item)) {
            continue;
        }
        $row = [];
        foreach ($item as $key => $value) {
            $row[$key] = syl_convertirUtf8($value);
        }
        $arrUtf8[] = $row;
    }

    $json = json_encode($arrUtf8, JSON_UNESCAPED_UNICODE);
    return ($json !== false) ? $json : '[]';
}

function getBiblioUA($intSyllabusUA) {
    global $globalConnection;
    if ($intSyllabusUA <= 0) return [];

    $strQuery = "SELECT b.SYLLABUS_UA_BIBLIO, b.REFERENCIA_COMPLETA
                 FROM SYLLABUS_UA_BIBLIO b
                 WHERE b.SYLLABUS_UA = :id
                 ORDER BY b.ADD_FECHA";

    $stid = oci_parse($globalConnection, $strQuery);
    oci_bind_by_name($stid, ':id', $intSyllabusUA, -1, SQLT_INT);
    oci_execute($stid);
    $arrBiblio = [];
    while ($row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS + OCI_RETURN_LOBS)) {
        $arrBiblio[] = $row['REFERENCIA_COMPLETA'] ?? '';
    }
    oci_free_statement($stid);
    return $arrBiblio;
}


// ============================================================
// DATOS DEL CURSO (bloque CIMP) - desde BD
// ============================================================

$arrCurso = [
    'TITULO'         => '',
    'NOMBRE'         => '',
    'CODIGO'         => '',
    'AREA_ACADEMICA' => '',
    'UMA'            => '',
    'SEMESTRE'       => '',   
    'ANIO'           => '', 
    'SECCION'        => '',    
    'FACULTAD'       => '',
];


$strQueryImpartido = "
    SELECT
        CI.CURSO,
        CI.SECCION,
        C.NOMBRE        AS NOMBRE_CURSO,
        C.CODIGO        AS CODIGO_CURSO,
        C.UMAS,
        C.TIPO_CURSO,
        F.NOMBRE        AS FACULTAD,
        A.NOMBRE AS AREA_ACADEMICA,
        AC.CICLO,
        CF.FECHA_INICIO,
        CF.FECHA_FIN,
        P.NOMBRE1 || ' ' || P.NOMBRE2 || ' ' || P.APELLIDO1 || ' ' || P.APELLIDO2 AS NOMBRE_PERSONA,
        P.USUARIO || '@ufm.edu' AS EMAIL_PERSONA,
        TS.DESCRIPCION  AS ROL
    FROM CURSO_IMPARTIDO CI
    INNER JOIN CURSO_IMPARTIDO_STAFF CIF
        ON CI.CURSO_IMPARTIDO = CIF.CURSO_IMPARTIDO
        AND CIF.BLOQUEADO_MIU = 'N'
    INNER JOIN PERSONA P
        ON CIF.PERSONA = P.PERSONA
    INNER JOIN TIPO_STAFF TS
        ON CIF.TIPO_STAFF = TS.TIPO_STAFF
    INNER JOIN CICLO_FECHA CF
        ON CI.CICLO_FECHA = CF.CICLO_FECHA
    INNER JOIN CURSO C
        ON CI.CURSO = C.CURSO
    INNER JOIN AREA A
        ON C.AREA = A.AREA
    INNER JOIN FACULTAD F
        ON A.FACULTAD = F.FACULTAD
    LEFT JOIN CUENTAC.VW_NCC_AGRUPADOR_CICLOS AC
        ON CF.CICLO_FECHA = AC.CICLO_FECHA
    WHERE CI.CURSO_IMPARTIDO = :cimp
";

$stidImp = oci_parse($globalConnection, $strQueryImpartido);
oci_bind_by_name($stidImp, ':cimp', $intCursoImpartido, -1, SQLT_INT);
oci_execute($stidImp);

$arrProfesores  = [];
$tipoCursoOriginal = '';
$primeraFila    = true;

while ($rowImp = oci_fetch_array($stidImp, OCI_ASSOC + OCI_RETURN_NULLS)) {

    // Datos del curso: leer solo de la primera fila (son iguales en todas)
    if ($primeraFila) {
        $intCurso                  = intval($rowImp['CURSO'] ?? 0); // derivado del impartido
        $arrCurso['SECCION']       = $rowImp['SECCION']     ?? '';
        $arrCurso['NOMBRE']        = $rowImp['NOMBRE_CURSO'] ?? '';
        $arrCurso['CODIGO']        = $rowImp['CODIGO_CURSO'] ?? '';
        $arrCurso['UMA']           = intval($rowImp['UMAS']  ?? 0);
        $arrCurso['FACULTAD']      = $rowImp['FACULTAD']     ?? '';
        $arrCurso['AREA_ACADEMICA']= $rowImp['AREA_ACADEMICA'] ?? '';
        $tipoCursoOriginal         = $rowImp['TIPO_CURSO']   ?? '';

        $ciclo = $rowImp['CICLO'] ?? '';
        $arrCurso['ANIO']      = substr($ciclo, 0, 4);
         $arrCurso['SEMESTRE'] = substr($ciclo, 4, 2);

        $arrCurso['TITULO']        = $arrCurso['NOMBRE']  . ' - ' . $arrCurso['SECCION'] . ' - ' . ($rowImp['CICLO'] ?? '');

        $primeraFila = false;
    }

    // Datos del profesor: acumular en todas las filas
    $arrProfesores[] = [
        'NOMBRE' => $rowImp['NOMBRE_PERSONA'] ?? '',
        'EMAIL'  => $rowImp['EMAIL_PERSONA']  ?? '',
        'ROL'    => $rowImp['ROL']            ?? '',
    ];
}

oci_free_statement($stidImp);

$arrProfesores = syl_uac_ordenarProfesoresPrincipalPrimero($arrProfesores);


if ($intCurso <= 0) {
    die('<p style="font-family:sans-serif;padding:40px;color:#cc0000;">
         <strong>Error:</strong> acceso denegado</p>');
}


// ============================================================
// LOGICA ESPECIAL: CURSOS DE ETICA
// Si el cimp tiene entrada en ASIGNACION_ETICA, sobreescribir
// nombre, seccion, ciclo y facultad con los del curso vinculado
// ============================================================

$strQueryEtica = "SELECT COUNT(*) AS ETICA
                  FROM ASIGNACION_ETICA
                  WHERE CURSO_IMPARTIDO_ETICA = :cimp";

$stidEtica = oci_parse($globalConnection, $strQueryEtica);
oci_bind_by_name($stidEtica, ':cimp', $intCursoImpartido, -1, SQLT_INT);
oci_execute($stidEtica);
$rowEtica = oci_fetch_array($stidEtica, OCI_ASSOC + OCI_RETURN_NULLS);
oci_free_statement($stidEtica);

if (intval($rowEtica['ETICA'] ?? 0) > 0) {

    $strQueryDatosEtica = "
        SELECT
            CI.SECCION,
            C.CODIGO        AS CODIGO_CURSO,
            C.NOMBRE        AS NOMBRE_CURSO,
            C.TIPO_CURSO,
            AC.CICLO,
            CF.FECHA_INICIO,
            CF.FECHA_FIN
        FROM CURSO_IMPARTIDO CI
        INNER JOIN CURSO C
            ON CI.CURSO = C.CURSO
        INNER JOIN CICLO_FECHA CF
            ON CI.CICLO_FECHA = CF.CICLO_FECHA
        LEFT JOIN CUENTAC.VW_NCC_AGRUPADOR_CICLOS AC
            ON CF.CICLO_FECHA = AC.CICLO_FECHA
        WHERE CI.CURSO_IMPARTIDO = :cimp";

    $stidDE = oci_parse($globalConnection, $strQueryDatosEtica);
    oci_bind_by_name($stidDE, ':cimp', $intCursoImpartido, -1, SQLT_INT);
    oci_execute($stidDE);
    $rowDE = oci_fetch_array($stidDE, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stidDE);

    if ($rowDE) {
        $arrCurso['NOMBRE']  = $rowDE['NOMBRE_CURSO'] ?? $arrCurso['NOMBRE'];
        $arrCurso['CODIGO']  = $rowDE['CODIGO_CURSO'] ?? $arrCurso['CODIGO'];
        $arrCurso['SECCION'] = $rowDE['SECCION']      ?? $arrCurso['SECCION'];

        $cicloEtica          = $rowDE['CICLO'] ?? '';
        $arrCurso['ANIO']    = substr($cicloEtica, 0, 4);
        $arrCurso['SEMESTRE']= substr($cicloEtica, 4, 2);

        $arrCurso['TITULO']  = $arrCurso['NOMBRE'] . ' - ' . $arrCurso['SECCION'] . ' - ' . $cicloEtica;

        // Facultad fija segun tipo de curso original (miuapp/api/moodle/coursesv3.php )
        $arrCurso['FACULTAD'] = ($tipoCursoOriginal === 'COLIB')
            ? 'Colaboratorio'
            : 'Centro Henry Hazlitt';
    }
}

// ============================================================
// DATOS DE LA UNIDAD ACADEMICA - desde BD
// ============================================================

$intSyllabusUA        = 0;
$strDescInstitucional = '';
$strAportePlan        = '';
$strConocPrevios      = '';
$strMarco             = '';
$arrRA                = [];
$arrBiblioUA          = [];

$syllabusUA = getSyllabusUA($intCurso);

if ($syllabusUA) {
    $intSyllabusUA = intval($syllabusUA['SYLLABUS_UA']);

    $clobs             = getClobsSyllabusUA($intSyllabusUA);
    $strDescInstitucional = $clobs['DESCRIPCION_INSTITUCIONAL'] ?? '';
    $strAportePlan        = $clobs['APORTE_PLAN_ESTUDIOS']      ?? '';
    $strConocPrevios      = $clobs['CONOCIMIENTOS_PREVIOS']     ?? '';
    $strMarco             = $clobs['MARCO_NORMATIVO']           ?? '';

    $arrRA       = getRAsSyllabusUA($intSyllabusUA);
    $arrBiblioUA = getBiblioUA($intSyllabusUA);
}

// ============================================================
// CAMPOS DEL CATEDRATICO 
// ============================================================
$intSyllabusCatedratico = 0;
$strNormas              = '';
$strUsoIA               = '';
$strPensamientoCritico  = '';
$strQueryCat = "SELECT SYLLABUS_UA_CATEDRATICO, NORMAS_REGLAS, USO_IA, PENSAMIENTO_CRITICO
                FROM   SYLLABUS_UA_CATEDRATICO
                WHERE  CURSO_IMPARTIDO = :cimp
                  AND  FECHA_INICIO IS NULL
                  AND  FECHA_FIN IS NULL";

$stidCat = oci_parse($globalConnection, $strQueryCat);
oci_bind_by_name($stidCat, ':cimp', $intCursoImpartido, -1, SQLT_INT);
oci_execute($stidCat);
$rowCat = oci_fetch_array($stidCat, OCI_ASSOC + OCI_RETURN_NULLS + OCI_RETURN_LOBS);
oci_free_statement($stidCat);

if ($rowCat) {
    $intSyllabusCatedratico = intval($rowCat['SYLLABUS_UA_CATEDRATICO']);
    $strNormas              = $rowCat['NORMAS_REGLAS'] ?? '';
    $strUsoIA               = $rowCat['USO_IA'] ?? '';
    $strPensamientoCritico  = $rowCat['PENSAMIENTO_CRITICO'] ?? '';

}



$intContadorPublicaciones = syl_uac_contarPublicaciones($globalConnection, $intCursoImpartido);
$arrUltimaPublicacion     = syl_uac_getUltimaPublicacion($globalConnection, $intCursoImpartido);
$strUltimaPublicacionFmt  = $arrUltimaPublicacion ? ($arrUltimaPublicacion['FECHA_FMT'] ?? '') : '';
$intIdPublicadaVigente    = syl_uac_getIdPublicadaVigente($globalConnection, $intCursoImpartido);
$strPathPdfVigente        = ($intIdPublicadaVigente > 0)
    ? syl_uac_verificarSnapshotPublicado($globalConnection, $intIdPublicadaVigente, $intCursoImpartido)
    : false;
$boolTienePdfVigente      = ($strPathPdfVigente !== false && trim((string) $strPathPdfVigente) !== '');

$arrEvaluacion   = [];
$intContadorEval = 0;

if ($intSyllabusCatedratico > 0) {
    $strQueryEval = "SELECT SYLLABUS_UAC_EVALUACION, RUBRO, PORCENTAJE
                     FROM   SYLLABUS_UA_CATEDRATICO_EVALUACION
                     WHERE  SYLLABUS_UA_CATEDRATICO = :id
                     ORDER  BY SYLLABUS_UAC_EVALUACION";

    $stidEval = oci_parse($globalConnection, $strQueryEval);
    oci_bind_by_name($stidEval, ':id', $intSyllabusCatedratico, -1, SQLT_INT);
    oci_execute($stidEval);
    while ($rowEval = oci_fetch_array($stidEval, OCI_ASSOC + OCI_RETURN_NULLS)) {
        $intContadorEval++;
        $arrEvaluacion[] = [
            'ID'         => intval($rowEval['SYLLABUS_UAC_EVALUACION']),
            'RUBRO'      => $rowEval['RUBRO']      ?? '',
            'PORCENTAJE' => $rowEval['PORCENTAJE'] ?? 0,
        ];
    }
    oci_free_statement($stidEval);
}



//$strCronogramaArchivo = "cronograma_SIFE_202601_U.pdf";

/*$arrBiblioEvolutiva = [
    'Garcia-Lopez, R. (2023). IA aplicada a bibliotecas digitales.',
    'Martinez, C. (2024). Machine Learning para catalogacion automatica.',
    'UNESCO (2023). Directrices para bibliotecas digitales en America Latina.',
];*/

$arrBiblioEvolutiva   = [];
$intContadorBiblioEv  = 0;

if ($intSyllabusCatedratico > 0) {
    $strQueryBiblioEv = "SELECT SYLLABUS_UAC_BIBLIOGRAFIA
                         FROM   SYLLABUS_UA_CATEDRATICO_BIBLIOGRAFIA
                         WHERE  SYLLABUS_UA_CATEDRATICO = :id
                         ORDER  BY SYLLABUS_UAC_BIBLIOGRAFIA";

    $stidBiblioEv = oci_parse($globalConnection, $strQueryBiblioEv);
    oci_bind_by_name($stidBiblioEv, ':id', $intSyllabusCatedratico, -1, SQLT_INT);
    oci_execute($stidBiblioEv);
    while ($rowBiblioEv = oci_fetch_array($stidBiblioEv, OCI_ASSOC + OCI_RETURN_NULLS)) {
        $intContadorBiblioEv++;
        $intBiblioId = intval($rowBiblioEv['SYLLABUS_UAC_BIBLIOGRAFIA']);
        $arrBiblioEvolutiva[] = [
            'ID'        => $intBiblioId,
            'REFERENCIA'=> uac_getReferenciaBiblioEv($globalConnection, $intBiblioId),
        ];
    }
    oci_free_statement($stidBiblioEv);
}

$arrExperiencias  = [];
$intContadorExp   = 0;

if ($intSyllabusCatedratico > 0) {
    $strQueryExp = "SELECT SYLLABUS_UAC_EXPERIENCIA, DESCRIPCION
                    FROM   SYLLABUS_UA_CATEDRATICO_EXPERIENCIA
                    WHERE  SYLLABUS_UA_CATEDRATICO = :id
                    ORDER  BY SYLLABUS_UAC_EXPERIENCIA";

    $stidExp = oci_parse($globalConnection, $strQueryExp);
    oci_bind_by_name($stidExp, ':id', $intSyllabusCatedratico, -1, SQLT_INT);
    oci_execute($stidExp);
    while ($rowExp = oci_fetch_array($stidExp, OCI_ASSOC + OCI_RETURN_NULLS)) {
        $intContadorExp++;
        $arrExperiencias[] = [
            'ID'          => intval($rowExp['SYLLABUS_UAC_EXPERIENCIA']),
            'DESCRIPCION' => $rowExp['DESCRIPCION'] ?? '',
        ];
    }
    oci_free_statement($stidExp);
}

$arrCronogramas   = [];
$intContadorCrono = 0;

if ($intSyllabusCatedratico > 0) {
    $stidCrono = oci_parse($globalConnection,
        "SELECT SYLLABUS_UAC_CRONOGRAMA, PATH_ARCHIVO, PATH_ARCHIVO_REV, NOMBRE_ARCHIVO, ACTIVO
         FROM   SYLLABUS_UA_CATEDRATICO_CRONOGRAMA
         WHERE  SYLLABUS_UA_CATEDRATICO = :id
         ORDER  BY ADD_FECHA, SYLLABUS_UAC_CRONOGRAMA");
    oci_bind_by_name($stidCrono, ':id', $intSyllabusCatedratico, -1, SQLT_INT);
    oci_execute($stidCrono);
    while ($rowCrono = oci_fetch_array($stidCrono, OCI_ASSOC + OCI_RETURN_NULLS)) {
        $intContadorCrono++;
        $strNomArch = $rowCrono['NOMBRE_ARCHIVO'] ?? '';
        $strExtCrono = strtolower(pathinfo($strNomArch, PATHINFO_EXTENSION));
        $arrCronogramas[] = [
            'ID'       => intval($rowCrono['SYLLABUS_UAC_CRONOGRAMA']),
            'PATH'     => $rowCrono['PATH_ARCHIVO'] ?? '',
            'PATH_REV' => $rowCrono['PATH_ARCHIVO_REV'] ?? '',
            'NOMBRE'   => $strNomArch,
            'ACTIVO'   => $rowCrono['ACTIVO'] ?? 'Y',
            'ES_PDF'   => ($strExtCrono === 'pdf'),
        ];
    }
    oci_free_statement($stidCrono);
}

$arrFlashUAC = null;
$arrRevisionSesion = null;
if ($intSyllabusCatedratico > 0) {
    $arrFlashUAC = syl_spell_consumirFlash();
    $arrRevisionSesion = syl_spell_obtenerRevisionSesion($intSyllabusCatedratico);
}


// --- Usuario en sesion  ---
//$strUsuario = 'jpescobar@ufm.edu';


// Contenido del modal de ayuda: formato Chicago (chicago_info.html)
$strContenidoModalBiblio = '';
$strContenidoModalBiblio .= "<hr>";
$strContenidoModalBiblio .= "<p><strong>Formato bibliogr&aacute;fico requerido: Chicago (Bibliograf&iacute;a)</strong></p>";
$strContenidoModalBiblio .= "<p>La validaci&oacute;n autom&aacute;tica con IA eval&uacute;a las referencias &uacute;nicamente contra el formato Chicago para ";
$strContenidoModalBiblio .= "<strong>bibliograf&iacute;a</strong> (no el de notas al pie/finales, ni el de autor-fecha).</p>";
$strContenidoModalBiblio .= "<hr>";
$strContenidoModalBiblio .= "<p><strong>Estructura general (libro completo, el caso m&aacute;s frecuente):</strong></p>";
$strContenidoModalBiblio .= "<p>Autor. <em>T&iacute;tulo del libro</em>. Ciudad: Editorial, A&ntilde;o.</p>";
$strContenidoModalBiblio .= "<p><em>Ejemplo:</em> L&oacute;pez, Mar&iacute;a. <em>Estad&iacute;stica aplicada</em>. Madrid: Springer, 2018.</p>";
$strContenidoModalBiblio .= "<ul>";
$strContenidoModalBiblio .= "<li>El apellido del autor va primero, seguido de coma y el nombre (solo se invierte el primer autor si hay varios).</li>";
$strContenidoModalBiblio .= "<li>El t&iacute;tulo del libro siempre va en <em>cursiva</em>.</li>";
$strContenidoModalBiblio .= "<li>Los datos de publicaci&oacute;n no van entre par&eacute;ntesis (eso es solo para notas al pie).</li>";
$strContenidoModalBiblio .= "<li>Para libros, solo se indica el a&ntilde;o de publicaci&oacute;n, sin mes ni d&iacute;a.</li>";
$strContenidoModalBiblio .= "</ul>";
$strContenidoModalBiblio .= "<hr>";
$strContenidoModalBiblio .= "<p><strong>Otros tipos de fuente (menos frecuentes):</strong></p>";
$strContenidoModalBiblio .= "<p><strong>Art&iacute;culo de revista:</strong><br>";
$strContenidoModalBiblio .= "Autor. &quot;T&iacute;tulo del art&iacute;culo&quot;. <em>Nombre de la Revista</em> Volumen, n.&ordm; N&uacute;mero (A&ntilde;o): p&aacute;ginas.</p>";
$strContenidoModalBiblio .= "<p style=\"margin-left: 1em;\"><em>Ejemplo:</em> P&eacute;rez, Juan. &quot;El cambio clim&aacute;tico en Am&eacute;rica Latina&quot;. <em>Revista de Ecolog&iacute;a</em> 12, n.&ordm; 3 (2020): 45-60.</p>";
$strContenidoModalBiblio .= "<p><strong>Cap&iacute;tulo de libro:</strong><br>";
$strContenidoModalBiblio .= "Autor del cap&iacute;tulo. &quot;T&iacute;tulo del cap&iacute;tulo&quot;. En <em>T&iacute;tulo del libro</em>, editado por Nombre del Editor, p&aacute;ginas. Ciudad: Editorial, A&ntilde;o.</p>";
$strContenidoModalBiblio .= "<hr>";
$strContenidoModalBiblio .= "<p style=\"font-style: italic; color: #666;\">Consejo: si la fuente es un libro completo (el caso m&aacute;s com&uacute;n), basta con seguir el ejemplo de arriba: ";
$strContenidoModalBiblio .= "Autor. <em>T&iacute;tulo</em>. Ciudad: Editorial, A&ntilde;o.</p>";

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="windows-1252">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UFM Syllabus - Catedr?tico</title>
    <style>
        /* ========== RESET & BASE ========== */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            font-size: 14px;
            color: #222;
            background: #fff;
        }

        /* ========== HEADER ========== */
        .site-header {
            background: #1a1a1a;
            color: #fff;
            padding: 0;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 6px rgba(0,0,0,0.4);
        }
        .site-header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            height: 64px;
        }
        .site-logo { display: flex; align-items: center; gap: 12px; }
        .site-logo-badge {
            width: 44px; height: 44px;
            background: #fff; border-radius: 4px;
            display: flex; align-items: center; justify-content: center;
            font-size: 10px; font-weight: 900; color: #8B0000;
            text-align: center; line-height: 1.1; padding: 3px;
        }
        .site-logo-text { font-size: 20px; font-weight: 300; letter-spacing: 1px; color: #fff; }
        .site-header-nav { display: flex; align-items: center; gap: 24px; }
        .site-header-nav a { color: #ccc; text-decoration: none; font-size: 14px; }
        .site-header-nav a:hover { color: #fff; }
        .btn-header-miu {
            background: #cc0000; color: #fff !important;
            padding: 7px 18px; border-radius: 4px;
            font-weight: 600; font-size: 13px; text-decoration: none;
        }
        .btn-header-miu:hover { background: #aa0000; }
        .header-user { font-size: 12px; color: #aaa; }
        .header-user a { color: #aaa; text-decoration: none; }
        .header-user a:hover { color: #fff; }
        .header-right { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }

        /* ========== CONTAINER ========== */
        .container { max-width: 960px; margin: 0 auto; padding: 40px 24px 60px; }

        /* ========== COURSE TITLE ========== */
        .course-title {
            text-align: center; font-size: 26px; font-weight: 700;
            color: #cc0000; margin-bottom: 32px; line-height: 1.3;
        }

        /* ========== CIMP BLOCK ========== */
        .cimp-block {
            display: flex; gap: 0; margin-bottom: 28px; border: 1px solid #ddd;
        }
        .cimp-left {
            background: #f0f0f0; min-width: 160px; padding: 20px 16px;
            display: flex; flex-direction: column; gap: 14px; border-right: 1px solid #ddd;
        }
        .cimp-item { text-align: center; }
        .cimp-label { font-size: 12px; font-weight: 700; color: #cc0000; text-transform: uppercase; letter-spacing: 0.5px; }
        .cimp-value { font-size: 16px; font-weight: 400; color: #222; margin-top: 2px; }
        .cimp-right { flex: 1; padding: 20px 24px; }
        .cimp-right-title {
            font-size: 15px; font-weight: 700; color: #222;
            border-left: 4px solid #cc0000; padding-left: 10px; margin-bottom: 16px;
        }
        .prof-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 20px; }
        .prof-label { font-size: 12px; color: #888; }
        .prof-value { font-size: 13px; font-weight: 600; color: #222; }

        /* ========== TOOLBAR SUPERIOR (versiones / import-export) ========== */
        .cimp-toolbar-wrap {
            display: flex; flex-direction: column; align-items: center;
            margin-bottom: 28px; width: 100%;
        }
        .cimp-toolbar-section { margin-bottom: 14px; text-align: center; width: 100%; }
        .cimp-toolbar-section-title {
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            color: #888; letter-spacing: 0.5px; margin-bottom: 8px;
        }
        .cimp-toolbar-row {
            display: flex; flex-wrap: wrap; align-items: center;
            justify-content: center; gap: 8px 12px;
        }
        .cimp-toolbar-meta { font-size: 12px; color: #555; }
        .cimp-toolbar-meta strong { color: #222; }
        .cimp-toolbar-alert {
            display: inline-flex; align-items: center; gap: 10px;
            padding: 12px 20px; max-width: 100%;
            font-size: 14px; font-weight: 700; color: #8b0000;
            background: #ffe5e5; border: 2px solid #cc0000;
            border-radius: 6px; box-shadow: 0 2px 10px rgba(204, 0, 0, 0.15);
        }
        .cimp-toolbar-alert-icon {
            font-size: 20px; line-height: 1; flex-shrink: 0;
        }
        .cimp-toolbar-link {
            background: none; border: none; padding: 0;
            font-size: 13px; font-weight: 600; color: #0066cc;
            text-decoration: underline; cursor: pointer; line-height: 1.3;
        }
        .cimp-toolbar-link:hover { color: #004499; }
        .btn-toolbar {
            padding: 6px 14px; font-size: 12px; font-weight: 600;
            border-radius: 4px; border: none; cursor: pointer; line-height: 1.3;
        }
        .btn-toolbar:disabled {
            opacity: 0.45; cursor: not-allowed;
        }
        .btn-toolbar-export  { background: #f0a500; color: #fff; }
        .btn-toolbar-export:not(:disabled):hover  { background: #d9940a; }
        .btn-toolbar-import  { background: #28a745; color: #fff; }
        .btn-toolbar-import:not(:disabled):hover  { background: #1e7e34; }
        .btn-toolbar-version {
            background: #fff; color: #444; border: 1px solid #ccc;
        }
        .btn-toolbar-version:hover { border-color: #888; background: #f5f5f5; }
        .btn-toolbar-pdf { background: #5c3d8f; color: #fff; }
        .btn-toolbar-pdf:hover { background: #4a3173; }
        .btn-toolbar-save { background: #f0a500; color: #fff; }
        .btn-toolbar-save:hover { background: #d9940a; }
        .btn-toolbar-save:disabled { opacity: 0.65; cursor: wait; }
        .btn-toolbar-cancel {
            background: #fff; color: #444; border: 1px solid #ccc;
        }
        .btn-toolbar-cancel:hover { border-color: #888; background: #f5f5f5; }
        .btn-toolbar-approve { background: #28a745; color: #fff; }
        .btn-toolbar-approve:hover { background: #1e7e34; }
        .btn-toolbar-approve:disabled { opacity: 0.65; cursor: wait; }
        .btn-toolbar-ver-pdf {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 10px; font-size: 12px; font-weight: 600;
            background: #fff; color: #5c3d8f; border: 1px solid #5c3d8f;
            border-radius: 4px; cursor: pointer;
        }
        .btn-toolbar-ver-pdf:hover { background: #f3eef9; }
        .btn-ver-icon { font-size: 14px; line-height: 1; }
        .modal-pdf-viewer-box { max-width: min(1100px, 96vw); height: 90vh; }
        .modal-pdf-viewer-body { flex: 1; min-height: 0; overflow: hidden; }
        .modal-pdf-viewer-body iframe {
            width: 100%; height: 100%; border: none; background: #525659;
        }

        /* ========== PART DIVIDERS ========== */
        .part-header { display: flex; align-items: center; gap: 12px; margin: 36px 0 20px; }
        .part-header-line { flex: 1; height: 1px; background: #ddd; }
        .part-header-label {
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: 1.5px; padding: 4px 14px; border-radius: 20px; white-space: nowrap;
        }
        .part-header-label.ua { background: #f0f0f0; color: #666; border: 1px solid #ddd; }
        .part-header-label.catedratico { background: #cc0000; color: #fff; }

        /* ========== IMPORTAR / EXPORTAR (catedratico) ========== */
        .cat-import-export {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin: 0 0 24px;
        }

        /* ========== SECTIONS ========== */
        .section { margin-bottom: 32px; }
        .section-header { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
        .section-title {
            font-size: 16px; font-weight: 700; color: #222;
            border-left: 4px solid #cc0000; padding-left: 10px; line-height: 1.3;
        }
        .section-subtitle { font-size: 13px; color: #888; margin-bottom: 12px; padding-left: 14px; }
        .badge-readonly {
            font-size: 10px; background: #e8e8e8; color: #888; padding: 2px 8px;
            border-radius: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap;
        }
        .badge-catedratico {
            font-size: 10px; background: #fff0f0; color: #cc0000; padding: 2px 8px;
            border-radius: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;
            border: 1px solid #ffcccc; white-space: nowrap;
        }

        /* ========== BIT?CORA E INFO ICONS ========== */
.btn-bitacora {
    background: none;
    border: 1px solid #cc0000;
    color: #cc0000;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 10px;
    border-radius: 12px;
    cursor: pointer;
    white-space: nowrap;
    margin-left: 8px;
}
.btn-bitacora:hover { background: #fff0f0; }
.btn-bitacora:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}
.btn-bitacora-icon,
.btn-preview-icon {
    background: #fff8e1;
    border: 1px solid #f0a500;
    color: #d9940a;
    border-radius: 50%;
    width: 26px;
    height: 26px;
    font-size: 13px;
    line-height: 1;
    cursor: pointer;
    padding: 0;
    vertical-align: middle;
}
.btn-bitacora-icon:hover,
.btn-preview-icon:hover {
    background: #f0a500;
    color: #fff;
}

        /* ========== MODAL BITACORA ========== */
        .modal-bitacora-close {
            position: absolute;
            top: 12px;
            right: 14px;
            width: 32px;
            height: 32px;
            border: none;
            background: #f0f0f0;
            color: #444;
            font-size: 22px;
            line-height: 1;
            border-radius: 50%;
            cursor: pointer;
            z-index: 2;
            padding: 0;
        }
        .modal-bitacora-close:hover {
            background: #cc0000;
            color: #fff;
        }
        .modal-bitacora-body {
            overflow-y: auto;
            flex: 1;
            padding-right: 8px;
            padding-top: 4px;
        }
        .bitacora-wrap { font-size: 14px; }
        .bitacora-title {
            font-size: 13px;
            font-weight: 700;
            color: #222;
            border-left: 4px solid #cc0000;
            padding-left: 10px;
            margin: 0 36px 14px 0;
            line-height: 1.35;
        }
        .bitacora-subtitle {
            font-size: 14px;
            font-weight: 700;
            color: #333;
            text-align: center;
            background: #e8e8e8;
            border: 1px solid #bbb;
            padding: 11px 14px;
            margin: 36px 0 0;
        }
        .bitacora-title + .bitacora-subtitle,
        .bitacora-meta + .bitacora-subtitle,
        .bitacora-meta.bitacora-deleted + .bitacora-subtitle {
            margin-top: 14px;
        }
        .bitacora-subtitle + .bitacora-table,
        .bitacora-subtitle + .bitacora-content-box,
        .bitacora-subtitle + .bitacora-empty {
            margin-top: 0;
        }
        .bitacora-subtitle + .bitacora-table {
            border-top: none;
        }
        .bitacora-subtitle + .bitacora-table th {
            border-top: none;
        }
        .bitacora-subtitle + .bitacora-content-box {
            border-top: none;
            margin-top: 0;
        }
        .bitacora-meta {
            font-size: 14px;
            color: #555;
            margin-bottom: 12px;
            line-height: 1.6;
        }
        .bitacora-meta.bitacora-deleted {
            background: #ffebee;
            border: 1px solid #ffcdd2;
            border-radius: 4px;
            padding: 10px 12px;
        }
        .bitacora-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            margin-bottom: 4px;
        }
        .bitacora-table th {
            background: #f5f5f5;
            border: 1px solid #ddd;
            padding: 10px 12px;
            text-align: center;
            font-weight: 700;
        }
        .bitacora-table td {
            border: 1px solid #e8e8e8;
            padding: 10px 12px;
            text-align: center;
            vertical-align: middle;
        }
        .bitacora-table tr:nth-child(even) td { background: #fafafa; }
        .bitacora-btn-descarga {
            background: none;
            border: none;
            padding: 0;
            color: #1565c0;
            text-decoration: underline;
            cursor: pointer;
            font: inherit;
        }
        .bitacora-btn-descarga:hover {
            color: #0d47a1;
        }
        .bitacora-actions { text-align: center; white-space: nowrap; }
        .bitacora-empty {
            text-align: center;
            color: #888;
            padding: 16px;
            font-size: 14px;
        }
        .bitacora-content-box {
            border: 1px solid #ccc;
            padding: 12px;
            background: #f9f9f9;
            line-height: 1.6;
            max-height: 400px;
            overflow-y: auto;
            font-size: 14px;
        }

.info-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    background: #e8e8e8;
    color: #666;
    border-radius: 50%;
    font-size: 11px;
    font-weight: 700;
    cursor: default;
    margin-left: 6px;
    position: relative;
}
.info-icon:hover { background: #1565c0; color: #fff; }
.info-icon:hover::after {
    content: attr(data-desc);
    position: absolute;
    top: 24px;
    left: 50%;
    transform: translateX(-50%);
    background: #222;
    color: #fff;
    font-size: 11px;
    font-weight: 400;
    padding: 5px 10px;
    border-radius: 4px;
    white-space: nowrap;
    z-index: 50;
    pointer-events: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.25);
}
.info-icon:hover::before {
    content: '';
    position: absolute;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    border: 4px solid transparent;
    border-bottom-color: #222;
    z-index: 51;
}
.info-icon-clickable {
    cursor: pointer;
}
.info-icon-clickable:hover::after,
.info-icon-clickable:hover::before {
    display: none;
}
.modal-box.modal-ayuda-box {
    position: relative;
    max-width: min(1000px, 96vw);
    width: 96%;
    max-height: 88vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    text-align: left;
    padding: 28px 32px 24px;
}
.modal-ayuda-body {
    overflow-y: auto;
    line-height: 1.7;
    font-size: 13px;
    color: #333;
    padding-right: 8px;
}
.modal-ayuda-body hr {
    border: none;
    border-top: 1px solid #ccc;
    margin: 15px 0;
}
.modal-ayuda-body ul {
    margin: 8px 0 8px 20px;
}
.modal-ayuda-body li {
    margin-bottom: 6px;
}

        /* ========== READ-ONLY CONTENT ========== */
        .readonly-box {
            background: #fafafa; border: 1px solid #e8e8e8; border-radius: 4px;
            padding: 14px 16px; line-height: 1.7; color: #333; font-size: 13.5px;
        }

        /* ========== TABLES ========== */
        .data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .data-table th {
            background: #f5f5f5; border: 1px solid #ddd; padding: 8px 12px;
            text-align: left; font-weight: 700; color: #444; font-size: 12px;
            text-transform: uppercase; letter-spacing: 0.3px;
        }
        .data-table td { border: 1px solid #e8e8e8; padding: 9px 12px; vertical-align: top; }
        .data-table tr:nth-child(even) td { background: #fafafa; }
        .pub-estado-actual { color: #28a745; font-weight: 700; }
        .bloom-badge {
            display: inline-block; background: #fff0f0; color: #cc0000;
            border: 1px solid #ffcccc; border-radius: 12px;
            padding: 2px 10px; font-size: 12px; font-weight: 600;
        }

        /* ========== BIBLIO LIST ========== */
        .biblio-list { list-style: none; padding: 0; }
        .biblio-list li {
            padding: 8px 12px 8px 32px; position: relative;
            border-bottom: 1px solid #f0f0f0; font-size: 13.5px; line-height: 1.6;
        }
        .biblio-list li:last-child { border-bottom: none; }
        .biblio-list li::before {
            content: attr(data-num); position: absolute; left: 8px; top: 8px;
            font-weight: 700; color: #cc0000; font-size: 12px;
        }


        /* ========== EVALUATION TABLE ========== */
        .eval-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .eval-table th {
            border-left: 4px solid #cc0000; padding: 8px 12px;
            text-align: left; font-size: 14px; font-weight: 700; color: #222; background: #fff;
        }
        .eval-table th .eval-th-sub {
            display: block; font-size: 11px; font-weight: 400; color: #999; margin-top: 2px;
        }
        .eval-table td { padding: 8px 12px; border-bottom: 1px solid #eee; vertical-align: middle; }
        .eval-table tr:last-child td { border-bottom: none; }
        .eval-input-text {
            width: 100%; border: 1px solid #ccc; border-radius: 4px;
            padding: 7px 10px; font-size: 13px; font-family: inherit;
        }
        .eval-input-text:focus { outline: none; border-color: #cc0000; box-shadow: 0 0 0 2px rgba(204,0,0,0.1); }
        .eval-input-num {
            width: 90px; border: 1px solid #ccc; border-radius: 4px;
            padding: 7px 10px; font-size: 13px; font-family: inherit; text-align: right;
        }
        .eval-input-num:focus { outline: none; border-color: #cc0000; box-shadow: 0 0 0 2px rgba(204,0,0,0.1); }
        .btn-remove-row {
            background: none; border: none; color: #cc0000;
            cursor: pointer; font-size: 16px; padding: 0 4px; opacity: 0.6;
        }
        .btn-remove-row:hover { opacity: 1; }
        .eval-total-row {
            display: flex; justify-content: flex-end; align-items: center;
            gap: 12px; padding: 8px 12px; font-size: 13px; font-weight: 700; color: #333;
        }
        .eval-total-ok { color: #28a745; }
        .eval-total-err { color: #cc0000; }
        .btn-add-row {
            background: none; border: 1.5px dashed #ccc; color: #888;
            padding: 7px 16px; border-radius: 4px; font-size: 13px; cursor: pointer;
            width: 100%; text-align: center; margin-top: 4px;
        }
        .btn-add-row:hover { border-color: #cc0000; color: #cc0000; }

        /* ========== CRONOGRAMA LISTA MULTIPLE ========== */
        .crono-inactivo { opacity: 0.55; }
        .crono-upload-error .btn-upload {
            border-color: #c62828 !important;
            background-color: #ffebee !important;
            color: #c62828 !important;
        }
        .crono-badge-inactivo {
            display: inline-block; background: #e0e0e0; color: #777;
            font-size: 11px; padding: 1px 6px; border-radius: 3px; margin-left: 6px;
        }
        .crono-toggle-activo {
            font-size: 12px; color: #555; cursor: pointer;
            display: flex; align-items: center; gap: 3px; white-space: nowrap;
        }

        /* ========== CRONOGRAMA UPLOAD ========== */
        .upload-area {
            border: 2px dashed #ddd; border-radius: 6px; padding: 28px;
            text-align: center; background: #fafafa; cursor: pointer; transition: border-color 0.2s;
        }
        .upload-area:hover { border-color: #cc0000; background: #fff5f5; }
        .upload-icon { font-size: 36px; color: #ccc; margin-bottom: 10px; }
        .upload-label { font-size: 14px; color: #666; margin-bottom: 6px; }
        .upload-sublabel { font-size: 12px; color: #aaa; }
        .btn-upload {
            display: inline-block; background: #fff; border: 1px solid #ccc;
            padding: 8px 20px; border-radius: 4px; font-size: 13px; cursor: pointer; margin-top: 12px; color: #444;
        }
        .btn-upload:hover { border-color: #888; }
        .file-attached {
            display: flex; align-items: center; gap: 10px;
            background: #f0fff4; border: 1px solid #b2dfdb; border-radius: 4px; padding: 10px 14px; font-size: 13px;
        }
        .file-icon { font-size: 20px; }
        .file-name { font-weight: 600; color: #1a7a4a; }
        .file-size { color: #888; font-size: 12px; }
        .btn-remove-file {
            margin-left: auto; background: none; border: none;
            color: #cc0000; cursor: pointer; font-size: 18px; opacity: 0.6;
        }
        .btn-remove-file:hover { opacity: 1; }

        /* ========== BIBLIO EVOLUTIVA ========== */
        .biblio-ev-list { list-style: none; padding: 0; margin-bottom: 10px; }
        .biblio-ev-item {
            display: flex; align-items: flex-start; gap: 8px; flex-wrap: wrap;
            padding: 8px 0; border-bottom: 1px solid #f0f0f0;
        }
        .biblio-ev-llm-result {
            flex: 1 1 100%;
            width: 100%;
            order: 99;
            margin: 8px 0 0 28px;
            padding: 12px;
            border-left: 4px solid #ccc;
            background-color: #f9f9f9;
            font-size: 12px;
            line-height: 1.6;
            box-sizing: border-box;
        }
        .biblio-ev-item:last-child { border-bottom: none; }
        .biblio-ev-num {
            font-weight: 700;
            font-size: 13px;
            color: #333;
            min-width: 20px;
            padding: 7px 0;
            flex-shrink: 0;
            line-height: 1.5;
        }
        .biblio-ev-text {
            flex: 1; border: 1px solid #e0e0e0; border-radius: 4px;
            padding: 7px 10px; font-size: 13px; font-family: inherit; line-height: 1.5;
            resize: vertical; min-height: 40px;
        }
        .biblio-ev-text:focus { outline: none; border-color: #cc0000; }

        /* ========== EDICION INDIVIDUAL (patron admin) ========== */
        /*.btn-edit-field {
            background: #f0a500;
            border: none;
            color: #fff;
            cursor: pointer;
            font-size: 17px;
            line-height: 1;
            width: 30px;
            height: 30px;
            padding: 0;
            border-radius: 50%;
            vertical-align: middle;
            opacity: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.18);
            transition: background 0.15s ease, transform 0.15s ease;
        }
        .btn-edit-field:hover {
            background: #d9940a;
            color: #fff;
            transform: scale(1.08);
        }
        .btn-edit-field:active {
            transform: scale(0.96);
        }*/

                 /* ========== EDICION INDIVIDUAL (patron admin) ========== */
        .btn-edit-field {
            background: #f0a500;
            border: none;
            color: #fff;
            cursor: pointer;
            font-size: 11px;
            line-height: 1;
            width: 18px;
            height: 18px;
            padding: 0;
            margin-right: 4px;
            border-radius: 50%;
            vertical-align: middle;
            opacity: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.15);
            transition: background 0.15s ease;
        }
        .btn-edit-field:hover {
            background: #d9940a;
            color: #fff;
        }
        
        .btn-edit-field:active {
            transform: scale(0.96);
        }

        .row-mark-delete,
        .row-mark-delete td,
        li.row-mark-delete {
            background-color: #ffebee !important;
            color: #c62828 !important;
            text-decoration: line-through;
        }

        /* Cronograma marcado para eliminar: rojo mas intenso para distinguirlo
           del rojo claro de errores ortograficos. */
        #cronoBody tr.row-mark-delete > td {
            background-color: #b71c1c !important;
            color: #fff !important;
            text-decoration: line-through;
        }
        #cronoBody tr.row-mark-delete .file-name,
        #cronoBody tr.row-mark-delete .crono-spell-badge,
        #cronoBody tr.row-mark-delete .item-view-text {
            color: #fff !important;
        }


        .item-view-text {
            display: block;
            padding: 7px 10px;
            font-size: 13px;
            line-height: 1.5;
        }

        .eval-input-text,
        .eval-input-num,
        .biblio-ev-text {
            /* los inputs quedan ocultos en filas existentes hasta pulsar lapiz */
        }


        /* ========== ACTION BAR ========== */
        .action-bar {
            background: #f9f9f9; border-top: 2px solid #e0e0e0;
            padding: 20px 0 40px; display: flex; flex-wrap: wrap;
            align-items: center; justify-content: center; gap: 10px; margin-top: 40px;
        }
        .action-bar-spellcheck-llm {
            flex: 1 0 100%;
            display: flex;
            justify-content: center;
            margin-top: 6px;
        }
        .action-bar-spellcheck-llm label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #555;
            cursor: pointer;
            user-select: none;
        }
        .action-bar-spellcheck-llm input {
            width: 15px;
            height: 15px;
            cursor: pointer;
        }

        /* ========== MODAL: APROBAR ========== */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.55); z-index: 200;
            align-items: center; justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: #fff; border-radius: 8px; padding: 32px 36px;
            max-width: 480px; width: 90%; box-shadow: 0 8px 40px rgba(0,0,0,0.25);
        }
        .modal-box.modal-bitacora-box {
            position: relative;
            max-width: min(1450px, 96vw);
            width: 96%;
            max-height: 88vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            text-align: left;
            padding: 28px 32px 24px;
        }
        .modal-box h3 { font-size: 18px; font-weight: 700; color: #222; margin-bottom: 14px; }
        .modal-box p { font-size: 14px; color: #555; line-height: 1.7; margin-bottom: 24px; }
        .modal-actions { display: flex; gap: 12px; justify-content: flex-end; }
        .btn-modal-cancel {
            background: #fff; border: 1px solid #ccc; color: #444;
            padding: 9px 24px; border-radius: 5px; font-size: 14px; font-weight: 600; cursor: pointer;
        }
        .btn-modal-cancel:hover { border-color: #888; }
        .btn-modal-confirm {
            background: #28a745; border: none; color: #fff;
            padding: 9px 24px; border-radius: 5px; font-size: 14px; font-weight: 700; cursor: pointer;
        }
        .btn-modal-confirm:hover { background: #1e7e34; }

        /* ========== WARNING BANNER ========== */
        .warning-banner {
            background: #fffbe6; border: 1px solid #ffe58f; border-radius: 4px;
            padding: 12px 16px; font-size: 13px; color: #856404;
            display: flex; align-items: center; gap: 8px; margin-bottom: 32px;
        }
        .warning-icon { font-size: 16px; }

        /* ========== STATUS BAR ========== */
        .status-bar {
            display: flex; align-items: center; justify-content: space-between;
            background: #f9f9f9; border: 1px solid #e8e8e8; border-radius: 4px;
            padding: 8px 16px; margin-bottom: 24px; font-size: 13px; color: #666;
        }
        .status-dot {
            display: inline-block; width: 10px; height: 10px;
            border-radius: 50%; margin-right: 6px;
        }
        .status-dot.draft { background: #f0a500; }
        .status-dot.approved { background: #28a745; }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 640px) {
            .cimp-block { flex-direction: column; }
            .prof-grid { grid-template-columns: 1fr; }
            .action-bar { flex-direction: column; gap: 12px; }
        }

              /* ========== VALIDACION: campos en error (borde + fondo rojo) ========== */
        /* ========== VALIDACION: campos en error (borde + fondo rojo) ========== */
        .field-error {
            border: 2px solid #cc0000 !important;
            background-color: #ffe5e5 !important;
            box-shadow: 0 0 0 2px rgba(204, 0, 0, 0.15) !important;
        }
        .section-pub-error {
            outline: 2px solid #cc0000;
            outline-offset: 4px;
            border-radius: 4px;
            background-color: #fff8f8;
        }
        #wrapEditorNormas.field-error .note-editor {
            border: 2px solid #cc0000 !important;
        }
        #wrapEditorNormas.field-error .note-editable {
            background-color: #ffe5e5 !important;
        }
        #wrapEditorUsoIA.field-error .note-editor,
        #wrapEditorPensamientoCritico.field-error .note-editor {
            border: 2px solid #cc0000 !important;
        }
        #wrapEditorUsoIA.field-error .note-editable,
        #wrapEditorPensamientoCritico.field-error .note-editable {
            background-color: #ffe5e5 !important;
        }
        [id^="wrapEditorBiblio_"].field-error .note-editor {
            border: 2px solid #cc0000 !important;
        }
        [id^="wrapEditorBiblio_"].field-error .note-editable {
            background-color: #ffe5e5 !important;
        }
        #evalTotal.field-error {
            border: 2px solid #cc0000;
            background-color: #ffe5e5;
            border-radius: 4px;
            padding: 2px 8px;
        }

        /* ========== MODAL VALIDACION (blur + centrado) ========== */
        .modal-overlay.modal-blur {
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            background: rgba(0, 0, 0, 0.45);
        }
        #modalBitacoraDetalle { z-index: 210; }
        #modalBitacora { z-index: 200; }
        #modalPdfViewer { z-index: 220; }
        #modalVersionesPublicadas { z-index: 195; }
        .modal-box.modal-error {
            text-align: center;
            max-width: 420px;
        }
        .modal-box.modal-error .modal-error-icon {
            font-size: 36px;
            color: #cc0000;
            margin-bottom: 12px;
            display: block;
        }
        .modal-box.modal-error p {
            margin-bottom: 28px;
            color: #333;
            font-size: 15px;
        }

        /* ========== SPELLCHECK CRONOGRAMA (marcas verde/gris; sin fondo rojo en filas con errores) ========== */
        #modalSpellcheck { z-index: 230; }
        tr.crono-spell-ok      > td { background-color: #eafbea; }
        tr.crono-spell-omitido > td { background-color: #f2f2f2; }
        tr.crono-sin-contenido > td {
            background-color: #ffb3b3 !important;
        }
        tr.crono-sin-contenido {
            box-shadow: inset 4px 0 0 #cc0000;
        }
        .crono-spell-badge {
            display: inline-block;
            margin-left: 8px;
            vertical-align: middle;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.6;
            white-space: nowrap;
        }
        .crono-spell-badge.is-error {
            background-color: #cc0000;
            color: #fff;
            cursor: pointer;
        }
        .crono-spell-badge.is-error:hover { background-color: #a30000; }
        .crono-spell-badge.is-ok {
            background-color: #1e7e34;
            color: #fff;
        }
        .crono-spell-badge.is-omitido {
            background-color: #9e9e9e;
            color: #fff;
        }
        .btn-crono-rev {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 6px;
            margin-left: 0;
            padding: 4px 10px;
            font-size: 12px;
            font-weight: 600;
            color: #b45309;
            background: #fff8e6;
            border: 1px solid #f0ad4e;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-crono-rev:hover { background: #ffefcc; }
        .btn-crono-rev-icon { font-size: 14px; line-height: 1; }
        .uac-flash-banner {
            display: none;
            margin: 12px 24px 0;
            padding: 12px 16px;
            border-radius: 6px;
            font-size: 14px;
            position: relative;
        }
        .uac-flash-banner.is-warning {
            background: #fff8e6;
            border: 1px solid #f0ad4e;
            color: #7a4d00;
        }
        .uac-flash-banner.is-success {
            background: #eafbea;
            border: 1px solid #6fbf73;
            color: #1e5c2e;
        }
        .uac-flash-banner.is-published {
            background: #e8f4fd;
            border: 1px solid #5bc0de;
            color: #0c5460;
        }
        .uac-flash-close {
            position: absolute;
            right: 10px;
            top: 8px;
            border: none;
            background: transparent;
            font-size: 18px;
            cursor: pointer;
            color: inherit;
            line-height: 1;
        }
        #modalPublicarRevision .pub-rev-list {
            margin: 16px 0;
            max-height: 320px;
            overflow-y: auto;
        }
        #modalPublicarRevision .pub-rev-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        #modalPublicarRevision .pub-rev-item:last-child { border-bottom: none; }
        .spell-file-block {
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 12px 14px;
            margin-bottom: 14px;
        }
        .spell-file-block h4 {
            margin: 0 0 8px 0;
            font-size: 15px;
            color: #222;
        }
        .spell-file-block h4 .spell-file-count {
            color: #cc0000;
            font-weight: 700;
        }
        .spell-error-list { list-style: none; margin: 0; padding: 0; }
        .spell-error-list li {
            padding: 6px 0;
            border-bottom: 1px dashed #eee;
            font-size: 14px;
        }
        .spell-error-list li:last-child { border-bottom: none; }
        .spell-word { font-weight: 700; color: #cc0000; }
        .spell-type {
            display: inline-block;
            font-size: 11px;
            text-transform: uppercase;
            color: #666;
            margin-left: 6px;
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 0 6px;
        }
        .spell-sugg { color: #1e7e34; font-weight: 600; }
        .spell-empty-ok { color: #1e7e34; font-weight: 600; }

    </style>



	<link href="libraries/summernote-0.8.18-dist/summernote-lite.min.css" charset="UTF-8" rel="stylesheet">
</head>
<body>

<!-- ========== HEADER ========== -->

<header class="site-header">
    <div class="site-header-inner">
        <div class="site-logo">
            <div class="site-logo-badge">
                UFM<br>&#9632;&#9632;&#9632;
            </div>
            <span class="site-logo-text">UFM Syllabus &ndash; Catedr&aacute;tico</span>
        </div>
        <nav class="site-header-nav">
            <div class="header-right">
                <a href="https://compras135.ufm.edu/MiU/mis_cursos.php?typeContent=cursos_actuales" class="btn-header-miu">Regresar</a>
            </div>
        </nav>
    </div>
</header>

<div id="bannerFlashUAC" class="uac-flash-banner" role="status" style="display:none;">
    <span id="bannerFlashUACMsg"></span>
    <button type="button" class="uac-flash-close" onclick="cerrarBannerFlashUAC()" title="Cerrar">&times;</button>
</div>

<!-- ========== MAIN CONTENT ========== -->
<div class="container">

    <!-- Course Title -->
    <h1 class="course-title"><?php print htmlspecialchars($arrCurso['TITULO']); ?></h1>

    <!-- CIMP Block -->
    <div class="cimp-block">
        <div class="cimp-left">
            <div class="cimp-item">
                <div class="cimp-label">UMA</div>
                <div class="cimp-value"><?php print $arrCurso['UMA']; ?></div>
            </div>
            <div class="cimp-item">
                <div class="cimp-label">Semestre</div>
                <div class="cimp-value"><?php print $arrCurso['SEMESTRE']; ?></div>
            </div>
            <div class="cimp-item">
                <div class="cimp-label">A&ntilde;o</div>
                <div class="cimp-value"><?php print $arrCurso['ANIO']; ?></div>
            </div>
            <div class="cimp-item">
                <div class="cimp-label">Secci&oacute;n</div>
                <div class="cimp-value"><?php print $arrCurso['SECCION']; ?></div>
            </div>

            <!--
            <div class="cimp-item">
                <div class="cimp-label">C&oacute;digo del curso:</div>
                <div class="cimp-value"><?php //print htmlspecialchars($arrCurso['CODIGO']); ?></div>
            </div>
            -->

            <div class="cimp-item">
                <div class="cimp-label">&Aacute;rea acad&eacute;mica</div>
                <div class="cimp-value"><?php print htmlspecialchars($arrCurso['AREA_ACADEMICA']); ?></div>
            </div>

            <div class="cimp-item">
                <div class="cimp-label">Facultad</div>
                <div class="cimp-value"><?php print htmlspecialchars($arrCurso['FACULTAD']); ?></div>
            </div>
                     
        </div>
        <div class="cimp-right">
            <div class="cimp-right-title">Informaci&oacute;n de los profesores</div>
            <?php foreach($arrProfesores as $prof):
                $esAuxiliar = (stripos($prof['ROL'] ?? '', 'auxiliar') !== false);
                $labelNombre = $esAuxiliar ? 'Nombre del profesor auxiliar'           : 'Nombre del profesor';
                $labelEmail  = $esAuxiliar ? 'Correo electr&oacute;nico del profesor auxiliar' : 'Correo electr&oacute;nico';
            ?>
            <div class="prof-grid" style="margin-bottom:12px;">
                <div>
                    <div class="prof-label"><?php print $labelNombre; ?></div>
                    <div class="prof-value"><?php print htmlspecialchars($prof['NOMBRE']); ?></div>
                </div>
                <div>
                    <div class="prof-label"><?php print $labelEmail; ?></div>
                    <div class="prof-value"><?php print htmlspecialchars($prof['EMAIL']); ?></div>
                </div>
            </div>
            <hr style="border:none;border-top:1px solid #eee;margin-bottom:12px;">
            <?php endforeach; ?>
 
        </div>
    </div>

            <?php if ($syllabusUA === null): ?>
            <div style="
                background: #fff8e1;
                border: 1px solid #f5c518;
                border-left: 4px solid #f5c518;
                border-radius: 4px;
                padding: 20px 24px;
                margin: 32px 0;
                display: flex;
                align-items: flex-start;
                gap: 14px;
            ">
                <span style="font-size: 22px; line-height: 1;">&#9888;</span>
                <div>
                    <div style="font-weight: 700; font-size: 15px; color: #7a5c00; margin-bottom: 6px;">
                        Syllabus no disponible
                    </div>
                    <div style="font-size: 13.5px; color: #5a4200; line-height: 1.6;">
                        La unidad academica no ha definido el syllabus para este curso todavia.
                        No es posible continuar hasta que la unidad academica registre el contenido base del curso.
                    </div>
                </div>
            </div>
        </div><!-- /container -->
        </body>
        </html>
        <?php die(); ?>
        <?php endif; ?>

    <!-- Toolbar superior -->
    <div class="cimp-toolbar-wrap">
    <!-- Toolbar: ver version actual -->
    <div class="cimp-toolbar-section">
        <div class="cimp-toolbar-section-title">Ver versi&oacute;n actual</div>
        <div class="cimp-toolbar-row">
            <?php if ($intIdPublicadaVigente > 0 && $boolTienePdfVigente) { ?>
            <button type="button" class="btn-toolbar-ver-pdf"
                onclick="fntAbrirVisorPdfPublicado(<?php print $intIdPublicadaVigente; ?>, 'Version actual publicada');"
                title="Ver PDF publicado">
                <span class="btn-ver-icon" aria-hidden="true">&#128065;</span> Ver PDF
            </button>
            <?php } elseif ($intIdPublicadaVigente > 0) { ?>
            <span class="cimp-toolbar-meta"><em>Sin PDF disponible para la versi&oacute;n vigente</em></span>
            <?php } else { ?>
            <div class="cimp-toolbar-alert" role="alert">
                <span class="cimp-toolbar-alert-icon" aria-hidden="true">&#9888;</span>
                <span>No hay ninguna versi&oacute;n publicada</span>
            </div>
            <?php } ?>
        </div>
    </div>

    <?php if ($intContadorPublicaciones > 0) { ?>
    <!-- Toolbar: versiones publicadas -->
    <div class="cimp-toolbar-section">
        <div class="cimp-toolbar-section-title">Versiones publicadas</div>
        <div class="cimp-toolbar-row">
            <span class="cimp-toolbar-meta">
                &Uacute;ltima publicaci&oacute;n: <strong><?php print htmlspecialchars($strUltimaPublicacionFmt); ?></strong>
            </span>
            <button type="button" class="cimp-toolbar-link"
                onclick="fntMostrarVersionesPublicadas()">Ver versiones</button>
        </div>
    </div>
    <?php } ?>
    </div><!-- /cimp-toolbar-wrap -->


    <!-- ================================================== -->
    <!-- PARTE FIJA - Definida por la Unidad Academica       -->
    <!-- ================================================== -->
    <div class="part-header">
        <div class="part-header-line"></div>
        <span class="part-header-label ua">Definido por la Unidad Acad&eacute;mica &mdash; Solo lectura</span>
        <div class="part-header-line"></div>
    </div>

    <!-- 1. Descripcion institucional -->
    <div class="section">
        <div class="section-header">
            <div class="section-title">Descripci&oacute;n institucional del curso</div>
            <span class="badge-readonly">Solo lectura</span>
        </div>
        <div class="readonly-box"><?php print $strDescInstitucional; ?></div>
    </div>

    <!-- 2. Aporte al plan de estudios -->
    <div class="section">
        <div class="section-header">
            <div class="section-title">Aportes al plan de estudios / perfil de egreso</div>
            <span class="badge-readonly">Solo lectura</span>
        </div>
        <div class="readonly-box"><?php print $strAportePlan; ?></div>
    </div>

    <!-- 3. Resultados de Aprendizaje -->
    <div class="section">
        <div class="section-header">
            <div class="section-title">Resultados de aprendizaje del curso</div>
            <span class="badge-readonly">Solo lectura</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:18%">Nivel Bloom</th>
                    <th>Descripci&oacute;n</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($arrRA as $rRA): ?>
                <tr>
                    <td><span class="bloom-badge"><?php print $rRA['BLOOM']; ?></span></td>
                    <td><?php print htmlspecialchars($rRA['DESC']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- 4. Conocimientos previos -->
    <div class="section">
        <div class="section-header">
            <div class="section-title">Conocimientos previos esperados</div>
            <span class="badge-readonly">Solo lectura</span>
        </div>
        <div class="readonly-box"><?php print $strConocPrevios; ?></div>
    </div>

    <!-- 5. Bibliografia base minima -->
    <div class="section">
        <div class="section-header">
            <div class="section-title">Bibliograf&iacute;a base m&iacute;nima (curada por la unidad)</div>
            <span class="badge-readonly">Solo lectura</span>
            <span class="info-icon info-icon-clickable" title="Ver informaci&oacute;n"
                  onclick="mostrarAyuda(<?php print json_encode('Bibliograf?a base m?nima'); ?>);">i</span>
        </div>
        <div class="readonly-box" style="padding:8px 0;">
            <ul class="biblio-list">
                <?php foreach($arrBiblioUA as $i => $ref): ?>
                <li data-num="<?php print $i+1; ?>"><?php print uac_renderReferenciaBiblioVista($ref); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- 6. Marco normativo -->
    <div class="section">
        <div class="section-header">
            <div class="section-title">Marco normativo institucional</div>
            <span class="badge-readonly">Solo lectura</span>
        </div>
        <div class="readonly-box"><?php print $strMarco; ?></div>
    </div>


    <!-- ================================================== -->
    <!-- PARTE DEL CATEDRATICO - Editable                   -->
    <!-- ================================================== -->
    <div class="part-header">
        <div class="part-header-line"></div>
        <span class="part-header-label catedratico">Definido por el Catedr&aacute;tico</span>
        <div class="part-header-line"></div>
    </div>

    <!-- Importar / Exportar syllabus de otros CIMP -->
    <div class="cat-import-export">
        <button type="button" class="btn-toolbar btn-toolbar-import" disabled>Importar</button>
        <button type="button" class="btn-toolbar btn-toolbar-export" disabled>Exportar</button>
        <span class="info-icon" data-desc="Aqu&iacute; podr&aacute; importar o exportar syllabus de otros cimps de sus cursos">i</span>
    </div>

    <!-- 7. Normas y reglas operativas -->
    <div class="section" id="secNormas">
                <div class="section-header">
    <div class="section-title">Normas y reglas operativas del curso</div>
    <span class="badge-catedratico">Editable</span>
    <button type="button" class="btn-bitacora" title="Ver bit&aacute;cora"
        <?php if ($intSyllabusCatedratico <= 0) print 'disabled'; ?>
        onclick="fntMostrarBitacoraNormas();">Ver bit&aacute;cora</button>
    <span class="info-icon" data-desc="Reglas y normas que rigen el desarrollo del curso">i</span>
    <button type="button" class="btn-edit-field" id="btnEditNormas"
        title="Editar" onclick="fntEditNormas();">&#9998;</button>
</div>

        <p class="section-subtitle">&iquest;Cu&aacute;les son las normas y reglas operativas que los estudiantes deben cumplir durante el curso?</p>
        

<input type="hidden" id="hidEditedNormas" value="N">
<input type="hidden" id="hidUltimoValidadoNormas" value="">
<input type="hidden" id="hidEstadoLLMNormas" value="">
<input type="hidden" id="hidAceptadoManualNormas" value="">

<div id="viewNormas" class="readonly-box">
    <?php print !empty($strNormas) ? $strNormas : '<em>Sin informaci&oacute;n</em>'; ?>
</div>

<div id="wrapEditorNormas" style="display:none;">
    <textarea id="txtNormas" style="width:100%;">
<?php print htmlspecialchars($strNormas); ?>
    </textarea>
</div>

<div id="divResultadoLLMNormas" style="display:none; margin:10px 0;"></div>

        <div style="margin-top:8px; min-height:20px;">
            <span id="iaStatusNormas" style="font-size:12px;"></span>
        </div>
    </div>

    <!-- 8. Evaluacion del curso -->
    <div class="section" id="secEvaluacion">


                <div class="section-header">
            <div class="section-title">Evaluaci&oacute;n del curso</div>
            <span class="badge-catedratico">Editable</span>
            <button type="button" class="btn-bitacora" title="Ver bit&aacute;cora"
                <?php if ($intSyllabusCatedratico <= 0) print 'disabled'; ?>
                onclick="fntMostrarBitacoraTodosEval();">Ver bit&aacute;cora</button>
            <span class="info-icon" data-desc="Distribuci&oacute;n porcentual de las actividades de evaluaci&oacute;n">i</span>
        </div>

        <p class="section-subtitle">&iquest;C&oacute;mo se distribuye la evaluaci&oacute;n del curso? La suma de los porcentajes debe ser exactamente 100%.</p>

        <table class="eval-table" id="evalTable">
            <thead>
                <tr>
                    <th>
                        Actividad de aprendizaje *
                        <span class="eval-th-sub">(Debe llenar estos campos antes de publicar el programa)</span>
                    </th>
                    <th style="width:160px;">
                        Porcentaje *
                        <span class="eval-th-sub">(Debe llenar estos campos antes de publicar)</span>
                    </th>
                    <th style="width:70px;"></th>
                </tr>
            </thead>
            <tbody id="evalBody">

<?php
$nEval = 0;
foreach ($arrEvaluacion as $ev):
    $nEval++;
    $n = $nEval;
?>

<tr id="trEval_<?php print $n; ?>">
    <td>
        <span id="spanRubroEval_<?php print $n; ?>" class="item-view-text">
            <?php print htmlspecialchars($ev['RUBRO']); ?>
        </span>
        <input type="text" class="eval-input-text eval-rubro"
               id="txtRubroEval_<?php print $n; ?>"
               name="txtRubroEval_<?php print $n; ?>"
               style="display:none;" readonly
               value="<?php print htmlspecialchars($ev['RUBRO']); ?>">
        <div id="divResultadoLLMRubroEval_<?php print $n; ?>"
             style="display:none; margin-top:6px; padding:10px; border-left:4px solid #ccc;
                    background-color:#f9f9f9; font-size:12px; line-height:1.5;"></div>
        <input type="hidden" id="hidEstadoLLMRubroEval_<?php print $n; ?>" value="">
    </td>
    
    <td>
        <span id="spanPctEval_<?php print $n; ?>" class="item-view-text">
            <?php print $ev['PORCENTAJE']; ?>%
        </span>
        <span id="wrapPctEdit_<?php print $n; ?>" style="display:none;">
            <input type="number" class="eval-input-num eval-pct"
                   id="txtPctEval_<?php print $n; ?>"
                   name="txtPctEval_<?php print $n; ?>"
                   readonly
                   value="<?php print $ev['PORCENTAJE']; ?>"
                   min="0" max="100" onchange="recalcTotal()"> %
        </span>
    </td>

    <td align="center" style="white-space:nowrap;">
        <button type="button" class="btn-edit-field"
                onclick="fntEditEval(<?php print $n; ?>)" title="Editar">&#9998;</button>
        <button type="button" class="btn-remove-row"
                onclick="fntDeleteEval(<?php print $n; ?>)" title="Eliminar">&#215;</button>
        <input type="hidden" id="hidDeleteEval_<?php print $n; ?>" name="hidDeleteEval_<?php print $n; ?>" value="N">
        <input type="hidden" id="hidEditedEval_<?php print $n; ?>" name="hidEditedEval_<?php print $n; ?>" value="N">
        <input type="hidden" id="hidUpdateEval_<?php print $n; ?>" name="hidUpdateEval_<?php print $n; ?>"
               value="<?php print intval($ev['ID']); ?>">
    </td>
</tr>
<?php endforeach; ?>

            </tbody>
        </table>

        
        <!-- Plantilla oculta: FUERA de evalBody para evitar IDs duplicados -->
        <table style="display:none;" aria-hidden="true">
            <tbody>
                <tr id="trEvalInicial">
                    <td>
                        <input type="text" class="eval-input-text eval-rubro"
                               id="txtRubroEval_0" placeholder="Nombre de la actividad">
                        <div id="divResultadoLLMRubroEval_0"
                             style="display:none; margin-top:6px; padding:10px; border-left:4px solid #ccc;
                                    background-color:#f9f9f9; font-size:12px; line-height:1.5;"></div>
                        <input type="hidden" id="hidEstadoLLMRubroEval_0" value="">
                    </td>
                    <td>
                        <input type="number" class="eval-input-num eval-pct"
                               id="txtPctEval_0" value="0" min="0" max="100"
                               onchange="recalcTotal()"> %
                    </td>
                    <td align="center">
                        <button type="button" class="btn-remove-row"
                                onclick="fntDeleteEval(0)" title="Eliminar">&#215;</button>
                        <input type="hidden" id="hidDeleteEval_0" value="N">
                        <input type="hidden" id="hidNewEval_0" value="1">
                    </td>
                </tr>
            </tbody>
        </table>

        <script>var intContadorEval = <?php print intval($intContadorEval); ?>;</script>

        <div class="eval-total-row">
            Total: <span id="evalTotal" class="eval-total-ok">100%</span>
        </div>
        <button class="btn-add-row" onclick="addEvalRow()">+ Agregar actividad de evaluaci&oacute;n</button>
    </div>

    <!-- 9. Cronograma -->
    <div class="section" id="secCronograma">

        <div class="section-header">
            <div class="section-title">Cronograma de actividades</div>
            <span class="badge-catedratico">Editable</span>
            <button type="button" class="btn-bitacora" title="Ver bit&aacute;cora"
                <?php if ($intSyllabusCatedratico <= 0) print 'disabled'; ?>
                onclick="fntMostrarBitacoraTodosCronogramas();">Ver bit&aacute;cora</button>
            <span class="info-icon" data-desc="Cronograma de actividades">i</span>
        </div>

        <p class="section-subtitle">En formato PDF, Word, Excel o PowerPoint. (Debe adjuntar al menos un cronograma al syllabus antes de publicar el programa)</p>

        <table class="eval-table" id="cronoTable">
            <thead>
                <tr>
                    <th>Archivo</th>
                    <th style="width:260px;">&iquest;El archivo debe mostrarse en el .pdf del syllabus?</th>
                    <th style="width:80px;"></th>
                </tr>
            </thead>
            <tbody id="cronoBody">
<?php
$nCrono = 0;
foreach ($arrCronogramas as $itemCrono):
    $nCrono++;
    $n = $nCrono;
    $intCronoId   = intval($itemCrono['ID']);
    $strNomCrono  = htmlspecialchars($itemCrono['NOMBRE'], ENT_QUOTES, 'ISO-8859-1');
    $strActivoVal = ($itemCrono['ACTIVO'] === 'N') ? 'N' : 'Y';
    $bolInactivo  = ($strActivoVal === 'N');
    $bolTieneRev  = (trim($itemCrono['PATH_REV'] ?? '') !== '');
?>
<tr id="trCrono_<?php print $n; ?>"<?php if ($bolInactivo) print ' class="crono-inactivo"'; ?>
    data-crono-id="<?php print $intCronoId; ?>"
    data-tiene-rev="<?php print $bolTieneRev ? '1' : '0'; ?>">
    <td>
        <button type="button" class="bitacora-btn-descarga file-name"
                onclick="fntDescargarCronoAdjunto(<?php print $intCronoId; ?>)"
                title="Descargar archivo"><?php print $strNomCrono; ?></button>
        <?php if ($bolTieneRev): ?>
        <button type="button" class="btn-crono-rev"
                onclick="fntAbrirRevisionCrono(<?php print $intCronoId; ?>)"
                title="Descargar el documento con la revisi&oacute;n ortogr&aacute;fica marcada">
            <span class="btn-crono-rev-icon" aria-hidden="true">&#9888;</span> Descargar revisi&oacute;n
        </button>
        <?php endif; ?>
        <div id="wrapUploadCrono_<?php print $n; ?>" style="display:none;margin-top:6px;">
            <input type="file" id="fileInputCrono_<?php print $n; ?>"
                   accept=".pdf,.doc,.docx,.docm,.odt,.rtf,.xls,.xlsx,.xlsm,.ods,.csv,.ppt,.pptx,.pptm,.odp"
                   style="display:none;" onchange="handleFileCrono(<?php print $n; ?>, this)">
            <button type="button" class="btn-upload"
                    onclick="document.getElementById('fileInputCrono_<?php print $n; ?>').click()">
                Seleccionar archivo
            </button>
            <span id="spanNuevoArchCrono_<?php print $n; ?>" class="item-view-text" style="margin-left:8px;"></span>
        </div>
    </td>
    <td>
        <label style="cursor:pointer;" id="lblActivoCrono_<?php print $n; ?>">
            <input type="checkbox" id="chkActivoCrono_<?php print $n; ?>"
                   <?php if (!$bolInactivo) print 'checked'; ?>
                   disabled
                   onchange="fntToggleActivoCrono(<?php print $n; ?>)">
        </label>
    </td>
    <td align="center" style="white-space:nowrap;">
        <button type="button" class="btn-edit-field" title="Editar"
                onclick="fntEditCrono(<?php print $n; ?>)">&#9998;</button>
        <button type="button" class="btn-remove-row" title="Eliminar"
                onclick="fntDeleteCrono(<?php print $n; ?>)">&#215;</button>
        <input type="hidden" id="hidDeleteCrono_<?php print $n; ?>" value="N">
        <input type="hidden" id="hidNewCrono_<?php print $n; ?>"    value="N">
        <input type="hidden" id="hidUpdateCrono_<?php print $n; ?>" value="<?php print $intCronoId; ?>">
        <input type="hidden" id="hidEditedCrono_<?php print $n; ?>" value="N">
        <input type="hidden" id="hidActivoCrono_<?php print $n; ?>" value="<?php print $strActivoVal; ?>">
    </td>
</tr>
<?php endforeach; ?>
            </tbody>
        </table>

        <script>var intContadorCrono = <?php print intval($intContadorCrono); ?>;</script>
        <button class="btn-add-row" onclick="addCronoRow()">+ Agregar cronograma</button>

    </div>

    <!-- 10. Bibliografia evolutiva -->
    <div class="section" id="secBiblioEv">
        <div class="section-header">
            <div class="section-title">Bibliograf&iacute;a (evolutiva)</div>
            <span class="badge-catedratico">Editable</span>
            <button type="button" class="btn-bitacora" title="Ver bit&aacute;cora"
                <?php if ($intSyllabusCatedratico <= 0) print 'disabled'; ?>
                onclick="fntMostrarBitacoraTodosBiblioEv();">Ver bit&aacute;cora</button>
            <span class="info-icon info-icon-clickable" title="Ver informaci&oacute;n"
                  onclick="mostrarAyuda(<?php print json_encode('Bibliograf?a (evolutiva)'); ?>);">i</span>

        </div>
        <p class="section-subtitle">Bibliograf&iacute;a adicional y actualizada que el catedr&aacute;tico incorpora al curso. Use formato Chicago para bibliograf&iacute;as; la validaci&oacute;n con IA verifica ese estilo.</p>

        <ul class="biblio-ev-list" id="biblioEvList">
  <?php
$nBiblio = 0;
foreach ($arrBiblioEvolutiva as $item):
    $nBiblio++;
    $n = $nBiblio;
?>
<li class="biblio-ev-item" id="liBiblio_<?php print $n; ?>">
    <span class="biblio-ev-num" id="numBiblio_<?php print $n; ?>"><?php print $n; ?>.</span>
    <div id="spanBiblio_<?php print $n; ?>" class="item-view-text" style="flex:1;">
        <?php print uac_renderReferenciaBiblioVista($item['REFERENCIA']); ?>
    </div>
    <div id="wrapEditorBiblio_<?php print $n; ?>" style="display:none; flex:1;">
        <textarea id="txtBiblio_<?php print $n; ?>"
                  name="txtBiblio_<?php print $n; ?>"
                  style="width:100%;"><?php print htmlspecialchars($item['REFERENCIA']); ?></textarea>
    </div>
    <div id="divResultadoLLMBiblio_<?php print $n; ?>"
         class="biblio-ev-llm-result" style="display:none;"></div>
    <input type="hidden" id="hidEstadoLLMBiblio_<?php print $n; ?>" value="">
    <button type="button" class="btn-edit-field"
            onclick="fntEditBiblioEv(<?php print $n; ?>)" title="Editar">&#9998;</button>
    <button type="button" class="btn-remove-row"
            onclick="fntDeleteBiblioEv(<?php print $n; ?>)" title="Eliminar">&#215;</button>
    <input type="hidden" id="hidDeleteBiblio_<?php print $n; ?>" value="N">
    <input type="hidden" id="hidEditedBiblio_<?php print $n; ?>" value="N">
    <input type="hidden" id="hidUpdateBiblio_<?php print $n; ?>"
           value="<?php print intval($item['ID']); ?>">
</li>
<?php endforeach; ?>

        </ul>

        <!-- Plantilla oculta: FUERA de biblioEvList -->
        <ul style="display:none;" aria-hidden="true">
            <li class="biblio-ev-item" id="liBiblioInicial">
                <div id="spanBiblio_0" style="display:none;"></div>
                <div id="wrapEditorBiblio_0" style="flex:1;">
                    <textarea id="txtBiblio_0" rows="2" style="width:100%;"
                        placeholder="Ingrese la bibliograf&iacute;a completa (formato Chicago)"></textarea>
                </div>
                <div id="divResultadoLLMBiblio_0"
                     class="biblio-ev-llm-result" style="display:none;"></div>
                <input type="hidden" id="hidEstadoLLMBiblio_0" value="">
                <button type="button" class="btn-remove-row"
                        onclick="fntDeleteBiblioEv(0)" title="Eliminar">&#215;</button>
                <input type="hidden" id="hidDeleteBiblio_0" value="N">
                <input type="hidden" id="hidNewBiblio_0" value="1">
                <input type="hidden" id="hidEditedBiblio_0" value="N">
            </li>
        </ul>

        <script>var intContadorBiblioEv = <?php print intval($intContadorBiblioEv); ?>;</script>

        <button class="btn-add-row" onclick="addBiblioEv()">+ Agregar bibliograf&iacute;a</button>
        <div style="margin-top:8px; min-height:20px;">
            <span id="iaStatusBiblio" style="font-size:12px;"></span>
        </div>
    </div>

    <!-- 11. Experiencias principales -->
    <div class="section" id="secExperiencias">
        <div class="section-header">
            <div class="section-title">Experiencias principales</div>
            <span class="badge-catedratico">Editable</span>
            <button type="button" class="btn-bitacora" title="Ver bit&aacute;cora"
                <?php if ($intSyllabusCatedratico <= 0) print 'disabled'; ?>
                onclick="fntMostrarBitacoraTodosExp();">Ver bit&aacute;cora</button>
            <span class="info-icon" data-desc="Actividades que realizar&aacute; el estudiante para lograr los Resultados de aprendizaje">i</span>
        </div>
        <p class="section-subtitle">&iquest;Qu&eacute; har&aacute; el estudiante para lograr los Resultados de aprendizaje?</p>

        <ul class="biblio-ev-list" id="expList">
<?php
$nExp = 0;
foreach ($arrExperiencias as $item):
    $nExp++;
    $n = $nExp;
?>
<li class="biblio-ev-item" id="liExp_<?php print $n; ?>">
    <span id="spanExp_<?php print $n; ?>" class="item-view-text" style="flex:1;">
        <?php print nl2br(htmlspecialchars(strip_tags($item['DESCRIPCION']))); ?>
    </span>
    <textarea class="biblio-ev-text" id="txtExp_<?php print $n; ?>"
              name="txtExp_<?php print $n; ?>"
              style="display:none; flex:1;" rows="2" readonly><?php
        print htmlspecialchars(strip_tags($item['DESCRIPCION']));
    ?></textarea>
    <div id="divResultadoLLMExp_<?php print $n; ?>"
         class="biblio-ev-llm-result" style="display:none;"></div>
    <input type="hidden" id="hidEstadoLLMExp_<?php print $n; ?>" value="">
    <button type="button" class="btn-edit-field"
            onclick="fntEditExp(<?php print $n; ?>)" title="Editar">&#9998;</button>
    <button type="button" class="btn-remove-row"
            onclick="fntDeleteExp(<?php print $n; ?>)" title="Eliminar">&#215;</button>
    <input type="hidden" id="hidDeleteExp_<?php print $n; ?>" value="N">
    <input type="hidden" id="hidEditedExp_<?php print $n; ?>" value="N">
    <input type="hidden" id="hidUpdateExp_<?php print $n; ?>"
           value="<?php print intval($item['ID']); ?>">
</li>
<?php endforeach; ?>
        </ul>

        <script>var intContadorExp = <?php print intval($intContadorExp); ?>;</script>

        <button class="btn-add-row" onclick="addExp()">+ Agregar experiencia</button>
        <div style="margin-top:8px; min-height:20px;">
            <span id="iaStatusExp" style="font-size:12px;"></span>
        </div>
    </div>

    <!-- 12. Uso de IA -->
    <div class="section" id="secUsoIA">
        <div class="section-header">
            <div class="section-title">Uso de IA en el curso</div>
            <span class="badge-catedratico">Editable</span>
            <button type="button" class="btn-bitacora" title="Ver bit&aacute;cora"
                <?php if ($intSyllabusCatedratico <= 0) print 'disabled'; ?>
                onclick="fntMostrarBitacoraUsoIA();">Ver bit&aacute;cora</button>
            <span class="info-icon" data-desc="Pol&iacute;tica del catedr&aacute;tico sobre uso de inteligencia artificial: permitido/prohibido, citaci&oacute;n y l&iacute;mites">i</span>
            <button type="button" class="btn-edit-field" id="btnEditUsoIA"
                title="Editar" onclick="fntEditUsoIA();">&#9998;</button>
        </div>

        <p class="section-subtitle">&iquest;Cu&aacute;l es la pol&iacute;tica de uso de inteligencia artificial en su curso? Indique si est&aacute; permitido o prohibido, c&oacute;mo debe citarse y cu&aacute;les son los l&iacute;mites.</p>

        <input type="hidden" id="hidEditedUsoIA" value="N">
        <input type="hidden" id="hidUltimoValidadoUsoIA" value="">
        <input type="hidden" id="hidEstadoLLMUsoIA" value="">
        <input type="hidden" id="hidAceptadoManualUsoIA" value="">

        <div id="viewUsoIA" class="readonly-box">
            <?php print !empty($strUsoIA) ? $strUsoIA : '<em>Sin informaci&oacute;n</em>'; ?>
        </div>

        <div id="wrapEditorUsoIA" style="display:none;">
            <textarea id="txtUsoIA" style="width:100%;">
<?php print htmlspecialchars($strUsoIA); ?>
            </textarea>
        </div>

        <div id="divResultadoLLMUsoIA" style="display:none; margin:10px 0;"></div>

        <div style="margin-top:8px; min-height:20px;">
            <span id="iaStatusUsoIA" style="font-size:12px;"></span>
        </div>
    </div>

    <!-- 13. Desarrollo del pensamiento critico -->
    <div class="section" id="secPensamientoCritico">
        <div class="section-header">
            <div class="section-title">Desarrollo del pensamiento cr&iacute;tico</div>
            <span class="badge-catedratico">Editable</span>
            <button type="button" class="btn-bitacora" title="Ver bit&aacute;cora"
                <?php if ($intSyllabusCatedratico <= 0) print 'disabled'; ?>
                onclick="fntMostrarBitacoraPensamientoCritico();">Ver bit&aacute;cora</button>
            <span class="info-icon" data-desc="Estrategias y evidencias para promover el pensamiento cr&iacute;tico en el curso">i</span>
            <button type="button" class="btn-edit-field" id="btnEditPensamientoCritico"
                title="Editar" onclick="fntEditPensamientoCritico();">&#9998;</button>
        </div>

        <p class="section-subtitle">&iquest;Qu&eacute; estrategias utilizar&aacute; para desarrollar el pensamiento cr&iacute;tico y qu&eacute; evidencias demostrar&aacute;n ese desarrollo en su curso?</p>

        <input type="hidden" id="hidEditedPensamientoCritico" value="N">
        <input type="hidden" id="hidUltimoValidadoPensamientoCritico" value="">
        <input type="hidden" id="hidEstadoLLMPensamientoCritico" value="">
        <input type="hidden" id="hidAceptadoManualPensamientoCritico" value="">

        <div id="viewPensamientoCritico" class="readonly-box">
            <?php print !empty($strPensamientoCritico) ? $strPensamientoCritico : '<em>Sin informaci&oacute;n</em>'; ?>
        </div>

        <div id="wrapEditorPensamientoCritico" style="display:none;">
            <textarea id="txtPensamientoCritico" style="width:100%;">
<?php print htmlspecialchars($strPensamientoCritico); ?>
            </textarea>
        </div>

        <div id="divResultadoLLMPensamientoCritico" style="display:none; margin:10px 0;"></div>

        <div style="margin-top:8px; min-height:20px;">
            <span id="iaStatusPensamientoCritico" style="font-size:12px;"></span>
        </div>
    </div>

    <!-- Barra de acciones al final de la pagina -->
    <div class="action-bar">
        <button type="button" class="btn-toolbar btn-toolbar-save btn-save" onclick="guardarSyllabus()">Guardar</button>
        <button type="button" class="btn-toolbar btn-toolbar-cancel" onclick="cancelarSyllabus()">Cancelar</button>
        <button type="button" class="btn-toolbar btn-toolbar-approve btn-approve" onclick="abrirModalAprobar()">Guardar y Publicar</button>
        <div class="action-bar-spellcheck-llm">
            <label for="chkUsarSpellcheckLlm" title="Solo para prueba de rendimiento">
                <input type="checkbox" id="chkUsarSpellcheckLlm" name="usar_spellcheck_llm" value="1">
                Usar revisi&oacute;n ortogr&aacute;fica con LLM (prueba)
            </label>
        </div>
    </div>

</div><!-- /container -->


<!-- ========== MODAL: VERSIONES PUBLICADAS ========== -->
<div class="modal-overlay modal-blur" id="modalVersionesPublicadas">
    <div class="modal-box modal-bitacora-box">
        <button type="button" class="modal-bitacora-close" onclick="cerrarModalVersionesPublicadas()" title="Cerrar">&times;</button>
        <h3 style="margin-top:0;">Versiones publicadas</h3>
        <div class="modal-bitacora-body" id="modalVersionesPublicadasContent"></div>
    </div>
</div>

<!-- ========== MODAL: VISOR PDF PUBLICADO ========== -->
<div class="modal-overlay modal-blur" id="modalPdfViewer">
    <div class="modal-box modal-bitacora-box modal-pdf-viewer-box">
        <button type="button" class="modal-bitacora-close" onclick="cerrarModalPdfViewer()" title="Cerrar">&times;</button>
        <h3 id="modalPdfViewerTitulo" style="margin-top:0;">Programa publicado</h3>
        <div class="modal-pdf-viewer-body">
            <iframe id="modalPdfViewerFrame" title="PDF publicado"></iframe>
        </div>
    </div>
</div>

<!-- ========== MODAL: APROBAR ========== -->
<div class="modal-overlay" id="modalAprobar">
    <div class="modal-box">
        <h3>Publicar programa del curso</h3>
        <p>Al publicar el programa del curso, ser&aacute; visible a los alumnos y quedar&aacute; en el registro de la facultad.</p>
        <div class="modal-actions">
            <button class="btn-modal-cancel" onclick="cerrarModalAprobar()">Cancelar</button>
            <button class="btn-modal-confirm" onclick="confirmarAprobacion()">Continuar</button>
        </div>
    </div>
</div>

<!-- ========== MODAL: VALIDACION ========== -->
<div class="modal-overlay modal-blur" id="modalValidacion">
    <div class="modal-box modal-error">
        <span class="modal-error-icon">&#9888;</span>
        <p id="modalValidacionMsg">Favor revisar campos en rojo.</p>
        <div class="modal-actions" style="justify-content: center;">
            <button type="button" class="btn-modal-confirm" onclick="cerrarModalValidacion()">Aceptar</button>
        </div>
    </div>
</div>

<!-- ========== MODAL: BITACORA (principal) ========== -->
<div class="modal-overlay modal-blur" id="modalBitacora">
    <div class="modal-box modal-bitacora-box">
        <button type="button" class="modal-bitacora-close" onclick="cerrarModalBitacora()" title="Cerrar">&times;</button>
        <div class="modal-bitacora-body" id="modalBitacoraContent"></div>
    </div>
</div>

<!-- ========== MODAL: BITACORA (detalle) ========== -->
<div class="modal-overlay modal-blur" id="modalBitacoraDetalle">
    <div class="modal-box modal-bitacora-box">
        <button type="button" class="modal-bitacora-close" onclick="cerrarModalBitacoraDetalle()" title="Cerrar">&times;</button>
        <div class="modal-bitacora-body" id="modalBitacoraDetalleContent"></div>
    </div>
</div>

<!-- ========== MODAL: PUBLICAR CON REVISION ORTOGRAFICA ========== -->
<div class="modal-overlay modal-blur" id="modalPublicarRevision">
    <div class="modal-box modal-bitacora-box" style="max-width:640px;">
        <button type="button" class="modal-bitacora-close" onclick="cerrarModalPublicarRevision()" title="Cerrar">&times;</button>
        <h3 style="margin-top:0;">Revisi&oacute;n ortogr&aacute;fica antes de publicar</h3>
        <p id="modalPublicarRevisionResumen" style="color:#333;font-size:14px;"></p>
        <div class="pub-rev-list" id="modalPublicarRevisionLista"></div>
        <p style="font-size:13px;color:#666;margin-top:8px;">
            Puede corregir los archivos y volver a guardar, o continuar con los adjuntos actuales.
        </p>
        <div class="modal-actions" style="margin-top:16px;justify-content:flex-end;gap:10px;">
            <button type="button" class="btn-modal-cancel" onclick="fntPublicarRevisionRevisarArchivos()">Revisar archivos</button>
            <button type="button" class="btn-modal-confirm" onclick="fntPublicarRevisionConfirmar()">Publicar con los adjuntos actuales</button>
        </div>
    </div>
</div>

<!-- ========== MODAL: ERRORES ORTOGRAFICOS (cronograma) ========== -->
<div class="modal-overlay modal-blur" id="modalSpellcheck">
    <div class="modal-box modal-bitacora-box">
        <button type="button" class="modal-bitacora-close" onclick="cerrarModalSpellcheck()" title="Cerrar">&times;</button>
        <h3 style="margin-top:0;">Errores ortogr&aacute;ficos en cronogramas</h3>
        <p id="modalSpellcheckResumen" style="color:#333;font-size:14px;"></p>
        <div class="modal-bitacora-body" id="modalSpellcheckContent"></div>
        <div class="modal-actions" style="margin-top:16px;justify-content:flex-end;">
            <button type="button" class="btn-modal-confirm" onclick="cerrarModalSpellcheck()">Entendido</button>
        </div>
    </div>
</div>

<!-- Contenido oculto para modal de ayuda bibliogr&aacute;fica -->
<div id="hidContenidoAyudaBiblio" style="display:none;"><?php print $strContenidoModalBiblio; ?></div>

<!-- ========== MODAL: AYUDA BIBLIOGRAF&Iacute;A ========== -->
<div class="modal-overlay modal-blur" id="modalAyuda">
    <div class="modal-box modal-ayuda-box">
        <button type="button" class="modal-bitacora-close" onclick="cerrarModalAyuda()" title="Cerrar">&times;</button>
        <h3 id="modalAyudaTitulo"></h3>
        <div class="modal-ayuda-body" id="modalAyudaContenido"></div>
        <div class="modal-actions" style="margin-top: 16px;">
            <button type="button" class="btn-modal-cancel" onclick="cerrarModalAyuda()">Cerrar</button>
        </div>
    </div>
</div>



<!-- ========== JAVASCRIPT ========== -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>


<script src="libraries/summernote-0.8.18-dist/summernote-lite.min.js"></script>
<script src="syllabus_catedratico_llm.js"></script>

<script>

    var intSyllabusUAC = <?php print intval($intSyllabusCatedratico); ?>;
    var intCimpBitacora = <?php print intval($intCursoImpartido); ?>;
    var __syllabusFlashUAC = <?php echo json_encode($arrFlashUAC, JSON_UNESCAPED_UNICODE); ?>;
    var __syllabusRevisionSesion = <?php echo json_encode($arrRevisionSesion, JSON_UNESCAPED_UNICODE); ?>;
    var _publicarRevisionPendiente = null;
    var strBitacoraUrl = <?php print json_encode($_SERVER['PHP_SELF'] ?? 'syllabus_catedratico.php'); ?>;
    var strActionLLM = <?php print json_encode($strActionLLM); ?>;
    var ICONO_IA_URL = 'https://miu.ufm.edu/intranet/reportesai/icon_ia_sparkle.png';
    var arrResultadosAprendizajeUA = <?php print syl_jsonParaJs($arrRA); ?>;

    var summernoteNormasInit = false;
    var summernoteUsoIAInit = false;
    var summernotePensamientoCriticoInit = false;

function fntIsSummernoteBiblioEvInit(n) {
    return window['summernoteBiblioEvInit_' + n] === true;
}

function fntIniciarSummernoteBiblioEv(n) {
    var textareaId = 'txtBiblio_' + n;
    var wrapEditorId = 'wrapEditorBiblio_' + n;
    if (fntIsSummernoteBiblioEvInit(n)) {
        $('#' + wrapEditorId + ' .note-editor').show();
        return;
    }

    //            ['font', ['bold', 'italic', 'underline']],

    $('#' + textareaId).summernote({
        height: 120,
        toolbar: [
            ['font', ['italic']]
                ]
    });
    $('#' + wrapEditorId + ' .note-editable').css({'background-color': 'white'});
    window['summernoteBiblioEvInit_' + n] = true;
}

function fntGetHtmlBiblioEv(n) {
    if (fntIsSummernoteBiblioEvInit(n)) {
        return $('#txtBiblio_' + n).summernote('code') || '';
    }
    var textarea = document.getElementById('txtBiblio_' + n);
    return textarea ? textarea.value : '';
}

function fntSetHtmlBiblioEv(n, html) {
    if (fntIsSummernoteBiblioEvInit(n)) {
        $('#txtBiblio_' + n).summernote('code', html);
    } else {
        $('#txtBiblio_' + n).val(html);
    }
}

function fntSyncBiblioEvToPost(n) {
    if ($('#hidEditedBiblio_' + n).val() !== 'Y' && $('#hidNewBiblio_' + n).val() !== '1') {
        return;
    }
    $('#txtBiblio_' + n).val(fntGetHtmlBiblioEv(n));
}

function fntBiblioEvEnModoEdicion(n) {
    var wrapEditor = $('#wrapEditorBiblio_' + n);
    return wrapEditor.length > 0 && wrapEditor.css('display') !== 'none';
}

function fntIniciarSummernoteCampo(cfg) {
    if (window[cfg.initFlag]) {
        $('#' + cfg.wrapEditorId + ' .note-editor').show();
        return;
    }
    $('#' + cfg.textareaId).summernote({
        placeholder: cfg.placeholder,
        height: 220,
        toolbar: [
            ['style', ['style']],
            ['font', ['bold','italic','underline']],
            ['para', ['ul','ol','paragraph']],
            ['insert', ['link']]
        ]
    });
    $('.note-editable').css({'background-color': 'white'});
    window[cfg.initFlag] = true;
    if (cfg.initFlag === 'summernoteNormasInit') summernoteNormasInit = true;
    if (cfg.initFlag === 'summernoteUsoIAInit') summernoteUsoIAInit = true;
    if (cfg.initFlag === 'summernotePensamientoCriticoInit') summernotePensamientoCriticoInit = true;
}

function fntEditCampoSummernote(cfg) {
    document.getElementById(cfg.viewId).style.display = 'none';
    document.getElementById(cfg.wrapEditorId).style.display = 'block';
    document.getElementById(cfg.editedId).value = 'Y';
    $('#' + cfg.estadoId).val('');
    $('#' + cfg.ultimoValidadoId).val('');
    $('#' + cfg.aceptadoId).val('');
    $('#' + cfg.divResultadoId).hide().html('');
    $('#' + cfg.wrapEditorId + ' .note-editor').css('border', '');
    fntIniciarSummernoteCampo(cfg);
}

function fntEditNormas() {
    fntEditCampoSummernote({
        viewId: 'viewNormas',
        wrapEditorId: 'wrapEditorNormas',
        editedId: 'hidEditedNormas',
        estadoId: 'hidEstadoLLMNormas',
        ultimoValidadoId: 'hidUltimoValidadoNormas',
        aceptadoId: 'hidAceptadoManualNormas',
        divResultadoId: 'divResultadoLLMNormas',
        textareaId: 'txtNormas',
        initFlag: 'summernoteNormasInit'
    });
}

function fntEditUsoIA() {
    fntEditCampoSummernote({
        viewId: 'viewUsoIA',
        wrapEditorId: 'wrapEditorUsoIA',
        editedId: 'hidEditedUsoIA',
        estadoId: 'hidEstadoLLMUsoIA',
        ultimoValidadoId: 'hidUltimoValidadoUsoIA',
        aceptadoId: 'hidAceptadoManualUsoIA',
        divResultadoId: 'divResultadoLLMUsoIA',
        textareaId: 'txtUsoIA',
        initFlag: 'summernoteUsoIAInit'
    });
}

function fntEditPensamientoCritico() {
    fntEditCampoSummernote({
        viewId: 'viewPensamientoCritico',
        wrapEditorId: 'wrapEditorPensamientoCritico',
        editedId: 'hidEditedPensamientoCritico',
        estadoId: 'hidEstadoLLMPensamientoCritico',
        ultimoValidadoId: 'hidUltimoValidadoPensamientoCritico',
        aceptadoId: 'hidAceptadoManualPensamientoCritico',
        divResultadoId: 'divResultadoLLMPensamientoCritico',
        textareaId: 'txtPensamientoCritico',
        initFlag: 'summernotePensamientoCriticoInit'
    });
}

// Total de evaluacion (excluye filas marcadas para eliminar)
function obtenerTotalEvaluacion() {
    var total = 0;
    document.querySelectorAll('#evalBody tr[id^="trEval_"]').forEach(function(tr) {
        var hidDel = tr.querySelector('[id^="hidDeleteEval_"]');
        if (hidDel && hidDel.value === 'Y') return;

        var inp  = tr.querySelector('[id^="txtPctEval_"]');
        var span = tr.querySelector('[id^="spanPctEval_"]');
        var wrap = tr.querySelector('[id^="wrapPctEdit_"]');
        var val  = 0;

        if (wrap && wrap.style.display === 'none') {
            val = parseFloat(span ? span.textContent : '0') || 0;
        } else if (inp) {
            val = parseFloat(inp.value) || 0;
        } else if (span) {
            val = parseFloat(span.textContent) || 0;
        }
        total += val;
    });
    return total;
}

function recalcTotal() {
    var total = obtenerTotalEvaluacion();
    var spanTotal = document.getElementById('evalTotal');
    spanTotal.textContent = total + '%';
    spanTotal.className = (total === 100) ? 'eval-total-ok' : 'eval-total-err';
    if (total === 100) {
        document.querySelectorAll('#evalBody .eval-pct.field-error').forEach(function(el) {
            el.classList.remove('field-error');
        });
        spanTotal.classList.remove('field-error');
    }
}



function fntEditEval(n) {
    $('#spanRubroEval_' + n).hide();
    $('#spanPctEval_' + n).hide();
    $('#txtRubroEval_' + n).show().removeAttr('readonly');
    $('#wrapPctEdit_' + n).show();
    $('#txtPctEval_' + n).removeAttr('readonly');
    $('#hidEditedEval_' + n).val('Y');
    $('#txtRubroEval_' + n).attr('data-ultimo-validado', '');
    $('#txtRubroEval_' + n).attr('data-aceptado-manual', 'false');
    $('#hidEstadoLLMRubroEval_' + n).val('');
    $('#divResultadoLLMRubroEval_' + n).hide().html('');
    $('#txtRubroEval_' + n).css({ border: '', backgroundColor: '' });
}

function fntDeleteEval(n) {
    if ($('#hidNewEval_' + n).length && $('#hidNewEval_' + n).val() === '1') {
        $('#trEval_' + n).remove();
    } else {
        $('#trEval_' + n).find('td').addClass('row-mark-delete');
        $('#hidDeleteEval_' + n).val('Y');
    }
    recalcTotal();
}


function addEvalRow() {
    intContadorEval++;
    var n = intContadorEval;

    var tr = document.createElement('tr');
    tr.id = 'trEval_' + n;
    tr.innerHTML =
        '<td>' +
            '<input type="text" class="eval-input-text eval-rubro" id="txtRubroEval_' + n + '" ' +
            'placeholder="Nombre de la actividad">' +
            '<div id="divResultadoLLMRubroEval_' + n + '" style="display:none; margin-top:6px; padding:10px; ' +
            'border-left:4px solid #ccc; background-color:#f9f9f9; font-size:12px; line-height:1.5;"></div>' +
            '<input type="hidden" id="hidEstadoLLMRubroEval_' + n + '" value="">' +
        '</td>' +
        '<td>' +
            '<input type="number" class="eval-input-num eval-pct" id="txtPctEval_' + n + '" ' +
            'value="0" min="0" max="100" onchange="recalcTotal()"> %' +
        '</td>' +
        '<td align="center" style="white-space:nowrap;">' +
            '<button type="button" class="btn-remove-row" onclick="fntDeleteEval(' + n + ')" ' +
            'title="Eliminar">&#215;</button>' +
            '<input type="hidden" id="hidDeleteEval_' + n + '" value="N">' +
            '<input type="hidden" id="hidNewEval_' + n + '" value="1">' +
            '<input type="hidden" id="hidEditedEval_' + n + '" value="Y">' +
        '</td>';

    document.getElementById('evalBody').appendChild(tr);
    tr.querySelector('.eval-rubro').focus();
    recalcTotal();
}


function renumerarBiblioEv() {
    var num = 0;
    document.querySelectorAll('#biblioEvList .biblio-ev-item[id^="liBiblio_"]').forEach(function(li) {
        var spanView = li.querySelector('[id^="spanBiblio_"]');
        if (!spanView || spanView.style.display === 'none') return;
        num++;
        var numEl = li.querySelector('.biblio-ev-num');
        if (numEl) {
            numEl.textContent = num + '.';
            numEl.style.display = '';
        }
    });
}


function fntEditBiblioEv(n) {
    $('#numBiblio_' + n).hide();
    $('#spanBiblio_' + n).hide();
    $('#wrapEditorBiblio_' + n).show();
    $('#hidEditedBiblio_' + n).val('Y');
    $('#txtBiblio_' + n).attr('data-ultimo-validado', '');
    $('#txtBiblio_' + n).attr('data-aceptado-manual', 'false');
    $('#hidEstadoLLMBiblio_' + n).val('');
    $('#divResultadoLLMBiblio_' + n).hide().html('');
    $('#wrapEditorBiblio_' + n).removeClass('field-error');
    $('#wrapEditorBiblio_' + n + ' .note-editor').css('border', '');
    $('#wrapEditorBiblio_' + n + ' .note-editable').css('background-color', 'white');
    fntIniciarSummernoteBiblioEv(n);
}

function fntDeleteBiblioEv(n) {
    if ($('#hidNewBiblio_' + n).length && $('#hidNewBiblio_' + n).val() === '1') {
        $('#liBiblio_' + n).remove();
        renumerarBiblioEv();
    } else {
        $('#liBiblio_' + n).addClass('row-mark-delete');
        $('#hidDeleteBiblio_' + n).val('Y');
    }
}



function addBiblioEv() {
    intContadorBiblioEv++;
    var n = intContadorBiblioEv;

    var li = document.createElement('li');
    li.className = 'biblio-ev-item';
    li.id = 'liBiblio_' + n;
    li.innerHTML =
        '<span class="biblio-ev-num" id="numBiblio_' + n + '" style="display:none;">' + n + '.</span>' +
        '<div id="spanBiblio_' + n + '" class="item-view-text" style="flex:1; display:none;"></div>' +
        '<div id="wrapEditorBiblio_' + n + '" style="flex:1;">' +
            '<textarea id="txtBiblio_' + n + '" name="txtBiblio_' + n + '" style="width:100%;" ' +
            'placeholder="Ingrese la bibliograf\u00eda completa (formato Chicago)"></textarea>' +
        '</div>' +
        '<div id="divResultadoLLMBiblio_' + n + '" class="biblio-ev-llm-result" style="display:none;"></div>' +
        '<input type="hidden" id="hidEstadoLLMBiblio_' + n + '" value="">' +
        '<button type="button" class="btn-remove-row" onclick="fntDeleteBiblioEv(' + n + ')" ' +
        'title="Eliminar">&#215;</button>' +
        '<input type="hidden" id="hidDeleteBiblio_' + n + '" value="N">' +
        '<input type="hidden" id="hidNewBiblio_' + n + '" value="1">' +
        '<input type="hidden" id="hidEditedBiblio_' + n + '" value="Y">';

    document.getElementById('biblioEvList').appendChild(li);
    window['summernoteBiblioEvInit_' + n] = false;
    fntIniciarSummernoteBiblioEv(n);
}


function fntEditExp(n) {
    $('#spanExp_' + n).hide();
    $('#txtExp_' + n).show().removeAttr('readonly').focus();
    $('#hidEditedExp_' + n).val('Y');
    $('#txtExp_' + n).attr('data-ultimo-validado', '');
    $('#txtExp_' + n).attr('data-aceptado-manual', 'false');
    $('#hidEstadoLLMExp_' + n).val('');
    $('#divResultadoLLMExp_' + n).hide().html('');
    $('#txtExp_' + n).css({ border: '', backgroundColor: '' });
}

function fntDeleteExp(n) {
    if ($('#hidNewExp_' + n).length && $('#hidNewExp_' + n).val() === '1') {
        $('#liExp_' + n).remove();
    } else {
        $('#liExp_' + n).addClass('row-mark-delete');
        $('#hidDeleteExp_' + n).val('Y');
    }
}

function addExp() {
    intContadorExp++;
    var n = intContadorExp;

    var li = document.createElement('li');
    li.className = 'biblio-ev-item';
    li.id = 'liExp_' + n;
    li.innerHTML =
        '<textarea class="biblio-ev-text" id="txtExp_' + n + '" rows="2" style="flex:1;" ' +
        'placeholder="Describa la experiencia que realizar? el estudiante"></textarea>' +
        '<div id="divResultadoLLMExp_' + n + '" class="biblio-ev-llm-result" style="display:none;"></div>' +
        '<input type="hidden" id="hidEstadoLLMExp_' + n + '" value="">' +
        '<button type="button" class="btn-remove-row" onclick="fntDeleteExp(' + n + ')" ' +
        'title="Eliminar">&#215;</button>' +
        '<input type="hidden" id="hidDeleteExp_' + n + '" value="N">' +
        '<input type="hidden" id="hidNewExp_' + n + '" value="1">' +
        '<input type="hidden" id="hidEditedExp_' + n + '" value="Y">';

    document.getElementById('expList').appendChild(li);
    li.querySelector('.biblio-ev-text').focus();
}


// ===== CRONOGRAMA ? ADJUNTOS MULTIPLES =====
var CRONOGRAMA_EXT_PERMITIDAS = [
    'pdf',
    'doc', 'docx', 'docm', 'odt', 'rtf',
    'xls', 'xlsx', 'xlsm', 'ods', 'csv',
    'ppt', 'pptx', 'pptm', 'odp'
];

var _cronoArchivosPendientes = {};

function extensionArchivoCronograma(nombre) {
    var m = (nombre || '').toLowerCase().match(/\.([^.]+)$/);
    return m ? m[1] : '';
}

function esExtensionCronogramaPermitida(ext) {
    return CRONOGRAMA_EXT_PERMITIDAS.indexOf(ext) >= 0;
}

function esArchivoCronogramaPermitido(file) {
    if (!file) return false;
    return esExtensionCronogramaPermitida(extensionArchivoCronograma(file.name));
}

function addCronoRow() {
    intContadorCrono++;
    var n = intContadorCrono;
    var tr = document.createElement('tr');
    tr.id = 'trCrono_' + n;
    tr.innerHTML =
        '<td>' +
            '<span id="spanNuevoNomCrono_' + n + '" class="item-view-text file-name"></span>' +
            '<div id="wrapUploadCrono_' + n + '" style="margin-top:4px;">' +
                '<input type="file" id="fileInputCrono_' + n + '"' +
                ' accept=".pdf,.doc,.docx,.docm,.odt,.rtf,.xls,.xlsx,.xlsm,.ods,.csv,.ppt,.pptx,.pptm,.odp"' +
                ' style="display:none;" onchange="handleFileCrono(' + n + ', this)">' +
                '<button type="button" class="btn-upload"' +
                ' onclick="document.getElementById(\'fileInputCrono_' + n + '\').click()">' +
                'Seleccionar archivo</button>' +
            '</div>' +
        '</td>' +
        '<td>' +
            '<label style="cursor:pointer;">' +
                '<input type="checkbox" id="chkActivoCrono_' + n + '" checked' +
                ' onchange="fntToggleActivoCrono(' + n + ')">' +
            '</label>' +
        '</td>' +
        '<td align="center" style="white-space:nowrap;">' +
            '<button type="button" class="btn-remove-row" onclick="fntDeleteCrono(' + n + ')" title="Eliminar">&#215;</button>' +
            '<input type="hidden" id="hidDeleteCrono_' + n + '" value="N">' +
            '<input type="hidden" id="hidNewCrono_' + n + '" value="1">' +
            '<input type="hidden" id="hidEditedCrono_' + n + '" value="N">' +
            '<input type="hidden" id="hidActivoCrono_' + n + '" value="Y">' +
        '</td>';
    document.getElementById('cronoBody').appendChild(tr);
}

function fntDeleteCrono(n) {
    var hidNew = document.getElementById('hidNewCrono_' + n);
    var hidDel = document.getElementById('hidDeleteCrono_' + n);
    if (hidNew && hidNew.value === '1') {
        document.getElementById('trCrono_' + n).remove();
        delete _cronoArchivosPendientes[n];
    } else {
        if (hidDel) hidDel.value = 'Y';
        $('#trCrono_' + n).addClass('row-mark-delete');
    }
}

function fntEditCrono(n) {
    document.getElementById('hidEditedCrono_' + n).value = 'Y';
    var wrap = document.getElementById('wrapUploadCrono_' + n);
    if (wrap) wrap.style.display = 'block';
    var chk = document.getElementById('chkActivoCrono_' + n);
    if (chk) chk.disabled = false;
}

function fntToggleActivoCrono(n) {
    var chk = document.getElementById('chkActivoCrono_' + n);
    var hid = document.getElementById('hidActivoCrono_' + n);
    var tr  = document.getElementById('trCrono_' + n);
    if (!chk || !hid) return;
    hid.value = chk.checked ? 'Y' : 'N';
    if (tr) {
        if (chk.checked) tr.classList.remove('crono-inactivo');
        else             tr.classList.add('crono-inactivo');
    }
}

function handleFileCrono(n, input) {
    if (!input.files || !input.files[0]) return;
    var file = input.files[0];
    if (!esArchivoCronogramaPermitido(file)) {
        input.value = '';
        delete _cronoArchivosPendientes[n];
        mostrarErrorValidacion('Formato no permitido. Use PDF, Word, Excel o PowerPoint.');
        return;
    }
    _cronoArchivosPendientes[n] = file;
    document.getElementById('hidEditedCrono_' + n).value = 'Y';
    var wrap = document.getElementById('wrapUploadCrono_' + n);
    if (wrap) wrap.classList.remove('crono-upload-error');
    var span = document.getElementById('spanNuevoNomCrono_' + n)
             || document.getElementById('spanNuevoArchCrono_' + n);
    if (span) span.textContent = file.name;
}

function fntDescargarCronoRev(intCronoId) {
    $.ajax({
        url: 'syllabus_catedratico_ws.php',
        type: 'POST',
        data: {
            ajaxDescargarCronoRev: true,
            cimp: intCimpBitacora,
            syllabus_uac: intSyllabusUAC,
            syllabus_uac_crono: intCronoId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.url_descarga) {
                var a = document.createElement('a');
                a.href = response.url_descarga;
                a.download = response.nombre_archivo || '';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            } else {
                mostrarErrorValidacion(response.msg || 'Error al descargar la revision.');
            }
        },
        error: function() {
            mostrarErrorValidacion('Error al procesar la descarga de la revision.');
        }
    });
}

function fntPreviewCronoRev(intCronoId) {
    var url = 'syllabus_catedratico_ws.php?ACTION=verCronoRevPreview&crono_id=' + intCronoId
            + '&cimp=' + intCimpBitacora;
    window.open(url, '_blank', 'noopener,noreferrer');
}

function fntAbrirRevisionCrono(intCronoId) {
    var res = (_spellcheckResultados || []).find(function(item) {
        return parseInt(item.syllabus_uac_cronograma, 10) === parseInt(intCronoId, 10);
    });
    if (res && res.url_ver_rev) {
        window.open(res.url_ver_rev, '_blank', 'noopener,noreferrer');
        return;
    }
    if (res && res.url_descargar_rev) {
        fntDescargarCronoRev(intCronoId);
        return;
    }
    $.ajax({
        url: 'syllabus_catedratico_ws.php',
        type: 'POST',
        data: {
            ajaxDescargarCronoRev: true,
            cimp: intCimpBitacora,
            syllabus_uac: intSyllabusUAC,
            syllabus_uac_crono: intCronoId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.url_ver) {
                window.open(response.url_ver, '_blank', 'noopener,noreferrer');
            } else if (response.success && response.url_descarga) {
                var a = document.createElement('a');
                a.href = response.url_descarga;
                a.download = response.nombre_archivo || '';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            } else {
                mostrarErrorValidacion(response.msg || 'No hay revision disponible.');
            }
        },
        error: function() {
            mostrarErrorValidacion('Error al abrir la revision del cronograma.');
        }
    });
}

function fntDescargarCronoAdjunto(intCronoId) {
    $.ajax({
        url: 'syllabus_catedratico_ws.php',
        type: 'POST',
        data: {
            ajaxDescargarCronoAdjunto: true,
            cimp: intCimpBitacora,
            syllabus_uac: intSyllabusUAC,
            syllabus_uac_crono: intCronoId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.url_descarga) {
                var a = document.createElement('a');
                a.href = response.url_descarga;
                a.download = response.nombre_archivo || '';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            } else {
                mostrarErrorValidacion(response.msg || 'Error al descargar el cronograma.');
            }
        },
        error: function() {
            mostrarErrorValidacion('Error al procesar la descarga del cronograma.');
        }
    });
}

function fntPreviewCronoAdjunto(intCronoId) {
    var url = 'syllabus_catedratico_ws.php?ACTION=verCronoPreview&crono_id=' + intCronoId
            + '&cimp=' + intCimpBitacora;
    window.open(url, '_blank');
}

function fntMostrarBitacoraTodosCronogramas() {
    bitacoraAjaxPrincipal(bitacoraPostData({
        drawBlurBitacoraTodosCronogramas: true
    }));
}

function fntMostrarBitacoraCronoAdjunto(intCronoId) {
    bitacoraAjaxPrincipal(bitacoraPostData({
        drawBlurBitacoraCronoAdjunto: true,
        syllabus_uac_crono: intCronoId
    }), true);
}

function fntMostrarBitacoraCronoEliminado(intCronoId) {
    bitacoraAjaxPrincipal(bitacoraPostData({
        drawBlurBitacoraCronoEliminado: true,
        syllabus_uac_crono: intCronoId
    }), true);
}


// Cancelar
function cancelarSyllabus() {
    // Re-POST de cimp para recargar el syllabus sin perder el curso impartido
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = window.location.pathname;
    var inp = document.createElement('input');
    inp.type = 'hidden';
    inp.name = 'cimp';
    inp.value = '<?php print intval($intCursoImpartido); ?>';
    form.appendChild(inp);
    document.body.appendChild(form);
    form.submit();
}


function limpiarErroresValidacion() {
    document.querySelectorAll('.field-error').forEach(function(el) {
        el.classList.remove('field-error');
    });
}

function limpiarErroresPublicacion() {
    document.querySelectorAll('.section-pub-error').forEach(function(el) {
        el.classList.remove('section-pub-error');
    });
    limpiarErroresValidacion();
}

function marcarSeccionPublicacion(secId) {
    var sec = document.getElementById(secId);
    if (sec) sec.classList.add('section-pub-error');
}

function esContenidoVacioPlano(texto) {
    if (!texto) return true;
    var t = String(texto).toLowerCase().replace(/\s+/g, ' ').trim();
    return t === '' || t === 'sin informaci\u00f3n' || t === 'sin informacion';
}

function obtenerTextoCampoClob(viewId, wrapId, textareaId, bolSummernoteInit) {
    if ($('#' + wrapId).is(':visible')) {
        if (bolSummernoteInit) {
            return textoPlanoDesdeHtml($('#' + textareaId).summernote('code'));
        }
        return ($('#' + textareaId).val() || '').trim();
    }
    return textoPlanoDesdeHtml($('#' + viewId).html());
}

function contarFilasEvalActivas() {
    var total = 0;
    document.querySelectorAll('#evalBody tr[id^="trEval_"]').forEach(function(tr) {
        if (tr.id === 'trEvalInicial') return;
        var hidDelete = tr.querySelector('[id^="hidDeleteEval_"]');
        if (hidDelete && hidDelete.value === 'Y') return;
        total++;
    });
    return total;
}

function contarCronogramasActivosPublicar() {
    var total = 0;
    document.querySelectorAll('#cronoBody tr[id^="trCrono_"]').forEach(function(tr) {
        var n = tr.id.replace('trCrono_', '');
        if ($('#hidDeleteCrono_' + n).val() === 'Y') return;
        if ($('#hidActivoCrono_' + n).val() !== 'Y') return;
        var esNuevo = $('#hidNewCrono_' + n).val() === '1';
        if (esNuevo) {
            if (typeof _cronoArchivosPendientes === 'undefined' || !_cronoArchivosPendientes[n]) return;
        }
        total++;
    });
    return total;
}

function contarBiblioEvConContenido() {
    var total = 0;
    document.querySelectorAll('#biblioEvList .biblio-ev-item[id^="liBiblio_"]').forEach(function(li) {
        var n = li.id.replace('liBiblio_', '');
        if ($('#hidDeleteBiblio_' + n).val() === 'Y') return;
        var refPlano = '';
        if ($('#wrapEditorBiblio_' + n).is(':visible')) {
            refPlano = textoPlanoDesdeHtml(fntGetHtmlBiblioEv(n));
        } else {
            refPlano = textoPlanoDesdeHtml($('#spanBiblio_' + n).html());
        }
        if (refPlano) total++;
    });
    return total;
}

function contarExpConContenido() {
    var total = 0;
    document.querySelectorAll('#expList .biblio-ev-item[id^="liExp_"]').forEach(function(li) {
        var n = li.id.replace('liExp_', '');
        if ($('#hidDeleteExp_' + n).val() === 'Y') return;
        var desc = '';
        var textarea = li.querySelector('[id^="txtExp_"]');
        var spanDesc = li.querySelector('[id^="spanExp_"]');
        if (textarea && textarea.style.display !== 'none') {
            desc = (textarea.value || '').trim();
        } else if (spanDesc) {
            desc = (spanDesc.textContent || '').trim();
        }
        if (desc) total++;
    });
    return total;
}

function encontrarPrimeraSeccionPublicacion() {
    var orden = [
        'secNormas', 'secEvaluacion', 'secCronograma', 'secBiblioEv',
        'secExperiencias', 'secUsoIA', 'secPensamientoCritico'
    ];
    var i, sec;
    for (i = 0; i < orden.length; i++) {
        sec = document.getElementById(orden[i]);
        if (!sec) continue;
        if (sec.classList.contains('section-pub-error') || sec.querySelector('.field-error')) {
            return orden[i];
        }
    }
    return null;
}

function fallarPublicacion(mensaje, secId, elMarcar) {
    if (secId) marcarSeccionPublicacion(secId);
    if (elMarcar) elMarcar.classList.add('field-error');
    mostrarErrorValidacion(mensaje, { scrollTo: secId || encontrarPrimeraSeccionPublicacion() });
    return false;
}

function validarCamposAntesDePublicar() {
    limpiarErroresPublicacion();

    if (esContenidoVacioPlano(obtenerTextoCampoClob('viewNormas', 'wrapEditorNormas', 'txtNormas', !!summernoteNormasInit))) {
        return fallarPublicacion(
            'Debe completar las normas y reglas operativas del curso antes de publicar.',
            'secNormas',
            $('#wrapEditorNormas').is(':visible') ? document.getElementById('wrapEditorNormas') : document.getElementById('viewNormas')
        );
    }

    if (contarFilasEvalActivas() < 1) {
        return fallarPublicacion(
            'Debe registrar al menos una actividad de evaluaci\u00f3n antes de publicar.',
            'secEvaluacion',
            document.getElementById('evalTable')
        );
    }

    if (contarCronogramasActivosPublicar() < 1) {
        return fallarPublicacion(
            'Debe adjuntar al menos un cronograma activo antes de publicar.',
            'secCronograma',
            document.getElementById('cronoTable')
        );
    }

    if (contarBiblioEvConContenido() < 1) {
        return fallarPublicacion(
            'Debe registrar al menos una bibliograf\u00eda antes de publicar.',
            'secBiblioEv',
            document.getElementById('biblioEvList')
        );
    }

    if (contarExpConContenido() < 1) {
        return fallarPublicacion(
            'Debe registrar al menos una experiencia principal antes de publicar.',
            'secExperiencias',
            document.getElementById('expList')
        );
    }

    if (esContenidoVacioPlano(obtenerTextoCampoClob('viewUsoIA', 'wrapEditorUsoIA', 'txtUsoIA', !!summernoteUsoIAInit))) {
        return fallarPublicacion(
            'Debe completar el uso de IA en el curso antes de publicar.',
            'secUsoIA',
            $('#wrapEditorUsoIA').is(':visible') ? document.getElementById('wrapEditorUsoIA') : document.getElementById('viewUsoIA')
        );
    }

    if (esContenidoVacioPlano(obtenerTextoCampoClob('viewPensamientoCritico', 'wrapEditorPensamientoCritico', 'txtPensamientoCritico', !!summernotePensamientoCriticoInit))) {
        return fallarPublicacion(
            'Debe completar el desarrollo del pensamiento cr\u00edtico antes de publicar.',
            'secPensamientoCritico',
            $('#wrapEditorPensamientoCritico').is(':visible') ? document.getElementById('wrapEditorPensamientoCritico') : document.getElementById('viewPensamientoCritico')
        );
    }

    if (!validarCamposAntesDeGuardar({ modoPublicar: true, sinModal: true })) {
        var secId = encontrarPrimeraSeccionPublicacion();
        if (secId) marcarSeccionPublicacion(secId);
        var totalEval = obtenerTotalEvaluacion();
        var msgDetalle = 'Favor revise los campos en rojo antes de publicar el programa.';
        if (totalEval !== 100) {
            msgDetalle = 'La evaluaci\u00f3n debe sumar exactamente 100% (suma actual: ' + totalEval + '%).';
        }
        return fallarPublicacion(msgDetalle, secId, null);
    }

    return true;
}

function abrirModalValidacion() {
    document.getElementById('modalValidacion').classList.add('active');
}

function cerrarModalValidacion() {
    document.getElementById('modalValidacion').classList.remove('active');
}

function mostrarErrorValidacion(mensaje, opts) {
    var msgEl = document.getElementById('modalValidacionMsg');
    if (msgEl) {
        msgEl.textContent = mensaje || 'Favor revisar campos en rojo.';
    }
    abrirModalValidacion();
    if (opts && opts.scrollLLM) {
        scrollAlPrimerFeedbackLLM();
        return;
    }
    if (opts && opts.scrollTo) {
        var sec = document.getElementById(opts.scrollTo);
        if (sec) {
            sec.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
    }
    var primero = document.querySelector('.field-error');
    if (primero) {
        primero.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } else {
        document.getElementById('evalTotal').scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

/** Devuelve texto plano de HTML (Summernote); vac?o si solo hay etiquetas vac?as */
function textoPlanoDesdeHtml(html) {
    if (!html) return '';
    var div = document.createElement('div');
    div.innerHTML = html;
    return (div.textContent || div.innerText || '').replace(/\s+/g, ' ').trim();
}

function validarCamposAntesDeGuardar(opts) {
    opts = opts || {};
    if (!opts.sinLimpiar) {
        limpiarErroresValidacion();
    }
    var hayError = false;
    var errorCamposVacios = false;
    var errorPctCero = false;
    var modoPublicar = !!opts.modoPublicar;

    // --- Normas ---
    if (modoPublicar || document.getElementById('hidEditedNormas').value === 'Y') {
        var textoNormas = modoPublicar
            ? obtenerTextoCampoClob('viewNormas', 'wrapEditorNormas', 'txtNormas', !!summernoteNormasInit)
            : '';
        if (!modoPublicar) {
            if (summernoteNormasInit) {
                textoNormas = textoPlanoDesdeHtml($('#txtNormas').summernote('code'));
            } else {
                textoNormas = (document.getElementById('txtNormas').value || '').trim();
            }
        }
        if (esContenidoVacioPlano(textoNormas)) {
            document.getElementById('wrapEditorNormas').classList.add('field-error');
            if (modoPublicar) marcarSeccionPublicacion('secNormas');
            hayError = true;
            errorCamposVacios = true;
        }
    }

    // --- Uso de IA ---
    if (modoPublicar || document.getElementById('hidEditedUsoIA').value === 'Y') {
        var textoUsoIA = modoPublicar
            ? obtenerTextoCampoClob('viewUsoIA', 'wrapEditorUsoIA', 'txtUsoIA', !!summernoteUsoIAInit)
            : '';
        if (!modoPublicar) {
            if (summernoteUsoIAInit) {
                textoUsoIA = textoPlanoDesdeHtml($('#txtUsoIA').summernote('code'));
            } else {
                textoUsoIA = (document.getElementById('txtUsoIA').value || '').trim();
            }
        }
        if (esContenidoVacioPlano(textoUsoIA)) {
            document.getElementById('wrapEditorUsoIA').classList.add('field-error');
            if (modoPublicar) marcarSeccionPublicacion('secUsoIA');
            hayError = true;
            errorCamposVacios = true;
        }
    }

    // --- Pensamiento critico ---
    if (modoPublicar || document.getElementById('hidEditedPensamientoCritico').value === 'Y') {
        var textoPensamientoCritico = modoPublicar
            ? obtenerTextoCampoClob('viewPensamientoCritico', 'wrapEditorPensamientoCritico', 'txtPensamientoCritico', !!summernotePensamientoCriticoInit)
            : '';
        if (!modoPublicar) {
            if (summernotePensamientoCriticoInit) {
                textoPensamientoCritico = textoPlanoDesdeHtml($('#txtPensamientoCritico').summernote('code'));
            } else {
                textoPensamientoCritico = (document.getElementById('txtPensamientoCritico').value || '').trim();
            }
        }
        if (esContenidoVacioPlano(textoPensamientoCritico)) {
            document.getElementById('wrapEditorPensamientoCritico').classList.add('field-error');
            if (modoPublicar) marcarSeccionPublicacion('secPensamientoCritico');
            hayError = true;
            errorCamposVacios = true;
        }
    }

    // --- Evaluaci?n: rubros ---
    document.querySelectorAll('#evalBody tr[id^="trEval_"]').forEach(function(tr) {
        var n = tr.id.replace('trEval_', '');
        var hidDelete = tr.querySelector('[id^="hidDeleteEval_"]');
        if (hidDelete && hidDelete.value === 'Y') return;

        var hidNew    = tr.querySelector('[id^="hidNewEval_"]');
        var hidEdited = tr.querySelector('[id^="hidEditedEval_"]');
        var inpRubro  = tr.querySelector('[id^="txtRubroEval_"]');
        var spanRubro = tr.querySelector('[id^="spanRubroEval_"]');
        var esNueva    = hidNew && hidNew.value === '1';
        var fueEditada = hidEdited && hidEdited.value === 'Y';

        if (!modoPublicar && !esNueva && !fueEditada) return;

        var rubro = '';
        if (inpRubro && (esNueva || fueEditada || inpRubro.style.display !== 'none')) {
            rubro = inpRubro.value.trim();
        } else if (spanRubro) {
            rubro = (spanRubro.textContent || '').trim();
        }
        if (!rubro) {
            if (inpRubro) inpRubro.classList.add('field-error');
            if (modoPublicar) marcarSeccionPublicacion('secEvaluacion');
            hayError = true;
            errorCamposVacios = true;
        }
    });

    // --- Evaluaci?n: porcentaje debe ser mayor a 0 en cada fila activa ---
    document.querySelectorAll('#evalBody tr[id^="trEval_"]').forEach(function(tr) {
        var hidDelete = tr.querySelector('[id^="hidDeleteEval_"]');
        if (hidDelete && hidDelete.value === 'Y') return;

        var inpPct  = tr.querySelector('[id^="txtPctEval_"]');
        var spanPct = tr.querySelector('[id^="spanPctEval_"]');
        var wrapPct = tr.querySelector('[id^="wrapPctEdit_"]');
        var pct = 0;

        if (wrapPct && wrapPct.style.display === 'none' && spanPct) {
            pct = parseFloat((spanPct.textContent || '').replace('%', '').trim()) || 0;
        } else if (inpPct) {
            pct = parseFloat(inpPct.value) || 0;
        }

        if (pct <= 0) {
            if (inpPct) inpPct.classList.add('field-error');
            if (modoPublicar) marcarSeccionPublicacion('secEvaluacion');
            hayError = true;
            errorPctCero = true;
        }
    });

    // --- Bibliograf?a evolutiva ---
    document.querySelectorAll('#biblioEvList .biblio-ev-item[id^="liBiblio_"]').forEach(function(li) {
        var n = li.id.replace('liBiblio_', '');
        var hidDelete = li.querySelector('[id^="hidDeleteBiblio_"]');
        if (hidDelete && hidDelete.value === 'Y') return;

        var hidNew    = li.querySelector('[id^="hidNewBiblio_"]');
        var hidEdited = li.querySelector('[id^="hidEditedBiblio_"]');
        var esNueva    = hidNew && hidNew.value === '1';
        var fueEditada = hidEdited && hidEdited.value === 'Y';

        if (!modoPublicar && !esNueva && !fueEditada) return;

        var refPlano = '';
        if ($('#wrapEditorBiblio_' + n).is(':visible')) {
            refPlano = textoPlanoDesdeHtml(fntGetHtmlBiblioEv(n));
        } else {
            refPlano = textoPlanoDesdeHtml($('#spanBiblio_' + n).html());
        }
        if (!refPlano) {
            var wrapEditor = li.querySelector('[id^="wrapEditorBiblio_"]');
            if (wrapEditor) wrapEditor.classList.add('field-error');
            if (modoPublicar) marcarSeccionPublicacion('secBiblioEv');
            hayError = true;
            errorCamposVacios = true;
        }
    });

    // --- Experiencias principales ---
    document.querySelectorAll('#expList .biblio-ev-item[id^="liExp_"]').forEach(function(li) {
        var hidDelete = li.querySelector('[id^="hidDeleteExp_"]');
        if (hidDelete && hidDelete.value === 'Y') return;

        var hidNew    = li.querySelector('[id^="hidNewExp_"]');
        var hidEdited = li.querySelector('[id^="hidEditedExp_"]');
        var textarea  = li.querySelector('[id^="txtExp_"]');
        var spanDesc  = li.querySelector('[id^="spanExp_"]');
        var esNueva    = hidNew && hidNew.value === '1';
        var fueEditada = hidEdited && hidEdited.value === 'Y';

        if (!modoPublicar && !esNueva && !fueEditada) return;

        var desc = '';
        if (textarea && textarea.style.display !== 'none') {
            desc = textarea.value.trim();
        } else if (spanDesc) {
            desc = (spanDesc.textContent || '').trim();
        }
        if (!desc) {
            if (textarea) textarea.classList.add('field-error');
            if (modoPublicar) marcarSeccionPublicacion('secExperiencias');
            hayError = true;
            errorCamposVacios = true;
        }
    });

    // --- Evaluaci?n: porcentajes deben sumar exactamente 100 ---
    var totalEval = obtenerTotalEvaluacion();
    var errorPct  = (totalEval !== 100);
    if (errorPct) {
        document.querySelectorAll('#evalBody tr[id^="trEval_"]').forEach(function(tr) {
            var hidDelete = tr.querySelector('[id^="hidDeleteEval_"]');
            if (hidDelete && hidDelete.value === 'Y') return;
            var inpPct = tr.querySelector('[id^="txtPctEval_"]');
            if (inpPct) inpPct.classList.add('field-error');
        });
        document.getElementById('evalTotal').classList.add('field-error');
        if (modoPublicar) marcarSeccionPublicacion('secEvaluacion');
        hayError = true;
    }

    if (hayError) {
        if (opts.sinModal) {
            return false;
        }
        var mensaje;
        if (errorCamposVacios && errorPct && errorPctCero) {
            mensaje = 'Favor revisar campos en rojo. Cada actividad debe tener un porcentaje mayor a 0. La evaluaci\u00f3n debe sumar exactamente 100% (suma actual: ' + totalEval + '%).';
        } else if (errorCamposVacios && errorPct) {
            mensaje = 'Favor revisar campos en rojo. La evaluaci\u00f3n debe sumar exactamente 100% (suma actual: ' + totalEval + '%).';
        } else if (errorPctCero && errorPct) {
            mensaje = 'Cada actividad debe tener un porcentaje mayor a 0. La evaluaci\u00f3n debe sumar exactamente 100% (suma actual: ' + totalEval + '%).';
        } else if (errorPctCero) {
            mensaje = 'Cada actividad de evaluaci\u00f3n debe tener un porcentaje mayor a 0.';
        } else if (errorPct) {
            mensaje = 'La evaluaci\u00f3n debe sumar exactamente 100% (suma actual: ' + totalEval + '%).';
        } else {
            mensaje = 'Favor revisar campos en rojo.';
        }
        mostrarErrorValidacion(mensaje);
        return false;
    }
    return true;
}

// Guardar - muestra confirmacion IA en los spans

function guardarSyllabus() {
    var btn = document.querySelector('.btn-save');
    var orig = btn.textContent;

    if (!validarCamposAntesDeGuardar()) {
        return;
    }

    var llmCheck = validarTodosCamposLLMAntesDeGuardar();
    if (!llmCheck.ok) {
        var msgLLM = llmCheck.haySugerencias
            ? 'Por favor revise los campos en rojo o las sugerencias de IA.'
            : 'Por favor revise los campos en rojo o las sugerencias de IA.';
        mostrarErrorValidacion(msgLLM, { scrollLLM: true });
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Guardando...';

    var fd = new FormData();
    fd.append('ACTION', 'guardarSyllabusUAC');
    fd.append('cimp', <?php print intval($intCursoImpartido); ?>);
    fntAppendSpellcheckLlmFlag(fd);

    // --- Normas ---
    fd.append('hidEditedNormas', document.getElementById('hidEditedNormas').value);
    if (document.getElementById('hidEditedNormas').value === 'Y' && summernoteNormasInit) {
        fd.append('normas', $('#txtNormas').summernote('code'));
    }

    // --- Uso de IA ---
    fd.append('hidEditedUsoIA', document.getElementById('hidEditedUsoIA').value);
    if (document.getElementById('hidEditedUsoIA').value === 'Y' && summernoteUsoIAInit) {
        fd.append('uso_ia', $('#txtUsoIA').summernote('code'));
    }

    // --- Pensamiento critico ---
    fd.append('hidEditedPensamientoCritico', document.getElementById('hidEditedPensamientoCritico').value);
    if (document.getElementById('hidEditedPensamientoCritico').value === 'Y' && summernotePensamientoCriticoInit) {
        fd.append('pensamiento_critico', $('#txtPensamientoCritico').summernote('code'));
    }

    // --- Evaluacion: todas las filas trEval_{n} (incluidas marcadas en rojo) ---
    document.querySelectorAll('#evalBody tr[id^="trEval_"]').forEach(function(tr) {
        if (tr.id === 'trEvalInicial') return;
        var n = tr.id.replace('trEval_', '');

                fd.append('hidDeleteEval_' + n, $('#hidDeleteEval_' + n).val() || 'N');
        fd.append('hidEditedEval_' + n, $('#hidEditedEval_' + n).val() || 'N');

        if ($('#hidUpdateEval_' + n).length) {
            fd.append('hidUpdateEval_' + n, $('#hidUpdateEval_' + n).val());
        }
        if ($('#hidNewEval_' + n).length) {
            fd.append('hidNewEval_' + n, $('#hidNewEval_' + n).val());
        }

        var esNuevaEval    = $('#hidNewEval_' + n).length > 0 && $('#hidNewEval_' + n).val() === '1';
        var fueEditadaEval = $('#hidEditedEval_' + n).val() === 'Y';
        var rubro, pct;

        if (esNuevaEval || fueEditadaEval) {
            rubro = ($('#txtRubroEval_' + n).val() || '').trim();
            pct   = $('#txtPctEval_' + n).val() || '0';
        } else {
            rubro = ($('#spanRubroEval_' + n).text() || '').trim();
            pct   = ($('#spanPctEval_' + n).text() || '').replace('%', '').trim() || '0';
        }

        fd.append('txtRubroEval_' + n, rubro);
        fd.append('txtPctEval_' + n, pct);


    });

    // --- Bibliografia evolutiva ---
    document.querySelectorAll('#biblioEvList .biblio-ev-item[id^="liBiblio_"]').forEach(function(li) {
        var n = li.id.replace('liBiblio_', '');

        var hidDelete = li.querySelector('[id^="hidDeleteBiblio_"]');
        var hidEdited = li.querySelector('[id^="hidEditedBiblio_"]');
        var hidUpdate = li.querySelector('[id^="hidUpdateBiblio_"]');
        var hidNew    = li.querySelector('[id^="hidNewBiblio_"]');
        var textarea  = li.querySelector('[id^="txtBiblio_"]');
        var spanRef   = li.querySelector('[id^="spanBiblio_"]');

        fd.append('hidDeleteBiblio_' + n, hidDelete ? hidDelete.value : 'N');
        fd.append('hidEditedBiblio_' + n, hidEdited ? hidEdited.value : 'N');

        if (hidUpdate) {
            fd.append('hidUpdateBiblio_' + n, hidUpdate.value);
        }
        if (hidNew) {
            fd.append('hidNewBiblio_' + n, hidNew.value);
        }

        var esNueva    = hidNew && hidNew.value === '1';
        var fueEditada = hidEdited && hidEdited.value === 'Y';
        var ref;

        if (esNueva || fueEditada) {
            fntSyncBiblioEvToPost(n);
            ref = fntGetHtmlBiblioEv(n);
        } else {
            ref = spanRef ? spanRef.innerHTML.trim() : '';
        }

        fd.append('txtBiblio_' + n, ref);
    });

    // --- Experiencias principales ---
    document.querySelectorAll('#expList .biblio-ev-item[id^="liExp_"]').forEach(function(li) {
        var n = li.id.replace('liExp_', '');

        var hidDelete = li.querySelector('[id^="hidDeleteExp_"]');
        var hidEdited = li.querySelector('[id^="hidEditedExp_"]');
        var hidUpdate = li.querySelector('[id^="hidUpdateExp_"]');
        var hidNew    = li.querySelector('[id^="hidNewExp_"]');
        var textarea  = li.querySelector('[id^="txtExp_"]');
        var spanDesc  = li.querySelector('[id^="spanExp_"]');

        fd.append('hidDeleteExp_' + n, hidDelete ? hidDelete.value : 'N');
        fd.append('hidEditedExp_' + n, hidEdited ? hidEdited.value : 'N');

        if (hidUpdate) {
            fd.append('hidUpdateExp_' + n, hidUpdate.value);
        }
        if (hidNew) {
            fd.append('hidNewExp_' + n, hidNew.value);
        }

        var esNueva    = hidNew && hidNew.value === '1';
        var fueEditada = hidEdited && hidEdited.value === 'Y';
        var desc;

        if (esNueva || fueEditada) {
            desc = textarea ? textarea.value.trim() : '';
        } else {
            desc = spanDesc ? spanDesc.textContent.trim() : '';
        }

        fd.append('txtExp_' + n, desc);
    });


    // --- Cronogramas (m?ltiples adjuntos) ---
    var cronoError = false;
    document.querySelectorAll('#cronoBody tr[id^="trCrono_"]').forEach(function(tr) {
        if (cronoError) return;
        var n = tr.id.replace('trCrono_', '');
        var hidDel    = tr.querySelector('#hidDeleteCrono_' + n);
        var hidEdited = tr.querySelector('#hidEditedCrono_' + n);
        var hidUpdate = tr.querySelector('#hidUpdateCrono_' + n);
        var hidNew    = tr.querySelector('#hidNewCrono_' + n);
        var hidActivo = tr.querySelector('#hidActivoCrono_' + n);

        fd.append('hidDeleteCrono_' + n, hidDel    ? hidDel.value    : 'N');
        fd.append('hidEditedCrono_' + n, hidEdited ? hidEdited.value : 'N');
        if (hidUpdate) fd.append('hidUpdateCrono_' + n, hidUpdate.value);
        if (hidNew)    fd.append('hidNewCrono_' + n, hidNew.value);
        if (hidActivo) fd.append('hidActivoCrono_' + n, hidActivo.value);

        var esCronoNuevo   = hidNew && hidNew.value === '1';
        var esCronoEliminado = hidDel && hidDel.value === 'Y';
        if (esCronoNuevo && !esCronoEliminado && !_cronoArchivosPendientes[n]) {
            var wrapErr = document.getElementById('wrapUploadCrono_' + n);
            if (wrapErr) wrapErr.classList.add('crono-upload-error');
            mostrarErrorValidacion('Debe seleccionar un archivo para cada cronograma nuevo antes de guardar.');
            btn.disabled = false;
            btn.textContent = orig;
            cronoError = true;
            return;
        }

        if (_cronoArchivosPendientes[n]) {
            var f = _cronoArchivosPendientes[n];
            if (!esArchivoCronogramaPermitido(f)) {
                mostrarErrorValidacion('Formato de cronograma no permitido: ' + f.name + '. Use PDF, Word, Excel o PowerPoint.');
                btn.disabled = false;
                btn.textContent = orig;
                cronoError = true;
                return;
            }
            fd.append('archivo_cronograma_' + n, f, f.name);
        }
    });
    if (cronoError) return;
    

    $.ajax({
        url: 'syllabus_catedratico_ws.php',
        method: 'POST',
        processData: false,
        contentType: false,
        data: fd,
                success: function(response) {

                    if (typeof response === 'string') {
                        try { response = JSON.parse(response); } catch (e) {}
                    }
    if (response && response.ok) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = window.location.pathname;
        var inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'cimp';
        inp.value = '<?php print intval($intCursoImpartido); ?>';
        form.appendChild(inp);
        document.body.appendChild(form);
        form.submit();
    } else {
        fntProcesarErrorSinContenido(response);
        mostrarErrorValidacion(
            fntMensajeErrorRespuesta(response, 'Error al guardar: '),
            fntOptsErrorSinContenido(response)
        );
        btn.textContent = orig;
        btn.disabled = false;
    }

        },
        error: function() {
            mostrarErrorValidacion('Error de conexi?n al guardar');
            btn.textContent = orig;
            btn.disabled = false;
        }

    });
}

function fntOptsErrorSinContenido(response) {
    if (response && response.sin_contenido) {
        return { scrollTo: 'secCronograma' };
    }
    return undefined;
}

function fntMensajeErrorRespuesta(response, strPrefijo) {
    if (response && response.sin_contenido) {
        return 'Existen adjuntos sin contenido';
    }
    var msg = (response && response.msg) ? response.msg : 'Respuesta invalida';
    return (strPrefijo || '') + msg;
}

function fntLimpiarMarcasSinContenido() {
    document.querySelectorAll('#cronoBody tr.crono-sin-contenido').forEach(function(tr) {
        tr.classList.remove('crono-sin-contenido');
    });
}

function fntMarcarFilasSinContenido(arrFilas) {
    fntLimpiarMarcasSinContenido();
    if (!arrFilas || !arrFilas.length) return;
    arrFilas.forEach(function(n) {
        var tr = document.getElementById('trCrono_' + n);
        if (tr) tr.classList.add('crono-sin-contenido');
    });
}

function fntMarcarSinContenidoPorResultados(arrResultados) {
    fntLimpiarMarcasSinContenido();
    if (!arrResultados || !arrResultados.length) return;
    var idx = fntConstruirIndicePkFila();
    arrResultados.forEach(function(res) {
        var pk = parseInt(res.syllabus_uac_cronograma, 10);
        var n = idx[pk];
        if (!n) return;
        var tr = document.getElementById('trCrono_' + n);
        if (tr) tr.classList.add('crono-sin-contenido');
    });
}

function fntProcesarErrorSinContenido(response) {
    if (!response || !response.sin_contenido) return;
    if (response.sin_contenido_filas && response.sin_contenido_filas.length) {
        fntMarcarFilasSinContenido(response.sin_contenido_filas);
        return;
    }
    if (response.sin_contenido_resultados && response.sin_contenido_resultados.length) {
        fntMarcarSinContenidoPorResultados(response.sin_contenido_resultados);
    }
}

// Modal Aprobar
function abrirModalAprobar() {
    if (!validarCamposAntesDePublicar()) {
        return;
    }
    document.getElementById('modalAprobar').classList.add('active');
}

function cerrarModalAprobar() {
    document.getElementById('modalAprobar').classList.remove('active');
}

function confirmarAprobacion() {
    cerrarModalAprobar();

    if (!validarCamposAntesDePublicar()) {
        return;
    }

    var llmCheck = validarTodosCamposLLMAntesDeGuardar();
    if (!llmCheck.ok) {
        var msgLLM = llmCheck.haySugerencias
            ? 'Por favor revise los campos en rojo o las sugerencias de IA.'
            : 'Por favor revise los campos en rojo o las sugerencias de IA.';
        mostrarErrorValidacion(msgLLM, { scrollLLM: true });
        return;
    }

    var btn = document.querySelector('.btn-approve');
    var orig = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Publicando...';

    var fd = construirFormDataSyllabus('prePublicarSyllabusUAC');
    if (!fd) {
        mostrarErrorValidacion('Debe seleccionar un archivo para cada cronograma nuevo antes de publicar.');
        btn.textContent = orig;
        btn.disabled = false;
        return;
    }

    $.ajax({
        url: 'syllabus_catedratico_ws.php',
        method: 'POST',
        processData: false,
        contentType: false,
        data: fd,
        success: function(response) {
            if (typeof response === 'string') {
                try { response = JSON.parse(response); } catch (e) {}
            }
            if (!response || !response.ok) {
                fntProcesarErrorSinContenido(response);
                mostrarErrorValidacion(
                    fntMensajeErrorRespuesta(response, 'Error al publicar: '),
                    fntOptsErrorSinContenido(response)
                );
                btn.textContent = orig;
                btn.disabled = false;
                return;
            }

            fntAplicarMapeoCronograma(response.cronograma_map || {});

            if (response.requiere_confirmacion && response.revision) {
                _publicarRevisionPendiente = response;
                fntProcesarRevisionCronograma(response);
                abrirModalPublicarRevision(response.revision);
                btn.textContent = orig;
                btn.disabled = false;
                return;
            }

            ejecutarConfirmarPublicacion(btn, orig);
        },
        error: function() {
            mostrarErrorValidacion('Error de conexion al publicar');
            btn.textContent = orig;
            btn.disabled = false;
        }
    });
}

function ejecutarConfirmarPublicacion(btn, orig) {
    var fd = construirFormDataSyllabus('confirmarPublicarSyllabusUAC');
    if (!fd) {
        mostrarErrorValidacion('Debe seleccionar un archivo para cada cronograma nuevo antes de publicar.');
        if (btn) {
            btn.textContent = orig;
            btn.disabled = false;
        }
        return;
    }

    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Publicando...';
    }

    $.ajax({
        url: 'syllabus_catedratico_ws.php',
        method: 'POST',
        processData: false,
        contentType: false,
        data: fd,
        success: function(response) {
            if (typeof response === 'string') {
                try { response = JSON.parse(response); } catch (e) {}
            }
            if (response && response.ok) {
                if (btn) {
                    btn.textContent = 'Publicado';
                    btn.style.background = '#1e7e34';
                }
                setTimeout(function() {
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.action = window.location.pathname;
                    var inp = document.createElement('input');
                    inp.type = 'hidden';
                    inp.name = 'cimp';
                    inp.value = '<?php print intval($intCursoImpartido); ?>';
                    form.appendChild(inp);
                    document.body.appendChild(form);
                    form.submit();
                }, 600);
            } else {
                mostrarErrorValidacion('Error al publicar: ' + (response && response.msg ? response.msg : 'Respuesta invalida'));
                if (btn) {
                    btn.textContent = orig;
                    btn.disabled = false;
                }
            }
        },
        error: function() {
            mostrarErrorValidacion('Error de conexion al publicar');
            if (btn) {
                btn.textContent = orig;
                btn.disabled = false;
            }
        }
    });
}

function abrirModalPublicarRevision(revision) {
    revision = revision || {};
    var resumen = document.getElementById('modalPublicarRevisionResumen');
    if (resumen) {
        resumen.textContent = 'Se detectaron errores en '
            + (revision.cronogramas_con_errores || 0) + ' de '
            + (revision.total_cronogramas || 0) + ' cronograma(s) activo(s).';
    }

    var lista = document.getElementById('modalPublicarRevisionLista');
    if (lista) {
        lista.innerHTML = '';
        (revision.resultados || []).forEach(function(res) {
            if (!res.tiene_errores) return;
            var item = document.createElement('div');
            item.className = 'pub-rev-item';

            var info = document.createElement('div');
            info.innerHTML = '<strong>' + fntEscapeHtml(res.nombre_archivo || 'Cronograma') + '</strong>'
                + ' <span style="color:#a30000;">(' + (res.total_errores || 0) + ' error(es))</span>';

            var acciones = document.createElement('div');
            var btnRev = document.createElement('button');
            btnRev.type = 'button';
            btnRev.className = 'btn-crono-rev';
            btnRev.innerHTML = '<span class="btn-crono-rev-icon" aria-hidden="true">&#9888;</span> Descargar revisi&oacute;n';
            btnRev.title = 'Descargar el documento con la revisi\u00F3n ortogr\u00E1fica marcada';
            btnRev.onclick = (function(pk) {
                return function() { fntAbrirRevisionCrono(pk); };
            })(parseInt(res.syllabus_uac_cronograma, 10));

            acciones.appendChild(btnRev);
            item.appendChild(info);
            item.appendChild(acciones);
            lista.appendChild(item);
        });
    }

    document.getElementById('modalPublicarRevision').classList.add('active');
}

function cerrarModalPublicarRevision() {
    document.getElementById('modalPublicarRevision').classList.remove('active');
}

function fntPublicarRevisionRevisarArchivos() {
    cerrarModalPublicarRevision();
    if (_publicarRevisionPendiente) {
        fntProcesarRevisionCronograma(_publicarRevisionPendiente);
    }
    var sec = document.getElementById('secCronograma');
    if (sec) {
        sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function fntPublicarRevisionConfirmar() {
    cerrarModalPublicarRevision();
    var btn = document.querySelector('.btn-approve');
    ejecutarConfirmarPublicacion(btn, 'Guardar y Publicar');
}

function cerrarBannerFlashUAC() {
    var banner = document.getElementById('bannerFlashUAC');
    if (banner) banner.style.display = 'none';
}

function fntInicializarFlashYRevision() {
    if (window.__syllabusRevisionSesion && __syllabusRevisionSesion.resultados) {
        _spellcheckResultados = __syllabusRevisionSesion.resultados;
        fntPintarMarcasSpellcheck(_spellcheckResultados);
    }

    if (window.__syllabusFlashUAC && __syllabusFlashUAC.mensaje) {
        var banner = document.getElementById('bannerFlashUAC');
        var msgEl = document.getElementById('bannerFlashUACMsg');
        if (banner && msgEl) {
            msgEl.textContent = __syllabusFlashUAC.mensaje;
            banner.className = 'uac-flash-banner';
            if (__syllabusFlashUAC.tipo === 'publicado') {
                banner.classList.add('is-published');
            } else if (__syllabusFlashUAC.revisar_adjuntos) {
                banner.classList.add('is-warning');
            } else {
                banner.classList.add('is-success');
            }
            banner.style.display = 'block';
        }
        if (__syllabusFlashUAC.revisar_adjuntos) {
            var sec = document.getElementById('secCronograma');
            if (sec) {
                setTimeout(function() {
                    sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 350);
            }
        }
    }
}

document.addEventListener('DOMContentLoaded', fntInicializarFlashYRevision);

function fntUsarSpellcheckLlm() {
    var el = document.getElementById('chkUsarSpellcheckLlm');
    return !!(el && el.checked);
}

function fntAppendSpellcheckLlmFlag(fd) {
    if (fntUsarSpellcheckLlm()) {
        fd.append('usar_spellcheck_llm', '1');
    }
}

function construirFormDataSyllabus(strAction) {
    var fd = new FormData();
    fd.append('ACTION', strAction);
    fd.append('cimp', <?php print intval($intCursoImpartido); ?>);
    fntAppendSpellcheckLlmFlag(fd);

    fd.append('hidEditedNormas', document.getElementById('hidEditedNormas').value);
    if (document.getElementById('hidEditedNormas').value === 'Y' && summernoteNormasInit) {
        fd.append('normas', $('#txtNormas').summernote('code'));
    }

    fd.append('hidEditedUsoIA', document.getElementById('hidEditedUsoIA').value);
    if (document.getElementById('hidEditedUsoIA').value === 'Y' && summernoteUsoIAInit) {
        fd.append('uso_ia', $('#txtUsoIA').summernote('code'));
    }

    fd.append('hidEditedPensamientoCritico', document.getElementById('hidEditedPensamientoCritico').value);
    if (document.getElementById('hidEditedPensamientoCritico').value === 'Y' && summernotePensamientoCriticoInit) {
        fd.append('pensamiento_critico', $('#txtPensamientoCritico').summernote('code'));
    }

    document.querySelectorAll('#evalBody tr[id^="trEval_"]').forEach(function(tr) {
        if (tr.id === 'trEvalInicial') return;
        var n = tr.id.replace('trEval_', '');
        fd.append('hidDeleteEval_' + n, $('#hidDeleteEval_' + n).val() || 'N');
        fd.append('hidEditedEval_' + n, $('#hidEditedEval_' + n).val() || 'N');
        if ($('#hidUpdateEval_' + n).length) fd.append('hidUpdateEval_' + n, $('#hidUpdateEval_' + n).val());
        if ($('#hidNewEval_' + n).length) fd.append('hidNewEval_' + n, $('#hidNewEval_' + n).val());
        var esNuevaEval = $('#hidNewEval_' + n).length > 0 && $('#hidNewEval_' + n).val() === '1';
        var fueEditadaEval = $('#hidEditedEval_' + n).val() === 'Y';
        var rubro, pct;
        if (esNuevaEval || fueEditadaEval) {
            rubro = ($('#txtRubroEval_' + n).val() || '').trim();
            pct   = $('#txtPctEval_' + n).val() || '0';
        } else {
            rubro = ($('#spanRubroEval_' + n).text() || '').trim();
            pct   = ($('#spanPctEval_' + n).text() || '').replace('%', '').trim() || '0';
        }
        fd.append('txtRubroEval_' + n, rubro);
        fd.append('txtPctEval_' + n, pct);
    });

    document.querySelectorAll('#biblioEvList .biblio-ev-item[id^="liBiblio_"]').forEach(function(li) {
        var n = li.id.replace('liBiblio_', '');
        var hidDelete = li.querySelector('[id^="hidDeleteBiblio_"]');
        var hidEdited = li.querySelector('[id^="hidEditedBiblio_"]');
        var hidUpdate = li.querySelector('[id^="hidUpdateBiblio_"]');
        var hidNew    = li.querySelector('[id^="hidNewBiblio_"]');
        var spanRef   = li.querySelector('[id^="spanBiblio_"]');
        fd.append('hidDeleteBiblio_' + n, hidDelete ? hidDelete.value : 'N');
        fd.append('hidEditedBiblio_' + n, hidEdited ? hidEdited.value : 'N');
        if (hidUpdate) fd.append('hidUpdateBiblio_' + n, hidUpdate.value);
        if (hidNew) fd.append('hidNewBiblio_' + n, hidNew.value);
        var esNueva = hidNew && hidNew.value === '1';
        var fueEditada = hidEdited && hidEdited.value === 'Y';
        var ref;
        if (esNueva || fueEditada) {
            fntSyncBiblioEvToPost(n);
            ref = fntGetHtmlBiblioEv(n);
        } else {
            ref = spanRef ? spanRef.innerHTML.trim() : '';
        }
        fd.append('txtBiblio_' + n, ref);
    });

    document.querySelectorAll('#expList .biblio-ev-item[id^="liExp_"]').forEach(function(li) {
        var n = li.id.replace('liExp_', '');
        var hidDelete = li.querySelector('[id^="hidDeleteExp_"]');
        var hidEdited = li.querySelector('[id^="hidEditedExp_"]');
        var hidUpdate = li.querySelector('[id^="hidUpdateExp_"]');
        var hidNew    = li.querySelector('[id^="hidNewExp_"]');
        var spanDesc  = li.querySelector('[id^="spanExp_"]');
        fd.append('hidDeleteExp_' + n, hidDelete ? hidDelete.value : 'N');
        fd.append('hidEditedExp_' + n, hidEdited ? hidEdited.value : 'N');
        if (hidUpdate) fd.append('hidUpdateExp_' + n, hidUpdate.value);
        if (hidNew) fd.append('hidNewExp_' + n, hidNew.value);
        var esNueva = hidNew && hidNew.value === '1';
        var fueEditada = hidEdited && hidEdited.value === 'Y';
        var desc;
        if (esNueva || fueEditada) {
            var textarea = li.querySelector('[id^="txtExp_"]');
            desc = textarea ? textarea.value.trim() : '';
        } else {
            desc = spanDesc ? spanDesc.textContent.trim() : '';
        }
        fd.append('txtExp_' + n, desc);
    });

    var cronoError = false;
    document.querySelectorAll('#cronoBody tr[id^="trCrono_"]').forEach(function(tr) {
        if (cronoError) return;
        var n = tr.id.replace('trCrono_', '');
        var hidDel    = tr.querySelector('#hidDeleteCrono_' + n);
        var hidEdited = tr.querySelector('#hidEditedCrono_' + n);
        var hidUpdate = tr.querySelector('#hidUpdateCrono_' + n);
        var hidNew    = tr.querySelector('#hidNewCrono_' + n);
        var hidActivo = tr.querySelector('#hidActivoCrono_' + n);

        fd.append('hidDeleteCrono_' + n, hidDel    ? hidDel.value    : 'N');
        fd.append('hidEditedCrono_' + n, hidEdited ? hidEdited.value : 'N');
        if (hidUpdate) fd.append('hidUpdateCrono_' + n, hidUpdate.value);
        if (hidNew)    fd.append('hidNewCrono_' + n, hidNew.value);
        if (hidActivo) fd.append('hidActivoCrono_' + n, hidActivo.value);

        var esCronoNuevo = hidNew && hidNew.value === '1';
        var esCronoEliminado = hidDel && hidDel.value === 'Y';
        if (esCronoNuevo && !esCronoEliminado && typeof _cronoArchivosPendientes !== 'undefined' && !_cronoArchivosPendientes[n]) {
            cronoError = true;
            return;
        }

        if (typeof _cronoArchivosPendientes !== 'undefined' && _cronoArchivosPendientes[n]) {
            var f = _cronoArchivosPendientes[n];
            fd.append('archivo_cronograma_' + n, f, f.name);
        }
    });

    if (cronoError) {
        return null;
    }

    return fd;
}

// ===== SPELLCHECK CRONOGRAMA (marcas rojo/verde/gris + modal) =====
var _spellcheckResultados = [];

// Aplica el mapeo fila->PK a los cronogramas recien creados para poder
// identificarlos por SYLLABUS_UAC_CRONOGRAMA y evitar reinsertarlos al re-publicar.
function fntAplicarMapeoCronograma(cronogramaMap) {
    if (!cronogramaMap) return;
    Object.keys(cronogramaMap).forEach(function(n) {
        var pk = parseInt(cronogramaMap[n], 10);
        if (!pk) return;
        var tr = document.getElementById('trCrono_' + n);
        if (!tr) return;

        var hidUpdate = document.getElementById('hidUpdateCrono_' + n);
        if (!hidUpdate) {
            hidUpdate = document.createElement('input');
            hidUpdate.type = 'hidden';
            hidUpdate.id = 'hidUpdateCrono_' + n;
            tr.querySelector('td:last-child').appendChild(hidUpdate);
        }
        hidUpdate.value = pk;

        var hidNew = document.getElementById('hidNewCrono_' + n);
        if (hidNew) hidNew.value = '0';

        var hidEdited = document.getElementById('hidEditedCrono_' + n);
        if (hidEdited) hidEdited.value = 'N';

        if (typeof _cronoArchivosPendientes !== 'undefined') {
            delete _cronoArchivosPendientes[n];
        }

        // El cronograma ya esta en S3: convertir el nombre en boton de descarga.
        var spanNom = document.getElementById('spanNuevoNomCrono_' + n);
        if (spanNom && spanNom.parentNode) {
            var nombre = spanNom.textContent || '';
            var btnDesc = document.createElement('button');
            btnDesc.type = 'button';
            btnDesc.className = 'bitacora-btn-descarga file-name';
            btnDesc.title = 'Descargar archivo';
            btnDesc.textContent = nombre;
            btnDesc.onclick = (function(id) {
                return function() { fntDescargarCronoAdjunto(id); };
            })(pk);
            spanNom.parentNode.replaceChild(btnDesc, spanNom);
        }
    });
}

// Construye un indice PK -> indice de fila n (para pintar cada resultado).
function fntConstruirIndicePkFila() {
    var idx = {};
    document.querySelectorAll('#cronoBody tr[id^="trCrono_"]').forEach(function(tr) {
        var n = tr.id.replace('trCrono_', '');
        var hidUpdate = document.getElementById('hidUpdateCrono_' + n);
        if (hidUpdate && hidUpdate.value) {
            idx[parseInt(hidUpdate.value, 10)] = n;
        }
    });
    return idx;
}

function fntLimpiarMarcasSpellcheck() {
    document.querySelectorAll('#cronoBody tr[id^="trCrono_"]').forEach(function(tr) {
        tr.classList.remove('crono-spell-ok', 'crono-spell-omitido', 'crono-sin-contenido');
        tr.querySelectorAll('.crono-spell-badge, .btn-crono-rev[data-dynamic="1"]').forEach(function(el) {
            el.remove();
        });
    });
}

function fntPintarMarcasSpellcheck(resultados) {
    fntLimpiarMarcasSpellcheck();
    var idx = fntConstruirIndicePkFila();

    resultados.forEach(function(res) {
        var pk = parseInt(res.syllabus_uac_cronograma, 10);
        var n = idx[pk];
        if (!n) return;
        var tr = document.getElementById('trCrono_' + n);
        if (!tr) return;

        var celda = tr.querySelector('td');
        if (!celda) return;

        if (res.sin_contenido) {
            tr.classList.add('crono-sin-contenido');
            return;
        }

        if (res.omitido || !res.ok) {
            tr.classList.add('crono-spell-omitido');
            var noteOmit = document.createElement('span');
            noteOmit.className = 'crono-spell-badge is-omitido';
            noteOmit.textContent = res.omitido ? 'No verificable' : 'No verificado';
            if (res.motivo) noteOmit.title = res.motivo;
            celda.appendChild(noteOmit);
            return;
        }

        if (res.tiene_errores) {
            // Sin fondo rojo en la fila: el banner, el scroll y el boton de revision
            // son suficientes para indicar que hay errores ortograficos.
            fntAgregarBotonRevisionCrono(celda, pk, true);
            return;
        }

        tr.classList.add('crono-spell-ok');
    });
}

function fntAgregarBotonRevisionCrono(celda, intCronoId, bolDynamic) {
    if (!celda) return;
    if (celda.querySelector('.btn-crono-rev')) return;

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn-crono-rev';
    btn.setAttribute('data-pk', intCronoId);
    if (bolDynamic) btn.setAttribute('data-dynamic', '1');
    btn.innerHTML = '<span class="btn-crono-rev-icon" aria-hidden="true">&#9888;</span> Descargar revisi&oacute;n';
    btn.title = 'Descargar el documento con la revisi\u00F3n ortogr\u00E1fica marcada';
    btn.onclick = (function(id) {
        return function() { fntAbrirRevisionCrono(id); };
    })(intCronoId);
    celda.appendChild(btn);
}

function fntEscapeHtml(str) {
    return (str === null || str === undefined ? '' : String(str))
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function fntRenderErroresSpellcheck(pkFiltro) {
    var html = '';
    var conErrores = _spellcheckResultados.filter(function(res) {
        if (pkFiltro) return parseInt(res.syllabus_uac_cronograma, 10) === parseInt(pkFiltro, 10);
        return res.tiene_errores;
    });

    if (!conErrores.length) {
        return '<p class="spell-empty-ok">No hay errores ortogr\u00E1ficos que mostrar.</p>';
    }

    conErrores.forEach(function(res) {
        html += '<div class="spell-file-block">';
        html += '<h4>' + fntEscapeHtml(res.nombre_archivo || 'Cronograma');
        if (res.tiene_errores) {
            html += ' <span class="spell-file-count">(' + res.total_errores + ' error(es))</span>';
        }
        html += '</h4>';

        if (res.errores && res.errores.length) {
            html += '<ul class="spell-error-list">';
            res.errores.forEach(function(err) {
                var sugg = (err.sugerencias && err.sugerencias.length)
                    ? err.sugerencias.map(fntEscapeHtml).join(', ')
                    : 'sin sugerencias';
                html += '<li><span class="spell-word">' + fntEscapeHtml(err.palabra) + '</span>'
                     +  ' &rarr; <span class="spell-sugg">' + sugg + '</span></li>';
            });
            html += '</ul>';
        } else if (res.sin_contenido) {
            html += '<p class="spell-empty-ok">Archivo sin contenido.</p>';
        } else {
            html += '<p class="spell-empty-ok">Sin errores.</p>';
        }

        if (res.path_archivo_rev || res.url_descargar_rev || res.url_ver_rev) {
            html += '<p><button type="button" class="btn-crono-rev" title="Descargar el documento con la revisi&oacute;n ortogr&aacute;fica marcada" onclick="fntAbrirRevisionCrono('
                 + parseInt(res.syllabus_uac_cronograma, 10) + ')">'
                 + '<span class="btn-crono-rev-icon" aria-hidden="true">&#9888;</span> Descargar revisi&oacute;n</button></p>';
        }
        html += '</div>';
    });

    return html;
}

function abrirModalSpellcheck(pkFiltro) {
    document.getElementById('modalSpellcheckContent').innerHTML = fntRenderErroresSpellcheck(pkFiltro || null);
    document.getElementById('modalSpellcheck').classList.add('active');
}

function cerrarModalSpellcheck() {
    document.getElementById('modalSpellcheck').classList.remove('active');
}

function fntProcesarRevisionCronograma(response) {
    var rev = response.revision || response.spellcheck || {};
    _spellcheckResultados = rev.resultados || [];
    fntAplicarMapeoCronograma(response.cronograma_map || {});
    fntPintarMarcasSpellcheck(_spellcheckResultados);
}

function fntProcesarSpellcheckCronograma(response) {
    fntProcesarRevisionCronograma(response);
    var rev = response.revision || response.spellcheck || {};
    var resumen = 'Se encontraron errores ortogr\u00E1ficos en '
        + (rev.cronogramas_con_errores || 0) + ' de '
        + (rev.total_cronogramas || 0) + ' cronograma(s).';
    var elResumen = document.getElementById('modalSpellcheckResumen');
    if (elResumen) elResumen.textContent = resumen;
}

function fntMostrarVersionesPublicadas() {
    $.ajax({
        url: 'syllabus_catedratico_ws.php',
        type: 'POST',
        data: {
            ACTION: 'drawBlurVersionesPublicadas',
            cimp: <?php print intval($intCursoImpartido); ?>
        },
        dataType: 'html',
        success: function(html) {
            document.getElementById('modalVersionesPublicadasContent').innerHTML = html;
            document.getElementById('modalVersionesPublicadas').classList.add('active');
        },
        error: function() {
            alert('Error al cargar versiones publicadas');
        }
    });
}

function cerrarModalVersionesPublicadas() {
    document.getElementById('modalVersionesPublicadas').classList.remove('active');
    document.getElementById('modalVersionesPublicadasContent').innerHTML = '';
}

function fntAbrirVisorPdf(url, strTitulo) {
    var titulo = strTitulo || 'PDF';
    document.getElementById('modalPdfViewerTitulo').textContent = titulo;
    document.getElementById('modalPdfViewerFrame').src = '';
    document.getElementById('modalPdfViewer').classList.add('active');
    document.getElementById('modalPdfViewerFrame').src = url;
}

function fntAbrirVisorPdfPublicado(intVersionId, strTitulo) {
    if (!intVersionId || intVersionId <= 0) {
        mostrarErrorValidacion('Version publicada no valida.');
        return;
    }

    var titulo = strTitulo || 'Programa publicado';
    document.getElementById('modalPdfViewerTitulo').textContent = titulo;
    document.getElementById('modalPdfViewerFrame').src = '';

    $.ajax({
        url: 'syllabus_catedratico_ws.php',
        type: 'POST',
        dataType: 'json',
        data: {
            ACTION: 'descargarPdfVersionPublicada',
            cimp: <?php print intval($intCursoImpartido); ?>,
            version_id: intVersionId,
            modo: 'ver'
        },
        success: function(resp) {
            if (resp && resp.ok && resp.url) {
                fntAbrirVisorPdf(resp.url, titulo);
            } else {
                mostrarErrorValidacion(resp && resp.msg ? resp.msg : 'No se pudo abrir el PDF');
            }
        },
        error: function() {
            mostrarErrorValidacion('Error de conexion al cargar el PDF');
        }
    });
}

function cerrarModalPdfViewer() {
    document.getElementById('modalPdfViewer').classList.remove('active');
    document.getElementById('modalPdfViewerFrame').src = '';
}

function fntVerPdfVersionPublicada(intVersionId) {
    fntAbrirVisorPdfPublicado(intVersionId, 'Programa publicado');
}

document.getElementById('modalVersionesPublicadas').addEventListener('click', function(e) {
    if (e.target === this) cerrarModalVersionesPublicadas();
});
document.getElementById('modalPdfViewer').addEventListener('click', function(e) {
    if (e.target === this) cerrarModalPdfViewer();
});

document.getElementById('modalAprobar').addEventListener('click', function(e) {
    if (e.target === this) cerrarModalAprobar();
});

function mostrarAyuda(titulo) {
    document.getElementById('modalAyudaTitulo').textContent = titulo;
    document.getElementById('modalAyudaContenido').innerHTML =
        document.getElementById('hidContenidoAyudaBiblio').innerHTML;
    document.getElementById('modalAyuda').classList.add('active');
}

function cerrarModalAyuda() {
    document.getElementById('modalAyuda').classList.remove('active');
}

document.getElementById('modalAyuda').addEventListener('click', function(e) {
    if (e.target === this) cerrarModalAyuda();
});

// Cerrar modal de validaci?n al hacer clic en el fondo blur
document.getElementById('modalValidacion').addEventListener('click', function(e) {
    if (e.target === this) cerrarModalValidacion();
});

// ========== BITACORA ==========

function abrirModalBitacora() {
    document.getElementById('modalBitacora').classList.add('active');
}

function cerrarModalBitacora() {
    document.getElementById('modalBitacora').classList.remove('active');
    document.getElementById('modalBitacoraContent').innerHTML = '';
}

function abrirModalBitacoraDetalle() {
    document.getElementById('modalBitacoraDetalle').classList.add('active');
}

function cerrarModalBitacoraDetalle() {
    document.getElementById('modalBitacoraDetalle').classList.remove('active');
    document.getElementById('modalBitacoraDetalleContent').innerHTML = '';
}

function bitacoraPostData(extra) {
    var data = {
        cimp: intCimpBitacora,
        syllabus_uac: intSyllabusUAC
    };
    if (extra) {
        for (var k in extra) {
            if (extra.hasOwnProperty(k)) data[k] = extra[k];
        }
    }
    return data;
}

function bitacoraAjaxPrincipal(postData, targetDetalle) {
    if (intSyllabusUAC <= 0) {
        alert('Guarde el syllabus al menos una vez para ver la bit\u00e1cora.');
        return;
    }
    var $target = targetDetalle ? $('#modalBitacoraDetalleContent') : $('#modalBitacoraContent');
    $.ajax({
        url: strBitacoraUrl,
        type: 'POST',
        data: postData,
        dataType: 'html',
        success: function(html) {
            $target.html(html);
            if (targetDetalle) {
                abrirModalBitacoraDetalle();
            } else {
                abrirModalBitacora();
            }
        },
        error: function() {
            alert('Error al cargar la bit\u00e1cora.');
        }
    });
}

function fntMostrarBitacoraNormas() {
    bitacoraAjaxPrincipal(bitacoraPostData({
        drawBlurBitacoraCampoUAC: true,
        campo: 'NORMAS_REGLAS'
    }));
}

function fntMostrarBitacoraUsoIA() {
    bitacoraAjaxPrincipal(bitacoraPostData({
        drawBlurBitacoraCampoUAC: true,
        campo: 'USO_IA'
    }));
}

function fntMostrarBitacoraPensamientoCritico() {
    bitacoraAjaxPrincipal(bitacoraPostData({
        drawBlurBitacoraCampoUAC: true,
        campo: 'PENSAMIENTO_CRITICO'
    }));
}

function fntDescargarCronogramaBitacora(intLogId) {
    $.ajax({
        url: 'syllabus_catedratico_ws.php',
        type: 'POST',
        data: {
            ajaxDescargarCronogramaBitacora: true,
            cimp: intCimpBitacora,
            syllabus_uac: intSyllabusUAC,
            log_id: intLogId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.url_descarga) {
                var a = document.createElement('a');
                a.href = response.url_descarga;
                a.download = response.nombre_archivo || '';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            } else {
                mostrarErrorValidacion(response.msg || 'Error al descargar el cronograma.');
            }
        },
        error: function() {
            mostrarErrorValidacion('Error al procesar la descarga del cronograma.');
        }
    });
}

function fntVerDetalleLogCampoUAC(intLogId, strCampo, strNombreCampo) {
    bitacoraAjaxPrincipal(bitacoraPostData({
        drawBlurDetalleLogCampoUAC: true,
        log_id: intLogId,
        campo: strCampo,
        nombre_campo: strNombreCampo
    }), true);
}

function fntVerValorActualCampoUAC(intSid, strCampo, strNombreCampo) {
    bitacoraAjaxPrincipal(bitacoraPostData({
        drawBlurValorActualCampoUAC: true,
        campo: strCampo,
        nombre_campo: strNombreCampo
    }), true);
}

function fntMostrarBitacoraTodosEval() {
    bitacoraAjaxPrincipal(bitacoraPostData({
        drawBlurBitacoraTodosEval: true
    }));
}

function fntMostrarBitacoraEval(intEvalId) {
    bitacoraAjaxPrincipal(bitacoraPostData({
        drawBlurBitacoraEval: true,
        syllabus_uac_eval: intEvalId
    }), true);
}

function fntMostrarBitacoraEvalEliminado(intEvalId) {
    bitacoraAjaxPrincipal(bitacoraPostData({
        drawBlurBitacoraEvalEliminado: true,
        syllabus_uac_eval: intEvalId
    }), true);
}

function fntMostrarBitacoraTodosBiblioEv() {
    bitacoraAjaxPrincipal(bitacoraPostData({
        drawBlurBitacoraTodosBiblioEv: true
    }));
}

function fntMostrarBitacoraBiblioEv(intBiblioId) {
    bitacoraAjaxPrincipal(bitacoraPostData({
        drawBlurBitacoraBiblioEv: true,
        syllabus_uac_biblio: intBiblioId
    }), true);
}

function fntMostrarBitacoraBiblioEvEliminado(intBiblioId) {
    bitacoraAjaxPrincipal(bitacoraPostData({
        drawBlurBitacoraBiblioEvEliminado: true,
        syllabus_uac_biblio: intBiblioId
    }), true);
}

function fntMostrarBitacoraTodosExp() {
    bitacoraAjaxPrincipal(bitacoraPostData({
        drawBlurBitacoraTodosExp: true
    }));
}

function fntMostrarBitacoraExp(intExpId) {
    bitacoraAjaxPrincipal(bitacoraPostData({
        drawBlurBitacoraExp: true,
        syllabus_uac_exp: intExpId
    }), true);
}

function fntMostrarBitacoraExpEliminado(intExpId) {
    bitacoraAjaxPrincipal(bitacoraPostData({
        drawBlurBitacoraExpEliminado: true,
        syllabus_uac_exp: intExpId
    }), true);
}

document.getElementById('modalBitacora').addEventListener('click', function(e) {
    if (e.target === this) cerrarModalBitacora();
});
document.getElementById('modalBitacoraDetalle').addEventListener('click', function(e) {
    if (e.target === this) cerrarModalBitacoraDetalle();
});

// Inicializar
recalcTotal();

</script>





</body>



        

</html>
