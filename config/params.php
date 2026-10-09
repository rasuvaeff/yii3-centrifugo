<?php

declare(strict_types=1);

use Psr\Http\Client\ClientInterface;

return [
    'centrifugo' => [
        'api_url' => 'http://localhost:8000',
        'api_key' => '',
        'token_hmac_secret' => '',
        'token_ttl' => 3600,
        // container id of the PSR-18 client for the server API; point it at a
        // dedicated client to bound publish time independently of the app's
        'http_client' => ClientInterface::class,
    ],
];
