<?php
require_once("core/main.php");

require_once("core/forms.php");

require_once __DIR__ . '/core/aws-php-sdk/s3_carga_descarga_funciones.php';
require_once __DIR__ . '/syllabus_catedratico_bitacora.php';
require_once __DIR__ . '/syllabus_catedratico_versiones.php';
require_once __DIR__ . '/syllabus_catedratico_pdf_gutenberg.php';
require_once __DIR__ . '/syllabus_catedratico_spellcheck.php';
require_once __DIR__ . '/syllabus_catedratico_spellcheck_llm.php';


global $arrConfigSite;
$globalConnection = $arrConfigSite["db"]["database_resource"];

function syl_ws_cronogramaExtensionPermitida($strExt)
{
    $strExt = strtolower(trim((string) $strExt));
    $arrPermitidas = [
        'pdf',
        'doc', 'docx', 'docm', 'odt', 'rtf',
        'xls', 'xlsx', 'xlsm', 'ods', 'csv',
        'ppt', 'pptx', 'pptm', 'odp',
    ];

    return in_array($strExt, $arrPermitidas, true);
}

/** Checkbox de prueba en syllabus_catedratico.php → POST usar_spellcheck_llm=1 */
function syl_ws_usarSpellcheckLlm()
{
    $strVal = isset($_POST['usar_spellcheck_llm']) ? trim((string) $_POST['usar_spellcheck_llm']) : '';
    return ($strVal === '1' || strcasecmp($strVal, 'true') === 0 || strcasecmp($strVal, 'Y') === 0);
}

$strAction = isset($_REQUEST['ACTION']) ? $_REQUEST['ACTION'] : '';

if ($strAction === 'verCronoRevPreview') {

    $intCronoId = isset($_REQUEST['crono_id']) ? intval($_REQUEST['crono_id']) : 0;
    $intCimp    = isset($_REQUEST['cimp'])     ? intval($_REQUEST['cimp'])     : 0;
    $intAddUser = isset($_SESSION["hml"]["persona"]) ? intval($_SESSION["hml"]["persona"]) : 0;

    if ($intCronoId <= 0 || $intCimp <= 0 || $intAddUser <= 0) {
        http_response_code(403);
        die('Acceso denegado');
    }

    $stidPath = oci_parse($globalConnection,
        "SELECT c.PATH_ARCHIVO_REV, c.NOMBRE_ARCHIVO
         FROM   SYLLABUS_UA_CATEDRATICO_CRONOGRAMA c
         JOIN   SYLLABUS_UA_CATEDRATICO s
                ON c.SYLLABUS_UA_CATEDRATICO = s.SYLLABUS_UA_CATEDRATICO
         WHERE  c.SYLLABUS_UAC_CRONOGRAMA = :p_crono_id
           AND  s.CURSO_IMPARTIDO = :p_curso_impartido
           AND  s.FECHA_INICIO IS NULL
           AND  s.FECHA_FIN IS NULL
           AND  c.ACTIVO = 'Y'");
    oci_bind_by_name($stidPath, ':p_crono_id', $intCronoId, -1, SQLT_INT);
    oci_bind_by_name($stidPath, ':p_curso_impartido', $intCimp,    -1, SQLT_INT);
    oci_execute($stidPath);
    $rowPath = oci_fetch_array($stidPath, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stidPath);

    $strPathCronograma  = trim($rowPath['PATH_ARCHIVO_REV'] ?? '');
    $strNombreCronograma = trim($rowPath['NOMBRE_ARCHIVO'] ?? '');

    if ($strPathCronograma === '') {
        http_response_code(404);
        die('Revision no encontrada');
    }

    $strExtCronograma = strtolower(pathinfo($strNombreCronograma, PATHINFO_EXTENSION));

    if ($strExtCronograma !== 'pdf') {
        http_response_code(415);
        header('Content-Type: text/plain; charset=utf-8');
        die('Vista previa solo disponible para revisiones PDF.');
    }

    $objBody = core_DescargarContenidoS3DesdeUrl($strPathCronograma);

    if ($objBody === false) {
        http_response_code(500);
        die('No se pudo cargar la revision');
    }

    $strPdf = is_object($objBody) && method_exists($objBody, 'getContents')
        ? $objBody->getContents()
        : (string) $objBody;

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . rawurlencode('rev_' . $strNombreCronograma) . '"');
    header('Content-Length: ' . strlen($strPdf));
    header('Cache-Control: private, max-age=300');
    echo $strPdf;
    die();
}

