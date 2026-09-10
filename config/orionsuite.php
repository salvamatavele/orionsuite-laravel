<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Módulo Pagar.co.mz (Gateway de Pagamentos)
    |--------------------------------------------------------------------------
    */
    'pagar' => [
        'enabled' => (bool) env('PAGAR_ENABLED', true),
        'base_url' => env('PAGAR_API_BASE_URL', 'https://api.pagar.co.mz'),
        'api_key' => env('PAGAR_API_KEY'),
        'signing_secret' => env('PAGAR_SIGNING_SECRET'),
        'webhook_secret' => env('PAGAR_WEBHOOK_SECRET'),
        
        // Limites dinâmicos (em MZN) - ajustáveis conforme políticas da Pagar
        'min_amount' => (float) env('PAGAR_MIN_AMOUNT', 20.0),
        'max_amount' => (float) env('PAGAR_MAX_AMOUNT', 40000.0),
        'currency' => env('PAGAR_CURRENCY', 'MZN'),

        'webhook' => [
            'enabled' => (bool) env('PAGAR_WEBHOOK_ENABLED', true),
            'path' => env('PAGAR_WEBHOOK_PATH', '/api/webhooks/pagar'),
        ],
        'disabled_message' => env('PAGAR_DISABLED_MESSAGE', 'Os pagamentos via Pagar encontram-se temporariamente suspensos para manutenção.'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Módulo Notifica.co.mz (Comunicação Multicanal)
    |--------------------------------------------------------------------------
    */
    'notifica' => [
        'enabled' => (bool) env('NOTIFICA_ENABLED', true),
        'base_url' => env('NOTIFICA_API_BASE_URL', 'https://api.notifica.co.mz/api/v1'),
        'api_token' => env('NOTIFICA_API_TOKEN'),
        'default_sms_sender' => env('NOTIFICA_DEFAULT_SMS_SENDER', 'ORIONCODE'),
        'disabled_message' => env('NOTIFICA_DISABLED_MESSAGE', 'O serviço de notificações encontra-se temporariamente indisponível.'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Módulo Orion OCR / KYC (Verificação de Identidade)
    |--------------------------------------------------------------------------
    */
    'kyc' => [
        'enabled' => (bool) env('ORION_KYC_ENABLED', true),
        'base_url' => env('ORION_KYC_BASE_URL', 'https://api.ocr.orioncodetech.com/v1'),
        'token' => env('ORION_KYC_TOKEN'),
        'environment' => env('ORION_KYC_ENVIRONMENT', 'test'), // 'test' ou 'production'
        'disabled_message' => env('ORION_KYC_DISABLED_MESSAGE', 'A verificação de identidade encontra-se temporariamente indisponível.'),
    ],
];
