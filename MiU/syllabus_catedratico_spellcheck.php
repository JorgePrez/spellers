<?php
/**
 * Comprobacion y revision ortografica de cronogramas (syllabus catedratico).
 *
 * /spellcheck      -> verificacion al publicar (legacy)
 * /spellcheck/mark -> documento rev_* + PATH_ARCHIVO_REV
 *   1. Se descarga el binario desde S3 (PATH_ARCHIVO).
 *   2. Se envia a la API de spellcheck (multipart) con su SYLLABUS_UAC_CRONOGRAMA.
 *   3. Se normaliza la respuesta para el cliente.
 *
 * Requiere: syllabus_catedratico_pdf_gutenberg.php
 *   (syl_gtb_descargarBinarioS3, syl_gtb_guardarBinarioTemporal, syl_gtb_mimeDesdeExtension)
 */

require_once __DIR__ . '/syllabus_catedratico_pdf_gutenberg.php';
if (!function_exists('core_ObtenerUrlDescargaS3DesdeUrl')) {
    require_once __DIR__ . '/core/aws-php-sdk/s3_carga_descarga_funciones.php';
}

// ---------------------------------------------------------------------------
// Configuracion de la API (por ahora como constante; mover a .env a futuro)
// ---------------------------------------------------------------------------
if (!defined('SYL_SPELLCHECK_URL')) {
    define('SYL_SPELLCHECK_URL', 'http://3.150.240.23:5000/spellcheck');
}
if (!defined('SYL_SPELLCHECK_TOKEN')) {
    define('SYL_SPELLCHECK_TOKEN', 'd998288b94d3aec8b535c1f01a710ee1df3bbbc545b1f992edd41322ab93b7bb');
}
if (!defined('SYL_SPELLCHECK_TIMEOUT')) {
    define('SYL_SPELLCHECK_TIMEOUT', 120);
}
if (!defined('SYL_SPELLCHECK_MARK_URL')) {
    define('SYL_SPELLCHECK_MARK_URL', 'http://3.150.240.23:5000/spellcheck/mark');
}
if (!defined('SYL_SPELLCHECK_MARK_TIMEOUT')) {
    define('SYL_SPELLCHECK_MARK_TIMEOUT', 180);
}
if (!defined('SYL_SPELLCHECK_CONNECT_TIMEOUT')) {
    define('SYL_SPELLCHECK_CONNECT_TIMEOUT', 15);
}

/**
 * Extensiones que la API de spellcheck soporta. El cronograma admite mas
 * formatos (odt, rtf, csv, ...); los no soportados se omiten (no verificable).
 */
function syl_spell_extensionSoportada($strExt)
{
    $strExt = strtolower(trim((string) $strExt));
    $arrSoportadas = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'pdf'];
    return in_array($strExt, $arrSoportadas, true);
}

/**
 * Devuelve los cronogramas ACTIVOS de un borrador/syllabus catedratico.
 * @return array[] cada item: ['ID' => int, 'PATH' => string, 'NOMBRE' => string]
 */
function syl_uac_getCronogramasActivos($globalConnection, $intSyllabusUAC)
{
    $arrOut = [];
    $intSyllabusUAC = intval($intSyllabusUAC);
    if ($intSyllabusUAC <= 0) {
        return $arrOut;
    }

    $stid = oci_parse($globalConnection,
        "SELECT SYLLABUS_UAC_CRONOGRAMA, PATH_ARCHIVO, NOMBRE_ARCHIVO
         FROM   SYLLABUS_UA_CATEDRATICO_CRONOGRAMA
         WHERE  SYLLABUS_UA_CATEDRATICO = :id
           AND  ACTIVO = 'Y'
         ORDER  BY ADD_FECHA, SYLLABUS_UAC_CRONOGRAMA");
    oci_bind_by_name($stid, ':id', $intSyllabusUAC, -1, SQLT_INT);
    oci_execute($stid);
    while ($row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS)) {
        $arrOut[] = [
            'ID'     => intval($row['SYLLABUS_UAC_CRONOGRAMA']),
            'PATH'   => (string) ($row['PATH_ARCHIVO'] ?? ''),
            'NOMBRE' => (string) ($row['NOMBRE_ARCHIVO'] ?? ''),
        ];
    }
    oci_free_statement($stid);

    return $arrOut;
}

