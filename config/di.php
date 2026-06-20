<?php

declare(strict_types=1);

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Rasuvaeff\Yii3Centrifugo\CentrifugoClient;
use Rasuvaeff\Yii3Centrifugo\Proxy\Action\ConnectAction;
use Rasuvaeff\Yii3Centrifugo\Proxy\Action\PublishAction;
use Rasuvaeff\Yii3Centrifugo\Proxy\Action\RefreshAction;
use Rasuvaeff\Yii3Centrifugo\Proxy\Action\RpcAction;
use Rasuvaeff\Yii3Centrifugo\Proxy\Action\SubRefreshAction;
use Rasuvaeff\Yii3Centrifugo\Proxy\Action\SubscribeAction;
use Rasuvaeff\Yii3Centrifugo\Proxy\Handler\ConnectProxyHandler;
use Rasuvaeff\Yii3Centrifugo\Proxy\Handler\PublishProxyHandler;
use Rasuvaeff\Yii3Centrifugo\Proxy\Handler\RefreshProxyHandler;
use Rasuvaeff\Yii3Centrifugo\Proxy\Handler\RpcProxyHandler;
use Rasuvaeff\Yii3Centrifugo\Proxy\Handler\SubRefreshProxyHandler;
use Rasuvaeff\Yii3Centrifugo\Proxy\Handler\SubscribeProxyHandler;
use Rasuvaeff\Yii3Centrifugo\Proxy\Internal\ProxyResponseFactory;
use Rasuvaeff\Yii3Centrifugo\Token\ConnectionTokenIssuer;
use Rasuvaeff\Yii3Centrifugo\Token\SubscriptionTokenIssuer;

return [
    CentrifugoClient::class => static fn(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        array $params,
    ): CentrifugoClient => new CentrifugoClient(
        httpClient: $httpClient,
        requestFactory: $requestFactory,
        streamFactory: $streamFactory,
        apiUrl: $params['centrifugo']['api_url'],
        apiKey: $params['centrifugo']['api_key'],
    ),

    ConnectionTokenIssuer::class => static function (array $params): ConnectionTokenIssuer {
        $jwtConfig = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($params['centrifugo']['token_hmac_secret']),
        );

        return new ConnectionTokenIssuer(
            jwtConfig: $jwtConfig,
            defaultTtl: $params['centrifugo']['token_ttl'],
        );
    },

    SubscriptionTokenIssuer::class => static function (array $params): SubscriptionTokenIssuer {
        $jwtConfig = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($params['centrifugo']['token_hmac_secret']),
        );

        return new SubscriptionTokenIssuer(
            jwtConfig: $jwtConfig,
            defaultTtl: $params['centrifugo']['token_ttl'],
        );
    },

    ProxyResponseFactory::class => static fn(
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
    ): ProxyResponseFactory => new ProxyResponseFactory(
        responseFactory: $responseFactory,
        streamFactory: $streamFactory,
    ),

    ConnectAction::class => static fn(
        ConnectProxyHandler $handler,
        ProxyResponseFactory $responseFactory,
    ): ConnectAction => new ConnectAction(
        handler: $handler,
        responseFactory: $responseFactory,
    ),

    RefreshAction::class => static fn(
        RefreshProxyHandler $handler,
        ProxyResponseFactory $responseFactory,
    ): RefreshAction => new RefreshAction(
        handler: $handler,
        responseFactory: $responseFactory,
    ),

    SubscribeAction::class => static fn(
        SubscribeProxyHandler $handler,
        ProxyResponseFactory $responseFactory,
    ): SubscribeAction => new SubscribeAction(
        handler: $handler,
        responseFactory: $responseFactory,
    ),

    PublishAction::class => static fn(
        PublishProxyHandler $handler,
        ProxyResponseFactory $responseFactory,
    ): PublishAction => new PublishAction(
        handler: $handler,
        responseFactory: $responseFactory,
    ),

    SubRefreshAction::class => static fn(
        SubRefreshProxyHandler $handler,
        ProxyResponseFactory $responseFactory,
    ): SubRefreshAction => new SubRefreshAction(
        handler: $handler,
        responseFactory: $responseFactory,
    ),

    RpcAction::class => static fn(
        RpcProxyHandler $handler,
        ProxyResponseFactory $responseFactory,
    ): RpcAction => new RpcAction(
        handler: $handler,
        responseFactory: $responseFactory,
    ),
];
