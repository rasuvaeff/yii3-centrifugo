<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Tests\Proxy\Action;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Rasuvaeff\Yii3Centrifugo\Proxy\Action\RefreshAction;
use Rasuvaeff\Yii3Centrifugo\Proxy\Handler\RefreshProxyHandler;
use Rasuvaeff\Yii3Centrifugo\Proxy\Internal\ProxyResponseFactory;
use Rasuvaeff\Yii3Centrifugo\Proxy\Request\RefreshRequest;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyDisconnect;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyError;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyResult;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(RefreshAction::class)]
#[Covers(RefreshRequest::class)]
final class RefreshActionTest
{
    private Psr17Factory $factory;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    public function parsesUser(): void
    {
        $box = new \stdClass();
        $handler = new class ($box) implements RefreshProxyHandler {
            public function __construct(private readonly \stdClass $box) {}

            #[\Override]
            public function handle(RefreshRequest $request): ProxyResult|ProxyError|ProxyDisconnect
            {
                $this->box->request = $request;

                return new ProxyResult(data: ['expire_at' => 9999]);
            }
        };

        $rf = new ProxyResponseFactory($this->factory, $this->factory);
        $action = new RefreshAction(handler: $handler, responseFactory: $rf);
        $response = $action->handle($this->makeRequest([
            'client' => 'c1',
            'transport' => 'websocket',
            'protocol' => 'json',
            'encoding' => 'json',
            'user' => '55',
        ]));

        $body = json_decode((string) $response->getBody(), true);
        Assert::same($box->request->user, '55');
        Assert::same($body['result']['expire_at'], 9999);
    }

    private function makeRequest(array $body): \Psr\Http\Message\ServerRequestInterface
    {
        $json = json_encode($body, JSON_THROW_ON_ERROR);

        return (new ServerRequest('POST', '/centrifugo/refresh'))
            ->withBody($this->factory->createStream($json));
    }
}