/**
 * Extrae bucket y key de una URL HTTPS de S3 o de un URI s3://
 * @return array{bucket:string,key:string}|null
 */
function syl_spell_parseS3Url($strUrl)
{
    $strUrl = trim((string) $strUrl);
    if ($strUrl === '') {
        return null;
    }

    if (stripos($strUrl, 's3://') === 0) {
        $strPath = substr($strUrl, 5);
        $intSlash = strpos($strPath, '/');
        if ($intSlash === false) {
            return null;
        }
        return [
            'bucket' => substr($strPath, 0, $intSlash),
            'key'    => ltrim(substr($strPath, $intSlash + 1), '/'),
        ];
    }

    $arrParts = parse_url($strUrl);
    if (empty($arrParts['host']) || empty($arrParts['path'])) {
        return null;
    }

    $strHost = strtolower($arrParts['host']);
    $strKey  = ltrim($arrParts['path'], '/');

    if (preg_match('/^(.+)\.s3[.\-a-z0-9]*\.amazonaws\.com$/', $strHost, $m)) {
        return ['bucket' => $m[1], 'key' => $strKey];
    }
    if (preg_match('/^s3[.\-a-z0-9]*\.amazonaws\.com$/', $strHost)) {
        $intSlash = strpos($strKey, '/');
        if ($intSlash === false) {
            return null;
        }
        return [
            'bucket' => substr($strKey, 0, $intSlash),
            'key'    => substr($strKey, $intSlash + 1),
        ];
    }

    return null;
}

/**
 * Convierte s3://bucket/key a URL HTTPS (mismo patron que core_SubirArchivoS3).
 */
function syl_spell_s3UriToHttpsUrl($strS3Uri)
{
    $strS3Uri = trim((string) $strS3Uri);
    if ($strS3Uri === '') {
        return '';
    }
    if (stripos($strS3Uri, 'http://') === 0 || stripos($strS3Uri, 'https://') === 0) {
        return $strS3Uri;
    }

    $arrParts = syl_spell_parseS3Url($strS3Uri);
    if ($arrParts === null) {
        return '';
    }

    if (!class_exists('AWSConfig')) {
        require_once __DIR__ . '/core/aws-php-sdk/aws_config.php';
    }
    $strRegion = AWSConfig::getRegion();

    return 'https://' . $arrParts['bucket'] . '.s3.' . $strRegion . '.amazonaws.com/' . $arrParts['key'];
}

/**
 * Normaliza nombre de archivo para UTF-8 en JSON.
 */
function syl_spell_nombreUtf8($strNombre)
{
    $strNombre = (string) $strNombre;
    if (function_exists('mb_check_encoding')) {
        if (!mb_check_encoding($strNombre, 'UTF-8')) {
            return mb_convert_encoding($strNombre, 'UTF-8', 'ISO-8859-1');
        }
        return $strNombre;
    }
    if (@json_encode($strNombre) === false) {
        return utf8_encode($strNombre);
    }
    return $strNombre;
}

/**
 * Plantilla base del resultado normalizado de spellcheck/mark.
 */
function syl_spell_resultadoBase($intCronoId, $strNombre)
{
    return [
        'syllabus_uac_cronograma' => intval($intCronoId),
        'nombre_archivo'          => syl_spell_nombreUtf8($strNombre),
        'ok'                      => false,
        'tiene_errores'           => false,
        'total_errores'           => 0,
        'errores'                 => [],
        'omitido'                 => false,
        'motivo'                  => '',
        'sin_contenido'           => false,
        'mensaje'                 => '',
        'archivo_rev'             => null,
        'documento_rev'           => null,
        'path_archivo_rev'        => null,
        'url_ver_rev'             => null,
        'url_descargar_rev'       => null,
    ];
}

/**
 * Persiste PATH_ARCHIVO_REV en la tabla de cronogramas.
 */
