<?php

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
if (ob_get_length()) {
    ob_end_clean();
}
ini_set('display_errors', 0);
ini_set('zlib.output_compression', 'Off');

require_once __DIR__ . '/core/main.php';
require_once __DIR__ . '/core/aws-php-sdk/s3_carga_descarga_funciones.php';
require_once __DIR__ . '/syllabus_catedratico_bitacora.php';
require_once __DIR__ . '/syllabus_catedratico_versiones.php';

global $arrConfigSite;
$globalConnection = $arrConfigSite['db']['database_resource'];

$intCursoImpartido = isset($_REQUEST['cimp']) ? intval($_REQUEST['cimp']) : 0;

define('SYL_GTB_URL', 'http://3.150.240.23:3000/forms/chromium/convert/html');
define('SYL_GTB_URL_MERGE', 'http://3.150.240.23:3000/forms/pdfengines/merge');
define('SYL_GTB_URL_OFFICE', 'http://3.150.240.23:3000/forms/libreoffice/convert');
/** Excel en cronograma: una hoja = una pagina PDF (Gotenberg singlePageSheets) */
define('SYL_GTB_EXCEL_SINGLE_PAGE_SHEETS', true);
define('SYL_GTB_ROJO', '#CC0000');
define('SYL_GTB_TEXTO', '#222222');
define('SYL_GTB_FUENTE', '9pt');
define('SYL_GTB_FUENTE_SECCION', '10pt');
define('SYL_GTB_FUENTE_PEQUENA', '8pt');
define('SYL_GTB_FUENTE_MICRO', '8pt');
define('SYL_GTB_FUENTE_TITULO_CURSO', '12pt');
define('SYL_GTB_SEP_SECCION', '10px');
define('SYL_GTB_SEP_POST_ENCABEZADO', '8px');
define('SYL_GTB_SEP_TABLA_DESCRIPCION', '22px');
define('SYL_GTB_SEP_INFO_BLOQUE', '8px');
define('SYL_GTB_ANCHO_COL_INFO', '15%');
define('SYL_GTB_PAD_PROFESORES', '12px');

// ---------------------------------------------------------------------------
// Utilidades UTF-8 y HTML para Gotenberg
// ---------------------------------------------------------------------------

function syl_gtb_convertirUTF8($valor)
{
    if ($valor === null) {
        return '';
    }
    $detected = mb_detect_encoding($valor, 'UTF-8, ISO-8859-1, ISO-8859-15', true);
    if ($detected !== 'UTF-8') {
        $valor = mb_convert_encoding($valor, 'UTF-8', $detected);
    }
    return $valor;
}

function syl_gtb_esc($valor)
{
    return htmlspecialchars(syl_gtb_convertirUTF8($valor), ENT_QUOTES, 'UTF-8');
}

function syl_gtb_html($valor)
{
    return syl_gtb_convertirUTF8($valor ?? '');
}

function syl_gtb_txt($valor)
{
    return syl_gtb_esc($valor);
}

function syl_gtb_mostrarDato($valor)
{
    $texto = trim(syl_gtb_convertirUTF8($valor ?? ''));
    if ($texto === '') {
        return '<span class="sin-info"><i>' . syl_gtb_txt('Sin información') . '</i></span>';
    }
    return syl_gtb_esc($texto);
}

function syl_gtb_tituloSeccion($titulo)
{
    return '<div class="seccion-titulo">' . syl_gtb_txt($titulo) . '</div>';
}

function syl_gtb_envolverSeccion($titulo, $contenido, $strMarginTop = null)
{
    $strStyle = '';
    if ($strMarginTop !== null) {
        $strStyle = ' style="margin-top:' . $strMarginTop . ';"';
    }
    return '
    <div class="seccion-bloque"' . $strStyle . '>'
        . syl_gtb_tituloSeccion($titulo) .
        '<div class="seccion-cuerpo">' . $contenido . '</div>
    </div>';
}

function syl_gtb_bloqueHtml($contenido)
{
    return syl_gtb_bloqueSimple($contenido);
}

function syl_gtb_bloqueSimple($contenido)
{
    $html = trim(syl_gtb_html($contenido));
    if ($html === '') {
        $html = '<span class="sin-info"><i>' . syl_gtb_txt('Sin información') . '</i></span>';
    }
    return '<div class="texto-cuerpo">' . $html . '</div>';
}

function syl_gtb_htmlListaBiblio($arrReferencias)
{
    $html = '<ol class="lista-biblio">';
    if (count($arrReferencias) > 0) {
        $num = 0;
        foreach ($arrReferencias as $ref) {
            $num++;
            $html .= '<li>'
                . '<span class="biblio-num">' . $num . '.</span>'
                . '<span class="biblio-text">' . syl_gtb_html($ref) . '</span>'
                . '</li>';
        }
    } else {
        $html .= '<li><span class="sin-info">' . syl_gtb_txt('Sin información') . '</span></li>';
    }
    $html .= '</ol>';
    return $html;
}

function syl_gtb_fail($msg)
{
    error_log('[SYLLABUS GTB ERROR] ' . syl_gtb_convertirUTF8($msg));
    http_response_code(500);
    die(syl_gtb_convertirUTF8('Ocurrió un error al generar el PDF. Intente nuevamente.'));
}

function syl_gtb_resolverLogoDataUri()
{
    $strAbs = __DIR__ . '/syllabus_catedratico_img.png';
    if (!file_exists($strAbs) || !is_readable($strAbs)) {
        return '';
    }
    $bin = file_get_contents($strAbs);
    if ($bin === false || $bin === '') {
        return '';
    }
    return 'data:image/png;base64,' . base64_encode($bin);
}

function syl_gtb_espaciadorVertical($intPx)
{
    $intPx = max(1, intval($intPx));
    return '<div class="espaciador-vertical" style="height:' . $intPx . 'px;">&nbsp;</div>';
}

function syl_gtb_celdaInfoVertical($etiqueta, $valor)
{
    return '
        <div class="info-vertical">
            <div class="info-etiqueta">' . syl_gtb_txt($etiqueta) . '</div>
            <div class="info-valor">' . syl_gtb_mostrarDato($valor) . '</div>
        </div>';
}