if ($strAction === 'verCronoPreview') {

    $intCronoId = isset($_REQUEST['crono_id']) ? intval($_REQUEST['crono_id']) : 0;
    $intCimp    = isset($_REQUEST['cimp'])     ? intval($_REQUEST['cimp'])     : 0;
    $intAddUser = isset($_SESSION["hml"]["persona"]) ? intval($_SESSION["hml"]["persona"]) : 0;

    if ($intCronoId <= 0 || $intCimp <= 0 || $intAddUser <= 0) {
        http_response_code(403);
        die('Acceso denegado');
    }

    $stidPath = oci_parse($globalConnection,
        "SELECT c.PATH_ARCHIVO, c.NOMBRE_ARCHIVO
         FROM   SYLLABUS_UA_CATEDRATICO_CRONOGRAMA c
         JOIN   SYLLABUS_UA_CATEDRATICO s
                ON c.SYLLABUS_UA_CATEDRATICO = s.SYLLABUS_UA_CATEDRATICO
         WHERE  c.SYLLABUS_UAC_CRONOGRAMA = :p_crono_id
           AND  s.CURSO_IMPARTIDO = :p_curso_impartido
           AND  s.FECHA_INICIO IS NULL
           AND  s.FECHA_FIN IS NULL
           AND  c.ACTIVO = 'Y'");
    oci_bind_by_name($stidPath, ':p_crono_id', $intCronoId, -1, SQLT_INT);
    oci_bind_by_name($stidPath, ':p_curso_impartido', $intCimp,    -1, SQLT_INT);
    oci_execute($stidPath);
    $rowPath = oci_fetch_array($stidPath, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stidPath);

    $strPathCronograma  = trim($rowPath['PATH_ARCHIVO']    ?? '');
    $strNombreCronograma = trim($rowPath['NOMBRE_ARCHIVO'] ?? '');

    if ($strPathCronograma === '') {
        http_response_code(404);
        die('Cronograma no encontrado');
    }

    $strExtCronograma = strtolower(pathinfo($strNombreCronograma, PATHINFO_EXTENSION));

    if ($strExtCronograma !== 'pdf') {
        http_response_code(415);
        header('Content-Type: text/plain; charset=utf-8');
        die('Vista previa solo disponible para archivos PDF.');
    }

    $objBody = core_DescargarContenidoS3DesdeUrl($strPathCronograma);

    if ($objBody === false) {
        http_response_code(500);
        die('No se pudo cargar el cronograma');
    }

    $strPdf = is_object($objBody) && method_exists($objBody, 'getContents')
        ? $objBody->getContents()
        : (string) $objBody;

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . rawurlencode($strNombreCronograma) . '"');
    header('Content-Length: ' . strlen($strPdf));
    header('Cache-Control: private, max-age=300');
    echo $strPdf;
    die();
}

if (isset($_POST['ajaxDescargarCronoAdjunto']) && $_POST['ajaxDescargarCronoAdjunto']) {

    header('Content-Type: application/json; charset=utf-8');

    $intCimp        = isset($_POST['cimp'])              ? intval($_POST['cimp'])              : 0;
    $intSyllabusUAC = isset($_POST['syllabus_uac'])      ? intval($_POST['syllabus_uac'])      : 0;
    $intCronoId     = isset($_POST['syllabus_uac_crono'])? intval($_POST['syllabus_uac_crono']): 0;
    $intAddUser     = isset($_SESSION['hml']['persona']) ? intval($_SESSION['hml']['persona']) : 0;

    if ($intCimp <= 0 || $intSyllabusUAC <= 0 || $intCronoId <= 0 || $intAddUser <= 0) {
        echo json_encode(['success' => false, 'msg' => 'Acceso denegado']);
        die();
    }

    if (!uac_bitacora_verificarSyllabus($globalConnection, $intSyllabusUAC, $intCimp)) {
        echo json_encode(['success' => false, 'msg' => 'Acceso denegado']);
        die();
    }

    $stid = oci_parse($globalConnection,
        "SELECT PATH_ARCHIVO, NOMBRE_ARCHIVO
         FROM   SYLLABUS_UA_CATEDRATICO_CRONOGRAMA
         WHERE  SYLLABUS_UAC_CRONOGRAMA   = :p_crono_id
           AND  SYLLABUS_UA_CATEDRATICO   = :p_syllabus_uac_id");
    oci_bind_by_name($stid, ':p_crono_id', $intCronoId,     -1, SQLT_INT);
    oci_bind_by_name($stid, ':p_syllabus_uac_id',  $intSyllabusUAC, -1, SQLT_INT);
    oci_execute($stid);
    $row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stid);

    $strPath   = trim($row['PATH_ARCHIVO']    ?? '');
    $strNombre = trim($row['NOMBRE_ARCHIVO']  ?? '');

    if ($strPath === '') {
        echo json_encode(['success' => false, 'msg' => 'Archivo no encontrado']);
        die();
    }

    if ($strNombre === '') {
        $strNombre = basename(parse_url($strPath, PHP_URL_PATH));
    }

    $strUrlDescarga = core_ObtenerUrlDescargaS3DesdeUrl($strPath, 3600, $strNombre);

    if (empty($strUrlDescarga)) {
        echo json_encode(['success' => false, 'msg' => 'No se pudo generar la descarga']);
        die();
    }

    echo json_encode(['success' => true, 'url_descarga' => $strUrlDescarga, 'nombre_archivo' => $strNombre]);
    die();
}

