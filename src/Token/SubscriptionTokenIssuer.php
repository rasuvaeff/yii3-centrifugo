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
final readonly class SubscriptionTokenIssuer
{
    public function __construct(
        private Configuration $jwtConfig,
        private int $defaultTtl = 3600,
        private ?ClockInterface $clock = null,
    ) {}

    public function issue(
        string $userId,
        string $channel,
        ?int $ttl = null,
        mixed $info = null,
    ): string {
        if ($userId === '') {
            throw new \InvalidArgumentException('userId must not be empty');
        }

        if ($channel === '') {
            throw new \InvalidArgumentException('channel must not be empty');
        }

        $now = $this->clock?->now() ?? new DateTimeImmutable();
        $expiresAt = $now->setTimestamp($now->getTimestamp() + ($ttl ?? $this->defaultTtl));

        $builder = $this->jwtConfig->builder()
            ->relatedTo($userId)
            ->expiresAt($expiresAt)
            ->withClaim('channel', $channel);

        if ($info !== null) {
            $builder = $builder->withClaim('info', $info);
        }

        return $builder
            ->getToken($this->jwtConfig->signer(), $this->jwtConfig->signingKey())
            ->toString();
    }
}
