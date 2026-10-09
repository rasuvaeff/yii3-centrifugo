<?php

declare(strict_types=1);

use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Rasuvaeff\Yii3Centrifugo\CentrifugoClient;
use Rasuvaeff\Yii3Centrifugo\CentrifugoClientInterface;
use Rasuvaeff\Yii3Centrifugo\Internal\Params;
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
use Rasuvaeff\Yii3Centrifugo\Proxy\ProxySecretMiddleware;
use Rasuvaeff\Yii3Centrifugo\Token\ConnectionTokenIssuer;
use Rasuvaeff\Yii3Centrifugo\Token\SubscriptionTokenIssuer;

return [
    CentrifugoClient::class => static fn(
        ContainerInterface $container,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
    ): CentrifugoClient => new CentrifugoClient(
        httpClient: Params::httpClient($params, $container),
        requestFactory: $requestFactory,
        streamFactory: $streamFactory,
        apiUrl: Params::apiUrl($params),
        apiKey: Params::apiKey($params),
    ),

    // alias: the interface and the class resolve to the same shared instance
    CentrifugoClientInterface::class => CentrifugoClient::class,

    ConnectionTokenIssuer::class => static fn(ContainerInterface $container): ConnectionTokenIssuer => new ConnectionTokenIssuer(
        jwtConfig: Params::jwtConfiguration($params),
        defaultTtl: Params::tokenTtl($params),
        // the application's PSR-20 clock when it binds one, else the system clock
        clock: $container->has(ClockInterface::class) ? $container->get(ClockInterface::class) : null,
    ),

    SubscriptionTokenIssuer::class => static fn(ContainerInterface $container): SubscriptionTokenIssuer => new SubscriptionTokenIssuer(
        jwtConfig: Params::jwtConfiguration($params),
        defaultTtl: Params::tokenTtl($params),
        // the application's PSR-20 clock when it binds one, else the system clock
        clock: $container->has(ClockInterface::class) ? $container->get(ClockInterface::class) : null,
    ),

    // resolving it without params `proxy_secret` fails: the middleware is fail-closed
    ProxySecretMiddleware::class => static fn(ResponseFactoryInterface $responseFactory): ProxySecretMiddleware => new ProxySecretMiddleware(
        responseFactory: $responseFactory,
        secret: Params::proxySecret($params),
        header: Params::proxySecretHeader($params),
    ),

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
