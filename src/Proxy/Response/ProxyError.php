<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Proxy\Response;

/**
 * @api
 */
final readonly class ProxyError
{
    public function __construct(
        public int $code,
        public string $message,
    ) {
        if ($code < 400 || $code > 1999) {
            throw new \InvalidArgumentException(
                'ProxyError code must be in range 400-1999, got ' . $code,
            );
        }
    }
}
