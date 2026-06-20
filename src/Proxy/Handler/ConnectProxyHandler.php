<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Proxy\Handler;

use Rasuvaeff\Yii3Centrifugo\Proxy\Request\ConnectRequest;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyDisconnect;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyError;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyResult;

/**
 * @api
 */
interface ConnectProxyHandler
{
    public function handle(ConnectRequest $request): ProxyResult|ProxyError|ProxyDisconnect;
}
