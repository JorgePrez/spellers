<?php

// aws-php-sdk/aws_config.php
require_once __DIR__ . '/vendor/autoload.php';

use Aws\S3\S3Client;

class AWSConfig {
    private static $s3Client = null;
    private static $env = null;

    // Cargar el archivo .env solo una vez
    private static function loadEnv() {
        if (self::$env === null) {
            $envFile = __DIR__ . '/.env';

            //

            //Este es el .env que esta fuera de html
          //  $envFile = '/home/desait/.env';


            if (file_exists($envFile)) {
               // error_log(" Archivo .env encontrado en: " . $envFile);
                self::$env = parse_ini_file($envFile);
            } else {
                error_log(" Archivo .env NO encontrado en: " . $envFile);
                 //AH01071:Got error 'PHP message:  Archivo .env encontrado en: /DATA/var/www/html/test3/MiU/core/aws-php-sdk/.env\n'
                self::$env = [];
            }
        }
    }

    public static function getS3Client() {
        self::loadEnv();

        if (self::$s3Client === null) {
            self::$s3Client = new S3Client([
                'version'     => 'latest',
                'region'      => self::$env['AWS_REGION'] ?? '',
                'credentials' => [
                    'key'    => self::$env['AWS_ACCESS_KEY_ID'] ?? '',
                    'secret' => self::$env['AWS_SECRET_ACCESS_KEY'] ?? '',
                ],
                'suppress_php_deprecation_warning' => true,
            ]);
        }
        return self::$s3Client;
    }

    public static function getBucketName() {
        self::loadEnv();
        return self::$env['AWS_BUCKET_NAME'] ?? '';
    }

    public static function getRegion() {
        self::loadEnv();
        return self::$env['AWS_REGION'] ?? '';
    }

    public static function getDocumentosBasePath() {
        self::loadEnv();
        return rtrim(self::$env['AWS_DOCUMENTOS_BASE_PATH'] ?? '', '/') . '/';
    }

    
    // Métodos de acceso a la KB y webhook
    public static function getKnowledgeBaseId() {
        return self::$env['AWS_KNOWLEDGE_BASE_ID'] ?? '';
    }

    public static function getDataSourceId() {
        return self::$env['AWS_DATA_SOURCE_ID'] ?? '';
    }

    public static function getBucketRaiz() {
        return self::$env['AWS_BUCKET_RAIZ'] ?? '';
    }

}