function syl_spell_guardarPathRev($globalConnection, $intCronoId, $strPathRev)
{
    $intCronoId = intval($intCronoId);
    $strPathRev = trim((string) $strPathRev);
    if ($intCronoId <= 0) {
        return false;
    }

    $stid = oci_parse($globalConnection,
        "UPDATE SYLLABUS_UA_CATEDRATICO_CRONOGRAMA
         SET    PATH_ARCHIVO_REV = :p_path_rev
         WHERE  SYLLABUS_UAC_CRONOGRAMA = :p_crono_id");
    $strBind = ($strPathRev === '') ? null : substr($strPathRev, 0, 1000);
    oci_bind_by_name($stid, ':p_path_rev', $strBind, 1000);
    oci_bind_by_name($stid, ':p_crono_id', $intCronoId, -1, SQLT_INT);
    oci_execute($stid, OCI_COMMIT_ON_SUCCESS);
    oci_free_statement($stid);

    return true;
}

/**
 * Obtiene datos de un cronograma por PK.
 */
function syl_spell_getCronogramaPorId($globalConnection, $intCronoId)
{
    $intCronoId = intval($intCronoId);
    if ($intCronoId <= 0) {
        return null;
    }

    $stid = oci_parse($globalConnection,
        "SELECT SYLLABUS_UAC_CRONOGRAMA, SYLLABUS_UA_CATEDRATICO,
                PATH_ARCHIVO, PATH_ARCHIVO_REV, NOMBRE_ARCHIVO, ACTIVO
         FROM   SYLLABUS_UA_CATEDRATICO_CRONOGRAMA
         WHERE  SYLLABUS_UAC_CRONOGRAMA = :p_crono_id");
    oci_bind_by_name($stid, ':p_crono_id', $intCronoId, -1, SQLT_INT);
    oci_execute($stid);
    $row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stid);

    if (!$row) {
        return null;
    }

    return [
        'ID'              => intval($row['SYLLABUS_UAC_CRONOGRAMA']),
        'SYLLABUS_UAC'    => intval($row['SYLLABUS_UA_CATEDRATICO']),
        'PATH'            => (string) ($row['PATH_ARCHIVO'] ?? ''),
        'PATH_REV'        => (string) ($row['PATH_ARCHIVO_REV'] ?? ''),
        'NOMBRE'          => (string) ($row['NOMBRE_ARCHIVO'] ?? ''),
        'ACTIVO'          => (string) ($row['ACTIVO'] ?? 'Y'),
    ];
}

/**
 * Verifica si un archivo local tiene texto extraible (via /spellcheck/mark).
 * Si la API no responde, no bloquea el guardado.
 */
function syl_spell_verificarContenidoLocal($strRutaLocal, $strNombre, $intCronoId = 0)
{
    $intCronoId = intval($intCronoId);
    $strNombre  = (string) $strNombre;
    $strExt     = strtolower(pathinfo($strNombre, PATHINFO_EXTENSION));

    $arrOut = [
        'ok'            => false,
        'sin_contenido' => false,
        'omitido'       => false,
        'motivo'        => '',
    ];

    if (!syl_spell_extensionSoportada($strExt)) {
        $arrOut['omitido'] = true;
        $arrOut['ok']      = true;
        return $arrOut;
    }

    if (!is_readable($strRutaLocal)) {
        $arrOut['motivo'] = 'No se pudo leer el archivo';
        return $arrOut;
    }

    $strMime = syl_gtb_mimeDesdeExtension($strExt);
    $strKey  = 'validacion/' . uniqid('crono_', true) . '_' . basename($strNombre);
    $arrPostFields = [
        'syllabus_uac_cronograma' => (string) ($intCronoId > 0 ? $intCronoId : '0'),
        's3_bucket'               => 'syllabus-compras',
        's3_source_key'           => $strKey,
        'file'                    => new CURLFile($strRutaLocal, $strMime, $strNombre),
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => SYL_SPELLCHECK_MARK_URL,
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . SYL_SPELLCHECK_TOKEN],
        CURLOPT_POSTFIELDS     => $arrPostFields,
        CURLOPT_TIMEOUT        => SYL_SPELLCHECK_MARK_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => SYL_SPELLCHECK_CONNECT_TIMEOUT,
    ]);

    $strResp    = curl_exec($ch);
    $intHttp    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $strErrCurl = curl_error($ch);
    curl_close($ch);

    if ($strResp === false) {
        $arrOut['motivo'] = 'Error de conexion con la API de revision: ' . $strErrCurl;
        $arrOut['ok']     = true;
        return $arrOut;
    }

    $arrJson = json_decode($strResp, true);
    if (!is_array($arrJson)) {
        $arrOut['motivo'] = 'Respuesta invalida de la API de revision';
        $arrOut['ok']     = true;
        return $arrOut;
    }

    if ($intHttp < 200 || $intHttp >= 300) {
        $arrOut['motivo'] = 'La API de revision respondio HTTP ' . $intHttp;
        $arrOut['ok']     = true;
        return $arrOut;
    }

    $arrOut['ok'] = !empty($arrJson['ok']);
    if (!empty($arrJson['mensaje']) && $arrJson['mensaje'] === 'archivo sin contenido') {
        $arrOut['sin_contenido'] = true;
    }

    return $arrOut;
}

