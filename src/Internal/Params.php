<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Internal;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Psr\Container\ContainerInterface;
use Psr\Http\Client\ClientInterface;
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
    public const string KEY = 'rasuvaeff/yii3-centrifugo';

    // deprecated since 1.1, removed in 2.0: read only as a fallback
    public const string LEGACY_KEY = 'centrifugo';

    /** HS256 needs a key of at least 256 bits. */
    public const int MIN_SECRET_BYTES = 32;

    /**
     * @param array<array-key, mixed> $params
     */
    public static function apiUrl(array $params): string
    {
        $url = self::value($params, 'api_url');

        if (!is_string($url)) {
            throw new InvalidConfigException(self::path($params, 'api_url') . ' must be an http(s) URL');
        }

        $parts = parse_url($url);
        $parts = $parts === false ? [] : $parts;

        if (
            !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], strict: true)
            || ($parts['host'] ?? '') === ''
        ) {
            throw new InvalidConfigException(self::path($params, 'api_url') . ' must be an http(s) URL');
        }

        return $url;
    }

    /**
     * The PSR-18 client the server API client uses: the container entry named
     * by `http_client`, the application-wide `ClientInterface` by default.
     *
     * @param array<array-key, mixed> $params
     */
    public static function httpClient(array $params, ContainerInterface $container): ClientInterface
    {
        $id = self::value($params, 'http_client') ?? ClientInterface::class;

        if (!is_string($id) || $id === '') {
            throw new InvalidConfigException(self::path($params, 'http_client') . ' must be a container id');
        }

        $client = $container->get($id);

        if (!$client instanceof ClientInterface) {
            throw new InvalidConfigException(sprintf(
                '%s must name a %s service, "%s" resolves to %s',
                self::path($params, 'http_client'),
                ClientInterface::class,
                $id,
                get_debug_type($client),
            ));
        }

        return $client;
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
            throw new InvalidConfigException(self::path($params, 'api_key') . ' must be a string');
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
                self::path($params, 'token_hmac_secret'),
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
            throw new InvalidConfigException(self::path($params, 'token_ttl') . ' must be a positive integer');
        }

        return $ttl;
    }

    /**
     * The legacy `centrifugo` section, when the application still sets it,
     * overrides the package defaults that yiisoft/config always merges in
     * under the new key; otherwise the new key alone is read.
     *
     * @param array<array-key, mixed> $params
     */
    private static function value(array $params, string $name): mixed
    {
        $legacy = self::section($params, self::LEGACY_KEY);

        return array_key_exists($name, $legacy) ? $legacy[$name] : self::section($params, self::KEY)[$name] ?? null;
    }

    /**
     * @param array<array-key, mixed> $params
     */
    private static function path(array $params, string $name): string
    {
        $key = array_key_exists($name, self::section($params, self::LEGACY_KEY)) ? self::LEGACY_KEY : self::KEY;

        return sprintf("params['%s']['%s']", $key, $name);
    }

    /**
     * @param array<array-key, mixed> $params
     *
     * @return array<array-key, mixed>
     */
    private static function section(array $params, string $key): array
    {
        if (!isset($params[$key]) || !is_array($params[$key])) {
            return [];
        }

        return $params[$key];
    }
}
