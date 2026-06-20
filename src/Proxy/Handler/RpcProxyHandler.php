<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Proxy\Handler;

use Rasuvaeff\Yii3Centrifugo\Proxy\Request\RpcRequest;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyDisconnect;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyError;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyResult;

/**
 * @api
 */
interface RpcProxyHandler
{
    public function handle(RpcRequest $request): ProxyResult|ProxyError|ProxyDisconnect;
}
