<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Tests\Proxy\Action;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Rasuvaeff\Understudy\Arg;
use Rasuvaeff\Understudy\Understudy;
use Rasuvaeff\Yii3Centrifugo\Proxy\Action\SubRefreshAction;
use Rasuvaeff\Yii3Centrifugo\Proxy\Handler\SubRefreshProxyHandler;
use Rasuvaeff\Yii3Centrifugo\Proxy\Internal\ProxyResponseFactory;
use Rasuvaeff\Yii3Centrifugo\Proxy\Request\SubRefreshRequest;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyResult;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

use function Rasuvaeff\Understudy\when;

#[Test]
#[Covers(SubRefreshAction::class)]
#[Covers(SubRefreshRequest::class)]
final class SubRefreshActionTest
{
    public function parsesUserAndChannel(): void
    {
        $factory = new Psr17Factory();
        $requests = Arg::captor(SubRefreshRequest::class);
        $handler = Understudy::for(SubRefreshProxyHandler::class);
        when(fn() => $handler->handle($requests->capture()))->returns(new ProxyResult());

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

        Assert::same($requests->last()->user, '3');
        Assert::same($requests->last()->channel, 'private');
    }
}
