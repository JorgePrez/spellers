<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once(__DIR__ . '/tcpdf_new/tcpdf.php');

function convertirUTF8($valor) {
    if ($valor === null) return '';
    $detected = mb_detect_encoding($valor, "UTF-8, ISO-8859-1, ISO-8859-15", true);
    if ($detected !== 'UTF-8') {
        $valor = mb_convert_encoding($valor, 'UTF-8', $detected);
    }
    return $valor;
}

$pdf = new TCPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);

$pdf->SetCreator('UFM');
$pdf->SetAuthor('Jorge');
$pdf->SetTitle('Prueba Compleja TCPDF UTF-8');
$pdf->SetSubject('Prueba de estilos HTML');
$pdf->SetKeywords('TCPDF, UTF8, HTML, estilos');

$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 15);

$pdf->AddPage();
$pdf->SetFont('dejavusans', '', 10);

$html = '
<style>
    .titulo {
        text-align: center;
        color: #003366;
        font-size: 18px;
        font-weight: bold;
    }
    .subtitulo {
        text-align: center;
        color: #666666;
        font-size: 11px;
    }
    .bloque {
        background-color: #F5F7FA;
        border: 1px solid #CCCCCC;
        padding: 8px;
    }
    .etiqueta {
        color: #003366;
        font-weight: bold;
    }
    .nota {
        color: #990000;
        font-style: italic;
    }
    .centrado {
        text-align: center;
    }
    .derecha {
        text-align: right;
    }
</style>

<div class="titulo">Reporte de Prueba TCPDF</div>
<div class="subtitulo">Verificación de estilos, tablas, colores y tildes: á é í ó ú ñ Ñ</div>

<br>

<div class="bloque">
    <p>
        Este es un párrafo de prueba con <b>negrita</b>,
        <i>itálica</i>, <u>subrayado</u>,
        <span style="color:#006600;">texto verde</span> y
        <span style="color:#CC0000;">texto rojo</span>.
    </p>

    <p class="nota">
        Nota importante: este bloque contiene caracteres especiales y debe renderizarse correctamente.
    </p>
</div>

<br>

<h3 style="color:#003366;">Listado</h3>
<ul>
    <li>Resultado con tildes: matrícula, descripción, aprobación</li>
    <li>Otro elemento con ñ: año, niños, señor</li>
    <li>Elemento normal sin estilos especiales</li>
</ul>

<br>

<h3 style="color:#003366;">Tabla principal</h3>
<table border="1" cellpadding="5" cellspacing="0">
    <tr style="background-color:#D9EAF7;">
        <th width="15%" class="centrado">Código</th>
        <th width="45%" class="centrado">Descripción</th>
        <th width="20%" class="centrado">Estado</th>
        <th width="20%" class="centrado">Punteo</th>
    </tr>
    <tr>
        <td class="centrado">RPT-001</td>
        <td>Generación de PDF con HTML</td>
        <td class="centrado" style="color:#006600;">OK</td>
        <td class="derecha">100</td>
    </tr>
    <tr>
        <td class="centrado">RPT-002</td>
        <td>Prueba con tildes y eñes</td>
        <td class="centrado" style="color:#006600;">OK</td>
        <td class="derecha">95</td>
    </tr>
    <tr>
        <td class="centrado">RPT-003</td>
        <td>Validación de estilos HTML</td>
        <td class="centrado" style="color:#CC0000;">REVISAR</td>
        <td class="derecha">80</td>
    </tr>
</table>

<br>

<h3 style="color:#003366;">Tabla con colspan</h3>
<table border="1" cellpadding="5" cellspacing="0">
    <tr style="background-color:#EEEEEE;">
        <th colspan="2" class="centrado">Resumen general</th>
    </tr>
    <tr>
        <td width="35%" class="etiqueta">Total de registros</td>
        <td width="65%">3</td>
    </tr>
    <tr>
        <td class="etiqueta">Estado general</td>
        <td style="color:#006600; font-weight:bold;">Correcto</td>
    </tr>
</table>

<br>

<p style="text-align:justify;">
Este párrafo está justificado para simular un contenido más real de un reporte institucional.
La idea es verificar que TCPDF 6.11.3, junto con la conversión a UTF-8, pueda manejar texto
con caracteres especiales y estilos básicos sin romperse.
</p>

<p style="font-size:12px;">
Fecha y hora de generación: <b>' . date('Y-m-d H:i:s') . '</b>
</p>
';

$html = convertirUTF8($html);

$pdf->writeHTML($html, true, false, true, false, '');

$pdf->Output('prueba_estilos_utf8.pdf', 'I');