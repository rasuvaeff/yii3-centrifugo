# Examples

| Script | Shows | Needs server? |
|---|---|---|
| `publish.php` | Publish a message via server API | Yes (Centrifugo v6) |
| `token.php` | Issue connection and subscription JWT | No |
| `proxy-handler.php` | Skeleton connect proxy handler | No |

## Setup

Copy `.env.example` to `.env` and fill in your Centrifugo settings, then:

```bash
CENTRIFUGO_API_URL=http://localhost:8000 \
CENTRIFUGO_API_KEY=your-api-key \
CENTRIFUGO_SECRET=your-hmac-secret \
php examples/publish.php
```

No server needed for `token.php` and `proxy-handler.php`.
