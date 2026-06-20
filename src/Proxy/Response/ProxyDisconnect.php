<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Proxy\Response;

/**
 * @api
 */
final readonly class ProxyDisconnect
{
    public function __construct(
        public int $code,
        public string $reason,
    ) {
        if ($code < 4000 || $code > 4999) {
            throw new \InvalidArgumentException(
                'ProxyDisconnect code must be in range 4000-4999, got ' . $code,
            );
        }
    }
}
