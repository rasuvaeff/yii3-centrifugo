<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Tests\Proxy\Action;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Rasuvaeff\Understudy\Arg;
use Rasuvaeff\Understudy\Understudy;
use Rasuvaeff\Yii3Centrifugo\Proxy\Action\SubscribeAction;
use Rasuvaeff\Yii3Centrifugo\Proxy\Handler\SubscribeProxyHandler;
use Rasuvaeff\Yii3Centrifugo\Proxy\Internal\ProxyResponseFactory;
use Rasuvaeff\Yii3Centrifugo\Proxy\Request\SubscribeRequest;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyResult;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

use function Rasuvaeff\Understudy\when;

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
        $requests = Arg::captor(SubscribeRequest::class);
        $handler = Understudy::for(SubscribeProxyHandler::class);
        when(fn() => $handler->handle($requests->capture()))->returns(new ProxyResult());

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

        $request = $requests->last();
        Assert::same($request->user, '99');
        Assert::same($request->channel, 'news');
    }

    private function makeRequest(array $body): \Psr\Http\Message\ServerRequestInterface
    {
        $json = json_encode($body, JSON_THROW_ON_ERROR);

        return (new ServerRequest('POST', '/centrifugo/subscribe'))
            ->withBody($this->factory->createStream($json));
    }
}
