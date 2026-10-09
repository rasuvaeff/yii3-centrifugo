# rasuvaeff/yii3-centrifugo

[![Stable Version](https://poser.pugx.org/rasuvaeff/yii3-centrifugo/v/stable)](https://packagist.org/packages/rasuvaeff/yii3-centrifugo)
[![Total Downloads](https://poser.pugx.org/rasuvaeff/yii3-centrifugo/downloads)](https://packagist.org/packages/rasuvaeff/yii3-centrifugo)
[![Build](https://github.com/rasuvaeff/yii3-centrifugo/actions/workflows/build.yml/badge.svg)](https://github.com/rasuvaeff/yii3-centrifugo/actions/workflows/build.yml)
[![Static analysis](https://github.com/rasuvaeff/yii3-centrifugo/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/rasuvaeff/yii3-centrifugo/actions/workflows/static-analysis.yml)
[![Psalm Level](https://shepherd.dev/github/rasuvaeff/yii3-centrifugo/level.svg)](https://shepherd.dev/github/rasuvaeff/yii3-centrifugo)
[![License](https://poser.pugx.org/rasuvaeff/yii3-centrifugo/license)](https://packagist.org/packages/rasuvaeff/yii3-centrifugo)
[Русская версия](README.ru.md)

Centrifugo v6 integration for Yii3: full HTTP server API client, JWT connection/subscription token issuers, and PSR-15 proxy event handlers (connect, subscribe, publish, refresh, sub_refresh, rpc).

> Using an AI coding assistant? [llms.txt](llms.txt) has a compact API reference you can paste into context.

## Requirements

- PHP 8.3–8.5
- Centrifugo v6
- A PSR-18 HTTP client (e.g. `guzzlehttp/guzzle`, `symfony/http-client`)
- A PSR-17 factory (e.g. `nyholm/psr7`, `guzzlehttp/psr7`)

## Installation

```bash
composer require rasuvaeff/yii3-centrifugo
```

Then configure in `config/params.php`:

```php
'centrifugo' => [
    'api_url'            => 'http://localhost:8000',
    'api_key'            => 'your-api-key',
    'token_hmac_secret'  => 'your-hmac-secret-at-least-32-chars',
    'token_ttl'          => 3600,
],
```

Values are validated when the container builds the service that uses them, and an `InvalidConfigException` names the params key (never the value):

| Key | Rule | Checked by |
|---|---|---|
| `api_url` | http(s) URL with a host | `CentrifugoClient` |
| `api_key` | string; **empty is allowed** for a Centrifugo running with `api_insecure` (do not do this in production) | `CentrifugoClient` |
| `token_hmac_secret` | at least 32 bytes (HS256 needs a 256-bit key) | token issuers |
| `token_ttl` | integer > 0 | token issuers |

An application that only publishes does not have to configure the token secret.

## Usage

### Server API Client

`CentrifugoClient` provides all Centrifugo v6 OSS HTTP API methods. It requires a PSR-18 client and PSR-17 factories to be bound in the DI container.

```php
use Rasuvaeff\Yii3Centrifugo\CentrifugoClient;
use Rasuvaeff\Yii3Centrifugo\BatchCommand;
use Rasuvaeff\Yii3Centrifugo\PublishOptions;

$client = $container->get(CentrifugoClient::class);

// Publish to a channel
$client->publish(channel: 'news', data: ['title' => 'Breaking news']);

// Broadcast to multiple channels
$client->broadcast(channels: ['news', 'alerts'], data: ['ping' => 1]);

// Retry-safe publish from a queue worker: Centrifugo drops the duplicate
$client->publish(
    channel: 'orders',
    data: ['id' => 42],
    options: new PublishOptions(idempotencyKey: 'order-42-paid', tags: ['kind' => 'order']),
);

// Manage subscriptions
$client->subscribe(user: '42', channel: 'private#42');
$client->unsubscribe(user: '42', channel: 'private#42');

// Disconnect a user
$client->disconnect(user: '42');

// Presence
$presence = $client->presence(channel: 'news');
$stats = $client->presenceStats(channel: 'news');

// History
$history = $client->history(channel: 'news', limit: 50);
$client->historyRemove(channel: 'news');

// Cluster info
$channels = $client->channels(pattern: 'news*');
$info = $client->info();

// Batch (multiple commands in one HTTP request)
$client->batch(
    new BatchCommand(method: 'publish', params: ['channel' => 'a', 'data' => []]),
    new BatchCommand(method: 'publish', params: ['channel' => 'b', 'data' => []]),
);
```

| Method | Description |
|---|---|
| `publish(channel, data, options?)` | Publish to one channel; returns `offset`, `epoch` |
| `broadcast(channels, data, options?)` | Publish to many channels; returns `responses` |
| `subscribe(user, channel)` | Subscribe user server-side |
| `unsubscribe(user, channel)` | Unsubscribe user server-side |
| `disconnect(user, client?, whitelist?)` | Disconnect user |
| `refresh(user, client?, expireAt?)` | Refresh connection |
| `presence(channel)` | Detailed presence info |
| `presenceStats(channel)` | Aggregated presence counts |
| `history(channel, limit?, reverse?, since?)` | Channel message history |
| `historyRemove(channel)` | Clear channel history |
| `channels(pattern?)` | List active channels |
| `info()` | Cluster node info |
| `batch(BatchCommand ...)` | Multiple commands in one request |

`PublishOptions` (all optional, only non-defaults are sent):

| Argument | API field | Notes |
|---|---|---|
| `idempotencyKey` | `idempotency_key` | Centrifugo drops a retried duplicate (Memory and Redis engines) |
| `skipHistory` | `skip_history` | Do not save to history |
| `tags` | `tags` | `array<string, string>`, delivered to subscribers |
| `delta` | `delta` | Delta compression for this publication |
| `version`, `versionEpoch` | `version`, `version_epoch` | Centrifugo 6.2+, channels with history: lower versions are ignored |

#### Error handling

Every failed call throws a subclass of `CentrifugoException`, so one `catch` covers "Centrifugo is unavailable or misconfigured":

| Exception | When | Extra |
|---|---|---|
| `CentrifugoApiException` | Centrifugo answered with an `error` object | `getApiCode()` — Centrifugo error code |
| `CentrifugoTransportException` | the PSR-18 client failed, the HTTP status is not 2xx (e.g. 401 for a wrong `api_key`), or the body is not a JSON object | `getStatusCode()` — HTTP status or `null`; `getPrevious()` — the original client / JSON error |

```php
use Rasuvaeff\Yii3Centrifugo\CentrifugoException;

try {
    $client->publish(channel: 'news', data: ['title' => 'Hello']);
} catch (CentrifugoException $e) {
    $logger->warning('Centrifugo publish failed', ['exception' => $e]);
}
```

### Testing code that publishes

Depend on `CentrifugoClientInterface` (bound in `config/di.php` to the same shared `CentrifugoClient`) and use the bundled in-memory double in tests:

```php
use Rasuvaeff\Yii3Centrifugo\CentrifugoTransportException;
use Rasuvaeff\Yii3Centrifugo\Testing\InMemoryCentrifugoClient;

$centrifugo = new InMemoryCentrifugoClient();
$notifier = new ArticleNotifier($centrifugo);   // takes CentrifugoClientInterface

$notifier->articleUpdated(7);
$centrifugo->publishedTo('articles');           // [['id' => 7]]

$centrifugo->failNextWith(new CentrifugoTransportException('down'));
$notifier->articleUpdated(8);                   // the next call throws
```

| Method | Description |
|---|---|
| `calls()` | Every call: `list<array{method, params}>`, params named as in the Centrifugo API |
| `published()` | `list<array{channel, data}>` from `publish()` and `broadcast()` (one entry per channel) |
| `publishedTo(channel)` | Data delivered to one channel |
| `failNextWith(Throwable)` | The next call of any method throws it and is not recorded; failures queue |
| `reset()` | Forget calls and queued failures |

All API methods of the double return `[]`.

### JWT Token Issuance

```php
use Rasuvaeff\Yii3Centrifugo\Token\ConnectionTokenIssuer;
use Rasuvaeff\Yii3Centrifugo\Token\SubscriptionTokenIssuer;

// Connection token (sent to client on login)
$issuer = $container->get(ConnectionTokenIssuer::class);
$jwt = $issuer->issue(
    userId: '42',
    ttl: 3600,
    channels: ['news'],       // optional auto-subscribe
    info: ['name' => 'Alice'], // optional user info
);

// Subscription token (sent when client requests private channel access)
$subIssuer = $container->get(SubscriptionTokenIssuer::class);
$jwt = $subIssuer->issue(
    userId: '42',
    channel: 'private#42',
    ttl: 3600,
    info: ['role' => 'admin'],
);
```

`exp` is computed in whole seconds from a PSR-20 clock. When the application binds `Psr\Clock\ClockInterface` in the container, the DI definitions pass it to both issuers; otherwise the system clock is used. In tests, construct the issuer with a fixed clock to assert the exact `exp`:

```php
$issuer = new ConnectionTokenIssuer(jwtConfig: $jwtConfig, defaultTtl: 3600, clock: $frozenClock);
```

### Proxy Events

Centrifugo can proxy connection lifecycle events to your backend over HTTP. Configure endpoints in `centrifugo.json`:

```json
{
    "proxy": {
        "connect": {"endpoint": "http://app/centrifugo/connect", "timeout": "3s"},
        "subscribe": {"endpoint": "http://app/centrifugo/subscribe", "timeout": "3s"}
    }
}
```

Register routes in your Yii3 app:

```php
use Rasuvaeff\Yii3Centrifugo\Proxy\Action\ConnectAction;
use Rasuvaeff\Yii3Centrifugo\Proxy\Action\SubscribeAction;

Route::post('/centrifugo/connect', ConnectAction::class),
Route::post('/centrifugo/subscribe', SubscribeAction::class),
```

Implement the handler interface in your application:

```php
use Rasuvaeff\Yii3Centrifugo\Proxy\Handler\ConnectProxyHandler;
use Rasuvaeff\Yii3Centrifugo\Proxy\Request\ConnectRequest;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyDisconnect;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyError;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyResult;

final readonly class AppConnectHandler implements ConnectProxyHandler
{
    public function __construct(private AuthService $auth) {}

    #[\Override]
    public function handle(ConnectRequest $request): ProxyResult|ProxyError|ProxyDisconnect
    {
        $userId = $this->auth->getUserFromRequest($request);

        if ($userId === null) {
            return new ProxyDisconnect(code: 4001, reason: 'unauthorized');
        }

        return new ProxyResult(data: ['user' => $userId]);
    }
}
```

Bind your handler in the DI container:

```php
// config/common/di/centrifugo.php
use Rasuvaeff\Yii3Centrifugo\Proxy\Handler\ConnectProxyHandler;

return [
    ConnectProxyHandler::class => AppConnectHandler::class,
];
```

#### Available proxy handlers

| Handler interface | Action class | Centrifugo event |
|---|---|---|
| `ConnectProxyHandler` | `ConnectAction` | Client connects |
| `RefreshProxyHandler` | `RefreshAction` | Connection refresh |
| `SubscribeProxyHandler` | `SubscribeAction` | Client subscribes to channel |
| `PublishProxyHandler` | `PublishAction` | Client publishes to channel |
| `SubRefreshProxyHandler` | `SubRefreshAction` | Subscription refresh |
| `RpcProxyHandler` | `RpcAction` | Client RPC call |

#### Proxy response types

| Type | JSON envelope | Code range |
|---|---|---|
| `ProxyResult(array $data)` | `{"result": {...}}` | — |
| `ProxyError(int $code, string $message)` | `{"error": {"code": N, "message": "..."}}` | 400–1999 |
| `ProxyDisconnect(int $code, string $reason)` | `{"disconnect": {"code": N, "reason": "..."}}` | 4000–4999 |

## Security

- Proxy endpoints must be reachable only from the Centrifugo server (network ACL or shared secret header via `proxy.http_headers` config).
- HMAC secret and API key are injected from params/env, never hard-coded.
- Server API failures surface as `CentrifugoException`: `CentrifugoApiException` for an `error` reply, `CentrifugoTransportException` for network errors, non-2xx statuses and non-JSON bodies. Exception messages never contain the API key.
- `ProxyError` and `ProxyDisconnect` validate code ranges in constructors — invalid codes throw `InvalidArgumentException`.

## Examples

See [`examples/`](examples/) for runnable scripts.

## Development

```bash
make install
make build
make cs-fix
make psalm
make test
```

No PHP or Composer on the host — all commands run inside `composer:2` Docker container.

## License

BSD-3-Clause. See [LICENSE.md](LICENSE.md).
