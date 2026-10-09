<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Doctor;

/**
 * Result of one {@see CentrifugoDoctor} check. Details never contain the API
 * key or the HMAC secret.
 *
 * @api
 */
final readonly class CheckResult
{
    public function __construct(
        public string $name,
        public CheckCategory $category,
        public CheckStatus $status,
        public string $details,
    ) {}
}
