<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Proxy\Response;

/**
 * @api
 */
final readonly class ProxyResult
{
    public function __construct(
        public array $data = [],
    ) {}
}
