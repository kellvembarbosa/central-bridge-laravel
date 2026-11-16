<?php

use Applu\CentralBridge\CentralBridge;

return [

    /*
    | Liga as rotas da Bridge. Desligada, toda rota responde 404 `not_found`.
    */
    'enabled' => (bool) env('CENTRAL_BRIDGE_ENABLED', false),

    /*
    | Token do produto, gerado na Central (tela do produto -> "Gerar token").
    | Assina as requisições da Central para cá e do cliente de faturação para lá.
    */
    'token' => env('CENTRAL_BRIDGE_TOKEN'),

    /*
    | Slug do produto na Central. O /health devolve este valor.
    */
    'product' => env('CENTRAL_BRIDGE_PRODUCT'),

    /*
    | Versão anunciada no /health.
    */
    'version' => env('CENTRAL_BRIDGE_VERSION', CentralBridge::VERSION),

    /*
    | Prefixo das rotas. O contrato fixa `central-api/v1`.
    */
    'prefix' => 'central-api/v1',

    /*
    | Tolerância de relógio, em segundos.
    */
    'max_skew_seconds' => 300,

    /*
    | IPs (ou faixas CIDR) da Central. Vazio = qualquer IP.
    */
    'allowed_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CENTRAL_BRIDGE_ALLOWED_IPS', ''))
    ))),

    /*
    | Por quanto tempo uma resposta idempotente fica guardada.
    */
    'idempotency_ttl_hours' => 24,

    /*
    | Handlers do produto: contrato => classe. Contrato sem handler responde
    | 501 `capability_unsupported` e não aparece em `capabilities` no /health.
    |
    | 'handlers' => [
    |     \Applu\CentralBridge\Contracts\ProvidesUsers::class => \App\Central\Users::class,
    | ],
    */
    'handlers' => [],

    /*
    | URL base da Central, usada só pelo cliente de faturação (CentralClient).
    */
    'central_url' => env('CENTRAL_URL'),

];
