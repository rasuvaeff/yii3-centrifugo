<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Rasuvaeff\Yii3Centrifugo\CentrifugoClient;
use Rasuvaeff\Yii3Centrifugo\Proxy\Internal\ProxyResponseFactory;
use Rasuvaeff\Yii3Centrifugo\Token\ConnectionTokenIssuer;
use Rasuvaeff\Yii3Centrifugo\Token\SubscriptionTokenIssuer;
use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Data\DataProvider;
use Testo\Test;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;

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

    public function clientDefinitionReadsApiSettingsFromParams(): void
    {
        $httpClient = new RecordingHttpClient();

        $client = $this->container($httpClient)->get(CentrifugoClient::class);
        $client->publish(channel: 'news', data: ['x' => 1]);

        $request = $httpClient->lastRequest;
        Assert::notNull($request);
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

    private function container(?ClientInterface $httpClient = null): Container
    {
        $params = [
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
                ClientInterface::class => $httpClient ?? new RecordingHttpClient(),
                RequestFactoryInterface::class => $psr17,
                StreamFactoryInterface::class => $psr17,
                ResponseFactoryInterface::class => $psr17,
            ]),
        );
    }
}

final class RecordingHttpClient implements ClientInterface
{
    public ?RequestInterface $lastRequest = null;

    #[\Override]
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->lastRequest = $request;

        return (new Psr17Factory())->createResponse()->withBody(
            (new Psr17Factory())->createStream('{"result":{}}'),
        );
    }
}