function syl_spell_resultadosSinContenido($arrResultados)
{
    $arrOut = [];
    if (!is_array($arrResultados)) {
        return $arrOut;
    }
    foreach ($arrResultados as $arrRes) {
        if (empty($arrRes['sin_contenido'])) {
            continue;
        }
        $arrOut[] = [
            'syllabus_uac_cronograma' => intval($arrRes['syllabus_uac_cronograma'] ?? 0),
            'sin_contenido'           => true,
            'nombre_archivo'          => !empty($arrRes['nombre_archivo'])
                ? (string) $arrRes['nombre_archivo']
                : 'Cronograma',
        ];
    }
    return $arrOut;
}

/**
 * Nombres de archivos sin contenido a partir de resultados de mark/lote.
 * @return string[]
 */
function syl_spell_nombresSinContenido($arrResultados)
{
    $arrNombres = [];
    if (!is_array($arrResultados)) {
        return $arrNombres;
    }
    foreach ($arrResultados as $arrRes) {
        if (!empty($arrRes['sin_contenido'])) {
            $arrNombres[] = !empty($arrRes['nombre_archivo'])
                ? (string) $arrRes['nombre_archivo']
                : 'Cronograma';
        }
    }
    return $arrNombres;
}

/**
 * Genera documento rev_* y guarda PATH_ARCHIVO_REV.
 */
