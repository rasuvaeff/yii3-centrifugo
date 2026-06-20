# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.0.0 — 2026-06-27

- `CentrifugoClient`: PSR-18 HTTP client for the Centrifugo v6 server API (`publish`, `broadcast`, `subscribe`, `unsubscribe`, `disconnect`, `refresh`, `presence`, `presenceStats`, `history`, `historyRemove`, `channels`, `batch`).
- `BatchCommand`: value object for batching multiple API commands in one request.
- `CentrifugoApiException`: typed exception carrying the Centrifugo error code.
- `ConnectionTokenIssuer` / `SubscriptionTokenIssuer`: JWT issuers for connection and channel-subscription tokens (HMAC-SHA256 via `lcobucci/jwt`).
- PSR-15 proxy handler interfaces: `ConnectProxyHandler`, `RefreshProxyHandler`, `SubscribeProxyHandler`, `SubRefreshProxyHandler`, `PublishProxyHandler`, `RpcProxyHandler`.
- PSR-15 proxy actions: `ConnectAction`, `RefreshAction`, `SubscribeAction`, `SubRefreshAction`, `PublishAction`, `RpcAction` — parse the incoming Centrifugo proxy request, delegate to the handler, and return the JSON envelope.
- Typed proxy request / response VOs: `ConnectRequest`, `RefreshRequest`, `SubscribeRequest`, `SubRefreshRequest`, `PublishRequest`, `RpcRequest`, `ProxyResult`, `ProxyError`, `ProxyDisconnect`.
- Yii3 DI config (`config/di.php`, `config/params.php`) — zero-config wiring for all services when `centrifugo.api_url`, `centrifugo.api_key`, and `centrifugo.secret` are provided in params.
