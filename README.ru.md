# rasuvaeff/yii3-centrifugo

[![Stable Version](https://poser.pugx.org/rasuvaeff/yii3-centrifugo/v/stable)](https://packagist.org/packages/rasuvaeff/yii3-centrifugo)
[![Total Downloads](https://poser.pugx.org/rasuvaeff/yii3-centrifugo/downloads)](https://packagist.org/packages/rasuvaeff/yii3-centrifugo)
[![Build](https://github.com/rasuvaeff/yii3-centrifugo/actions/workflows/build.yml/badge.svg)](https://github.com/rasuvaeff/yii3-centrifugo/actions/workflows/build.yml)
[![Static analysis](https://github.com/rasuvaeff/yii3-centrifugo/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/rasuvaeff/yii3-centrifugo/actions/workflows/static-analysis.yml)
[![Psalm Level](https://shepherd.dev/github/rasuvaeff/yii3-centrifugo/level.svg)](https://shepherd.dev/github/rasuvaeff/yii3-centrifugo)
[![License](https://poser.pugx.org/rasuvaeff/yii3-centrifugo/license)](https://packagist.org/packages/rasuvaeff/yii3-centrifugo)
[English version](README.md)

Интеграция Centrifugo v6 с Yii3: полный клиент HTTP server API, эмитенты JWT-токенов
подключения/подписки и PSR-15 обработчики прокси-событий (connect, subscribe, publish,
refresh, sub_refresh, rpc).

> Используете AI-ассистента? В [llms.txt](llms.txt) — компактный API-справочник,
> который можно вставить в контекст.

## Требования

- PHP 8.3–8.5
- Centrifugo v6
- PSR-18 HTTP-клиент (например, `guzzlehttp/guzzle`, `symfony/http-client`)
- PSR-17 фабрика (например, `nyholm/psr7`, `guzzlehttp/psr7`)

## Установка

```bash
composer require rasuvaeff/yii3-centrifugo
```

Затем настройте в `config/params.php`:

```php
'centrifugo' => [
    'api_url'            => 'http://localhost:8000',
    'api_key'            => 'your-api-key',
    'token_hmac_secret'  => 'your-hmac-secret-at-least-32-chars',
    'token_ttl'          => 3600,
],
```

## Использование

### Клиент Server API

`CentrifugoClient` предоставляет все методы HTTP API Centrifugo v6 OSS. Требует,
чтобы PSR-18 клиент и PSR-17 фабрики были привязаны в DI-контейнере.

```php
use Rasuvaeff\Yii3Centrifugo\CentrifugoClient;
use Rasuvaeff\Yii3Centrifugo\BatchCommand;

$client = $container->get(CentrifugoClient::class);

// Публикация в канал
$client->publish(channel: 'news', data: ['title' => 'Breaking news']);

// Публикация в несколько каналов
$client->broadcast(channels: ['news', 'alerts'], data: ['ping' => 1]);

// Управление подписками
$client->subscribe(user: '42', channel: 'private#42');
$client->unsubscribe(user: '42', channel: 'private#42');

// Отключение пользователя
$client->disconnect(user: '42');

// Presence
$presence = $client->presence(channel: 'news');
$stats = $client->presenceStats(channel: 'news');

// History
$history = $client->history(channel: 'news', limit: 50);
$client->historyRemove(channel: 'news');

// Информация о кластере
$channels = $client->channels(pattern: 'news*');
$info = $client->info();

// Batch (несколько команд в одном HTTP-запросе)
$client->batch(
    new BatchCommand(method: 'publish', params: ['channel' => 'a', 'data' => []]),
    new BatchCommand(method: 'publish', params: ['channel' => 'b', 'data' => []]),
);
```

| Метод | Описание |
|---|---|
| `publish(channel, data)` | Публикация в один канал |
| `broadcast(channels, data)` | Публикация в несколько каналов |
| `subscribe(user, channel)` | Серверная подписка пользователя |
| `unsubscribe(user, channel)` | Серверная отписка пользователя |
| `disconnect(user, client?, whitelist?)` | Отключение пользователя |
| `refresh(user, client?, expireAt?)` | Обновление соединения |
| `presence(channel)` | Детальная информация о присутствии |
| `presenceStats(channel)` | Агрегированные счётчики присутствия |
| `history(channel, limit?, reverse?, since?)` | История сообщений канала |
| `historyRemove(channel)` | Очистка истории канала |
| `channels(pattern?)` | Список активных каналов |
| `info()` | Информация об узле кластера |
| `batch(BatchCommand ...)` | Несколько команд в одном запросе |

#### Обработка ошибок

Любой неудачный вызов бросает наследника `CentrifugoException`, так что один `catch` покрывает «Centrifugo недоступен или неверно настроен»:

| Исключение | Когда | Дополнительно |
|---|---|---|
| `CentrifugoApiException` | Centrifugo ответил объектом `error` | `getApiCode()` — код ошибки Centrifugo |
| `CentrifugoTransportException` | PSR-18 клиент упал, HTTP-статус не 2xx (например, 401 при неверном `api_key`) или тело не JSON-объект | `getStatusCode()` — HTTP-статус или `null`; `getPrevious()` — исходная ошибка клиента / JSON |

```php
use Rasuvaeff\Yii3Centrifugo\CentrifugoException;

try {
    $client->publish(channel: 'news', data: ['title' => 'Hello']);
} catch (CentrifugoException $e) {
    $logger->warning('Centrifugo publish failed', ['exception' => $e]);
}
```

### Тестирование кода, который публикует

Зависьте от `CentrifugoClientInterface` (в `config/di.php` он привязан к тому же общему `CentrifugoClient`) и используйте в тестах встроенный дублёр в памяти:

```php
use Rasuvaeff\Yii3Centrifugo\CentrifugoTransportException;
use Rasuvaeff\Yii3Centrifugo\Testing\InMemoryCentrifugoClient;

$centrifugo = new InMemoryCentrifugoClient();
$notifier = new ArticleNotifier($centrifugo);   // принимает CentrifugoClientInterface

$notifier->articleUpdated(7);
$centrifugo->publishedTo('articles');           // [['id' => 7]]

$centrifugo->failNextWith(new CentrifugoTransportException('down'));
$notifier->articleUpdated(8);                   // следующий вызов бросит исключение
```

| Метод | Описание |
|---|---|
| `calls()` | Все вызовы: `list<array{method, params}>`, имена параметров как в API Centrifugo |
| `published()` | `list<array{channel, data}>` из `publish()` и `broadcast()` (по записи на канал) |
| `publishedTo(channel)` | Данные, отправленные в один канал |
| `failNextWith(Throwable)` | Следующий вызов любого метода бросит его и не будет записан; ошибки встают в очередь |
| `reset()` | Забыть вызовы и очередь ошибок |

Все методы API дублёра возвращают `[]`.

### Выпуск JWT-токенов

```php
use Rasuvaeff\Yii3Centrifugo\Token\ConnectionTokenIssuer;
use Rasuvaeff\Yii3Centrifugo\Token\SubscriptionTokenIssuer;

// Токен подключения (выдаётся клиенту при логине)
$issuer = $container->get(ConnectionTokenIssuer::class);
$jwt = $issuer->issue(
    userId: '42',
    ttl: 3600,
    channels: ['news'],       // опциональная автоподписка
    info: ['name' => 'Alice'], // опциональная информация о пользователе
);

// Токен подписки (выдаётся, когда клиент запрашивает доступ к приватному каналу)
$subIssuer = $container->get(SubscriptionTokenIssuer::class);
$jwt = $subIssuer->issue(
    userId: '42',
    channel: 'private#42',
    ttl: 3600,
    info: ['role' => 'admin'],
);
```

### Прокси-события

Centrifugo может проксировать события жизненного цикла соединения на ваш backend
по HTTP. Настройте конечные точки в `centrifugo.json`:

```json
{
    "proxy": {
        "connect": {"endpoint": "http://app/centrifugo/connect", "timeout": "3s"},
        "subscribe": {"endpoint": "http://app/centrifugo/subscribe", "timeout": "3s"}
    }
}
```

Зарегистрируйте маршруты в приложении Yii3:

```php
use Rasuvaeff\Yii3Centrifugo\Proxy\Action\ConnectAction;
use Rasuvaeff\Yii3Centrifugo\Proxy\Action\SubscribeAction;

Route::post('/centrifugo/connect', ConnectAction::class),
Route::post('/centrifugo/subscribe', SubscribeAction::class),
```

Реализуйте интерфейс обработчика в своём приложении:

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

Привяжите свой обработчик в DI-контейнере:

```php
// config/common/di/centrifugo.php
use Rasuvaeff\Yii3Centrifugo\Proxy\Handler\ConnectProxyHandler;

return [
    ConnectProxyHandler::class => AppConnectHandler::class,
];
```

#### Доступные прокси-обработчики

| Интерфейс обработчика | Класс action | Событие Centrifugo |
|---|---|---|
| `ConnectProxyHandler` | `ConnectAction` | Клиент подключается |
| `RefreshProxyHandler` | `RefreshAction` | Обновление соединения |
| `SubscribeProxyHandler` | `SubscribeAction` | Клиент подписывается на канал |
| `PublishProxyHandler` | `PublishAction` | Клиент публикует в канал |
| `SubRefreshProxyHandler` | `SubRefreshAction` | Обновление подписки |
| `RpcProxyHandler` | `RpcAction` | RPC-вызов клиента |

#### Типы ответов прокси

| Тип | JSON-конверт | Диапазон кодов |
|---|---|---|
| `ProxyResult(array $data)` | `{"result": {...}}` | — |
| `ProxyError(int $code, string $message)` | `{"error": {"code": N, "message": "..."}}` | 400–1999 |
| `ProxyDisconnect(int $code, string $reason)` | `{"disconnect": {"code": N, "reason": "..."}}` | 4000–4999 |

## Безопасность

- Прокси-эндпоинты должны быть доступны только с сервера Centrifugo (сетевой ACL
  или общий секретный заголовок через конфигурацию `proxy.http_headers`).
- HMAC-секрет и API-ключ приходят из params/env, а не захардкожены.
- Ошибки серверного API приходят как `CentrifugoException`: `CentrifugoApiException`
  при ответе с `error`, `CentrifugoTransportException` при сетевой ошибке, статусе
  не 2xx и теле не в JSON. В сообщениях исключений нет API-ключа.
- `ProxyError` и `ProxyDisconnect` валидируют диапазоны кодов в конструкторах —
  недопустимые коды бросают `InvalidArgumentException`.

## Примеры

См. [`examples/`](examples/) — работоспособные скрипты.

## Разработка

```bash
make install
make build
make cs-fix
make psalm
make test
```

На хосте нет PHP и Composer — все команды выполняются внутри Docker-контейнера
`composer:2`.

## Лицензия

BSD-3-Clause. См. [LICENSE.md](LICENSE.md).