function syl_gtb_htmlProfesoresVista($arrProfesores)
{
    if (count($arrProfesores) === 0) {
        return '<div class="sin-info prof-titulo-block">' . syl_gtb_txt('Sin información') . '</div>';
    }

    $html = '<div class="prof-titulo-block">' . syl_gtb_txt('Información de los profesores') . '</div>';

    $intIdx = 0;

    foreach ($arrProfesores as $prof) {
        $intIdx++;
        $esAuxiliar  = (stripos($prof['ROL'] ?? '', 'auxiliar') !== false);
        $labelNombre = $esAuxiliar
            ? syl_gtb_txt('Nombre del profesor auxiliar')
            : syl_gtb_txt('Nombre del profesor');
        $labelEmail  = $esAuxiliar
            ? syl_gtb_txt('Correo electrónico del profesor auxiliar')
            : syl_gtb_txt('Correo electrónico');

        if ($intIdx > 1) {
            $html .= '<div class="prof-separador"></div>';
        }

        $html .= '
        <table class="prof-fila" border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td class="prof-col-nombre" width="50%" valign="top">
                    <div class="prof-etiqueta">' . $labelNombre . '</div>
                    <div class="prof-valor">' . syl_gtb_txt($prof['NOMBRE']) . '</div>
                </td>
                <td class="prof-col-email" width="50%" valign="top">
                    <div class="prof-etiqueta">' . $labelEmail . '</div>
                    <div class="prof-valor">' . syl_gtb_txt($prof['EMAIL']) . '</div>
                </td>
            </tr>
        </table>';
    }

    return $html;
}

function syl_gtb_paletaPastelHex()
{
    return [
        '#4179C8',
        '#E74C98',
        '#1ABC9C',
        '#E67E22',
        '#9B59B6',
        '#F1C40F',
        '#2ECC71',
        '#3498DB',
    ];
}

function syl_gtb_parsePorcentaje($valor)
{
    if ($valor === null || $valor === '') {
        return 0.0;
    }
    if (is_string($valor)) {
        $valor = str_replace(',', '.', trim($valor));
    }
    return floatval($valor);
}

function syl_gtb_slicesEvaluacion($arrEvaluacion)
{
    $paleta = syl_gtb_paletaPastelHex();
    $arrSlices = [];
    $intColorIdx = 0;
    foreach ($arrEvaluacion as $ev) {
        $pct = syl_gtb_parsePorcentaje($ev['PORCENTAJE'] ?? 0);
        if ($pct <= 0) {
            continue;
        }
        $arrSlices[] = [
            'RUBRO'      => $ev['RUBRO'] ?? '',
            'PORCENTAJE' => $pct,
            'HEX'        => $paleta[$intColorIdx % count($paleta)],
        ];
        $intColorIdx++;
    }

    $fltTotal = 0.0;
    foreach ($arrSlices as $slice) {
        $fltTotal += $slice['PORCENTAJE'];
    }
    if ($fltTotal > 0 && abs($fltTotal - 100.0) > 0.01) {
        foreach ($arrSlices as $intIdx => $slice) {
            $arrSlices[$intIdx]['PORCENTAJE'] = ($slice['PORCENTAJE'] / $fltTotal) * 100.0;
        }
    }

    return $arrSlices;
}

function syl_gtb_htmlLeyendaEvaluacion($arrSlices)
{
    $html = '<table class="eval-legend-table" border="0" cellpadding="0" cellspacing="0" width="100%">';
    foreach ($arrSlices as $slice) {
        $pct = number_format(floatval($slice['PORCENTAJE']), 2, '.', '');
        $html .= '
        <tr>
            <td class="eval-legend-swatch-cell">
                <span class="eval-legend-swatch" style="background-color:' . $slice['HEX'] . ';"></span>
            </td>
            <td class="eval-legend-text">'
                . syl_gtb_txt($slice['RUBRO']) . ' (' . syl_gtb_txt($pct . '%') . ')' .
            '</td>
        </tr>';
    }
    $html .= '</table>';
    return $html;
}

function syl_gtb_hexARgb($strHex)
{
    $strHex = ltrim((string) $strHex, '#');
    if (strlen($strHex) === 3) {
        $strHex = $strHex[0] . $strHex[0] . $strHex[1] . $strHex[1] . $strHex[2] . $strHex[2];
    }
    return [
        hexdec(substr($strHex, 0, 2)),
        hexdec(substr($strHex, 2, 2)),
        hexdec(substr($strHex, 4, 2)),
    ];
}

/**
 * Pastel simple: imagen PNG con GD (misma logica angular que TCPDF PieSector).
 */
function syl_gtb_pastelImagenDataUri($arrSlices)
{
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagefilledarc')) {
        return '';
    }

    $intSize = 240;
    $im = imagecreatetruecolor($intSize, $intSize);
    if ($im === false) {
        return '';
    }

    $intBlanco = imagecolorallocate($im, 255, 255, 255);
    $intNegro  = imagecolorallocate($im, 0, 0, 0);
    imagefill($im, 0, 0, $intBlanco);

    $intCx = (int) ($intSize / 2);
    $intCy = (int) ($intSize / 2);
    $intD  = $intSize - 20;

    $fltAngulo = 90.0;
    foreach ($arrSlices as $slice) {
        $arrRgb  = syl_gtb_hexARgb($slice['HEX']);
        $intColor = imagecolorallocate($im, $arrRgb[0], $arrRgb[1], $arrRgb[2]);
        $fltSweep = ($slice['PORCENTAJE'] / 100.0) * 360.0;
        $fltFin   = $fltAngulo - $fltSweep;
        imagefilledarc(
            $im,
            $intCx,
            $intCy,
            $intD,
            $intD,
            (int) round($fltFin),
            (int) round($fltAngulo),
            $intColor,
            IMG_ARC_PIE
        );
        $fltAngulo = $fltFin;
    }

    imageellipse($im, $intCx, $intCy, $intD, $intD, $intNegro);

    ob_start();
    imagepng($im);
    $strBin = ob_get_clean();
    imagedestroy($im);

    if ($strBin === false || $strBin === '') {
        return '';
    }

    return 'data:image/png;base64,' . base64_encode($strBin);
}

function syl_gtb_htmlPastelImagen($arrSlices)
{
    $strDataUri = syl_gtb_pastelImagenDataUri($arrSlices);
    if ($strDataUri === '') {
        return '';
    }

    return '<img src="' . $strDataUri . '" alt="" class="eval-chart-img" />';
}

function syl_gtb_htmlSeccionEvaluacion($arrEvaluacion)
{
    $arrSlices = syl_gtb_slicesEvaluacion($arrEvaluacion);

    if (count($arrSlices) === 0) {
        return syl_gtb_envolverSeccion('Evaluación del curso', syl_gtb_bloqueSimple(''));
    }

    $htmlLeyenda = syl_gtb_htmlLeyendaEvaluacion($arrSlices);
    $htmlPastel  = syl_gtb_htmlPastelImagen($arrSlices);

    $htmlChart = '
    <div class="eval-layout">
        <div class="eval-chart-wrap">' . $htmlPastel . '</div>
        <div class="eval-legend">' . $htmlLeyenda . '</div>
    </div>';

    return syl_gtb_envolverSeccion('Evaluación del curso', $htmlChart);
}

