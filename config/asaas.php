<?php

/**
 * Pagamento do info-produto (livro do sistema) pelo Asaas. Ver App\Support\AsaasCliente e CompraLivroController.
 * No painel do Asaas: Integrações > Webhooks, URL https://<site>/webhooks/asaas, com o mesmo token de ASAAS_WEBHOOK_TOKEN,
 * eventos de cobrança (PAYMENT_CONFIRMED e PAYMENT_RECEIVED).
 */
return [
    // sandbox | production
    'environment' => env('ASAAS_ENV', 'sandbox'),

    'api_key' => env('ASAAS_API_KEY'),

    // Token definido no webhook do painel do Asaas. Chega no header "asaas-access-token".
    'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),

    'urls' => [
        'sandbox'    => 'https://api-sandbox.asaas.com/v3',
        'production' => 'https://api.asaas.com/v3',
    ],
];
