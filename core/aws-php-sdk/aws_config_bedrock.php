<?php

require_once __DIR__ . '/vendor/autoload.php';

use Aws\BedrockRuntime\BedrockRuntimeClient;

class AWSConfigBedrock {
    private static $bedrockClient = null;
    private static $env = null;

    // Modelo fijo Haiku
    //private static $modelIdHaiku = 'global.anthropic.claude-haiku-4-5-20251001-v1:0';

    //private static $modelIdHaiku = 'arn:aws:bedrock:us-east-1:552102268375:application-inference-profile/m3hbkxy84qfp';

    //Esta ha funcionado perfecto
   private static $modelIdHaiku = 'arn:aws:bedrock:us-east-1:552102268375:application-inference-profile/zym8ef4k7anz';


    //  private static $modelIdHaiku = 'mistral.ministral-3-3b-instruct';



   


   

    // Cargar .env solo una vez
    private static function loadEnv() {
        if (self::$env === null) {
            $envFile = __DIR__ . '/.env';

            // Si lo quieres fuera de html:
            // $envFile = '/home/desait/.env';

            if (file_exists($envFile)) {
                self::$env = parse_ini_file($envFile);
            } else {
                error_log("Archivo .env NO encontrado en: " . $envFile);
                self::$env = [];
            }
        }
    }

    public static function getBedrockClient() {
        self::loadEnv();

        if (self::$bedrockClient === null) {
            self::$bedrockClient = new BedrockRuntimeClient([
                'version'     => 'latest',
                'region'      => self::$env['AWS_REGION'] ?? '',
                'credentials' => [
                    'key'    => self::$env['AWS_ACCESS_KEY_ID'] ?? '',
                    'secret' => self::$env['AWS_SECRET_ACCESS_KEY'] ?? '',
                ],
                'suppress_php_deprecation_warning' => true,
            ]);
        }

        return self::$bedrockClient;
    }

    public static function getRegion() {
        self::loadEnv();
        return self::$env['AWS_REGION'] ?? '';
    }




    public static function getModelIdHaiku() {
        return self::$modelIdHaiku;
    }
    /**
     * Normaliza texto a UTF-8 antes de json_encode hacia Bedrock.
     * Los PHP del syllabus suelen estar en Windows-1252; sin esta conversion,
     * JSON_INVALID_UTF8_SUBSTITUTE reemplaza acentos por U+FFFD.
     */
    private static function ensureUtf8($text) {
        $text = (string)$text;
        if ($text === '') {
            return $text;
        }
        if (function_exists('mb_check_encoding') && mb_check_encoding($text, 'UTF-8')) {
            return $text;
        }
        if (function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
            if ($converted !== false) {
                return $converted;
            }
        }
        if (function_exists('utf8_encode')) {
            return utf8_encode($text);
        }
        return $text;
    }