function syl_gtb_htmlEncabezadoCurso($arrCurso, $arrProfesores, $strLogoDataUri = '')
{
    $htmlInfoCurso =
        syl_gtb_celdaInfoVertical('UMA', $arrCurso['UMA']) .
        syl_gtb_celdaInfoVertical('Semestre', $arrCurso['SEMESTRE']) .
        syl_gtb_celdaInfoVertical('Año', $arrCurso['ANIO']) .
        syl_gtb_celdaInfoVertical('Sección', $arrCurso['SECCION']) .
        syl_gtb_celdaInfoVertical('Área académica', $arrCurso['AREA_ACADEMICA']) .
        syl_gtb_celdaInfoVertical('Facultad', $arrCurso['FACULTAD']);

    $htmlProfesores = syl_gtb_htmlProfesoresVista($arrProfesores);

    $htmlLogo = '';
    if ($strLogoDataUri !== '') {
        $htmlLogo = '<div class="encabezado-logo"><img src="' . $strLogoDataUri . '" alt="UFM" class="logo-ufm-inline" /></div>';
    }

    return '
    <div class="encabezado-curso">
        ' . $htmlLogo . '
        <div class="titulo-curso">' . syl_gtb_txt($arrCurso['TITULO']) . '</div>
        <table class="tabla-encabezado" border="1" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td class="col-info-curso" width="' . SYL_GTB_ANCHO_COL_INFO . '" valign="top">'
                    . $htmlInfoCurso .
                '</td>
                <td class="col-profesores" width="85%" valign="top">'
                    . $htmlProfesores .
                '</td>
            </tr>
        </table>
    </div>';
}

