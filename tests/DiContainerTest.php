<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Tests;

use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Rasuvaeff\Understudy\Arg;
use Rasuvaeff\Understudy\Understudy;
use Rasuvaeff\Yii3Centrifugo\CentrifugoClient;
use Rasuvaeff\Yii3Centrifugo\CentrifugoClientInterface;
use Rasuvaeff\Yii3Centrifugo\InvalidConfigException;
use Rasuvaeff\Yii3Centrifugo\Proxy\Internal\ProxyResponseFactory;
use Rasuvaeff\Yii3Centrifugo\Token\ConnectionTokenIssuer;
use Rasuvaeff\Yii3Centrifugo\Token\SubscriptionTokenIssuer;
use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Data\DataProvider;
use Testo\Test;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;

use function Rasuvaeff\Understudy\when;

/**
 * Builds the shipped config/di.php through a real yiisoft/di container.
 *
 * ConfigWiringTest only ever instantiated the classes by hand, so nothing
 * exercised the definitions themselves: a definition that the container cannot
 * resolve still passed CI and only blew up inside the consuming application.
 */
#[Test]
#[CoversNothing]
final class DiContainerTest
{
    /**
     * @return iterable<string, array{class-string}>
     */
    public static function definitions(): iterable
    {
        yield 'centrifugo client' => [CentrifugoClient::class];
        yield 'centrifugo client interface' => [CentrifugoClientInterface::class];
        yield 'connection token issuer' => [ConnectionTokenIssuer::class];
        yield 'subscription token issuer' => [SubscriptionTokenIssuer::class];
        yield 'proxy response factory' => [ProxyResponseFactory::class];
    }

    /**
     * @param class-string $id
     */
    #[DataProvider('definitions')]
    public function definitionIsResolvableByTheContainer(string $id): void
    {
        $service = $this->container()->get($id);

        Assert::instanceOf($service, $id);
    }

    public function interfaceResolvesToTheSameClientInstance(): void
    {
        $container = $this->container();

        Assert::same(
            $container->get(CentrifugoClientInterface::class),
            $container->get(CentrifugoClient::class),
        );
    }

    public function clientDefinitionReadsApiSettingsFromParams(): void
    {
        $requests = Arg::captor(RequestInterface::class);
        $httpClient = Understudy::for(ClientInterface::class);
        $psr17 = new Psr17Factory();

        when(fn() => $httpClient->sendRequest($requests->capture()))
            ->returns(
                $psr17->createResponse()
                    ->withBody($psr17->createStream('{"result":{}}')),
            );

        $client = $this->container($httpClient)->get(CentrifugoClient::class);
        $client->publish(channel: 'news', data: ['x' => 1]);

        $request = $requests->last();
        Assert::same((string) $request->getUri(), expected: 'https://centrifugo.test/api/publish');
        Assert::same($request->getHeaderLine('X-API-Key'), expected: 'params-api-key');
    }

    public function tokenIssuerDefinitionReadsSecretFromParams(): void
    {
        $issuer = $this->container()->get(ConnectionTokenIssuer::class);

        // Signed with the params secret, so it must verify against that same key.
        $jwt = $issuer->issue(userId: '42');

        Assert::same(substr_count($jwt, '.'), expected: 2);
    }

    /**
     * @return iterable<string, array{\Closure(Container): string}>
     */
    public static function issuers(): iterable
    {
        yield 'connection' => [static fn(Container $c): string => $c->get(ConnectionTokenIssuer::class)->issue(userId: '42', ttl: 60)];
        yield 'subscription' => [static fn(Container $c): string => $c->get(SubscriptionTokenIssuer::class)->issue(userId: '42', channel: 'c', ttl: 60)];
    }

    /**
     * @param \Closure(Container): string $issue
     */
    #[DataProvider('issuers')]
    public function issuerUsesTheContainerClockWhenBound(\Closure $issue): void
    {
        $clock = Understudy::for(ClockInterface::class);
        when(fn() => $clock->now())->returns(new \DateTimeImmutable('@1893456000'));

        $jwt = $issue($this->container(clock: $clock));

        Assert::same($this->expiry($jwt), 1893456060);
    }

