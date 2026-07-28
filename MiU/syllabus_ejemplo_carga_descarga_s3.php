<?php
//require_once __DIR__ . '/s3_carga_descarga_funciones.php';

//require_once __DIR__ . '/core/aws-php-sdk/aws_config.php';

require_once __DIR__ . "/core/aws-php-sdk/s3_carga_descarga_funciones.php";




$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        $mensaje = "Error al subir archivo";
    } else {


      //compras syllabus-compras
      //produccion syllabus-produccion
        $bucket = 'syllabus-compras';

        //ejemplo de ruta con subcarpeta'pruebas/a30350/53453'

        //Idea la ruta deberia ser algo tipo
        // curso_impartido/cronograma
        // curso_impartido/programa_generado 
   
        $urlOriginal = core_SubirArchivoS3(
            $bucket,
            $_FILES['archivo']['name'],
            $_FILES['archivo']['tmp_name'],
            'syllabus/1133325'
        );
/*
        $urlOriginal = core_SubirArchivoS3(
            $bucket,
            $_FILES['archivo']['name'],
            $_FILES['archivo']['tmp_name'],
            '15950585-2026-0008'
        );
*/        
        
        if ($urlOriginal !== false) {

            //  URL para ver
            $urlVer = core_ObtenerUrlVerS3DesdeUrl($urlOriginal);

            //  URL para descargar
            //Puede recibir
    
            $urlDescargar = core_ObtenerUrlDescargaS3DesdeUrl(
                $urlOriginal
            );

            $mensaje = "Archivo subido correctamente:<br><br>";

            $mensaje .= "URL original (privada):<br>";
            $mensaje .= "<small>{$urlOriginal}</small><br><br>";

            $mensaje .= "<a href='{$urlVer}' target='_blank'>
                            <button> Ver archivo</button>
                         </a>";

            $mensaje .= "<br><br>";

            $mensaje .= "<a href='{$urlDescargar}'>
                            <button> Descargar archivo</button>
                         </a>";

        } else {
            $mensaje = "Error al subir a S3";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Test S3 Upload</title>
</head>
<body>

<h2>Subir archivo a S3</h2>

<form method="POST" enctype="multipart/form-data">
    <input type="file" name="archivo" required>
    <br><br>
    <button type="submit">Subir</button>
</form>

<br>

<div>
    <?php echo $mensaje; ?>
</div>

</body>
</html>