function syl_gtb_cssGlobal()
{
    return '
    @page {
        size: letter;
        margin: 24mm 15mm 16mm 15mm;
    }
    * { box-sizing: border-box; }
    html, body {
        margin: 0;
        padding: 0;
    }
    body {
        font-family: Arial, Helvetica, sans-serif;
        font-size: ' . SYL_GTB_FUENTE . ';
        color: ' . SYL_GTB_TEXTO . ';
        line-height: 1.45;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .encabezado-curso {
        margin-bottom: ' . SYL_GTB_SEP_POST_ENCABEZADO . ';
    }
    .encabezado-logo {
        margin-bottom: 6px;
    }
    .logo-ufm-inline {
        display: block;
        width: 24mm;
        height: auto;
    }
    .titulo-curso {
        color: ' . SYL_GTB_ROJO . ';
        font-size: ' . SYL_GTB_FUENTE_TITULO_CURSO . ';
        font-weight: bold;
        line-height: 1.2;
        text-align: center;
        margin-bottom: 10px;
        padding: 0 4px;
    }
    .tabla-encabezado {
        border-color: #CCCCCC;
        border-collapse: collapse;
        width: 100%;
    }
    .col-info-curso {
        background-color: #E7E7E7;
        border-right: 1px solid #CCCCCC;
        padding: 8px 6px 10px 6px;
        vertical-align: top;
    }
    .col-profesores {
        background-color: #FFFFFF;
        padding: 8px 10px 10px 14px;
        vertical-align: top;
    }
    .info-vertical {
        margin-bottom: ' . SYL_GTB_SEP_INFO_BLOQUE . ';
        padding-bottom: 2px;
    }
    .info-vertical:last-child { margin-bottom: 0; }
    .info-etiqueta {
        color: ' . SYL_GTB_ROJO . ';
        font-weight: bold;
        font-size: ' . SYL_GTB_FUENTE_MICRO . ';
        text-align: center;
        line-height: 1.2;
        padding: 0 4px 3px 4px;
    }
    .info-valor {
        color: ' . SYL_GTB_TEXTO . ';
        font-weight: bold;
        font-size: ' . SYL_GTB_FUENTE_MICRO . ';
        text-align: center;
        line-height: 1.25;
        padding: 0 4px 2px 4px;
    }
    .prof-titulo-block {
        font-size: ' . SYL_GTB_FUENTE . ';
        font-weight: bold;
        color: ' . SYL_GTB_TEXTO . ';
        margin-bottom: 8px;
        line-height: 1.3;
    }
    .prof-separador {
        margin: 8px 0;
        padding-left: ' . SYL_GTB_PAD_PROFESORES . ';
        border-top: 1px solid #EEEEEE;
        height: 1px;
        font-size: 1px;
        line-height: 1px;
    }
    .prof-fila { margin-bottom: 6px; }
    .prof-col-nombre { padding-left: ' . SYL_GTB_PAD_PROFESORES . '; padding-right: 10px; }
    .prof-col-email { padding-left: 4px; padding-right: 4px; }
    .prof-etiqueta {
        font-size: ' . SYL_GTB_FUENTE_PEQUENA . ';
        color: #888888;
        margin-bottom: 3px;
        line-height: 1.2;
    }
    .prof-valor {
        font-size: ' . SYL_GTB_FUENTE . ';
        font-weight: bold;
        color: ' . SYL_GTB_TEXTO . ';
        line-height: 1.3;
        margin-bottom: 2px;
    }
    .seccion-bloque {
        break-inside: avoid;
        page-break-inside: avoid;
        margin-top: ' . SYL_GTB_SEP_SECCION . ';
        margin-bottom: ' . SYL_GTB_SEP_SECCION . ';
    }
    .seccion-titulo {
        background-color: ' . SYL_GTB_ROJO . ';
        color: #FFFFFF;
        font-size: ' . SYL_GTB_FUENTE_SECCION . ';
        font-weight: bold;
        text-align: center;
        padding: 5px 8px;
        margin-bottom: 3px;
    }
    .seccion-cuerpo {
        margin-top: 5px;
    }
    .texto-cuerpo {
        font-size: ' . SYL_GTB_FUENTE . ';
        line-height: 1.45;
        text-align: justify;
        color: ' . SYL_GTB_TEXTO . ';
    }
    .texto-cuerpo p { margin: 0 0 6px 0; }
    .texto-cuerpo ul, .texto-cuerpo ol {
        margin: 0 0 6px 0;
        padding-left: 18px;
    }
    .texto-cuerpo li { margin-bottom: 3px; }
    .sin-info { color: #999999; font-style: italic; }
    .tabla-ra {
        width: 100%;
        border-collapse: collapse;
        font-size: ' . SYL_GTB_FUENTE . ';
        border: 1px solid #DDDDDD;
    }
    .tabla-ra th {
        background-color: #E7E7E7;
        color: ' . SYL_GTB_ROJO . ';
        font-weight: bold;
        text-align: center;
        padding: 4px 6px;
        border: 1px solid #DDDDDD;
    }
    .tabla-ra td {
        padding: 4px 6px;
        border: 1px solid #DDDDDD;
        vertical-align: top;
    }
    .tabla-ra .col-bloom { width: 20%; text-align: center; }
    .tabla-ra .col-desc { width: 80%; }
    .lista-biblio {
        margin: 0;
        padding-left: 0;
        list-style: none;
        font-size: ' . SYL_GTB_FUENTE . ';
        color: ' . SYL_GTB_TEXTO . ';
    }
    .lista-biblio li {
        display: table;
        width: 100%;
        margin-bottom: 3px;
        table-layout: fixed;
    }
    .biblio-num {
        display: table-cell;
        width: 3em;
        vertical-align: top;
        padding-right: 6px;
        white-space: nowrap;
    }
    .biblio-text {
        display: table-cell;
        vertical-align: top;
    }
    .lista-exp {
        margin: 0;
        padding-left: 1.4em;
        list-style-type: disc;
        font-size: ' . SYL_GTB_FUENTE . ';
        color: ' . SYL_GTB_TEXTO . ';
    }
    .lista-exp li { margin-bottom: 8px; }
    .eval-layout {
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: 12px;
        width: 100%;
    }
    .eval-chart-wrap {
        flex: 0 0 42%;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 4px 0;
    }
    .eval-chart-img {
        width: 44mm;
        height: 44mm;
        display: block;
    }
    .eval-legend {
        flex: 1 1 58%;
        padding-left: 4px;
    }
    .eval-legend-table { font-size: ' . SYL_GTB_FUENTE . '; }
    .eval-legend-swatch-cell {
        width: 16px;
        padding: 2px 10px 2px 0;
        vertical-align: middle;
    }
    .eval-legend-swatch {
        display: inline-block;
        width: 8px;
        height: 8px;
        border: 1px solid #000000;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .eval-legend-text {
        color: ' . SYL_GTB_TEXTO . ';
        line-height: 1.4;
        padding: 2px 0 2px 6px;
        vertical-align: middle;
    }
    .espaciador-vertical {
        height: ' . SYL_GTB_SEP_TABLA_DESCRIPCION . ';
        font-size: 1px;
        line-height: 1px;
    }
    ';
}

function syl_gtb_htmlDocumentoCompleto($htmlBody, $strTituloDoc)
{
    return '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>' . $strTituloDoc . '</title>
    <style>' . syl_gtb_cssGlobal() . '</style>
</head>
<body>
' . $htmlBody . '
</body>
</html>';
}

function syl_gtb_convertirHtmlAPdf($html)
{
    $tmp = tempnam(sys_get_temp_dir(), 'syl_gtb_html_');
    if ($tmp === false) {
        return [false, 'No se pudo crear archivo temporal HTML.'];
    }

    $htmlFile = $tmp . '.html';
    if (!rename($tmp, $htmlFile)) {
        @unlink($tmp);
        return [false, 'No se pudo preparar el archivo temporal HTML.'];
    }

    if (file_put_contents($htmlFile, $html) === false) {
        @unlink($htmlFile);
        return [false, 'No se pudo escribir el HTML temporal.'];
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => SYL_GTB_URL,
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => [
            'files'             => new CURLFile($htmlFile, 'text/html', 'index.html'),
            'printBackground'   => 'true',
            'preferCssPageSize' => 'true',
        ],
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_CONNECTTIMEOUT => 15,
    ]);

    $pdf = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    @unlink($htmlFile);

    if ($pdf === false) {
        return [false, 'Error CURL al convertir HTML: ' . $curlError];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        return [false, 'Gotenberg HTTP ' . $httpCode . ': ' . substr((string) $pdf, 0, 500)];
    }

    if (strpos($pdf, '%PDF') !== 0) {
        return [false, 'La respuesta de Gotenberg no es un PDF valido.'];
    }

    return [$pdf, ''];
}

function syl_gtb_guardarBinarioTemporal($bin, $strPrefijo, $strExtension)
{
    $tmp = tempnam(sys_get_temp_dir(), $strPrefijo);
    if ($tmp === false) {
        return false;
    }
    $path = $tmp . '.' . $strExtension;
    if (!rename($tmp, $path)) {
        @unlink($tmp);
        return false;
    }
    if (file_put_contents($path, $bin) === false) {
        @unlink($path);
        return false;
    }
    return $path;
}

function syl_gtb_extensionDesdeUrl($strUrl)
{
    $strPath = parse_url($strUrl, PHP_URL_PATH);
    if (!is_string($strPath) || $strPath === '') {
        return '';
    }
    return strtolower(pathinfo($strPath, PATHINFO_EXTENSION));
}

function syl_gtb_tipoArchivoDesdeExtension($strExt)
{
    $strExt = strtolower(trim($strExt));
    if ($strExt === 'pdf') {
        return 'pdf';
    }
    if (in_array($strExt, ['doc', 'docx', 'docm', 'odt', 'rtf'], true)) {
        return 'word';
    }
    if (in_array($strExt, ['xls', 'xlsx', 'xlsm', 'ods', 'csv'], true)) {
        return 'excel';
    }
    if (in_array($strExt, ['ppt', 'pptx', 'pptm', 'odp'], true)) {
        return 'powerpoint';
    }
    return 'desconocido';
}

function syl_gtb_mimeDesdeExtension($strExt)
{
    $arrMap = [
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'docm' => 'application/vnd.ms-word.document.macroEnabled.12',
        'odt'  => 'application/vnd.oasis.opendocument.text',
        'rtf'  => 'application/rtf',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xlsm' => 'application/vnd.ms-excel.sheet.macroEnabled.12',
        'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
        'csv'  => 'text/csv',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'pptm' => 'application/vnd.ms-powerpoint.presentation.macroEnabled.12',
        'odp'  => 'application/vnd.oasis.opendocument.presentation',
    ];
    $strExt = strtolower($strExt);
    return $arrMap[$strExt] ?? 'application/octet-stream';
}

function syl_gtb_convertirOfficeAPdf($strPathArchivo, $strNombreArchivo, $strTipoArchivo = '')
{
    if (!is_readable($strPathArchivo)) {
        return [false, 'No se pudo leer el archivo Office temporal.'];
    }

    $strMime = syl_gtb_mimeDesdeExtension(pathinfo($strNombreArchivo, PATHINFO_EXTENSION));
    $arrPostFields = [
        'files' => new CURLFile($strPathArchivo, $strMime, $strNombreArchivo),
    ];

    if ($strTipoArchivo === 'excel' && SYL_GTB_EXCEL_SINGLE_PAGE_SHEETS) {
        $arrPostFields['singlePageSheets'] = 'true';
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => SYL_GTB_URL_OFFICE,
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => $arrPostFields,
        CURLOPT_TIMEOUT        => 180,
        CURLOPT_CONNECTTIMEOUT => 15,
    ]);

    $pdf = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($pdf === false) {
        return [false, 'Error CURL LibreOffice: ' . $curlError];
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        return [false, 'Gotenberg LibreOffice HTTP ' . $httpCode . ': ' . substr((string) $pdf, 0, 500)];
    }
    if (strpos($pdf, '%PDF') !== 0) {
        return [false, 'La respuesta de Gotenberg LibreOffice no es un PDF valido.'];
    }

    return [$pdf, ''];
}