if (isset($_POST['ajaxDescargarCronoRev']) && $_POST['ajaxDescargarCronoRev']) {

    header('Content-Type: application/json; charset=utf-8');

    $intCimp        = isset($_POST['cimp'])              ? intval($_POST['cimp'])              : 0;
    $intSyllabusUAC = isset($_POST['syllabus_uac'])      ? intval($_POST['syllabus_uac'])      : 0;
    $intCronoId     = isset($_POST['syllabus_uac_crono'])? intval($_POST['syllabus_uac_crono']): 0;
    $intAddUser     = isset($_SESSION['hml']['persona']) ? intval($_SESSION['hml']['persona']) : 0;

    if ($intCimp <= 0 || $intSyllabusUAC <= 0 || $intCronoId <= 0 || $intAddUser <= 0) {
        echo json_encode(['success' => false, 'msg' => 'Acceso denegado']);
        die();
    }

    if (!uac_bitacora_verificarSyllabus($globalConnection, $intSyllabusUAC, $intCimp)) {
        echo json_encode(['success' => false, 'msg' => 'Acceso denegado']);
        die();
    }

    $stid = oci_parse($globalConnection,
        "SELECT PATH_ARCHIVO_REV, NOMBRE_ARCHIVO, PATH_ARCHIVO
         FROM   SYLLABUS_UA_CATEDRATICO_CRONOGRAMA
         WHERE  SYLLABUS_UAC_CRONOGRAMA   = :p_crono_id
           AND  SYLLABUS_UA_CATEDRATICO   = :p_syllabus_uac_id");
    oci_bind_by_name($stid, ':p_crono_id', $intCronoId,     -1, SQLT_INT);
    oci_bind_by_name($stid, ':p_syllabus_uac_id',  $intSyllabusUAC, -1, SQLT_INT);
    oci_execute($stid);
    $row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stid);

    $strPath   = trim($row['PATH_ARCHIVO_REV'] ?? '');
    $strNombre = trim($row['NOMBRE_ARCHIVO']  ?? '');

    if ($strPath === '') {
        echo json_encode(['success' => false, 'msg' => 'No hay archivo de revision para este cronograma']);
        die();
    }

    if ($strNombre === '') {
        $strNombre = basename(parse_url($strPath, PHP_URL_PATH));
    } else {
        $strNombre = 'rev_' . $strNombre;
    }

    $strUrlDescarga = core_ObtenerUrlDescargaS3DesdeUrl($strPath, 3600, $strNombre);
    $strUrlVer = null;
    if (strtolower(pathinfo($strNombre, PATHINFO_EXTENSION)) === 'pdf') {
        $strUrlVer = core_ObtenerUrlVerS3DesdeUrl($strPath);
    }

    if (empty($strUrlDescarga)) {
        echo json_encode(['success' => false, 'msg' => 'No se pudo generar la descarga de revision']);
        die();
    }

    echo json_encode([
        'success'        => true,
        'url_descarga'   => $strUrlDescarga,
        'url_ver'        => $strUrlVer,
        'nombre_archivo' => $strNombre,
    ]);
    die();
}

