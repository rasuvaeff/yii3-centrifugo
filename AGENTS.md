# AGENTS.md — yii3-centrifugo

Guidance for AI agents working on this package. Read before changing code.

## What this is

Centrifugo v6 integration for Yii3. Three surfaces: `CentrifugoClient` (full OSS HTTP API), `ConnectionTokenIssuer`/`SubscriptionTokenIssuer` (HMAC JWT), and PSR-15 proxy actions (`ConnectAction`, `SubscribeAction`, `PublishAction`, `RefreshAction`, `SubRefreshAction`, `RpcAction`).

Namespace: `Rasuvaeff\Yii3Centrifugo`. Targets Centrifugo **v6** only.

## Golden rules

1. **Verification is mandatory.** Never claim "done" without a fresh green `composer build`. "Should work" does not count.
2. **No suppressions.** No `@psalm-suppress`, no baseline. Fix the root cause.
3. **Centrifugo v6 only.** Wire format (proxy envelope, JWT claims, API paths) changed between majors. Do not add v3/v4/v5 compatibility shims.
4. **Preserve the public contract.** Update README + tests with any API change.

## Commands

No PHP/Composer on the host — run in Docker via the `composer:2` image.

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
docker run --rm -v "$PWD":/app -w /app composer:2 composer cs:fix
docker run --rm -v "$PWD":/app -w /app composer:2 composer psalm
docker run --rm -v "$PWD":/app -w /app composer:2 composer test
docker run --rm -v "$PWD":/app -w /app composer:2 composer release-check
```

Or with Make:

```bash
make build
make cs-fix
make psalm
make test
make test-coverage
make mutation
make release-check
```

## Invariants & gotchas

- **Proxy response is always HTTP 200.** Centrifugo reads `{"result":{}}`, `{"error":{"code":N,"message":"..."}}`, or `{"disconnect":{"code":N,"reason":"..."}}` from the body. HTTP 4xx/5xx are treated as hard failures by Centrifugo.
- **Handler interfaces are NOT bound in `config/di.php`.** The application binds `ConnectProxyHandler::class => AppHandler::class`. Never add handler bindings to the package di.php — `ConfigWiringTest::diKeysDoNotOverlapHandlerInterfaces` enforces this.
- **ProxyError code: 400–1999. ProxyDisconnect code: 4000–4999.** Validated in constructors.
- **JWT secret min length.** `lcobucci/jwt` requires HMAC key to be non-empty. If params are misconfigured, the DI factory throws at instantiation time.
- **PSR-18 client comes from the app.** `CentrifugoClient` is transport-agnostic; no guzzle/curl in `require`.
- Code: `declare(strict_types=1)`, `final readonly class`, `#[\Override]`, explicit types.
- `examples/` is part of the public contract: keep scripts runnable and update `examples/README.md` when example usage changes.

## When you finish

- Update `README.md` (and `examples/` if usage changed); update `CHANGELOG.md` when releasing.
- Re-run `composer build`; if the change affects public API or release safety, also run `make release-check`. Paste the output.