function syl_gtb_descargarBinarioS3($strUrl)
{
    $strUrl = trim((string) $strUrl);
    if ($strUrl === '') {
        return [false, 'URL S3 del cronograma vacia.'];
    }

    $objBody = core_DescargarContenidoS3DesdeUrl($strUrl);
    if ($objBody === false) {
        return [false, 'No se pudo descargar el cronograma desde S3.'];
    }

    if (is_object($objBody) && method_exists($objBody, 'getContents')) {
        $bin = $objBody->getContents();
    } else {
        $bin = (string) $objBody;
    }

    if ($bin === '') {
        return [false, 'El cronograma descargado de S3 esta vacio.'];
    }

    return [$bin, ''];
}

function syl_gtb_cronogramaS3APdf($strUrl)
{
    list($bin, $strErrorDl) = syl_gtb_descargarBinarioS3($strUrl);
    if ($bin === false) {
        return [false, $strErrorDl];
    }

    $strExt = syl_gtb_extensionDesdeUrl($strUrl);
    if ($strExt === '') {
        return [false, 'No se pudo detectar la extension del cronograma en S3.'];
    }

    $strTipo = syl_gtb_tipoArchivoDesdeExtension($strExt);

    if ($strTipo === 'pdf') {
        if (strpos($bin, '%PDF') !== 0) {
            return [false, 'El cronograma .pdf de S3 no es valido.'];
        }
        return [$bin, ''];
    }

    if ($strTipo === 'desconocido') {
        return [false, 'Extension de cronograma no soportada: .' . $strExt];
    }

    $strNombre = 'cronograma.' . $strExt;
    $strPathTmp = syl_gtb_guardarBinarioTemporal($bin, 'syl_gtb_off_', $strExt);
    if ($strPathTmp === false) {
        return [false, 'No se pudo guardar temporal del cronograma Office.'];
    }

    list($pdf, $strErrorOffice) = syl_gtb_convertirOfficeAPdf($strPathTmp, $strNombre, $strTipo);
    @unlink($strPathTmp);

    if ($pdf === false) {
        return [false, $strErrorOffice];
    }

    return [$pdf, ''];
}

function syl_gtb_buildMergePostFields($arrPdfPaths)
{
    $arrPost = [];
    foreach ($arrPdfPaths as $intIdx => $strPath) {
        if (!is_readable($strPath)) {
            return [false, 'No se pudo leer PDF temporal: ' . $strPath];
        }
        $strFilename = sprintf('%02d_documento.pdf', $intIdx + 1);
        $arrPost[] = 'files';
        $arrPost[] = new CURLFile($strPath, 'application/pdf', $strFilename);
    }
    return [$arrPost, ''];
}

function syl_gtb_mergePdfsArchivos($arrPdfPaths)
{
    if (count($arrPdfPaths) < 2) {
        return [false, 'Se requieren al menos dos PDF para combinar.'];
    }

    list($arrPostFields, $strErrorBuild) = syl_gtb_buildMergePostFields($arrPdfPaths);
    if ($arrPostFields === false) {
        return [false, $strErrorBuild];
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => SYL_GTB_URL_MERGE,
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => $arrPostFields,
        CURLOPT_TIMEOUT        => 180,
        CURLOPT_CONNECTTIMEOUT => 15,
    ]);

    $pdf = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($pdf === false) {
        return [false, 'Error CURL merge: ' . $curlError];
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        return [false, 'Gotenberg merge HTTP ' . $httpCode . ': ' . substr((string) $pdf, 0, 500)];
    }
    if (strpos($pdf, '%PDF') !== 0) {
        return [false, 'La respuesta de Gotenberg merge no es un PDF valido.'];
    }

    return [$pdf, ''];
}

function syl_gtb_generarPdfFinal($htmlAntesCronograma, $htmlDespuesCronograma, $htmlCronogramaSeccion, $arrPathsCronograma, $strTituloDoc)
{
    $arrPathsCronograma = array_values(array_filter(
        is_array($arrPathsCronograma) ? $arrPathsCronograma : [],
        function($s) { return trim((string) $s) !== ''; }
    ));

    if (empty($arrPathsCronograma)) {
        $htmlCompleto = syl_gtb_htmlDocumentoCompleto(
            $htmlAntesCronograma . $htmlCronogramaSeccion . $htmlDespuesCronograma,
            $strTituloDoc
        );
        $htmlCompleto = syl_gtb_convertirUTF8($htmlCompleto);
        return syl_gtb_convertirHtmlAPdf($htmlCompleto);
    }

    $htmlParte1 = syl_gtb_htmlDocumentoCompleto($htmlAntesCronograma,   $strTituloDoc);
    $htmlParte2 = syl_gtb_htmlDocumentoCompleto($htmlDespuesCronograma, $strTituloDoc);
    $htmlParte1 = syl_gtb_convertirUTF8($htmlParte1);
    $htmlParte2 = syl_gtb_convertirUTF8($htmlParte2);

    list($pdfParte1, $strError1) = syl_gtb_convertirHtmlAPdf($htmlParte1);
    if ($pdfParte1 === false) {
        return [false, $strError1];
    }

    list($pdfParte2, $strError2) = syl_gtb_convertirHtmlAPdf($htmlParte2);
    if ($pdfParte2 === false) {
        return [false, $strError2];
    }

    $arrTmpPaths   = [];
    $strPath1      = syl_gtb_guardarBinarioTemporal($pdfParte1, 'syl_gtb_p1_', 'pdf');
    $arrTmpPaths[] = $strPath1;

    foreach ($arrPathsCronograma as $strPathS3) {
        list($pdfCrono, $strErrCrono) = syl_gtb_cronogramaS3APdf($strPathS3);
        if ($pdfCrono === false) {
            error_log('[SYLLABUS GTB] Cronograma omitido (' . $strPathS3 . '): ' . $strErrCrono);
            continue;
        }
        $strTmpCrono = syl_gtb_guardarBinarioTemporal($pdfCrono, 'syl_gtb_cr_', 'pdf');
        if ($strTmpCrono !== false) {
            $arrTmpPaths[] = $strTmpCrono;
        }
    }

    $strPath2      = syl_gtb_guardarBinarioTemporal($pdfParte2, 'syl_gtb_p2_', 'pdf');
    $arrTmpPaths[] = $strPath2;

    $arrValidPaths = array_filter($arrTmpPaths, function($p) { return $p !== false && is_readable($p); });

    if (count($arrValidPaths) < 2) {
        foreach ($arrTmpPaths as $p) { if ($p) @unlink($p); }
        return [false, 'No se pudieron crear suficientes archivos temporales para combinar.'];
    }

    list($pdfCombinado, $strErrorMerge) = syl_gtb_mergePdfsArchivos(array_values($arrValidPaths));

    foreach ($arrTmpPaths as $p) { if ($p) @unlink($p); }

    if ($pdfCombinado === false) {
        return [false, $strErrorMerge];
    }

    return [$pdfCombinado, ''];
}

