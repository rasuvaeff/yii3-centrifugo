# Examples

| Script | Shows | Needs server? |
|---|---|---|
| `publish.php` | Retry-safe publish (`PublishOptions` with `idempotencyKey`) and `CentrifugoException` handling | Yes (Centrifugo v6); without one it shows the transport-error path |
| `token.php` | Issue connection and subscription JWT | No |
| `proxy-handler.php` | Skeleton connect proxy handler | No |
| `testing.php` | Unit-test publishing code with `InMemoryCentrifugoClient` | No |

## Setup

Pass your Centrifugo settings as environment variables:

```bash
CENTRIFUGO_API_URL=http://localhost:8000 \
CENTRIFUGO_API_KEY=your-api-key \
php examples/publish.php

CENTRIFUGO_SECRET=your-hmac-secret-at-least-32-bytes \
php examples/token.php
```

No server needed for `token.php`, `proxy-handler.php` and `testing.php`.
