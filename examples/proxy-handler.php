<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Rasuvaeff\Yii3Centrifugo\Proxy\Handler\ConnectProxyHandler;
use Rasuvaeff\Yii3Centrifugo\Proxy\Request\ConnectRequest;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyDisconnect;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyError;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyResult;

/**
 * Example connect handler — authenticate user from a session cookie / auth header
 * that Centrifugo proxied along with the connect event.
 */
final readonly class ExampleConnectHandler implements ConnectProxyHandler
{
    #[\Override]
    public function handle(ConnectRequest $request): ProxyResult|ProxyError|ProxyDisconnect
    {
        // In a real app: resolve userId from session/JWT passed in $request->data
        $userId = $this->resolveUserId($request->data);

        if ($userId === null) {
            return new ProxyDisconnect(code: 4001, reason: 'unauthorized');
        }

        return new ProxyResult(data: [
            'user'     => $userId,
            'channels' => ['news'],
        ]);
    }

    private function resolveUserId(mixed $data): ?string
    {
        if (!is_array($data) || !isset($data['token'])) {
            return null;
        }

        // Stub: real implementation would verify the token
        return $data['token'] === 'valid' ? '42' : null;
    }
}

// Demonstrate the handler logic
$handler = new ExampleConnectHandler();

$allowed = $handler->handle(new ConnectRequest(
    client: 'c1',
    transport: 'websocket',
    protocol: 'json',
    encoding: 'json',
    data: ['token' => 'valid'],
));
echo 'Allowed: ' . json_encode($allowed->data, JSON_THROW_ON_ERROR) . PHP_EOL;

$denied = $handler->handle(new ConnectRequest(
    client: 'c2',
    transport: 'websocket',
    protocol: 'json',
    encoding: 'json',
    data: ['token' => 'bad'],
));
echo 'Denied: disconnect code ' . $denied->code . PHP_EOL;
