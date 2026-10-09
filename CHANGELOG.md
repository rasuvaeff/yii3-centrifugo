# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.1.0 — 2026-10-10

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
- `ConnectionTokenIssuer` and `SubscriptionTokenIssuer` accept an optional
  `?Psr\Clock\ClockInterface $clock = null` (#13); `config/di.php` passes the
  container's `ClockInterface` when the application binds one and falls back
  to the system clock otherwise. `exp` is now whole seconds (it used to carry
  the microseconds of `now`, encoded as a JSON float). `psr/clock` and
  `psr/container` are now explicit requirements.
- Params are validated when the container builds the service that uses them
  (#14): `api_url` must be an http(s) URL with a host, `api_key` a string,
  `token_hmac_secret` at least 32 bytes, `token_ttl` an integer > 0. A
  violation throws the new `InvalidConfigException` naming the params key
  instead of a low-level `lcobucci/jwt` error at first use. An empty `api_key`
  stays valid (Centrifugo `api_insecure`), and the client still resolves with
  an unset token secret.
- New params key `http_client` (#16): the container id of the PSR-18 client
  `CentrifugoClient` uses, `Psr\Http\Client\ClientInterface::class` by
  default. Point it at a dedicated client with its own timeouts; an id that
  does not resolve to a PSR-18 client throws `InvalidConfigException`.
- Params moved to the vendor/package key `params['rasuvaeff/yii3-centrifugo']`
  (#17), consistent with other `rasuvaeff/*` packages. The legacy
  `params['centrifugo']` key is deprecated (removed in 2.0) but still read:
  each value it sets overrides the same value under the new key, so an
  application that overrides only the old key keeps its settings while the
  package defaults under the new key fill the rest. Validation messages name
  the key the value came from.
- Added `Proxy\ProxySecretMiddleware` (#15), a PSR-15 middleware for the
  proxy endpoints: compares a header (default `X-Centrifugo-Proxy-Secret`)
  with the shared secret using `hash_equals` and answers HTTP 403 before the
  action otherwise. Fail-closed: an empty secret is refused at construction.
  New params `proxy_secret` and `proxy_secret_header`; DI definition included.
  `psr/http-server-middleware` is now required.
- Added the `centrifugo:doctor` console command (#18): checks API params,
  token params, server API reachability and API key (`info()`), and a
  connection-token round trip; prints one line per check and exits with 0
  (healthy), 2 (config) or 4 (upstream). The logic lives in the
  console-free `Doctor\CentrifugoDoctor`. `symfony/console` and
  `yiisoft/yii-console` are suggestions only; the command is registered through
  the `params-console` / `di-console` config-plugin groups.

## 1.0.0 — 2026-06-27

- `CentrifugoClient`: PSR-18 HTTP client for the Centrifugo v6 server API (`publish`, `broadcast`, `subscribe`, `unsubscribe`, `disconnect`, `refresh`, `presence`, `presenceStats`, `history`, `historyRemove`, `channels`, `batch`).
- `BatchCommand`: value object for batching multiple API commands in one request.
- `CentrifugoApiException`: typed exception carrying the Centrifugo error code.
- `ConnectionTokenIssuer` / `SubscriptionTokenIssuer`: JWT issuers for connection and channel-subscription tokens (HMAC-SHA256 via `lcobucci/jwt`).
- PSR-15 proxy handler interfaces: `ConnectProxyHandler`, `RefreshProxyHandler`, `SubscribeProxyHandler`, `SubRefreshProxyHandler`, `PublishProxyHandler`, `RpcProxyHandler`.
- PSR-15 proxy actions: `ConnectAction`, `RefreshAction`, `SubscribeAction`, `SubRefreshAction`, `PublishAction`, `RpcAction` — parse the incoming Centrifugo proxy request, delegate to the handler, and return the JSON envelope.
- Typed proxy request / response VOs: `ConnectRequest`, `RefreshRequest`, `SubscribeRequest`, `SubRefreshRequest`, `PublishRequest`, `RpcRequest`, `ProxyResult`, `ProxyError`, `ProxyDisconnect`.
- Yii3 DI config (`config/di.php`, `config/params.php`) — zero-config wiring for all services when `centrifugo.api_url`, `centrifugo.api_key`, and `centrifugo.secret` are provided in params.
