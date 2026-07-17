# Расуваефф/yii3-центрифуго
[![Stable Version](https://poser.pugx.org/rasuvaeff/yii3-centrifugo/v/stable)](https://packagist.org/packages/rasuvaeff/yii3-centrifugo)
[![Total Downloads](https://poser.pugx.org/rasuvaeff/yii3-centrifugo/downloads)](https://packagist.org/packages/rasuvaeff/yii3-centrifugo)
[![Build](https://github.com/rasuvaeff/yii3-centrifugo/actions/workflows/build.yml/badge.svg)](https://github.com/rasuvaeff/yii3-centrifugo/actions/workflows/build.yml)
[![Static analysis](https://github.com/rasuvaeff/yii3-centrifugo/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/rasuvaeff/yii3-centrifugo/actions/workflows/static-analysis.yml)
[![Psalm Level](https://shepherd.dev/github/rasuvaeff/yii3-centrifugo/level.svg)](https://shepherd.dev/github/rasuvaeff/yii3-centrifugo)
[![License](https://poser.pugx.org/rasuvaeff/yii3-centrifugo/license)](https://packagist.org/packages/rasuvaeff/yii3-centrifugo)
Интеграция Centrifugo v6 с Yii3: полный клиент API HTTP-сервера, эмитенты токенов подключения/подписки JWT и обработчики прокси-событий PSR-15 (подключение, подписка, публикация, обновление, sub_refresh, rpc).

 > Используете помощника по программированию с искусственным интеллектом? [llms.txt](llms.txt) содержит компактную ссылку на API, которую можно вставить в контекст. @@ЛИНИЯ@@
## Требования
- PHP 8.3–8.5
 - Centrifugo v6
 - HTTP-клиент PSR-18 (например, `guzzlehttp/guzzle`, `symfony/http-client`)
 - Фабрика PSR-17 (например, `nyholm/psr7`, `guzzlehttp/psr7`)

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
### API-клиент сервера
«CentrifugoClient» предоставляет все методы HTTP API Centrifugo v6 OSS. Для этого требуется, чтобы клиент PSR-18 и фабрики PSR-17 были связаны в контейнере DI. @@ЛИНИЯ@@
```php
use Rasuvaeff\Yii3Centrifugo\CentrifugoClient;
use Rasuvaeff\Yii3Centrifugo\BatchCommand;

$client = $container->get(CentrifugoClient::class);

// Publish to a channel
$client->publish(channel: 'news', data: ['title' => 'Breaking news']);

// Broadcast to multiple channels
$client->broadcast(channels: ['news', 'alerts'], data: ['ping' => 1]);

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
| Метод | Описание |
 |---|---|
 | `публиковать(канал, данные)` | Опубликовать на одном канале |
 | `трансляция(каналы, данные)` | Публикация на многих каналах |
 | `подписаться(пользователь, канал)` | Подписаться на пользователя на стороне сервера |
 | `отписаться(пользователь, канал)` | Отменить подписку пользователя на стороне сервера |
 | `disconnect(пользователь, клиент?, белый список?)` | Отключить пользователя |
 | `обновить(пользователь, клиент?, expireAt?)` | Обновить соединение |
 | `присутствие(канал)` | Подробная информация о присутствии |
 | `presenceStats(канал)` | Совокупный показатель присутствия |
 | `история(канал, предел?, обратный ход?, с тех пор?)` | История сообщений канала |
 | `историяRemove(канал)` | Очистить историю канала |
 | `каналы(шаблон?)` | Список активных каналов |
 | `информация()` | Информация об узле кластера |
 | `пакет (BatchCommand ...)` | Несколько команд в одном запросе | @@ЛИНИЯ@@
### Выпуск токена JWT
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
### Прокси-события
Centrifugo может проксировать события жизненного цикла соединения на ваш сервер через HTTP. Настройте конечные точки в `centrifugo.json`:

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
Реализуйте интерфейс обработчика в своем приложении:

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
Привяжите свой обработчик к контейнеру DI:

```php
// config/common/di/centrifugo.php
use Rasuvaeff\Yii3Centrifugo\Proxy\Handler\ConnectProxyHandler;

return [
    ConnectProxyHandler::class => AppConnectHandler::class,
];
```
#### Доступные обработчики прокси
| Интерфейс обработчика | Класс действия | Событие Центрифуго |
 |---|---|---|
 | `ConnectProxyHandler` | `ConnectAction` | Клиент подключается |
 | `RefreshProxyHandler` | `ОбновитьДействие` | Обновление соединения |
 | `SubscribeProxyHandler` | `ПодписатьДействие` | Клиент подписывается на канал |
 | `PublishProxyHandler` | `ПубликацияДействие` | Клиент публикует в канале |
 | `SubRefreshProxyHandler` | `SubRefreshAction` | Обновление подписки |
 | `RpcProxyHandler` | `RpcAction` | Клиентский вызов RPC | @@ЛИНИЯ@@
#### Типы ответов прокси
| Тип | Конверт JSON | Диапазон кодов |
 |---|---|---|
 | `ProxyResult(массив $data)` | `{"результат": {...}}` | — |
 | `ProxyError(int $code, string $message)` | `{"ошибка": {"код": N, "сообщение": "..."}}` | 400–1999 |
 | `ProxyDisconnect(int $code, string $reason)` | `{"disconnect": {"код": N, "причина": "..."}}` | 4000–4999 | @@ЛИНИЯ@@
## Безопасность
- Конечные точки прокси-сервера должны быть доступны только с сервера Centrifugo (сетевой список управления доступом или общий секретный заголовок через конфигурацию `proxy.http_headers`).
 — секрет HMAC и ключ API вводятся из params/env, а не запрограммированы жестко.
 — `CentrifugoApiException` выдается при ошибках API Centrifugo (ненулевая `error` в ответе).
 — `ProxyError` и `ProxyDisconnect` проверяют диапазоны кода в конструкторах — недопустимые коды вызывают `InvalidArgumentException`. @@ЛИНИЯ@@
## Примеры
См. [`examples/`](examples/) для ознакомления с работоспособными скриптами. @@ЛИНИЯ@@
## Разработка
```bash
make install
make build
make cs-fix
make psalm
make test
```
На хосте нет PHP или Composer — все команды выполняются внутри Docker-контейнера `composer:2`. @@ЛИНИЯ@@
## Лицензия
BSD-3-пункт. См. [LICENSE.md](LICENSE.md).
