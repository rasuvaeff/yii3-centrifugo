<?php

declare(strict_types=1);

use Psr\Http\Client\ClientInterface;

return [
    // the legacy top-level 'centrifugo' key is still read as a fallback
    // (deprecated, removed in 2.0)
    'rasuvaeff/yii3-centrifugo' => [
        'api_url' => 'http://localhost:8000',
        'api_key' => '',
        'token_hmac_secret' => '',
        'token_ttl' => 3600,
        // container id of the PSR-18 client for the server API; point it at a
        // dedicated client to bound publish time independently of the app's
        'http_client' => ClientInterface::class,
        // shared secret Centrifugo sends with proxy requests (http.static_headers);
        // ProxySecretMiddleware refuses to build while it is empty
        'proxy_secret' => '',
        'proxy_secret_header' => 'X-Centrifugo-Proxy-Secret',
    ],
];
