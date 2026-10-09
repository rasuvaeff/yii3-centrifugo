# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

- Tests build their doubles with `rasuvaeff/understudy-testo` instead of
  hand-written anonymous classes: the PSR-18 client in `CentrifugoClientTest`
  and `DiContainerTest` (request capture via `Arg::captor()`), the six proxy
  handler doubles in the Action tests, and the never-called client in
  `ConfigWiringTest`, which is now a strict double that fails on any HTTP
  call. Dev-dependency only; the public contract is untouched.
- Rector runs `rasuvaeff/rector-named-literals` (`AddNameToLiteralArgumentRector`):
  boolean literal arguments are passed by name (`json_decode(..., associative: true)`).
  Dev-dependency only; no behaviour change.

- Fixed: `CentrifugoClient` no longer leaks non-package exceptions (#11). A
  PSR-18 client failure, a non-2xx HTTP status (e.g. 401 for a wrong
  `api_key`) or a body that is not a JSON object now throws the new
  `CentrifugoTransportException` (`getStatusCode()`, original error as
  `previous`) instead of `ClientExceptionInterface` / `JsonException`.
  `CentrifugoApiException` and `CentrifugoTransportException` share the new
  abstract base `CentrifugoException` (a `RuntimeException`, so existing
  `catch (\RuntimeException)` blocks keep working).
- Added `CentrifugoClientInterface` with every public method of
  `CentrifugoClient`, which now implements it (#10). `config/di.php` aliases
  the interface to the same shared `CentrifugoClient` instance; the class stays
  resolvable.
- Added `Testing\InMemoryCentrifugoClient`, an in-memory test double
  (`calls()`, `published()`, `publishedTo()`, `failNextWith()`, `reset()`), and
  `examples/testing.php`.
- Added `PublishOptions` and an optional `?PublishOptions $options = null`
  argument to `publish()` and `broadcast()` (client, interface, in-memory
  double) (#12): `idempotency_key`, `skip_history`, `tags`, `delta`,
  `version` / `version_epoch`; only non-default fields are sent, so existing
  calls send the same request body. The return type stays `array`.

## 1.0.0 — 2026-06-27

- `CentrifugoClient`: PSR-18 HTTP client for the Centrifugo v6 server API (`publish`, `broadcast`, `subscribe`, `unsubscribe`, `disconnect`, `refresh`, `presence`, `presenceStats`, `history`, `historyRemove`, `channels`, `batch`).
- `BatchCommand`: value object for batching multiple API commands in one request.
- `CentrifugoApiException`: typed exception carrying the Centrifugo error code.
- `ConnectionTokenIssuer` / `SubscriptionTokenIssuer`: JWT issuers for connection and channel-subscription tokens (HMAC-SHA256 via `lcobucci/jwt`).
- PSR-15 proxy handler interfaces: `ConnectProxyHandler`, `RefreshProxyHandler`, `SubscribeProxyHandler`, `SubRefreshProxyHandler`, `PublishProxyHandler`, `RpcProxyHandler`.
- PSR-15 proxy actions: `ConnectAction`, `RefreshAction`, `SubscribeAction`, `SubRefreshAction`, `PublishAction`, `RpcAction` — parse the incoming Centrifugo proxy request, delegate to the handler, and return the JSON envelope.
- Typed proxy request / response VOs: `ConnectRequest`, `RefreshRequest`, `SubscribeRequest`, `SubRefreshRequest`, `PublishRequest`, `RpcRequest`, `ProxyResult`, `ProxyError`, `ProxyDisconnect`.
- Yii3 DI config (`config/di.php`, `config/params.php`) — zero-config wiring for all services when `centrifugo.api_url`, `centrifugo.api_key`, and `centrifugo.secret` are provided in params.
