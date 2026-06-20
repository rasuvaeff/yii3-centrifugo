<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo;

/**
 * @api
 */
final readonly class BatchCommand
{
    public function __construct(
        public string $method,
        public array $params,
    ) {}
}
