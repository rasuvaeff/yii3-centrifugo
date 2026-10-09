<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Tests\Internal;

use Psr\Container\ContainerInterface;
use Psr\Http\Client\ClientInterface;
use Rasuvaeff\PropertyTesting\Classify;
use Rasuvaeff\PropertyTesting\Gen;
use Rasuvaeff\PropertyTesting\Property;
use Rasuvaeff\Understudy\Understudy;
use Rasuvaeff\Yii3Centrifugo\Internal\Params;
use Rasuvaeff\Yii3Centrifugo\InvalidConfigException;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

use function Rasuvaeff\Understudy\when;

#[Test]
#[Covers(Params::class)]
#[Covers(InvalidConfigException::class)]
final class ParamsTest
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function validUrls(): iterable
    {
        yield 'http' => ['http://centrifugo:8000'];
        yield 'https with path' => ['https://rt.example.com/centrifugo'];
        yield 'upper-case scheme' => ['HTTPS://rt.example.com'];
    }

    #[DataProvider('validUrls')]
    public function httpUrlsAreAccepted(string $url): void
    {
        Assert::same(Params::apiUrl($this->params(api_url: $url)), $url);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidUrls(): iterable
    {
        yield 'missing' => [null];
        yield 'empty' => [''];
        yield 'not a url' => ['centrifugo:8000'];
        yield 'host only' => ['localhost'];
        yield 'websocket scheme' => ['ws://centrifugo:8000'];
        yield 'scheme without host' => ['http://'];
        yield 'scheme and path only' => ['https:/centrifugo'];
        yield 'malformed' => ['http://:80'];
        yield 'not a string' => [8000];
    }

    #[DataProvider('invalidUrls')]
    public function nonHttpUrlsAreRejected(mixed $url): void
    {
        Expect::exception(InvalidConfigException::class)
            ->withMessage("params['rasuvaeff/yii3-centrifugo']['api_url'] must be an http(s) URL");

        Params::apiUrl($this->params(api_url: $url));
    }

    public function missingSectionIsReportedByKey(): void
    {
        Expect::exception(InvalidConfigException::class)
            ->withMessage("params['rasuvaeff/yii3-centrifugo']['api_url'] must be an http(s) URL");

        Params::apiUrl([]);
    }

    public function nonArraySectionIsReportedByKey(): void
    {
        Expect::exception(InvalidConfigException::class)
            ->withMessage("params['rasuvaeff/yii3-centrifugo']['token_ttl'] must be a positive integer");

        Params::tokenTtl(['rasuvaeff/yii3-centrifugo' => 'oops']);
    }

    public function emptyApiKeyIsAllowedForApiInsecure(): void
    {
        Assert::same(Params::apiKey($this->params(api_key: '')), '');
        Assert::same(Params::apiKey($this->params(api_key: null)), '');
        Assert::same(Params::apiKey($this->params(api_key: 'k')), 'k');
    }

    public function nonStringApiKeyIsRejected(): void
    {
        Expect::exception(InvalidConfigException::class)
            ->withMessage("params['rasuvaeff/yii3-centrifugo']['api_key'] must be a string");

        Params::apiKey($this->params(api_key: 123));
    }

    public function jwtConfigurationSignsWithTheSecret(): void
    {
        $config = Params::jwtConfiguration($this->params(token_hmac_secret: str_repeat('s', 32)));

        Assert::same($config->signingKey()->contents(), str_repeat('s', 32));
        Assert::same($config->signer()->algorithmId(), 'HS256');
    }

    public function nonStringSecretIsRejected(): void
    {
        Expect::exception(InvalidConfigException::class)
            ->withMessage("params['rasuvaeff/yii3-centrifugo']['token_hmac_secret'] must be a string of at least 32 bytes");

        Params::jwtConfiguration($this->params(token_hmac_secret: null));
    }

    /**
     * The 32-byte floor is on bytes, not characters, and the message never
     * echoes the secret.
     */
    #[Property(runs: 300)]
    public function secretIsAcceptedExactlyFrom32Bytes(string $secret): void
    {
        $long = strlen($secret) >= 32;
        Classify::cover($long, 'accepted', 20.0);
        Classify::cover(!$long, 'rejected', 20.0);

        try {
            Params::jwtConfiguration($this->params(token_hmac_secret: $secret));
            Assert::true($long);
        } catch (InvalidConfigException $e) {
            Assert::false($long);
            Assert::same($e->getMessage(), "params['rasuvaeff/yii3-centrifugo']['token_hmac_secret'] must be a string of at least 32 bytes");
        }
    }

    /**
     * @return array<string, \Rasuvaeff\PropertyTesting\ArbitraryInterface>
     */
    public static function secretIsAcceptedExactlyFrom32BytesGenerators(): array
    {
        return ['secret' => Gen::stringOf(minLength: 0, maxLength: 48)];
    }

    #[Property(runs: 300)]
    public function ttlIsAcceptedExactlyWhenPositive(int $ttl): void
    {
        Classify::cover($ttl > 0, 'positive', 20.0);
        Classify::cover($ttl <= 0, 'not positive', 20.0);

        try {
            Assert::same(Params::tokenTtl($this->params(token_ttl: $ttl)), $ttl);
            Assert::true($ttl > 0);
        } catch (InvalidConfigException $e) {
            Assert::true($ttl <= 0);
            Assert::same($e->getMessage(), "params['rasuvaeff/yii3-centrifugo']['token_ttl'] must be a positive integer");
        }
    }

    /**
     * @return array<string, \Rasuvaeff\PropertyTesting\ArbitraryInterface>
     */
    public static function ttlIsAcceptedExactlyWhenPositiveGenerators(): array
    {
        return ['ttl' => Gen::intBetween(-5, 5)];
    }

    public function nonIntegerTtlIsRejected(): void
    {
        Expect::exception(InvalidConfigException::class);

        Params::tokenTtl($this->params(token_ttl: '3600'));
    }

    public function httpClientDefaultsToTheApplicationClient(): void
    {
        $client = Understudy::for(ClientInterface::class);
        $container = Understudy::for(ContainerInterface::class);
        when(fn() => $container->get(ClientInterface::class))->returns($client);

        Assert::same(Params::httpClient($this->params(), $container), $client);
    }

    public function httpClientIsTakenFromTheConfiguredId(): void
    {
        $client = Understudy::for(ClientInterface::class);
        $container = Understudy::for(ContainerInterface::class);
        when(fn() => $container->get('centrifugo.http'))->returns($client);

        Assert::same(Params::httpClient($this->params(http_client: 'centrifugo.http'), $container), $client);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidHttpClientIds(): iterable
    {
        yield 'empty' => [''];
        yield 'not a string' => [42];
    }

    #[DataProvider('invalidHttpClientIds')]
    public function invalidHttpClientIdIsRejected(mixed $id): void
    {
        Expect::exception(InvalidConfigException::class)
            ->withMessage("params['rasuvaeff/yii3-centrifugo']['http_client'] must be a container id");

        Params::httpClient($this->params(http_client: $id), Understudy::strict(Understudy::for(ContainerInterface::class)));
    }

    public function httpClientIdMustResolveToAPsr18Client(): void
    {
        $container = Understudy::for(ContainerInterface::class);
        when(fn() => $container->get('logger'))->returns(new \stdClass());

        Expect::exception(InvalidConfigException::class)->withMessage(
            "params['rasuvaeff/yii3-centrifugo']['http_client'] must name a Psr\\Http\\Client\\ClientInterface service, \"logger\" resolves to stdClass",
        );

        Params::httpClient($this->params(http_client: 'logger'), $container);
    }

    public function legacyKeyWinsOverTheNewKeyPerValue(): void
    {
        $params = [
            'rasuvaeff/yii3-centrifugo' => ['api_url' => 'http://new.test', 'token_ttl' => 60],
            'centrifugo' => ['api_url' => 'http://legacy.test'],
        ];

        Assert::same(Params::apiUrl($params), 'http://legacy.test');
        Assert::same(Params::tokenTtl($params), 60);
    }

    public function legacyKeyAloneIsRead(): void
    {
        Assert::same(Params::apiKey(['centrifugo' => ['api_key' => 'k']]), 'k');
    }

    public function errorsNameTheKeyTheValueCameFrom(): void
    {
        Expect::exception(InvalidConfigException::class)
            ->withMessage("params['centrifugo']['token_ttl'] must be a positive integer");

        Params::tokenTtl(['rasuvaeff/yii3-centrifugo' => ['token_ttl' => 60], 'centrifugo' => ['token_ttl' => 0]]);
    }

    public function nonArrayLegacySectionIsIgnored(): void
    {
        Assert::same(Params::tokenTtl(['rasuvaeff/yii3-centrifugo' => ['token_ttl' => 60], 'centrifugo' => 'oops']), 60);
    }

    public function proxySecretIsRequired(): void
    {
        Assert::same(Params::proxySecret($this->params(proxy_secret: 's')), 's');

        Expect::exception(InvalidConfigException::class)
            ->withMessage("params['rasuvaeff/yii3-centrifugo']['proxy_secret'] must be a non-empty string");

        Params::proxySecret($this->params(proxy_secret: ''));
    }

    public function nonStringProxySecretIsRejected(): void
    {
        Expect::exception(InvalidConfigException::class);

        Params::proxySecret($this->params(proxy_secret: 1));
    }

    public function proxySecretHeaderDefaultsToTheMiddlewareDefault(): void
    {
        Assert::same(Params::proxySecretHeader($this->params()), 'X-Centrifugo-Proxy-Secret');
        Assert::same(Params::proxySecretHeader($this->params(proxy_secret_header: 'X-S')), 'X-S');
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidHeaders(): iterable
    {
        yield 'empty' => [''];
        yield 'not a string' => [1];
    }

    #[DataProvider('invalidHeaders')]
    public function invalidProxySecretHeaderIsRejected(mixed $header): void
    {
        Expect::exception(InvalidConfigException::class)
            ->withMessage("params['rasuvaeff/yii3-centrifugo']['proxy_secret_header'] must be a non-empty string");

        Params::proxySecretHeader($this->params(proxy_secret_header: $header));
    }

    /**
     * @return array<string, mixed>
     */
    private function params(mixed ...$values): array
    {
        return ['rasuvaeff/yii3-centrifugo' => $values];
    }
}
