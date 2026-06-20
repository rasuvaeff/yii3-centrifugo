<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Tests\Proxy\Action;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Rasuvaeff\Yii3Centrifugo\Proxy\Action\SubRefreshAction;
use Rasuvaeff\Yii3Centrifugo\Proxy\Handler\SubRefreshProxyHandler;
use Rasuvaeff\Yii3Centrifugo\Proxy\Internal\ProxyResponseFactory;
use Rasuvaeff\Yii3Centrifugo\Proxy\Request\SubRefreshRequest;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyDisconnect;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyError;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyResult;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(SubRefreshAction::class)]
#[Covers(SubRefreshRequest::class)]
final class SubRefreshActionTest
{
    public function parsesUserAndChannel(): void
    {
        $factory = new Psr17Factory();
        $box = new \stdClass();
        $handler = new class ($box) implements SubRefreshProxyHandler {
            public function __construct(private readonly \stdClass $box) {}

            #[\Override]
            public function handle(SubRefreshRequest $request): ProxyResult|ProxyError|ProxyDisconnect
            {
                $this->box->request = $request;

                return new ProxyResult();
            }
        };

        $rf = new ProxyResponseFactory($factory, $factory);
        $action = new SubRefreshAction(handler: $handler, responseFactory: $rf);
        $json = json_encode([
            'client' => 'c1',
            'transport' => 'websocket',
            'protocol' => 'json',
            'encoding' => 'json',
            'user' => '3',
            'channel' => 'private',
        ], JSON_THROW_ON_ERROR);
        $request = (new ServerRequest('POST', '/centrifugo/sub_refresh'))
            ->withBody($factory->createStream($json));

        $action->handle($request);

        Assert::same($box->request->user, '3');
        Assert::same($box->request->channel, 'private');
    }
}
