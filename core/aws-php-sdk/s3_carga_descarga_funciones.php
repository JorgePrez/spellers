<?php

require_once __DIR__ . '/aws_config.php';

use Aws\Exception\AwsException;
use Aws\S3\MultipartUploader;
use Aws\Exception\MultipartUploadException;


function core_SanitizeFilename($str) {
    if (!is_string($str)) {
        return '';
    }

    $str = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
    $str = preg_replace('/[^A-Za-z0-9 _\.\-]/', '', $str);
    $str = preg_replace('/[\x00-\x1F\x7F]/', '', $str);
    $str = preg_replace('/\s+/', '_', $str);

    return trim($str);
}





/*
// Esta version usa putObject

function core_SubirArchivoS3($bucket, $strNombreArchivo, $strNombreTemporalArchivo, $strDirectorio = '') {
    if (empty($bucket) || empty($strNombreArchivo) || empty($strNombreTemporalArchivo)) {
        error_log("Error: parámetros vacíos en core_SubirArchivoS3()");
        return false;
    }

    if (!file_exists($strNombreTemporalArchivo)) {
        error_log("Error: no existe el archivo temporal en core_SubirArchivoS3()");
        return false;
    }

    $s3 = AWSConfig::getS3Client();

    $strNombreArchivo = core_SanitizeFilename($strNombreArchivo);

    if ($strNombreArchivo === '') {
        error_log("Error: nombre de archivo inválido en core_SubirArchivoS3()");
        return false;
    }

    if (!empty($strDirectorio)) {
        $s3Key = trim($strDirectorio, '/') . '/' . $strNombreArchivo;
    } else {
        $s3Key = $strNombreArchivo;
    }

    try {
        $s3->putObject([
            'Bucket'     => $bucket,
            'Key'        => $s3Key,
            'SourceFile' => $strNombreTemporalArchivo,
            'ACL'        => 'private'
        ]);

        $region = AWSConfig::getRegion();
        $urlOriginal = "https://{$bucket}.s3.{$region}.amazonaws.com/{$s3Key}";

        return $urlOriginal;

    } catch (AwsException $e) {
        error_log("Error al subir archivo a S3: " . $e->getAwsErrorMessage());
        return false;
    }
}
*/



function core_SubirArchivoS3($bucket, $strNombreArchivo, $strNombreTemporalArchivo, $strDirectorio = '') {
    if (empty($bucket) || empty($strNombreArchivo) || empty($strNombreTemporalArchivo)) {
        error_log("Error: parámetros vacíos en core_SubirArchivoS3()");
        return false;
    }

    if (!file_exists($strNombreTemporalArchivo)) {
        error_log("Error: no existe el archivo temporal en core_SubirArchivoS3()");
        return false;
    }

    $s3 = AWSConfig::getS3Client();
    $region = AWSConfig::getRegion();

    $strNombreArchivo = core_SanitizeFilename($strNombreArchivo);

    if ($strNombreArchivo === '') {
        error_log("Error: nombre de archivo inválido en core_SubirArchivoS3()");
        return false;
    }

    if (!empty($strDirectorio)) {
        $s3Key = trim($strDirectorio, '/') . '/' . $strNombreArchivo;
    } else {
        $s3Key = $strNombreArchivo;
    }

    $fileSize = filesize($strNombreTemporalArchivo);
    if ($fileSize === false) {
        error_log("Error: no se pudo obtener el tamaño del archivo en core_SubirArchivoS3()");
        return false;
    }

    $useMultipart = ($fileSize >= 100 * 1024 * 1024); // 100 MB

    try {
        if ($useMultipart) {
            ini_set('max_execution_time', 0);
            ini_set('memory_limit', '1024M');

            $uploader = new MultipartUploader($s3, $strNombreTemporalArchivo, [
                'bucket'    => $bucket,
                'key'       => $s3Key,
                'part_size'  => 10 * 1024 * 1024, // 10 MB por parte
            ]);

            try {
                $uploader->upload();
            } catch (MultipartUploadException $e) {
                $uploader = new MultipartUploader($s3, $strNombreTemporalArchivo, [
                    'state' => $e->getState()
                ]);

                $uploader->upload();
            }
        } else {
            $s3->putObject([
                'Bucket'     => $bucket,
                'Key'        => $s3Key,
                'SourceFile' => $strNombreTemporalArchivo,
                'ACL'        => 'private'
            ]);
        }

        $urlOriginal = "https://{$bucket}.s3.{$region}.amazonaws.com/{$s3Key}";
        return $urlOriginal;

    } catch (AwsException $e) {
        error_log("Error al subir archivo a S3: " . $e->getAwsErrorMessage());
        return false;
    } catch (Exception $e) {
        error_log("Error general al subir archivo a S3: " . $e->getMessage());
        return false;
    }
}