    /**
     * @param \Closure(Container): string $issue
     */
    #[DataProvider('issuers')]
    public function issuerFallsBackToTheSystemClockWhenNoneIsBound(\Closure $issue): void
    {
        $before = time();

        $expiry = $this->expiry($issue($this->container()));

        Assert::true($expiry >= $before + 60 && $expiry <= time() + 60);
    }

    private function expiry(string $jwt): int
    {
        $token = (new Parser(new JoseEncoder()))->parse($jwt);
        Assert::instanceOf($token, UnencryptedToken::class);
        $exp = $token->claims()->get('exp');
        Assert::instanceOf($exp, \DateTimeImmutable::class);

        return $exp->getTimestamp();
    }

    public function clientUsesTheDedicatedHttpClientNamedInParams(): void
    {
        $requests = Arg::captor(RequestInterface::class);
        $dedicated = Understudy::for(ClientInterface::class);
        $psr17 = new Psr17Factory();
        when(fn() => $dedicated->sendRequest($requests->capture()))
            ->returns($psr17->createResponse()->withBody($psr17->createStream('{"result":{}}')));
        $global = Understudy::strict(Understudy::for(ClientInterface::class));

        $container = $this->container(
            httpClient: $global,
            params: ['centrifugo' => ['api_url' => 'https://centrifugo.test', 'http_client' => 'centrifugo.http']],
            extra: ['centrifugo.http' => $dedicated],
        );
        $container->get(CentrifugoClient::class)->info();

        Assert::count($requests->all(), 1);
    }

    public function clientResolvesWithThePackageDefaultParams(): void
    {
        // The shipped defaults have an empty token secret: an application that
        // only publishes must not be forced to configure tokens.
        $params = require __DIR__ . '/../config/params.php';

        $client = $this->container(params: $params)->get(CentrifugoClient::class);

        Assert::instanceOf($client, CentrifugoClient::class);
    }

    public function issuerWithAShortSecretFailsNamingTheParamsKey(): void
    {
        $params = require __DIR__ . '/../config/params.php';

        try {
            $this->container(params: $params)->get(ConnectionTokenIssuer::class);
            Assert::fail('Expected InvalidConfigException');
        } catch (\Throwable $e) {
            $config = $e instanceof InvalidConfigException ? $e : $e->getPrevious();
            Assert::instanceOf($config, InvalidConfigException::class);
            Assert::string($config->getMessage())->contains("params['centrifugo']['token_hmac_secret']");
        }
    }

    public function clientWithAnInvalidUrlFailsNamingTheParamsKey(): void
    {
        try {
            $this->container(params: ['centrifugo' => ['api_url' => 'centrifugo:8000']])->get(CentrifugoClient::class);
            Assert::fail('Expected InvalidConfigException');
        } catch (\Throwable $e) {
            $config = $e instanceof InvalidConfigException ? $e : $e->getPrevious();
            Assert::instanceOf($config, InvalidConfigException::class);
            Assert::string($config->getMessage())->contains("params['centrifugo']['api_url']");
        }
    }

    /**
     * @param array<array-key, mixed>|null $params
     */
    /**
     * @param array<string, mixed> $extra
     */
    private function container(
        ?ClientInterface $httpClient = null,
        ?ClockInterface $clock = null,
        ?array $params = null,
        array $extra = [],
    ): Container {
        $params ??= [
            'centrifugo' => [
                'api_url' => 'https://centrifugo.test',
                'api_key' => 'params-api-key',
                'token_hmac_secret' => 'at-least-32-chars-secret-for-test',
                'token_ttl' => 600,
            ],
        ];

        $psr17 = new Psr17Factory();

        /** @var array<string, mixed> $definitions */
        $definitions = (static fn(array $params): array => require __DIR__ . '/../config/di.php')($params);

        return new Container(
            ContainerConfig::create()->withDefinitions([
                ...$definitions,
                ClientInterface::class => $httpClient ?? Understudy::for(ClientInterface::class),
                RequestFactoryInterface::class => $psr17,
                StreamFactoryInterface::class => $psr17,
                ResponseFactoryInterface::class => $psr17,
                ...($clock instanceof ClockInterface ? [ClockInterface::class => $clock] : []),
                ...$extra,
            ]),
        );
    }
}
