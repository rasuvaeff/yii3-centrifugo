<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Tests\Proxy\Action;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;
use Rasuvaeff\Understudy\Arg;
use Rasuvaeff\Understudy\Understudy;
use Rasuvaeff\Yii3Centrifugo\Proxy\Action\ConnectAction;
use Rasuvaeff\Yii3Centrifugo\Proxy\Handler\ConnectProxyHandler;
use Rasuvaeff\Yii3Centrifugo\Proxy\Internal\ProxyResponseFactory;
use Rasuvaeff\Yii3Centrifugo\Proxy\Request\ConnectRequest;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyDisconnect;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyError;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyResult;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

use function Rasuvaeff\Understudy\when;

#[Test]
#[Covers(ConnectAction::class)]
#[Covers(ProxyResponseFactory::class)]
#[Covers(ConnectRequest::class)]
#[Covers(ProxyResult::class)]
final class ConnectActionTest
{
    private Psr17Factory $factory;
    private ProxyResponseFactory $responseFactory;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->factory = new Psr17Factory();
        $this->responseFactory = new ProxyResponseFactory(
            responseFactory: $this->factory,
            streamFactory: $this->factory,
        );
    }

    public function returnsResultEnvelope(): void
    {
        $handler = Understudy::for(ConnectProxyHandler::class);
        when(fn() => $handler->handle(Arg::any()))->returns(new ProxyResult(data: ['user' => '42']));

        $action = new ConnectAction(handler: $handler, responseFactory: $this->responseFactory);
        $response = $action->handle($this->makeRequest(['client' => 'c1', 'transport' => 'websocket', 'protocol' => 'json', 'encoding' => 'json']));

        $body = json_decode((string) $response->getBody(), true);
        Assert::same($response->getStatusCode(), 200);
        Assert::same($body['result'], ['user' => '42']);
        Assert::array($body)->doesNotHaveKeys('error');
    }

    public function returnsErrorEnvelope(): void
    {
        $handler = Understudy::for(ConnectProxyHandler::class);
        when(fn() => $handler->handle(Arg::any()))->returns(new ProxyError(code: 403, message: 'permission denied'));

        $action = new ConnectAction(handler: $handler, responseFactory: $this->responseFactory);
        $response = $action->handle($this->makeRequest([]));

        $body = json_decode((string) $response->getBody(), true);
        Assert::same($body['error']['code'], 403);
        Assert::same($body['error']['message'], 'permission denied');
    }

    public function returnsDisconnectEnvelope(): void
    {
        $handler = Understudy::for(ConnectProxyHandler::class);
        when(fn() => $handler->handle(Arg::any()))->returns(new ProxyDisconnect(code: 4001, reason: 'unauthorized'));

        $action = new ConnectAction(handler: $handler, responseFactory: $this->responseFactory);
        $response = $action->handle($this->makeRequest([]));

        $body = json_decode((string) $response->getBody(), true);
        Assert::same($body['disconnect']['code'], 4001);
        Assert::same($body['disconnect']['reason'], 'unauthorized');
    }

    public function parsesConnectRequestFields(): void
    {
        $requests = Arg::captor(ConnectRequest::class);
        $handler = Understudy::for(ConnectProxyHandler::class);
        when(fn() => $handler->handle($requests->capture()))->returns(new ProxyResult());

        $action = new ConnectAction(handler: $handler, responseFactory: $this->responseFactory);
        $action->handle($this->makeRequest([
            'client' => 'c1',
            'transport' => 'websocket',
            'protocol' => 'json',
            'encoding' => 'json',
            'channels' => ['news'],
        ]));

        $request = $requests->last();
        Assert::same($request->client, 'c1');
        Assert::same($request->transport, 'websocket');
        Assert::same($request->channels, ['news']);
    }

    private function makeRequest(array $body): ServerRequestInterface
    {
        $json = json_encode($body, JSON_THROW_ON_ERROR);

        return (new ServerRequest('POST', '/centrifugo/connect'))
            ->withBody($this->factory->createStream($json));
    }
}
