<?php
/**
 * Revision ortografica con segunda capa LLM (LO + Haiku).
 *
 * Usa POST /spellcheck/mark-llm en el servicio Flask.
 * NO reemplaza syllabus_catedratico_spellcheck.php (sigue en /spellcheck/mark).
 *
 * Uso tipico para comparar:
 *   require_once .../syllabus_catedratico_spellcheck_llm.php;
 *   $arrRevLlm = syl_spell_llm_marcarLote($globalConnection, $intIdBorrador);
 *
 * Por defecto NO sobrescribe PATH_ARCHIVO_REV (para no pisar la revision LO
 * mientras se compara). Pasar $boolGuardarPathRev = true para persistir.
 */

require_once __DIR__ . '/syllabus_catedratico_spellcheck.php';

if (!defined('SYL_SPELLCHECK_MARK_LLM_URL')) {
    define('SYL_SPELLCHECK_MARK_LLM_URL', 'http://3.150.240.23:5000/spellcheck/mark-llm');
}
if (!defined('SYL_SPELLCHECK_MARK_LLM_TIMEOUT')) {
    // Haiku + LO: mas holgado que mark solo
    define('SYL_SPELLCHECK_MARK_LLM_TIMEOUT', 300);
}

/**
 * Igual que syl_spell_marcarCronograma, pero llama a /spellcheck/mark-llm.
 *
 * @param bool $boolGuardarPathRev Si true, escribe PATH_ARCHIVO_REV (como el flujo LO).
 */
function syl_spell_llm_marcarCronograma(
    $globalConnection,
    $strUrlS3,
    $strNombre,
    $intCronoId,
    $boolGuardarPathRev = false
) {
    $intCronoId = intval($intCronoId);
    $strNombre  = (string) $strNombre;
    $strExt     = strtolower(pathinfo($strNombre, PATHINFO_EXTENSION));
    $arrBase    = syl_spell_resultadoBase($intCronoId, $strNombre);
    $arrBase['capa_llm'] = true;
    $arrBase['endpoint'] = 'mark-llm';

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

    $strTmp = syl_gtb_guardarBinarioTemporal($binArchivo, 'syl_mark_llm_', $strExt !== '' ? $strExt : 'bin');
    if ($strTmp === false) {
        $arrBase['motivo'] = 'No se pudo crear archivo temporal para la revision LLM';
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
        CURLOPT_URL            => SYL_SPELLCHECK_MARK_LLM_URL,
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . SYL_SPELLCHECK_TOKEN],
        CURLOPT_POSTFIELDS     => $arrPostFields,
        CURLOPT_TIMEOUT        => SYL_SPELLCHECK_MARK_LLM_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => SYL_SPELLCHECK_CONNECT_TIMEOUT,
    ]);

    $strResp    = curl_exec($ch);
    $intHttp    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $strErrCurl = curl_error($ch);
    curl_close($ch);
    @unlink($strTmp);

    if ($strResp === false) {
        $arrBase['motivo'] = 'Error de conexion con la API mark-llm: ' . $strErrCurl;
        return $arrBase;
    }

    $arrJson = json_decode($strResp, true);
    if (!is_array($arrJson)) {
        $arrBase['motivo'] = 'Respuesta invalida de la API mark-llm';
        return $arrBase;
    }

    if ($intHttp < 200 || $intHttp >= 300) {
        $arrBase['motivo'] = 'La API mark-llm respondio HTTP ' . $intHttp;
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

    // Metadatos de la capa LLM (para comparar vs LO solo)
    if (isset($arrJson['candidatos_libreoffice'])) {
        $arrBase['candidatos_libreoffice'] = intval($arrJson['candidatos_libreoffice']);
    }
    if (isset($arrJson['llm']) && is_array($arrJson['llm'])) {
        $arrBase['llm'] = $arrJson['llm'];
    }

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

        if ($boolGuardarPathRev) {
            syl_spell_guardarPathRev($globalConnection, $intCronoId, $strPathRev);
        }

        $strNombreRev = !empty($arrJson['archivo_rev'])
            ? (string) $arrJson['archivo_rev']
            : basename(parse_url($strPathRev, PHP_URL_PATH));

        $arrBase['url_descargar_rev'] = core_ObtenerUrlDescargaS3DesdeUrl($strPathRev, 3600, $strNombreRev);

        $strExtRev = strtolower(pathinfo($strNombreRev, PATHINFO_EXTENSION));
        if ($strExtRev === 'pdf') {
            $arrBase['url_ver_rev'] = core_ObtenerUrlVerS3DesdeUrl($strPathRev);
        }
    } elseif ($boolGuardarPathRev && $arrBase['ok'] && !$arrBase['tiene_errores']) {
        syl_spell_guardarPathRev($globalConnection, $intCronoId, '');
    }

    if (!$arrBase['ok'] && $arrBase['motivo'] === '') {
        $arrBase['motivo'] = 'La API mark-llm no proceso el archivo';
    }

    return $arrBase;
}

/**
 * Lote mark-llm (paralelo a syl_spell_marcarLote).
 *
 * @param int[]|null $arrCronoIds
 * @param bool       $boolGuardarPathRev
 */
function syl_spell_llm_marcarLote(
    $globalConnection,
    $intSyllabusUAC,
    $arrCronoIds = null,
    $boolGuardarPathRev = false
) {
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
    $intCandidatosLo = 0;
    $intConfirmados = 0;

    foreach ($arrCronos as $arrCrono) {
        $arrRes = syl_spell_llm_marcarCronograma(
            $globalConnection,
            $arrCrono['PATH'],
            $arrCrono['NOMBRE'],
            $arrCrono['ID'],
            $boolGuardarPathRev
        );
        if (!empty($arrRes['tiene_errores'])) {
            $intConErrores++;
        }
        if (isset($arrRes['candidatos_libreoffice'])) {
            $intCandidatosLo += intval($arrRes['candidatos_libreoffice']);
        }
        if (isset($arrRes['llm']['confirmados'])) {
            $intConfirmados += intval($arrRes['llm']['confirmados']);
        }
        $arrResultados[] = $arrRes;
    }

    return [
        'ejecutado'               => true,
        'capa_llm'                => true,
        'endpoint'                => 'mark-llm',
        'guardo_path_rev'         => (bool) $boolGuardarPathRev,
        'con_errores'             => ($intConErrores > 0),
        'total_cronogramas'       => count($arrCronos),
        'cronogramas_con_errores' => $intConErrores,
        'suma_candidatos_lo'      => $intCandidatosLo,
        'suma_confirmados_llm'    => $intConfirmados,
        'resultados'              => $arrResultados,
    ];
}