/**
 * Genera el PDF completo del syllabus catedratico (borrador activo).
 * @return array [string|false $pdfBinario, string $strError]
 */
function syl_gtb_buildPdfSyllabusCatedratico($globalConnection, $intCursoImpartido)
{
    $intCursoImpartido = intval($intCursoImpartido);
    if ($intCursoImpartido <= 0) {
        return [false, 'Parametro cimp invalido.'];
    }

    // ---------------------------------------------------------------------------
    // Carga de datos (misma logica que syllabus_catedratico_pdf.php)
    // ---------------------------------------------------------------------------

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
        A.NOMBRE        AS AREA_ACADEMICA,
        AC.CICLO,
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

$arrProfesores     = [];
$tipoCursoOriginal = '';
$primeraFila       = true;
$intCurso          = 0;

while ($rowImp = oci_fetch_array($stidImp, OCI_ASSOC + OCI_RETURN_NULLS)) {
    if ($primeraFila) {
        $intCurso                   = intval($rowImp['CURSO'] ?? 0);
        $arrCurso['SECCION']        = $rowImp['SECCION'] ?? '';
        $arrCurso['NOMBRE']         = $rowImp['NOMBRE_CURSO'] ?? '';
        $arrCurso['CODIGO']         = $rowImp['CODIGO_CURSO'] ?? '';
        $arrCurso['UMA']            = intval($rowImp['UMAS'] ?? 0);
        $arrCurso['FACULTAD']       = $rowImp['FACULTAD'] ?? '';
        $arrCurso['AREA_ACADEMICA'] = $rowImp['AREA_ACADEMICA'] ?? '';
        $tipoCursoOriginal          = $rowImp['TIPO_CURSO'] ?? '';

        $ciclo = $rowImp['CICLO'] ?? '';
        $arrCurso['ANIO']     = substr($ciclo, 0, 4);
        $arrCurso['SEMESTRE'] = substr($ciclo, 4, 2);
        $arrCurso['TITULO']   = $arrCurso['NOMBRE'] . ' - ' . $arrCurso['SECCION'] . ' - ' . $ciclo;
        $primeraFila = false;
    }

    $arrProfesores[] = [
        'NOMBRE' => $rowImp['NOMBRE_PERSONA'] ?? '',
        'EMAIL'  => $rowImp['EMAIL_PERSONA'] ?? '',
        'ROL'    => $rowImp['ROL'] ?? '',
    ];
}
oci_free_statement($stidImp);

$arrProfesores = syl_uac_ordenarProfesoresPrincipalPrimero($arrProfesores);

if ($intCurso <= 0) {
    return [false, 'Acceso denegado.'];
}

$stidEtica = oci_parse($globalConnection,
    "SELECT COUNT(*) AS ETICA FROM ASIGNACION_ETICA WHERE CURSO_IMPARTIDO_ETICA = :cimp");
oci_bind_by_name($stidEtica, ':cimp', $intCursoImpartido, -1, SQLT_INT);
oci_execute($stidEtica);
$rowEtica = oci_fetch_array($stidEtica, OCI_ASSOC + OCI_RETURN_NULLS);
oci_free_statement($stidEtica);

if (intval($rowEtica['ETICA'] ?? 0) > 0) {
    $stidDE = oci_parse($globalConnection, "
        SELECT CI.SECCION, C.CODIGO AS CODIGO_CURSO, C.NOMBRE AS NOMBRE_CURSO,
               C.TIPO_CURSO, AC.CICLO
        FROM CURSO_IMPARTIDO CI
        INNER JOIN CURSO C ON CI.CURSO = C.CURSO
        INNER JOIN CICLO_FECHA CF ON CI.CICLO_FECHA = CF.CICLO_FECHA
        LEFT JOIN CUENTAC.VW_NCC_AGRUPADOR_CICLOS AC ON CF.CICLO_FECHA = AC.CICLO_FECHA
        WHERE CI.CURSO_IMPARTIDO = :cimp");
    oci_bind_by_name($stidDE, ':cimp', $intCursoImpartido, -1, SQLT_INT);
    oci_execute($stidDE);
    $rowDE = oci_fetch_array($stidDE, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stidDE);

    if ($rowDE) {
        $arrCurso['NOMBRE']   = $rowDE['NOMBRE_CURSO'] ?? $arrCurso['NOMBRE'];
        $arrCurso['CODIGO']   = $rowDE['CODIGO_CURSO'] ?? $arrCurso['CODIGO'];
        $arrCurso['SECCION']  = $rowDE['SECCION'] ?? $arrCurso['SECCION'];
        $cicloEtica           = $rowDE['CICLO'] ?? '';
        $arrCurso['ANIO']     = substr($cicloEtica, 0, 4);
        $arrCurso['SEMESTRE'] = substr($cicloEtica, 4, 2);
        $arrCurso['TITULO']   = $arrCurso['NOMBRE'] . ' - ' . $arrCurso['SECCION'] . ' - ' . $cicloEtica;
        $arrCurso['FACULTAD'] = ($tipoCursoOriginal === 'COLIB') ? 'Colaboratorio' : 'Centro Henry Hazlitt';
    }
}

$strDescInstitucional = '';
$strAportePlan        = '';
$strConocPrevios      = '';
$strMarco             = '';
$arrRA                = [];
$arrBiblioUA          = [];

$stidUA = oci_parse($globalConnection,
    "SELECT SYLLABUS_UA FROM SYLLABUS_UA WHERE CURSO = :curso AND FECHA_FIN IS NULL");
oci_bind_by_name($stidUA, ':curso', $intCurso, -1, SQLT_INT);
oci_execute($stidUA);
$rowUA = oci_fetch_array($stidUA, OCI_ASSOC + OCI_RETURN_NULLS);
oci_free_statement($stidUA);

if (!$rowUA) {
    return [false, 'Syllabus de unidad academica no disponible para este curso.'];
}

$intSyllabusUA = intval($rowUA['SYLLABUS_UA']);

$stidClobs = oci_parse($globalConnection,
    "SELECT DESCRIPCION_INSTITUCIONAL, APORTE_PLAN_ESTUDIOS,
            CONOCIMIENTOS_PREVIOS, MARCO_NORMATIVO
     FROM SYLLABUS_UA WHERE SYLLABUS_UA = :id");
oci_bind_by_name($stidClobs, ':id', $intSyllabusUA, -1, SQLT_INT);
oci_execute($stidClobs);
$rowClobs = oci_fetch_array($stidClobs, OCI_ASSOC + OCI_RETURN_NULLS + OCI_RETURN_LOBS);
oci_free_statement($stidClobs);

if ($rowClobs) {
    $strDescInstitucional = $rowClobs['DESCRIPCION_INSTITUCIONAL'] ?? '';
    $strAportePlan        = $rowClobs['APORTE_PLAN_ESTUDIOS'] ?? '';
    $strConocPrevios      = $rowClobs['CONOCIMIENTOS_PREVIOS'] ?? '';
    $strMarco             = $rowClobs['MARCO_NORMATIVO'] ?? '';
}

$stidRA = oci_parse($globalConnection,
    "SELECT ra.DESCRIPCION_RA, bn.NOMBRE AS BLOOM_NOMBRE
     FROM SYLLABUS_UA_RA ra
     LEFT JOIN BLOOM_NIVEL bn ON ra.BLOOM_NIVEL = bn.BLOOM_NIVEL
     WHERE ra.SYLLABUS_UA = :id
     ORDER BY ra.SYLLABUS_UA_RA");
oci_bind_by_name($stidRA, ':id', $intSyllabusUA, -1, SQLT_INT);
oci_execute($stidRA);
while ($rowRA = oci_fetch_array($stidRA, OCI_ASSOC + OCI_RETURN_NULLS)) {
    $arrRA[] = [
        'BLOOM' => $rowRA['BLOOM_NOMBRE'] ?? '',
        'DESC'  => $rowRA['DESCRIPCION_RA'] ?? '',
    ];
}
oci_free_statement($stidRA);

$stidBibUA = oci_parse($globalConnection,
    "SELECT REFERENCIA_COMPLETA FROM SYLLABUS_UA_BIBLIO
     WHERE SYLLABUS_UA = :id ORDER BY ADD_FECHA");
oci_bind_by_name($stidBibUA, ':id', $intSyllabusUA, -1, SQLT_INT);
oci_execute($stidBibUA);
while ($rowBib = oci_fetch_array($stidBibUA, OCI_ASSOC + OCI_RETURN_NULLS + OCI_RETURN_LOBS)) {
    $arrBiblioUA[] = $rowBib['REFERENCIA_COMPLETA'] ?? '';
}
oci_free_statement($stidBibUA);

$intSyllabusCatedratico = 0;
$strNormas              = '';
$strUsoIA               = '';
$strPensamientoCritico  = '';
$arrCronogramas         = [];
$arrEvaluacion          = [];
$arrBiblioEvolutiva     = [];
$arrExperiencias        = [];

$stidCat = oci_parse($globalConnection,
    "SELECT SYLLABUS_UA_CATEDRATICO, NORMAS_REGLAS, USO_IA, PENSAMIENTO_CRITICO
     FROM SYLLABUS_UA_CATEDRATICO
     WHERE CURSO_IMPARTIDO = :cimp
       AND FECHA_INICIO IS NULL
       AND FECHA_FIN IS NULL");
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

if ($intSyllabusCatedratico > 0) {
    $stidEval = oci_parse($globalConnection,
        "SELECT RUBRO, PORCENTAJE FROM SYLLABUS_UA_CATEDRATICO_EVALUACION
         WHERE SYLLABUS_UA_CATEDRATICO = :id ORDER BY SYLLABUS_UAC_EVALUACION");
    oci_bind_by_name($stidEval, ':id', $intSyllabusCatedratico, -1, SQLT_INT);
    oci_execute($stidEval);
    while ($rowEval = oci_fetch_array($stidEval, OCI_ASSOC + OCI_RETURN_NULLS)) {
        $arrEvaluacion[] = [
            'RUBRO'      => $rowEval['RUBRO'] ?? '',
            'PORCENTAJE' => $rowEval['PORCENTAJE'] ?? 0,
        ];
    }
    oci_free_statement($stidEval);

    $stidBibEv = oci_parse($globalConnection,
        "SELECT SYLLABUS_UAC_BIBLIOGRAFIA
         FROM SYLLABUS_UA_CATEDRATICO_BIBLIOGRAFIA
         WHERE SYLLABUS_UA_CATEDRATICO = :id ORDER BY SYLLABUS_UAC_BIBLIOGRAFIA");
    oci_bind_by_name($stidBibEv, ':id', $intSyllabusCatedratico, -1, SQLT_INT);
    oci_execute($stidBibEv);
    while ($rowBibEv = oci_fetch_array($stidBibEv, OCI_ASSOC + OCI_RETURN_NULLS)) {
        $intBiblioId = intval($rowBibEv['SYLLABUS_UAC_BIBLIOGRAFIA']);
        $arrBiblioEvolutiva[] = uac_getReferenciaBiblioEv($globalConnection, $intBiblioId);
    }
    oci_free_statement($stidBibEv);

    $stidExp = oci_parse($globalConnection,
        "SELECT DESCRIPCION FROM SYLLABUS_UA_CATEDRATICO_EXPERIENCIA
         WHERE SYLLABUS_UA_CATEDRATICO = :id ORDER BY SYLLABUS_UAC_EXPERIENCIA");
    oci_bind_by_name($stidExp, ':id', $intSyllabusCatedratico, -1, SQLT_INT);
    oci_execute($stidExp);
    while ($rowExp = oci_fetch_array($stidExp, OCI_ASSOC + OCI_RETURN_NULLS)) {
        $arrExperiencias[] = $rowExp['DESCRIPCION'] ?? '';
    }
    oci_free_statement($stidExp);

    $stidCrono = oci_parse($globalConnection,
        "SELECT PATH_ARCHIVO, NOMBRE_ARCHIVO
         FROM   SYLLABUS_UA_CATEDRATICO_CRONOGRAMA
         WHERE  SYLLABUS_UA_CATEDRATICO = :id
           AND  ACTIVO = 'Y'
         ORDER  BY ADD_FECHA, SYLLABUS_UAC_CRONOGRAMA");
    oci_bind_by_name($stidCrono, ':id', $intSyllabusCatedratico, -1, SQLT_INT);
    oci_execute($stidCrono);
    while ($rowCrono = oci_fetch_array($stidCrono, OCI_ASSOC + OCI_RETURN_NULLS)) {
        $arrCronogramas[] = [
            'PATH'   => trim($rowCrono['PATH_ARCHIVO']    ?? ''),
            'NOMBRE' => trim($rowCrono['NOMBRE_ARCHIVO']  ?? ''),
        ];
    }
    oci_free_statement($stidCrono);
}

// ---------------------------------------------------------------------------
// Construccion del HTML del PDF
// ---------------------------------------------------------------------------

$strLogoDataUri = syl_gtb_resolverLogoDataUri();

$htmlRA = '';
if (count($arrRA) > 0) {
    $htmlRA .= '
    <table class="tabla-ra" border="1" cellpadding="2" cellspacing="0" width="100%">
        <tr>
            <th class="col-bloom">' . syl_gtb_txt('Nivel Bloom') . '</th>
            <th class="col-desc">' . syl_gtb_txt('Descripción') . '</th>
        </tr>';
    foreach ($arrRA as $ra) {
        $htmlRA .= '
        <tr>
            <td class="col-bloom">' . syl_gtb_txt($ra['BLOOM']) . '</td>
            <td class="col-desc">' . syl_gtb_txt($ra['DESC']) . '</td>
        </tr>';
    }
    $htmlRA .= '</table>';
} else {
    $htmlRA = syl_gtb_bloqueSimple('');
}

$htmlBiblioUA = syl_gtb_htmlListaBiblio($arrBiblioUA);

$htmlBiblioEv = syl_gtb_htmlListaBiblio($arrBiblioEvolutiva);

$htmlExp = '<ul class="lista-exp">';
if (count($arrExperiencias) > 0) {
    foreach ($arrExperiencias as $desc) {
        $htmlExp .= '<li>' . syl_gtb_html($desc) . '</li>';
    }
} else {
    $htmlExp .= '<li><span class="sin-info">' . syl_gtb_txt('Sin información') . '</span></li>';
}
$htmlExp .= '</ul>';

$strNotaCronograma = syl_gtb_txt('Nota: el cronograma de actividades se adjunta como archivo(s) separado(s).');
if (count($arrCronogramas) > 0) {
    $arrNombresNotaCrono = array_map(function($c) { return $c['NOMBRE']; }, $arrCronogramas);
    $strNotaCronograma .= ' <b>' . syl_gtb_txt(implode(', ', $arrNombresNotaCrono)) . '</b>';
}
$htmlCronograma = syl_gtb_bloqueHtml('<span style="font-style:italic;">' . $strNotaCronograma . '</span>');

$htmlCronogramaSeccion = syl_gtb_envolverSeccion('Cronograma de actividades', $htmlCronograma);

$htmlAntesCronograma =
    syl_gtb_htmlEncabezadoCurso($arrCurso, $arrProfesores, $strLogoDataUri) .
    syl_gtb_espaciadorVertical(SYL_GTB_SEP_TABLA_DESCRIPCION) .
    syl_gtb_envolverSeccion('Descripción del curso', syl_gtb_bloqueHtml($strDescInstitucional), '0px') .
    syl_gtb_envolverSeccion('Aportes al plan de estudios / perfil de egreso', syl_gtb_bloqueHtml($strAportePlan)) .
    syl_gtb_envolverSeccion('Resultados de aprendizaje del curso (medibles)', $htmlRA) .
    syl_gtb_envolverSeccion('Conocimientos previos esperados', syl_gtb_bloqueHtml($strConocPrevios)) .
    syl_gtb_envolverSeccion('Bibliografía base mínima (curada por la unidad)', $htmlBiblioUA) .
    syl_gtb_envolverSeccion('Marco normativo institucional', syl_gtb_bloqueHtml($strMarco)) .
    syl_gtb_envolverSeccion('Normas y reglas operativas del curso', syl_gtb_bloqueHtml($strNormas)) .
    syl_gtb_htmlSeccionEvaluacion($arrEvaluacion);

$htmlDespuesCronograma =
    syl_gtb_envolverSeccion('Bibliografía del catedrático', $htmlBiblioEv) .
    syl_gtb_envolverSeccion('Experiencias principales', $htmlExp) .
    syl_gtb_envolverSeccion('Uso de IA en el curso', syl_gtb_bloqueHtml($strUsoIA)) .
    syl_gtb_envolverSeccion('Desarrollo del pensamiento crítico', syl_gtb_bloqueHtml($strPensamientoCritico));

$strTituloDoc = syl_gtb_esc('Syllabus - ' . $arrCurso['TITULO']);

// ---------------------------------------------------------------------------
// Gotenberg -> PDF (cronograma S3 insertado tras evaluacion si existe)
// ---------------------------------------------------------------------------

$arrPathsCronogramaActivos = array_map(function($c) { return $c['PATH']; }, $arrCronogramas);

list($pdfBinario, $strErrorGtb) = syl_gtb_generarPdfFinal(
    $htmlAntesCronograma,
    $htmlDespuesCronograma,
    $htmlCronogramaSeccion,
    $arrPathsCronogramaActivos,
    $strTituloDoc
);
if ($pdfBinario === false) {
    return [false, $strErrorGtb];
}

    return [$pdfBinario, ''];
}

// ---------------------------------------------------------------------------
// Vista previa HTTP (solo cuando se invoca este archivo directamente)
// ---------------------------------------------------------------------------

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {

    if ($intCursoImpartido <= 0) {
        http_response_code(400);
        die(syl_gtb_convertirUTF8('Parametro cimp invalido.'));
    }

    list($pdfBinario, $strErrorGtb) = syl_gtb_buildPdfSyllabusCatedratico($globalConnection, $intCursoImpartido);
    if ($pdfBinario === false) {
        syl_gtb_fail($strErrorGtb);
    }

    $nombreArchivo = 'syllabus_catedratico_' . $intCursoImpartido . '.pdf';

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $nombreArchivo . '"');
    header('Content-Length: ' . strlen($pdfBinario));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    echo $pdfBinario;
    exit;
}