if (isset($_POST['ajaxDescargarCronogramaBitacora']) && $_POST['ajaxDescargarCronogramaBitacora']) {

    header('Content-Type: application/json; charset=utf-8');

    $intCimp        = isset($_POST['cimp'])         ? intval($_POST['cimp'])         : 0;
    $intSyllabusUAC = isset($_POST['syllabus_uac']) ? intval($_POST['syllabus_uac']) : 0;
    $intLogId       = isset($_POST['log_id'])       ? intval($_POST['log_id'])       : 0;
    $intCronoId     = isset($_POST['syllabus_uac_crono']) ? intval($_POST['syllabus_uac_crono']) : 0;
    $intAddUser     = isset($_SESSION['hml']['persona']) ? intval($_SESSION['hml']['persona']) : 0;

    if ($intCimp <= 0 || $intSyllabusUAC <= 0 || $intAddUser <= 0) {
        echo json_encode(['success' => false, 'msg' => 'Acceso denegado']);
        die();
    }

    if (!uac_bitacora_verificarSyllabus($globalConnection, $intSyllabusUAC, $intCimp)) {
        echo json_encode(['success' => false, 'msg' => 'Acceso denegado']);
        die();
    }

    $strPath   = '';
    $strNombre = '';

    if ($intLogId > 0) {
        $stid = oci_parse($globalConnection,
            "SELECT PATH_ARCHIVO, NOMBRE_ARCHIVO
             FROM   SYLLABUS_UAC_CRONO_LOG
             WHERE  LOG_ID = :p_log_id");
        oci_bind_by_name($stid, ':p_log_id', $intLogId, -1, SQLT_INT);
        oci_execute($stid);
        $row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS);
        oci_free_statement($stid);
        $strPath   = trim($row['PATH_ARCHIVO']   ?? '');
        $strNombre = trim($row['NOMBRE_ARCHIVO'] ?? '');
    } elseif ($intCronoId > 0) {
        $stid = oci_parse($globalConnection,
            "SELECT PATH_ARCHIVO, NOMBRE_ARCHIVO
             FROM   SYLLABUS_UA_CATEDRATICO_CRONOGRAMA
             WHERE  SYLLABUS_UAC_CRONOGRAMA = :p_crono_id
               AND  SYLLABUS_UA_CATEDRATICO = :p_syllabus_uac_id");
        oci_bind_by_name($stid, ':p_crono_id', $intCronoId,     -1, SQLT_INT);
        oci_bind_by_name($stid, ':p_syllabus_uac_id',  $intSyllabusUAC, -1, SQLT_INT);
        oci_execute($stid);
        $row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS);
        oci_free_statement($stid);
        $strPath   = trim($row['PATH_ARCHIVO']   ?? '');
        $strNombre = trim($row['NOMBRE_ARCHIVO'] ?? '');
    }

    if ($strPath === '') {
        echo json_encode(['success' => false, 'msg' => 'Archivo no encontrado']);
        die();
    }

    if ($strNombre === '') {
        $strNombre = basename(parse_url($strPath, PHP_URL_PATH));
    }

    $strUrlDescarga = core_ObtenerUrlDescargaS3DesdeUrl($strPath, 3600, $strNombre);

    if (empty($strUrlDescarga)) {
        echo json_encode(['success' => false, 'msg' => 'No se pudo generar la descarga']);
        die();
    }

    echo json_encode(['success' => true, 'url_descarga' => $strUrlDescarga, 'nombre_archivo' => $strNombre]);
    die();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/syllabus_catedratico_ws_guardar.php';

/**
 * Genera PDF, publica borrador y crea uno nuevo.
 * @return array|null
 */
function syl_ws_ejecutarPublicacionSyllabus($globalConnection, $intCimp, $intIdBorrador, $intAddUser)
{
    $intCurso = syl_uac_getCursoDesdeCimp($globalConnection, $intCimp);
    $intSyllabusUA = syl_uac_getSyllabusUAActiva($globalConnection, $intCurso);
    if ($intSyllabusUA <= 0) {
        return ['ok' => false, 'msg' => 'No hay syllabus de unidad academica activo para este curso'];
    }

    list($pdfBinario, $strErrorPdf) = syl_gtb_buildPdfSyllabusCatedratico($globalConnection, $intCimp);
    if ($pdfBinario === false) {
        return ['ok' => false, 'msg' => 'Error al generar PDF: ' . $strErrorPdf];
    }

    $strTmpPdf = syl_gtb_guardarBinarioTemporal($pdfBinario, 'syl_pub_', 'pdf');
    if ($strTmpPdf === false) {
        return ['ok' => false, 'msg' => 'No se pudo crear archivo temporal del PDF'];
    }

    $strNombreS3   = 'documento_' . time() . '_syllabus_' . $intCimp . '.pdf';
    $strDirectorio = $intCimp . '/programa_publicado';
    $strPathS3 = core_SubirArchivoS3(
        'syllabus-compras',
        $strNombreS3,
        $strTmpPdf,
        $strDirectorio
    );
    @unlink($strTmpPdf);

    if ($strPathS3 === false || trim($strPathS3) === '') {
        return ['ok' => false, 'msg' => 'Error al subir PDF a S3'];
    }

    syl_uac_cerrarPublicacionVigente($globalConnection, $intCimp, $intAddUser);

    $arrPublicacion = syl_uac_publicarBorradorYCrearNuevo(
        $globalConnection,
        $intIdBorrador,
        $intCimp,
        $intSyllabusUA,
        $strPathS3,
        $intAddUser
    );

    if ($arrPublicacion === null) {
        oci_rollback($globalConnection);
        return ['ok' => false, 'msg' => 'Error al publicar y crear nuevo borrador'];
    }

    oci_commit($globalConnection);

    return [
        'ok'           => true,
        'id_publicado' => $arrPublicacion['id_publicado'],
        'id_borrador'  => $arrPublicacion['id_borrador'],
        'path_pdf'     => $strPathS3,
    ];
}

switch ($strAction) {

    case 'guardarSyllabusUAC':

        $intCimp    = isset($_POST['cimp']) ? intval($_POST['cimp']) : 0;
        $intAddUser = isset($_SESSION["hml"]["persona"]) ? intval($_SESSION["hml"]["persona"]) : 0;

        if ($intCimp <= 0 || $intAddUser <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Parametros invalidos']);
            die();
        }

        $arrResult = syl_ws_guardarSyllabusUACDesdePost($globalConnection, $intCimp, $intAddUser, true);
        if (!empty($arrResult['ok'])) {
            $intIdBorrador = intval($arrResult['id'] ?? 0);
            $arrRevision   = null;
            $boolUsarLlm  = syl_ws_usarSpellcheckLlm();

            if (!empty($arrResult['cronograma_revision'])) {
                if ($boolUsarLlm) {
                    $arrRevision = syl_spell_llm_marcarLote(
                        $globalConnection,
                        $intIdBorrador,
                        $arrResult['cronograma_revision'],
                        true
                    );
                } else {
                    $arrRevision = syl_spell_marcarLote(
                        $globalConnection,
                        $intIdBorrador,
                        $arrResult['cronograma_revision']
                    );
                }
            }

            if ($intIdBorrador > 0) {
                syl_spell_flashTrasGuardar($intIdBorrador, $arrRevision);
            }
        }

        unset($arrResult['revision']);
        echo json_encode($arrResult);
        die();

    case 'prePublicarSyllabusUAC':

        $intCimp    = isset($_POST['cimp']) ? intval($_POST['cimp']) : 0;
        $intAddUser = isset($_SESSION["hml"]["persona"]) ? intval($_SESSION["hml"]["persona"]) : 0;

        if ($intCimp <= 0 || $intAddUser <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Parametros invalidos']);
            die();
        }

        $arrGuardar = syl_ws_guardarSyllabusUACDesdePost($globalConnection, $intCimp, $intAddUser, true);
        if (empty($arrGuardar['ok'])) {
            echo json_encode($arrGuardar);
            die();
        }

        $intIdBorrador = intval($arrGuardar['id'] ?? 0);
        if ($intIdBorrador <= 0) {
            $intIdBorrador = syl_uac_getIdBorrador($globalConnection, $intCimp);
        }

        if ($intIdBorrador <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'No se encontro borrador para publicar']);
            die();
        }

        if (syl_uac_contarCronogramasActivos($globalConnection, $intIdBorrador) <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Debe adjuntar al menos un cronograma activo antes de publicar']);
            die();
        }

        $boolUsarLlm = syl_ws_usarSpellcheckLlm();
        if ($boolUsarLlm) {
            $arrRevision = syl_spell_llm_marcarLote($globalConnection, $intIdBorrador, null, true);
        } else {
            $arrRevision = syl_spell_marcarLote($globalConnection, $intIdBorrador);
        }

        $arrSinContenidoRes = syl_spell_resultadosSinContenido($arrRevision['resultados'] ?? []);
        if (count($arrSinContenidoRes) > 0) {
            echo json_encode([
                'ok'                       => false,
                'sin_contenido'            => true,
                'msg'                      => 'Existen adjuntos sin contenido',
                'sin_contenido_resultados' => $arrSinContenidoRes,
                'capa_llm'                 => $boolUsarLlm,
            ]);
            die();
        }

        syl_spell_guardarRevisionSesion($intIdBorrador, $arrRevision);

        echo json_encode([
            'ok'                    => true,
            'fase'                  => 'pre_publicacion',
            'capa_llm'              => $boolUsarLlm,
            'requiere_confirmacion' => !empty($arrRevision['con_errores']),
            'revision'              => $arrRevision,
            'cronograma_map'        => isset($arrGuardar['cronograma_map']) ? $arrGuardar['cronograma_map'] : [],
            'id_borrador'           => $intIdBorrador,
        ]);
        die();

    case 'confirmarPublicarSyllabusUAC':

        $intCimp    = isset($_POST['cimp']) ? intval($_POST['cimp']) : 0;
        $intAddUser = isset($_SESSION["hml"]["persona"]) ? intval($_SESSION["hml"]["persona"]) : 0;

        if ($intCimp <= 0 || $intAddUser <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Parametros invalidos']);
            die();
        }

        $arrGuardar = syl_ws_guardarSyllabusUACDesdePost($globalConnection, $intCimp, $intAddUser, true);
        if (empty($arrGuardar['ok'])) {
            echo json_encode($arrGuardar);
            die();
        }

        $intIdBorrador = intval($arrGuardar['id'] ?? 0);
        if ($intIdBorrador <= 0) {
            $intIdBorrador = syl_uac_getIdBorrador($globalConnection, $intCimp);
        }

        if ($intIdBorrador <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'No se encontro borrador para publicar']);
            die();
        }

        if (syl_uac_contarCronogramasActivos($globalConnection, $intIdBorrador) <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Debe adjuntar al menos un cronograma activo antes de publicar']);
            die();
        }

        $arrPub = syl_ws_ejecutarPublicacionSyllabus(
            $globalConnection,
            $intCimp,
            $intIdBorrador,
            $intAddUser
        );

        if (empty($arrPub['ok'])) {
            echo json_encode($arrPub);
            die();
        }

        syl_spell_flashTrasPublicar();

        echo json_encode([
            'ok'           => true,
            'msg'          => 'Programa publicado correctamente',
            'id_publicado' => $arrPub['id_publicado'],
            'id_borrador'  => $arrPub['id_borrador'],
            'path_pdf'     => $arrPub['path_pdf'],
        ]);
        die();

    case 'drawBlurVersionesPublicadas':

        header('Content-Type: text/html; charset=windows-1252');
        $intCimp    = isset($_POST['cimp']) ? intval($_POST['cimp']) : 0;
        $intAddUser = isset($_SESSION["hml"]["persona"]) ? intval($_SESSION["hml"]["persona"]) : 0;
        if ($intCimp <= 0 || $intAddUser <= 0) {
            echo '<p>Acceso denegado</p>';
            die();
        }
        syl_uac_drawBlurVersionesPublicadas($globalConnection, $intCimp);
        die();

    case 'descargarPdfVersionPublicada':

        $intCimp      = isset($_POST['cimp']) ? intval($_POST['cimp']) : 0;
        $intVersionId = isset($_POST['version_id']) ? intval($_POST['version_id']) : 0;
        $strModo      = isset($_POST['modo']) ? $_POST['modo'] : 'descargar';
        $intAddUser   = isset($_SESSION["hml"]["persona"]) ? intval($_SESSION["hml"]["persona"]) : 0;

        if ($intCimp <= 0 || $intVersionId <= 0 || $intAddUser <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Parametros invalidos']);
            die();
        }

        $strPath = syl_uac_verificarSnapshotPublicado($globalConnection, $intVersionId, $intCimp);
        if ($strPath === false) {
            echo json_encode(['ok' => false, 'msg' => 'Version publicada no encontrada']);
            die();
        }

        if ($strModo === 'ver') {
            $strUrl = core_ObtenerUrlVerS3DesdeUrl($strPath);
            echo json_encode(['ok' => true, 'url' => $strUrl]);
            die();
        }

        $strNombre = 'syllabus_' . $intCimp . '_v' . $intVersionId . '.pdf';
        $strUrl = core_ObtenerUrlDescargaS3DesdeUrl($strPath, 3600, $strNombre);
        if (empty($strUrl)) {
            echo json_encode(['ok' => false, 'msg' => 'No se pudo generar la descarga']);
            die();
        }
        echo json_encode(['ok' => true, 'url_descarga' => $strUrl, 'nombre_archivo' => $strNombre]);
        die();

    default:
        echo json_encode(['ok' => false, 'msg' => 'Accion no reconocida']);
        die();
}
?>



