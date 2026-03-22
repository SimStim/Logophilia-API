# Logophilia-API

## Security
This API is restricted to requests originating from `logophilia.eu`.
- **CORS**: Allowed for `https://logophilia.eu` and `https://www.logophilia.eu`.
- **Referrer Check**: Requests without an `Origin` header (e.g., direct navigation) must have a `Referer` from the allowed domains.
- **Middleware**: Handled by `App\Middleware\CorsMiddleware`.
