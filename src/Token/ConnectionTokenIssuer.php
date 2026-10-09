<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Token;

use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Psr\Clock\ClockInterface;

/**
 * `exp` is whole seconds from the injected PSR-20 clock (the system clock
 * when none is given).
 *
 * @api
 */
final readonly class ConnectionTokenIssuer
{
    public function __construct(
        private Configuration $jwtConfig,
        private int $defaultTtl = 3600,
        private ?ClockInterface $clock = null,
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

        $now = $this->clock?->now() ?? new DateTimeImmutable();
        $expiresAt = $now->setTimestamp($now->getTimestamp() + ($ttl ?? $this->defaultTtl));

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