    private static function utf8ize($value) {
        if (is_string($value)) {
            return self::ensureUtf8($value);
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = self::utf8ize($v);
            }
            return $out;
        }
        return $value;
    }


    /**
     * Genera texto usando Claude Haiku
     *
     * @param string $prompt Instrucción principal
     * @param string $textoAnalizar Texto que se quiere analizar
     * @return string Texto generado por el modelo
     * @throws Exception
     */
    public static function llm_generate_haiku($prompt, $textoAnalizar) {
        $prompt = trim((string)$prompt);
        $textoAnalizar = trim((string)$textoAnalizar);

        if ($prompt === '') {
            throw new Exception('Debe enviar prompt');
        }

        if ($textoAnalizar === '') {
            throw new Exception('Debe enviar texto a analizar');
        }

        $client = self::getBedrockClient();

        $textoCompleto = $prompt . "\n\nTexto a analizar:\n" . $textoAnalizar;

        $payload = [
            'anthropic_version' => 'bedrock-2023-05-31',
            'max_tokens' => 500,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $textoCompleto
                        ]
                    ]
                ]
            ]
        ];
    

       /*
        $result = $client->invokeModel([
            'modelId'     => self::getModelIdHaiku(),
            'contentType' => 'application/json',
            'accept'      => 'application/json',
            'body'        => json_encode($payload)
        ]);
        */

        $result = $client->invokeModel([
            'modelId'     => self::getModelIdHaiku(),
            'contentType' => 'application/json',
            'accept'      => 'application/json',
            'body'        =>  json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)
        ]);

        $responseBody = json_decode($result['body']->getContents(), true);

        if (
            isset($responseBody['content']) &&
            is_array($responseBody['content']) &&
            isset($responseBody['content'][0]['text'])
        ) {
            return $responseBody['content'][0]['text'];
        }

        throw new Exception('No se pudo obtener texto válido desde Bedrock');
    }


    /**
             * Genera texto usando Claude Haiku (prompt completo)
             *
             * @param string $prompt Prompt completo ya armado
             * @param int $maxTokens Máximo de tokens
             * @param float $temperature Creatividad (0.0 - 1.0)
             * @return string Texto generado
             * @throws Exception
             */
            public static function llm_generate($prompt, $maxTokens = 500, $temperature = 0.3) {
                
                $prompt = trim((string)$prompt);

                if ($prompt === '') {
                    throw new Exception('Debe enviar prompt');
                }

                $client = self::getBedrockClient();

                $payload = [
                    'anthropic_version' => 'bedrock-2023-05-31',
                    'max_tokens' => (int)$maxTokens,
                    'temperature' => (float)$temperature,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => $prompt
                                ]
                            ]
                        ]
                    ]
                ];

                $jsonBody = json_encode(
                    $payload,
                    JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
                );

                if ($jsonBody === false) {
                    throw new Exception('Error al convertir payload a JSON: ' . json_last_error_msg());
                }

                $result = $client->invokeModel([
                    'modelId'     => self::getModelIdHaiku(),
                    'contentType' => 'application/json',
                    'accept'      => 'application/json',
                    'body'        => $jsonBody
                ]);

                $responseBody = json_decode($result['body']->getContents(), true);

                // Manejo robusto de respuesta (por si viene en varios bloques)
                if (
                    isset($responseBody['content']) &&
                    is_array($responseBody['content'])
                ) {
                    $textoRespuesta = '';

                    foreach ($responseBody['content'] as $bloque) {
                        if (
                            isset($bloque['type']) &&
                            $bloque['type'] === 'text' &&
                            isset($bloque['text'])
                        ) {
                            $textoRespuesta .= $bloque['text'];
                        }
                    }

                    if (trim($textoRespuesta) !== '') {
                        return trim($textoRespuesta);
                    }
                }

                throw new Exception('No se pudo obtener texto válido desde Bedrock');
            }





/**
 * Genera JSON estructurado usando Claude en Bedrock con InvokeModel
 *
 * @param string $prompt Prompt completo ya armado
 * @param array $schema JSON Schema como arreglo PHP
 * @param int $maxTokens Máximo de tokens
 * @param float $temperature Creatividad (0.0 - 1.0)
 * @return array JSON decodificado como arreglo asociativo
 * @throws Exception
 */
public static function llm_generate_json_simple($prompt, array $schema, $maxTokens = 500, $temperature = 0.0) {

    $prompt = trim((string)$prompt);

    if ($prompt === '') {
        throw new Exception('Debe enviar prompt');
    }

    if (empty($schema)) {
        throw new Exception('Debe enviar schema');
    }

    $client = self::getBedrockClient();

    $payload = [
        'anthropic_version' => 'bedrock-2023-05-31',
        'max_tokens' => (int)$maxTokens,
        'temperature' => (float)$temperature,
        'messages' => [
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $prompt
                    ]
                ]
            ]
        ],
        'output_config' => [
            'format' => [
                'type' => 'json_schema',
                'schema' => $schema
            ]
        ]
    ];

    $jsonBody = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    if ($jsonBody === false) {
        throw new Exception('Error al convertir payload a JSON: ' . json_last_error_msg());
    }

    $result = $client->invokeModel([
        'modelId'     => self::getModelIdHaiku(), // o el modelo Claude compatible que uses
        'contentType' => 'application/json',
        'accept'      => 'application/json',
        'body'        => $jsonBody
    ]);

    $rawResponse = $result['body']->getContents();

    $responseBody = json_decode($rawResponse, true);

    if (!is_array($responseBody)) {
        throw new Exception('La respuesta de Bedrock no es JSON válido');
    }

    // Claude suele devolver bloques en content[]
    if (
        isset($responseBody['content']) &&
        is_array($responseBody['content'])
    ) {
        $textoRespuesta = '';

        foreach ($responseBody['content'] as $bloque) {
            if (
                isset($bloque['type']) &&
                $bloque['type'] === 'text' &&
                isset($bloque['text'])
            ) {
                $textoRespuesta .= $bloque['text'];
            }
        }

        $textoRespuesta = trim($textoRespuesta);

        if ($textoRespuesta === '') {
            throw new Exception('Bedrock devolvió content[], pero sin texto');
        }

        $jsonResultado = json_decode($textoRespuesta, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('La salida del modelo no es JSON válido: ' . json_last_error_msg() . '. Respuesta: ' . $textoRespuesta);
        }

        return $jsonResultado;
    }

    throw new Exception('No se pudo obtener JSON válido desde Bedrock');
}