function syl_spell_marcarCronograma($globalConnection, $strUrlS3, $strNombre, $intCronoId)
{
    $intCronoId = intval($intCronoId);
    $strNombre  = (string) $strNombre;
    $strExt     = strtolower(pathinfo($strNombre, PATHINFO_EXTENSION));
    $arrBase    = syl_spell_resultadoBase($intCronoId, $strNombre);

    if (!syl_spell_extensionSoportada($strExt)) {
        $arrBase['omitido'] = true;
        $arrBase['motivo']  = 'Formato no soportado por la comprobacion de ortografia';
        return $arrBase;
    }

    $arrS3 = syl_spell_parseS3Url($strUrlS3);
    if ($arrS3 === null || $arrS3['key'] === '') {
        $arrBase['motivo'] = 'No se pudo interpretar la ruta S3 del archivo';
        return $arrBase;
    }

    list($binArchivo, $strErrS3) = syl_gtb_descargarBinarioS3($strUrlS3);
    if ($binArchivo === false) {
        $arrBase['motivo'] = 'No se pudo descargar el archivo desde S3: ' . $strErrS3;
        return $arrBase;
    }

    $strTmp = syl_gtb_guardarBinarioTemporal($binArchivo, 'syl_mark_', $strExt !== '' ? $strExt : 'bin');
    if ($strTmp === false) {
        $arrBase['motivo'] = 'No se pudo crear archivo temporal para la revision';
        return $arrBase;
    }

    $strMime = syl_gtb_mimeDesdeExtension($strExt);
    $arrPostFields = [
        'syllabus_uac_cronograma' => (string) $intCronoId,
        's3_bucket'               => $arrS3['bucket'],
        's3_source_key'           => $arrS3['key'],
        'file'                    => new CURLFile($strTmp, $strMime, $strNombre),
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => SYL_SPELLCHECK_MARK_URL,
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . SYL_SPELLCHECK_TOKEN],
        CURLOPT_POSTFIELDS     => $arrPostFields,
        CURLOPT_TIMEOUT        => SYL_SPELLCHECK_MARK_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => SYL_SPELLCHECK_CONNECT_TIMEOUT,
    ]);

    $strResp    = curl_exec($ch);
    $intHttp    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $strErrCurl = curl_error($ch);
    curl_close($ch);
    @unlink($strTmp);

    if ($strResp === false) {
        $arrBase['motivo'] = 'Error de conexion con la API de revision: ' . $strErrCurl;
        return $arrBase;
    }

    $arrJson = json_decode($strResp, true);
    if (!is_array($arrJson)) {
        $arrBase['motivo'] = 'Respuesta invalida de la API de revision';
        return $arrBase;
    }

    if ($intHttp < 200 || $intHttp >= 300) {
        $arrBase['motivo'] = 'La API de revision respondio HTTP ' . $intHttp;
        if (!empty($arrJson['detail'])) {
            $arrBase['motivo'] .= ': ' . $arrJson['detail'];
        } elseif (!empty($arrJson['error'])) {
            $arrBase['motivo'] .= ': ' . $arrJson['error'];
        }
        return $arrBase;
    }

    $arrBase['ok']            = !empty($arrJson['ok']);
    $arrBase['tiene_errores'] = !empty($arrJson['tiene_errores']);
    $arrBase['total_errores'] = intval($arrJson['total_errores'] ?? 0);
    $arrBase['errores']       = (isset($arrJson['errores']) && is_array($arrJson['errores']))
        ? $arrJson['errores']
        : [];

    if (!empty($arrJson['mensaje'])) {
        $arrBase['mensaje'] = (string) $arrJson['mensaje'];
        if ($arrJson['mensaje'] === 'archivo sin contenido') {
            $arrBase['sin_contenido'] = true;
        }
    }

    if (!empty($arrJson['archivo_rev'])) {
        $arrBase['archivo_rev'] = (string) $arrJson['archivo_rev'];
    }
    if (!empty($arrJson['documento_rev'])) {
        $arrBase['documento_rev'] = (string) $arrJson['documento_rev'];
    }

    $strPathRev = '';
    if (!empty($arrJson['documento_rev'])) {
        $strPathRev = syl_spell_s3UriToHttpsUrl($arrJson['documento_rev']);
    }

    if ($strPathRev !== '') {
        $arrBase['path_archivo_rev'] = $strPathRev;
        syl_spell_guardarPathRev($globalConnection, $intCronoId, $strPathRev);

        $strNombreRev = !empty($arrJson['archivo_rev'])
            ? (string) $arrJson['archivo_rev']
            : basename(parse_url($strPathRev, PHP_URL_PATH));

        $arrBase['url_descargar_rev'] = core_ObtenerUrlDescargaS3DesdeUrl($strPathRev, 3600, $strNombreRev);

        $strExtRev = strtolower(pathinfo($strNombreRev, PATHINFO_EXTENSION));
        if ($strExtRev === 'pdf') {
            $arrBase['url_ver_rev'] = core_ObtenerUrlVerS3DesdeUrl($strPathRev);
        }
    } elseif ($arrBase['ok'] && !$arrBase['tiene_errores']) {
        syl_spell_guardarPathRev($globalConnection, $intCronoId, '');
    }

    if (!$arrBase['ok'] && $arrBase['motivo'] === '') {
        $arrBase['motivo'] = 'La API de revision no proceso el archivo';
    }

    return $arrBase;
}

/**
 * Marca cronogramas activos o una lista de PKs.
 * @param int[]|null $arrCronoIds
 */
function syl_spell_marcarLote($globalConnection, $intSyllabusUAC, $arrCronoIds = null)
{
    $arrCronos = [];

    if (is_array($arrCronoIds) && count($arrCronoIds) > 0) {
        foreach ($arrCronoIds as $intCronoId) {
            $arrCrono = syl_spell_getCronogramaPorId($globalConnection, $intCronoId);
            if ($arrCrono !== null && trim($arrCrono['PATH']) !== '') {
                $arrCronos[] = $arrCrono;
            }
        }
    } else {
        foreach (syl_uac_getCronogramasActivos($globalConnection, $intSyllabusUAC) as $arrCrono) {
            $arrCronos[] = [
                'ID'     => $arrCrono['ID'],
                'PATH'   => $arrCrono['PATH'],
                'NOMBRE' => $arrCrono['NOMBRE'],
            ];
        }
    }

    $arrResultados = [];
    $intConErrores = 0;

    foreach ($arrCronos as $arrCrono) {
        $arrRes = syl_spell_marcarCronograma(
            $globalConnection,
            $arrCrono['PATH'],
            $arrCrono['NOMBRE'],
            $arrCrono['ID']
        );
        if (!empty($arrRes['tiene_errores'])) {
            $intConErrores++;
        }
        $arrResultados[] = $arrRes;
    }

    return [
        'ejecutado'               => true,
        'con_errores'             => ($intConErrores > 0),
        'total_cronogramas'       => count($arrCronos),
        'cronogramas_con_errores' => $intConErrores,
        'resultados'              => $arrResultados,
    ];
}

