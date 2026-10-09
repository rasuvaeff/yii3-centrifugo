<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Internal;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Rasuvaeff\Yii3Centrifugo\InvalidConfigException;

/**
 * Reads and validates the package params for the DI definitions. Each value
 * is checked only by the definition that uses it, so an application that
 * resolves only the client is not forced to configure a token secret.
 *
 * @internal
 */
final readonly class Params
{
    public const string KEY = 'centrifugo';

    /** HS256 needs a key of at least 256 bits. */
    public const int MIN_SECRET_BYTES = 32;

    /**
     * @param array<array-key, mixed> $params
     */
    public static function apiUrl(array $params): string
    {
        $url = self::value($params, 'api_url');

        if (!is_string($url)) {
            throw new InvalidConfigException(self::path('api_url') . ' must be an http(s) URL');
        }

        $parts = parse_url($url);
        $parts = $parts === false ? [] : $parts;

        if (
            !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], strict: true)
            || ($parts['host'] ?? '') === ''
        ) {
            throw new InvalidConfigException(self::path('api_url') . ' must be an http(s) URL');
        }

        return $url;
    }

    /**
     * An empty key is valid: Centrifugo may run with `api_insecure`.
     *
     * @param array<array-key, mixed> $params
     */
    public static function apiKey(array $params): string
    {
        $key = self::value($params, 'api_key') ?? '';

        if (!is_string($key)) {
            throw new InvalidConfigException(self::path('api_key') . ' must be a string');
        }

        return $key;
    }

    /**
     * @param array<array-key, mixed> $params
     */
    public static function jwtConfiguration(array $params): Configuration
    {
        $secret = self::value($params, 'token_hmac_secret');

        if (!is_string($secret) || strlen($secret) < self::MIN_SECRET_BYTES) {
            throw new InvalidConfigException(sprintf(
                '%s must be a string of at least %d bytes',
                self::path('token_hmac_secret'),
                self::MIN_SECRET_BYTES,
            ));
        }

        /** @var non-empty-string $secret checked above: at least 32 bytes */
        return Configuration::forSymmetricSigner(new Sha256(), InMemory::plainText($secret));
    }

    /**
     * @param array<array-key, mixed> $params
     *
     * @return positive-int
     */
    public static function tokenTtl(array $params): int
    {
        $ttl = self::value($params, 'token_ttl');

        if (!is_int($ttl) || $ttl < 1) {
            throw new InvalidConfigException(self::path('token_ttl') . ' must be a positive integer');
        }

        return $ttl;
    }

    /**
     * @param array<array-key, mixed> $params
     */
    private static function value(array $params, string $name): mixed
    {
        return is_array($params[self::KEY] ?? null) ? $params[self::KEY][$name] ?? null : null;
    }

    private static function path(string $name): string
    {
        return sprintf("params['%s']['%s']", self::KEY, $name);
    }
}
