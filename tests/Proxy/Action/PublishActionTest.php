<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Tests\Proxy\Action;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Rasuvaeff\Yii3Centrifugo\Proxy\Action\PublishAction;
use Rasuvaeff\Yii3Centrifugo\Proxy\Handler\PublishProxyHandler;
use Rasuvaeff\Yii3Centrifugo\Proxy\Internal\ProxyResponseFactory;
use Rasuvaeff\Yii3Centrifugo\Proxy\Request\PublishRequest;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyDisconnect;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyError;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyResult;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(PublishAction::class)]
#[Covers(PublishRequest::class)]
final class PublishActionTest
{
    private Psr17Factory $factory;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    public function parsesChannelUserAndData(): void
    {
        $box = new \stdClass();
        $handler = new class ($box) implements PublishProxyHandler {
            public function __construct(private readonly \stdClass $box) {}

            #[\Override]
            public function handle(PublishRequest $request): ProxyResult|ProxyError|ProxyDisconnect
            {
                $this->box->request = $request;

                return new ProxyResult();
            }
        };

        $rf = new ProxyResponseFactory($this->factory, $this->factory);
        $action = new PublishAction(handler: $handler, responseFactory: $rf);
        $action->handle($this->makeRequest([
            'client' => 'c1',
            'transport' => 'websocket',
            'protocol' => 'json',
            'encoding' => 'json',
            'user' => '7',
            'channel' => 'chat',
            'data' => ['text' => 'hello'],
        ]));

        Assert::same($box->request->user, '7');
        Assert::same($box->request->channel, 'chat');
        Assert::same($box->request->data, ['text' => 'hello']);
    }

    private function makeRequest(array $body): \Psr\Http\Message\ServerRequestInterface
    {
        $json = json_encode($body, JSON_THROW_ON_ERROR);

        return (new ServerRequest('POST', '/centrifugo/publish'))
            ->withBody($this->factory->createStream($json));
    }
}
