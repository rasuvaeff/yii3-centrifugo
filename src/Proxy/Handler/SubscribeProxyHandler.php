<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Proxy\Handler;

use Rasuvaeff\Yii3Centrifugo\Proxy\Request\SubscribeRequest;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyDisconnect;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyError;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyResult;

/**
 * @api
 */
interface SubscribeProxyHandler
{
    public function handle(SubscribeRequest $request): ProxyResult|ProxyError|ProxyDisconnect;
}