// ---------------------------------------------------------------------------
// Sesion: flash de mensajes y estado de revision (sin columnas extra en BD)
// ---------------------------------------------------------------------------

function syl_spell_guardarRevisionSesion($intSyllabusUAC, $arrRevision)
{
    $intSyllabusUAC = intval($intSyllabusUAC);
    if ($intSyllabusUAC <= 0 || !is_array($arrRevision)) {
        return;
    }
    if (!isset($_SESSION['syllabus_uac_revision']) || !is_array($_SESSION['syllabus_uac_revision'])) {
        $_SESSION['syllabus_uac_revision'] = [];
    }
    $_SESSION['syllabus_uac_revision'][$intSyllabusUAC] = $arrRevision;
}

function syl_spell_obtenerRevisionSesion($intSyllabusUAC)
{
    $intSyllabusUAC = intval($intSyllabusUAC);
    if ($intSyllabusUAC <= 0) {
        return null;
    }
    if (empty($_SESSION['syllabus_uac_revision'][$intSyllabusUAC])) {
        return null;
    }
    $arr = $_SESSION['syllabus_uac_revision'][$intSyllabusUAC];
    return is_array($arr) ? $arr : null;
}

function syl_spell_establecerFlash($arrFlash)
{
    $_SESSION['syllabus_uac_flash'] = is_array($arrFlash) ? $arrFlash : [];
}

function syl_spell_consumirFlash()
{
    if (empty($_SESSION['syllabus_uac_flash']) || !is_array($_SESSION['syllabus_uac_flash'])) {
        return null;
    }
    $arrFlash = $_SESSION['syllabus_uac_flash'];
    unset($_SESSION['syllabus_uac_flash']);
    return $arrFlash;
}

function syl_spell_flashTrasGuardar($intSyllabusUAC, $arrRevision = null)
{
    $bolConErrores = is_array($arrRevision) && !empty($arrRevision['con_errores']);

    if (is_array($arrRevision)) {
        syl_spell_guardarRevisionSesion($intSyllabusUAC, $arrRevision);
    }

    syl_spell_establecerFlash([
        'tipo'             => 'guardado',
        'mensaje'          => $bolConErrores
            ? 'Cambios guardados. Se recomienda revisar los adjuntos del cronograma.'
            : 'Cambios guardados correctamente.',
        'revisar_adjuntos' => $bolConErrores,
    ]);
}

function syl_spell_flashTrasPublicar()
{
    syl_spell_establecerFlash([
        'tipo'             => 'publicado',
        'mensaje'          => 'Programa publicado correctamente.',
        'revisar_adjuntos' => false,
    ]);
}

/**
 * Verifica un cronograma contra la API de spellcheck.
 * @return array resultado normalizado.
 */
