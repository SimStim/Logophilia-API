# Logophilia-API

## Security
This API is restricted to requests originating from `logophilia.eu`.
- **CORS**: Allowed for `https://logophilia.eu` and `https://www.logophilia.eu`.
- **API Key**: All requests must include an `X-Api-key` header with a valid key.
- **Referrer Check**: Requests without an `Origin` header (e.g., direct navigation) must have a `Referer` from the allowed domains.
- **Middleware**: Handled by `App\Middleware\CorsMiddleware` and `App\Middleware\AuthMiddleware`.