//$expiracionSegundos = 86400; 1 dia

function core_ObtenerUrlVerS3DesdeUrl($originalUrl, $expiracionSegundos = 86400) {
    if (empty($originalUrl)) {
        return null;
    }

    $parsed = parse_url($originalUrl);
    if (!isset($parsed['host'], $parsed['path'])) {
        error_log("Error: URL inválida en core_ObtenerUrlVerS3DesdeUrl() -> " . $originalUrl);
        return null;
    }

    $bucketParts = explode('.', $parsed['host']);
    $bucket = $bucketParts[0] ?? '';
    $key    = ltrim($parsed['path'], '/');

    if (empty($bucket) || empty($key)) {
        error_log("Error: no se pudo obtener bucket o key en core_ObtenerUrlVerS3DesdeUrl()");
        return null;
    }

    $s3 = AWSConfig::getS3Client();

    try {
        $s3->headObject([
            'Bucket' => $bucket,
            'Key'    => $key,
        ]);

        $cmd = $s3->getCommand('GetObject', [
            'Bucket' => $bucket,
            'Key'    => $key
        ]);

        $request = $s3->createPresignedRequest($cmd, "+" . intval($expiracionSegundos) . " seconds");
        return (string) $request->getUri();

    } catch (AwsException $e) {
        if ($e->getAwsErrorCode() === 'NotFound') {
            error_log("Error: Objeto no encontrado en S3 -> " . $key);
            return null;
        }

        error_log("Error generando URL para ver archivo: " . $e->getAwsErrorMessage());
        return null;
    }
}

//$expiracionSegundos = 86400; 1 dia
function core_ObtenerUrlDescargaS3DesdeUrl($originalUrl, $expiracionSegundos = 86400, $customFileName = null) {
    if (empty($originalUrl)) {
        return null;
    }

    $parsed = parse_url($originalUrl);
    if (!isset($parsed['host'], $parsed['path'])) {
        error_log("Error: URL inválida en core_ObtenerUrlDescargaS3DesdeUrl() -> " . $originalUrl);
        return null;
    }

    $bucketParts = explode('.', $parsed['host']);
    $bucket = $bucketParts[0] ?? '';
    $key    = ltrim($parsed['path'], '/');

    if (empty($bucket) || empty($key)) {
        error_log("Error: no se pudo obtener bucket o key en core_ObtenerUrlDescargaS3DesdeUrl()");
        return null;
    }

    $s3 = AWSConfig::getS3Client();

    try {
        $s3->headObject([
            'Bucket' => $bucket,
            'Key'    => $key,
        ]);

        $fileName = $customFileName ?: basename($key);

        $cmd = $s3->getCommand('GetObject', [
            'Bucket'                     => $bucket,
            'Key'                        => $key,
            'ResponseContentDisposition' => 'attachment; filename="' . $fileName . '"',
            'ResponseContentType'        => 'application/octet-stream'
        ]);

        $request = $s3->createPresignedRequest($cmd, "+" . intval($expiracionSegundos) . " seconds");
        return (string) $request->getUri();

    } catch (AwsException $e) {
        if ($e->getAwsErrorCode() === 'NotFound') {
            error_log("Error: Objeto no encontrado en S3 -> " . $key);
            return null;
        }

        error_log("Error generando URL segura de descarga: " . $e->getAwsErrorMessage());
        return null;
    }
}


function core_DescargarContenidoS3DesdeUrl($originalUrl) {
    if (empty($originalUrl)) {
        return false;
    }

    $parsed = parse_url($originalUrl);
    if (!isset($parsed['host'], $parsed['path'])) {
        error_log("Error: URL inválida en core_DescargarContenidoS3DesdeUrl() -> " . $originalUrl);
        return false;
    }

    $bucketParts = explode('.', $parsed['host']);
    $bucket = $bucketParts[0] ?? '';
    $key    = ltrim($parsed['path'], '/');

    if (empty($bucket) || empty($key)) {
        error_log("Error: no se pudo obtener bucket o key");
        return false;
    }

    $s3 = AWSConfig::getS3Client();

    try {
        $result = $s3->getObject([
            'Bucket' => $bucket,
            'Key'    => $key
        ]);

        return $result['Body'];

    } catch (AwsException $e) {
        error_log("Error descargando archivo de S3: " . $e->getAwsErrorMessage());
        return false;
    }
}