function syl_spell_verificarCronograma($strUrlS3, $strNombre, $intCronoId)
{
    $intCronoId = intval($intCronoId);
    $strNombre  = (string) $strNombre;
    $strExt     = strtolower(pathinfo($strNombre, PATHINFO_EXTENSION));

    // Nombre real del documento (campo NOMBRE_ARCHIVO). La BD lo guarda en
    // ISO-8859-1; se normaliza a UTF-8 para que json_encode no falle y las
    // tildes se muestren bien en el modal.
    $strNombreUtf8 = $strNombre;
    if (function_exists('mb_check_encoding')) {
        if (!mb_check_encoding($strNombreUtf8, 'UTF-8')) {
            $strNombreUtf8 = mb_convert_encoding($strNombreUtf8, 'UTF-8', 'ISO-8859-1');
        }
    } elseif (@json_encode($strNombreUtf8) === false) {
        $strNombreUtf8 = utf8_encode($strNombreUtf8);
    }

    $arrBase = [
        'syllabus_uac_cronograma' => $intCronoId,
        'nombre_archivo'          => $strNombreUtf8,
        'ok'                      => false,
        'tiene_errores'           => false,
        'total_errores'           => 0,
        'errores'                 => [],
        'omitido'                 => false,
        'motivo'                  => '',
    ];

    // Formatos no soportados por la API -> se omiten (no verificable).
    if (!syl_spell_extensionSoportada($strExt)) {
        $arrBase['omitido'] = true;
        $arrBase['motivo']  = 'Formato no soportado por la comprobacion de ortografia';
        return $arrBase;
    }

    // Descargar binario desde S3.
    list($binArchivo, $strErrS3) = syl_gtb_descargarBinarioS3($strUrlS3);
    if ($binArchivo === false) {
        $arrBase['motivo'] = 'No se pudo descargar el archivo desde S3: ' . $strErrS3;
        return $arrBase;
    }

    $strTmp = syl_gtb_guardarBinarioTemporal($binArchivo, 'syl_spell_', $strExt !== '' ? $strExt : 'bin');
    if ($strTmp === false) {
        $arrBase['motivo'] = 'No se pudo crear archivo temporal para la comprobacion';
        return $arrBase;
    }

    $strMime = syl_gtb_mimeDesdeExtension($strExt);
    $arrPostFields = [
        'syllabus_uac_cronograma' => (string) $intCronoId,
        'file'                    => new CURLFile($strTmp, $strMime, $strNombre),
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => SYL_SPELLCHECK_URL,
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . SYL_SPELLCHECK_TOKEN],
        CURLOPT_POSTFIELDS     => $arrPostFields,
        CURLOPT_TIMEOUT        => SYL_SPELLCHECK_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => SYL_SPELLCHECK_CONNECT_TIMEOUT,
    ]);

    $strResp   = curl_exec($ch);
    $intHttp   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $strErrCurl = curl_error($ch);
    curl_close($ch);

    @unlink($strTmp);

    if ($strResp === false) {
        $arrBase['motivo'] = 'Error de conexion con la API de ortografia: ' . $strErrCurl;
        return $arrBase;
    }

    if ($intHttp < 200 || $intHttp >= 300) {
        $arrBase['motivo'] = 'La API de ortografia respondio HTTP ' . $intHttp;
        return $arrBase;
    }

    $arrJson = json_decode($strResp, true);
    if (!is_array($arrJson)) {
        $arrBase['motivo'] = 'Respuesta invalida de la API de ortografia';
        return $arrBase;
    }

    $arrBase['ok']            = !empty($arrJson['ok']);
    $arrBase['tiene_errores'] = !empty($arrJson['tiene_errores']);
    $arrBase['total_errores'] = intval($arrJson['total_errores'] ?? 0);
    $arrBase['errores']       = (isset($arrJson['errores']) && is_array($arrJson['errores']))
        ? $arrJson['errores']
        : [];

    // Nota: se conserva el NOMBRE_ARCHIVO real de la BD (ya asignado en
    // $arrBase). No se usa $arrJson['archivo'] porque la API antepone un hash.

    if (!$arrBase['ok'] && $arrBase['motivo'] === '') {
        $arrBase['motivo'] = 'La API de ortografia no proceso el archivo';
    }

    return $arrBase;
}

/**
 * Verifica en lote todos los cronogramas activos de un borrador.
 * @return array ['ejecutado', 'con_errores', 'total_cronogramas',
 *                'cronogramas_con_errores', 'resultados' => []]
 */
function syl_spell_verificarLote($globalConnection, $intSyllabusUAC)
{
    $arrCronos = syl_uac_getCronogramasActivos($globalConnection, $intSyllabusUAC);

    $arrResultados = [];
    $intConErrores = 0;

    foreach ($arrCronos as $arrCrono) {
        $arrRes = syl_spell_verificarCronograma(
            $arrCrono['PATH'],
            $arrCrono['NOMBRE'],
            $arrCrono['ID']
        );
        if (!empty($arrRes['tiene_errores'])) {
            $intConErrores++;
        }
        $arrResultados[] = $arrRes;
    }

    return [
        'ejecutado'               => true,
        'con_errores'             => ($intConErrores > 0),
        'total_cronogramas'       => count($arrCronos),
        'cronogramas_con_errores' => $intConErrores,
        'resultados'              => $arrResultados,
    ];
}
