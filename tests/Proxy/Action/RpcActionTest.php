<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Tests\Proxy\Action;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;
use Rasuvaeff\Yii3Centrifugo\Proxy\Action\RpcAction;
use Rasuvaeff\Yii3Centrifugo\Proxy\Handler\RpcProxyHandler;
use Rasuvaeff\Yii3Centrifugo\Proxy\Internal\ProxyResponseFactory;
use Rasuvaeff\Yii3Centrifugo\Proxy\Request\RpcRequest;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyDisconnect;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyError;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyResult;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(RpcAction::class)]
#[Covers(RpcRequest::class)]
final class RpcActionTest
{
    private Psr17Factory $factory;
    private ProxyResponseFactory $responseFactory;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->factory = new Psr17Factory();
        $this->responseFactory = new ProxyResponseFactory($this->factory, $this->factory);
    }

    public function parsesRpcMethodUserAndData(): void
    {
        $box = new \stdClass();
        $handler = new class ($box) implements RpcProxyHandler {
            public function __construct(private readonly \stdClass $box) {}

            #[\Override]
            public function handle(RpcRequest $request): ProxyResult|ProxyError|ProxyDisconnect
            {
                $this->box->request = $request;

                return new ProxyResult(data: ['ok' => true]);
            }
        };

        $action = new RpcAction(handler: $handler, responseFactory: $this->responseFactory);
        $action->handle($this->makeRequest([
            'client' => 'c1',
            'transport' => 'websocket',
            'protocol' => 'json',
            'encoding' => 'json',
            'user' => '42',
            'method' => 'getConfig',
            'data' => ['key' => 'x'],
        ]));

        Assert::same($box->request->method, 'getConfig');
        Assert::same($box->request->user, '42');
        Assert::same($box->request->data, ['key' => 'x']);
    }

    public function returnsResultInEnvelope(): void
    {
        $handler = new class implements RpcProxyHandler {
            #[\Override]
            public function handle(RpcRequest $request): ProxyResult|ProxyError|ProxyDisconnect
            {
                return new ProxyResult(data: ['value' => 42]);
            }
        };

        $action = new RpcAction(handler: $handler, responseFactory: $this->responseFactory);
        $response = $action->handle($this->makeRequest([]));

        $body = json_decode((string) $response->getBody(), true);
        Assert::same($body['result']['value'], 42);
    }

    private function makeRequest(array $body): ServerRequestInterface
    {
        $json = json_encode($body, JSON_THROW_ON_ERROR);

        return (new ServerRequest('POST', '/centrifugo/rpc'))
            ->withBody($this->factory->createStream($json));
    }
}
