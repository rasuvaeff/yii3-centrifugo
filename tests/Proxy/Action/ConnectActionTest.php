<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Tests\Proxy\Action;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;
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
        $handler = new class implements ConnectProxyHandler {
            #[\Override]
            public function handle(ConnectRequest $request): ProxyResult|ProxyError|ProxyDisconnect
            {
                return new ProxyResult(data: ['user' => '42']);
            }
        };

        $action = new ConnectAction(handler: $handler, responseFactory: $this->responseFactory);
        $response = $action->handle($this->makeRequest(['client' => 'c1', 'transport' => 'websocket', 'protocol' => 'json', 'encoding' => 'json']));

        $body = json_decode((string) $response->getBody(), true);
        Assert::same($response->getStatusCode(), 200);
        Assert::same($body['result'], ['user' => '42']);
        Assert::array($body)->doesNotHaveKeys('error');
    }

    public function returnsErrorEnvelope(): void
    {
        $handler = new class implements ConnectProxyHandler {
            #[\Override]
            public function handle(ConnectRequest $request): ProxyResult|ProxyError|ProxyDisconnect
            {
                return new ProxyError(code: 403, message: 'permission denied');
            }
        };

        $action = new ConnectAction(handler: $handler, responseFactory: $this->responseFactory);
        $response = $action->handle($this->makeRequest([]));

        $body = json_decode((string) $response->getBody(), true);
        Assert::same($body['error']['code'], 403);
        Assert::same($body['error']['message'], 'permission denied');
    }

    public function returnsDisconnectEnvelope(): void
    {
        $handler = new class implements ConnectProxyHandler {
            #[\Override]
            public function handle(ConnectRequest $request): ProxyResult|ProxyError|ProxyDisconnect
            {
                return new ProxyDisconnect(code: 4001, reason: 'unauthorized');
            }
        };

        $action = new ConnectAction(handler: $handler, responseFactory: $this->responseFactory);
        $response = $action->handle($this->makeRequest([]));

        $body = json_decode((string) $response->getBody(), true);
        Assert::same($body['disconnect']['code'], 4001);
        Assert::same($body['disconnect']['reason'], 'unauthorized');
    }

    public function parsesConnectRequestFields(): void
    {
        $box = new \stdClass();
        $handler = new class ($box) implements ConnectProxyHandler {
            public function __construct(private readonly \stdClass $box) {}

            #[\Override]
            public function handle(ConnectRequest $request): ProxyResult|ProxyError|ProxyDisconnect
            {
                $this->box->request = $request;

                return new ProxyResult();
            }
        };

        $action = new ConnectAction(handler: $handler, responseFactory: $this->responseFactory);
        $action->handle($this->makeRequest([
            'client' => 'c1',
            'transport' => 'websocket',
            'protocol' => 'json',
            'encoding' => 'json',
            'channels' => ['news'],
        ]));

        Assert::same($box->request->client, 'c1');
        Assert::same($box->request->transport, 'websocket');
        Assert::same($box->request->channels, ['news']);
    }

    private function makeRequest(array $body): ServerRequestInterface
    {
        $json = json_encode($body, JSON_THROW_ON_ERROR);

        return (new ServerRequest('POST', '/centrifugo/connect'))
            ->withBody($this->factory->createStream($json));
    }
}