/**
 * Genera una respuesta JSON estructurada usando Claude en Bedrock con InvokeModel
 *
 * @param string $prompt Prompt completo ya armado
 * @param array $schema JSON Schema como arreglo PHP
 * @param int $maxTokens Máximo de tokens
 * @param float $temperature Creatividad (0.0 - 1.0)
 * @return array Respuesta JSON decodificada como arreglo asociativo
 * @throws Exception
 */
public static function llm_generate_json($prompt, array $schema, $maxTokens = 500, $temperature = 0.1) {

    $prompt = trim((string)$prompt);

    if ($prompt === '') {
        throw new Exception('Debe enviar prompt');
    }

    if (empty($schema)) {
        throw new Exception('Debe enviar schema');
    }

    $client = self::getBedrockClient();

    $payload = [
        'anthropic_version' => 'bedrock-2023-05-31',
        'max_tokens' => (int)$maxTokens,
        'temperature' => (float)$temperature,
        'messages' => [
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $prompt
                    ]
                ]
            ]
        ],
        'output_config' => [
            'format' => [
                'type' => 'json_schema',
                'schema' => $schema
            ]
        ]
    ];

    $jsonBody = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    if ($jsonBody === false) {
        throw new Exception('Error al convertir payload a JSON: ' . json_last_error_msg());
    }

    $result = $client->invokeModel([
        'modelId'     => self::getModelIdHaiku(),
        'contentType' => 'application/json',
        'accept'      => 'application/json',
        'body'        => $jsonBody
    ]);

       error_log("config");
   error_log($result);


    $responseBody = json_decode($result['body']->getContents(), true);

    if (!is_array($responseBody)) {
        throw new Exception('La respuesta de Bedrock no es JSON ');
    }

    if (isset($responseBody['content']) && is_array($responseBody['content'])) {
        $textoRespuesta = '';

        foreach ($responseBody['content'] as $bloque) {
            if (
                isset($bloque['type']) &&
                $bloque['type'] === 'text' &&
                isset($bloque['text'])
            ) {
                $textoRespuesta .= $bloque['text'];
            }
        }

        $textoRespuesta = trim($textoRespuesta);

        if ($textoRespuesta === '') {
            throw new Exception('No se pudo obtener texto correct desde Bedrock');
        }

        $jsonResultado = json_decode($textoRespuesta, true);

        if (!is_array($jsonResultado)) {
            throw new Exception('La salida del modelo no es JSON correcto: ' . $textoRespuesta);
        }

        return $jsonResultado;
    }

    throw new Exception('No se pudo obtener JSON correcto desde Bedrock');
}


/*
private static $modelIdFast = 'mistral.ministral-3-8b-instruct';

public static function getModelIdFast() {
    return self::$modelIdFast;
}*/

public static function llm_generate_json_mistral($prompt, array $schema, $maxTokens = 500, $temperature = 0.1) {
    
    $prompt = trim((string)$prompt);
    
    if ($prompt === '') {
        throw new Exception('Debe enviar prompt');
    }
    
    if (empty($schema)) {
        throw new Exception('Debe enviar schema');
    }
    
    $client = self::getBedrockClient();
    
    // Formato específico de Mistral
    $payload = [
        'messages' => [
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'max_tokens' => (int)$maxTokens,
        'temperature' => (float)$temperature,
        'response_format' => [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'resultado_evaluacion',
                'strict' => true,
                'schema' => $schema
            ]
        ]
    ];
    
    $jsonBody = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    
    if ($jsonBody === false) {
        throw new Exception('Error al convertir payload a JSON: ' . json_last_error_msg());
    }
    
    $result = $client->invokeModel([
        'modelId'     => 'mistral.voxtral-mini-3b-2507',//'mistral.voxtral-mini-3b-2507',//'mistral.ministral-3-8b-instruct',
        'contentType' => 'application/json',
        'accept'      => 'application/json',
        'body'        => $jsonBody
    ]);
    
    $responseBody = json_decode($result['body']->getContents(), true);
    
    if (!is_array($responseBody)) {
        throw new Exception('La respuesta de Bedrock no es JSON válido');
    }
    
    // Mistral devuelve en choices[0].message.content
    if (
        isset($responseBody['choices']) &&
        is_array($responseBody['choices']) &&
        isset($responseBody['choices'][0]['message']['content'])
    ) {
        $textoRespuesta = trim($responseBody['choices'][0]['message']['content']);
        
        if ($textoRespuesta === '') {
            throw new Exception('Bedrock devolvió respuesta vacía');
        }
        
        $jsonResultado = json_decode($textoRespuesta, true);
        
        if (!is_array($jsonResultado)) {
            throw new Exception('La salida del modelo no es JSON correcto: ' . $textoRespuesta);
        }
        
        return $jsonResultado;
    }
    
    throw new Exception('No se pudo obtener JSON correcto desde Bedrock');
}


