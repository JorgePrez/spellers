<?php
// IMPORTANTE: no debe haber espacios ni líneas antes de este PHP.

// Cambia esto por la URL real de tu Gotenberg:
$gotenbergUrl = 'http://3.150.240.23:3000/forms/chromium/convert/html';

// HTML que quieres convertir a PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Prueba PDF</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
        }
        h1 {
            color: #222;
        }
    </style>
</head>
<body>
    <h1>Hola Jorge</h1>
    <p>Este PDF fue generado desde Gotenberg.</p>
    <p>Fecha: ' . date('Y-m-d H:i:s') . '</p>
</body>
</html>
';

// Crear archivo temporal HTML
$tempFile = tempnam(sys_get_temp_dir(), 'html_');
if ($tempFile === false) {
    die('No se pudo crear el archivo temporal.');
}

$htmlFile = $tempFile . '.html';
rename($tempFile, $htmlFile);
file_put_contents($htmlFile, $html);

// Iniciar cURL
$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $gotenbergUrl,
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS => [
        'files' => new CURLFile($htmlFile, 'text/html', 'index.html')
    ],
    CURLOPT_TIMEOUT => 60,
    CURLOPT_CONNECTTIMEOUT => 10,
]);

$pdf = curl_exec($ch);

// Error de cURL
if ($pdf === false) {
    $error = curl_error($ch);
    curl_close($ch);
    @unlink($htmlFile);
    die('Error CURL: ' . $error);
}

// Revisar código HTTP
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

curl_close($ch);
@unlink($htmlFile);

// Si Gotenberg respondió con error, mostrarlo
if ($httpCode < 200 || $httpCode >= 300) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Gotenberg devolvió HTTP $httpCode\n\n";
    echo $pdf;
    exit;
}

// Validación simple de PDF
if (strpos($pdf, '%PDF') !== 0) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "La respuesta no parece ser un PDF válido.\n\n";
    echo "Content-Type recibido: " . ($contentType ?: 'desconocido') . "\n\n";
    echo $pdf;
    exit;
}

// Mostrar PDF en el navegador
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="prueba.pdf"');
header('Content-Length: ' . strlen($pdf));

echo $pdf;
exit;