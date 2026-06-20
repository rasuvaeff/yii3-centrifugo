<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Tests;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Rasuvaeff\Yii3Centrifugo\CentrifugoClient;
use Rasuvaeff\Yii3Centrifugo\Token\ConnectionTokenIssuer;
use Rasuvaeff\Yii3Centrifugo\Token\SubscriptionTokenIssuer;
use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Test;

#[Test]
#[CoversNothing]
final class ConfigWiringTest
{
    public function clientCanBeInstantiatedFromParams(): void
    {
        $factory = new Psr17Factory();
        $httpClient = new class implements ClientInterface {
            #[\Override]
            public function sendRequest(RequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                throw new \LogicException('Not called in this test');
            }
        };

        $params = require __DIR__ . '/../config/params.php';

        $client = new CentrifugoClient(
            httpClient: $httpClient,
            requestFactory: $factory,
            streamFactory: $factory,
            apiUrl: $params['centrifugo']['api_url'],
            apiKey: $params['centrifugo']['api_key'],
        );

        Assert::instanceOf($client, CentrifugoClient::class);
    }

    public function connectionTokenIssuerCanBeInstantiated(): void
    {
        $jwtConfig = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText('at-least-32-chars-secret-for-test'),
        );
        $params = require __DIR__ . '/../config/params.php';

        $issuer = new ConnectionTokenIssuer(
            jwtConfig: $jwtConfig,
            defaultTtl: $params['centrifugo']['token_ttl'],
        );

        Assert::instanceOf($issuer, ConnectionTokenIssuer::class);
    }

    public function subscriptionTokenIssuerCanBeInstantiated(): void
    {
        $jwtConfig = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText('at-least-32-chars-secret-for-test'),
        );
        $params = require __DIR__ . '/../config/params.php';

        $issuer = new SubscriptionTokenIssuer(
            jwtConfig: $jwtConfig,
            defaultTtl: $params['centrifugo']['token_ttl'],
        );

        Assert::instanceOf($issuer, SubscriptionTokenIssuer::class);
    }

    public function diKeysDoNotOverlapHandlerInterfaces(): void
    {
        $di = require __DIR__ . '/../config/di.php';
        $handlerInterfaces = [
            \Rasuvaeff\Yii3Centrifugo\Proxy\Handler\ConnectProxyHandler::class,
            \Rasuvaeff\Yii3Centrifugo\Proxy\Handler\RefreshProxyHandler::class,
            \Rasuvaeff\Yii3Centrifugo\Proxy\Handler\SubscribeProxyHandler::class,
            \Rasuvaeff\Yii3Centrifugo\Proxy\Handler\PublishProxyHandler::class,
            \Rasuvaeff\Yii3Centrifugo\Proxy\Handler\SubRefreshProxyHandler::class,
            \Rasuvaeff\Yii3Centrifugo\Proxy\Handler\RpcProxyHandler::class,
        ];

        foreach ($handlerInterfaces as $interface) {
            Assert::array($di)->doesNotHaveKeys($interface);
        }
    }
}