public static function llm_generate_json_cached($systemPrompt, $userPrompt, array $schema, $maxTokens = 150, $temperature = 0.0) {
    
    $systemPrompt = self::ensureUtf8(trim((string)$systemPrompt));
    $userPrompt = self::ensureUtf8(trim((string)$userPrompt));
    $schema = self::utf8ize($schema);
    
    if ($systemPrompt === '') {
        throw new Exception('Debe enviar system prompt');
    }
    
    if ($userPrompt === '') {
        throw new Exception('Debe enviar user prompt');
    }
    
    if (empty($schema)) {
        throw new Exception('Debe enviar schema');
    }
    
    $client = self::getBedrockClient();
    
    $payload = [
        'anthropic_version' => 'bedrock-2023-05-31',
        'max_tokens' => (int)$maxTokens,
        'temperature' => (float)$temperature,
        'system' => [
            [
                'type' => 'text',
                'text' => $systemPrompt,
                'cache_control' => [
                            'type' => 'ephemeral',
                            'ttl' => '1h'
                        ]
            ]
        ],
        'messages' => [
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $userPrompt
                    ]
                ]
            ]
        ],
        'output_config' => [
            'format' => [
                'type' => 'json_schema',
                'schema' => $schema
            ]
        ]
    ];
    
    $jsonBody = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    
    if ($jsonBody === false) {
        throw new Exception('Error al convertir payload a JSON: ' . json_last_error_msg());
    }
    
    $result = $client->invokeModel([
        'modelId'     => self::getModelIdHaiku(),
        'contentType' => 'application/json',
        'accept'      => 'application/json',
        'body'        => $jsonBody
    ]);
    
    $responseBody = json_decode($result['body']->getContents(), true);

   // error_log($userPrompt);



    
    /*
    if (isset($responseBody['usage'])) {
    $u = $responseBody['usage'];
    error_log(sprintf(
        "Tokens - Input: %d | Cache read: %d | Cache creation: %d | Output: %d",
        $u['input_tokens'] ?? 0,
        $u['cache_read_input_tokens'] ?? 0,
        $u['cache_creation_input_tokens'] ?? 0,
        $u['output_tokens'] ?? 0
    ));
    }*/
    
    if (!is_array($responseBody)) {
        throw new Exception('La respuesta de Bedrock no es JSON válido');
    }



        
    //echo "\n\n=== CLAVES DISPONIBLES ===\n";
    //print_r(array_keys($responseBody));




    /*
        if (isset($responseBody['usage'])) {
        $usage = $responseBody['usage'];
        error_log("=== Token Usage ===");
        error_log("Input tokens: " . ($usage['input_tokens'] ?? 0));
        error_log("Cache creation tokens: " . ($usage['cache_creation_input_tokens'] ?? NA));
        error_log("Cache read tokens: " . ($usage['cache_read_input_tokens'] ?? NA));
        error_log("Output tokens: " . ($usage['output_tokens'] ?? 0));
        

    }*/
    
    if (
        isset($responseBody['content']) &&
        is_array($responseBody['content'])
    ) {
        $textoRespuesta = '';
        
        foreach ($responseBody['content'] as $bloque) {
            if (
                isset($bloque['type']) &&
                $bloque['type'] === 'text' &&
                isset($bloque['text'])
            ) {
                $textoRespuesta .= $bloque['text'];
            }
        }

       //error_log('RAW RESPONSE: ' . json_encode($responseBody, JSON_UNESCAPED_UNICODE));

       //Esto para debugear la respuesta completa
 /*      file_put_contents(
    __DIR__ . '/bedrock_response_debug.txt',
    date('Y-m-d H:i:s') . "\n" . 
    json_encode($responseBody, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n" .
    "TEXTO: " . $textoRespuesta . "\n\n",
    FILE_APPEND
);  */


        
        $textoRespuesta = trim($textoRespuesta);
        
        if ($textoRespuesta === '') {
            throw new Exception('Bedrock devolvió content[], pero sin texto');
        }

      // error_log($textoRespuesta);

        

        


        $jsonResultado = json_decode($textoRespuesta, true);
        
        if (!is_array($jsonResultado)) {

            throw new Exception('La salida del modelo no es JSON correcto: ' . $textoRespuesta);
        }


        
        
        return $jsonResultado;
    }
    
    throw new Exception('No se pudo obtener JSON correcto desde Bedrock');
}





}