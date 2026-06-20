<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Tests\Proxy\Action;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Rasuvaeff\Yii3Centrifugo\Proxy\Action\SubscribeAction;
use Rasuvaeff\Yii3Centrifugo\Proxy\Handler\SubscribeProxyHandler;
use Rasuvaeff\Yii3Centrifugo\Proxy\Internal\ProxyResponseFactory;
use Rasuvaeff\Yii3Centrifugo\Proxy\Request\SubscribeRequest;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyDisconnect;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyError;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyResult;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(SubscribeAction::class)]
#[Covers(SubscribeRequest::class)]
final class SubscribeActionTest
{
    private Psr17Factory $factory;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    public function parsesChannelAndUser(): void
    {
        $box = new \stdClass();
        $handler = new class ($box) implements SubscribeProxyHandler {
            public function __construct(private readonly \stdClass $box) {}

            #[\Override]
            public function handle(SubscribeRequest $request): ProxyResult|ProxyError|ProxyDisconnect
            {
                $this->box->request = $request;

                return new ProxyResult();
            }
        };

        $rf = new ProxyResponseFactory($this->factory, $this->factory);
        $action = new SubscribeAction(handler: $handler, responseFactory: $rf);
        $action->handle($this->makeRequest([
            'client' => 'c1',
            'transport' => 'websocket',
            'protocol' => 'json',
            'encoding' => 'json',
            'user' => '99',
            'channel' => 'news',
        ]));

        Assert::same($box->request->user, '99');
        Assert::same($box->request->channel, 'news');
    }

    private function makeRequest(array $body): \Psr\Http\Message\ServerRequestInterface
    {
        $json = json_encode($body, JSON_THROW_ON_ERROR);

        return (new ServerRequest('POST', '/centrifugo/subscribe'))
            ->withBody($this->factory->createStream($json));
    }
}
