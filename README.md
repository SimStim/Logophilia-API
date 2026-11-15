# Logophilia-API

Just a simple API for Logophilia.EU. No framework, no external dependencies.

## Security

This API is restricted to requests originating from `logophilia.eu`.

- **CORS**: Allowed for `https://logophilia.eu` and `https://www.logophilia.eu`.
- **API Key**: All requests must include an `X-Api-key` header with a valid key. The key lives in the gitignored
  `.env` file, which is read directly as INI (`parse_ini_file()`) — no dotenv package.
- **Referrer Check**: Requests without an `Origin` header (e.g., direct navigation) must have a `Referer` from the
  allowed domains.
- **Middleware**: Handled by `App\Middleware\CorsMiddleware` and `App\Middleware\AuthMiddleware`.
- **Rate Limiting**: POST endpoints are limited per client IP and route (4 per hour) by `App\Middleware\RateLimitMiddleware`.
- **Tests**: `php tests/run.php` runs the test suite (guards, atomic counters, rate limiter, email guard, API key check).
