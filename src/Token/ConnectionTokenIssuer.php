<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Token;

use DateTimeImmutable;
use Lcobucci\JWT\Configuration;

/**
 * @api
 */
final readonly class ConnectionTokenIssuer
{
    public function __construct(
        private Configuration $jwtConfig,
        private int $defaultTtl = 3600,
    ) {}

    public function issue(
        string $userId,
        ?int $ttl = null,
        array $channels = [],
        mixed $info = null,
        mixed $meta = null,
    ): string {
        if ($userId === '') {
            throw new \InvalidArgumentException('userId must not be empty');
        }

        $expiresAt = new DateTimeImmutable('+' . ($ttl ?? $this->defaultTtl) . ' seconds');

        $builder = $this->jwtConfig->builder()
            ->relatedTo($userId)
            ->expiresAt($expiresAt);

        if ($channels !== []) {
            $builder = $builder->withClaim('channels', $channels);
        }

        if ($info !== null) {
            $builder = $builder->withClaim('info', $info);
        }

        if ($meta !== null) {
            $builder = $builder->withClaim('meta', $meta);
        }

        return $builder
            ->getToken($this->jwtConfig->signer(), $this->jwtConfig->signingKey())
            ->toString();
    }
}
