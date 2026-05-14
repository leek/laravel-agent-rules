# Middleware

**Purpose:** filter or mutate requests for a defined group of routes.

## Naming

- **MUST** name middleware descriptively, **no suffix** (`Authentication`, `RateLimit`, `Cors`, `HandleLocale`).

## Rules

- **SHOULD** create middleware when the same pre/post-processing applies to a *set* of routes. For one-off logic, put it in the controller/action.
- **MUST** return a `Response` from `handle()`; either call `$next($request)` or short-circuit explicitly.

## Variadic params

Accept route-supplied arguments via variadic `string ...$args`:

```php
public function handle(Request $request, Closure $next, string ...$roles): Response
{
    abort_unless($request->user()?->hasAnyRole($roles), 403);

    return $next($request);
}
```

Pass from a route: `->middleware('role:admin,editor')`.

## `terminate()` — post-response work

Implement `terminate(Request, Response)` to run logic **after** the response has been sent to the client (logging, metrics, deferred cleanup). The auth user and DB connection are still available.

```php
public function terminate(Request $request, Response $response): void
{
    Log::channel('audit')->info('request', [
        'path'   => $request->path(),
        'status' => $response->getStatusCode(),
    ]);
}
```

## Registration — `bootstrap/app.php` (Laravel 11+)

Register middleware in `bootstrap/app.php` via `withMiddleware(fn (Middleware $m) => ...)`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'role'          => EnsureUserHasRole::class,
        'handle.locale' => HandleLocale::class,
    ]);

    $middleware->appendToGroup('web', [HandleLocale::class]);

    $middleware->group('admin', [
        'auth',
        EnsureUserHasRole::class . ':admin',
    ]);

    $middleware->priority([
        EncryptCookies::class,
        StartSession::class,
        // ...
    ]);
})
```

## Rate-limiter definitions

Define named limiters in `AppServiceProvider::boot()` and reference by name in routes (`throttle:uploads`):

```php
RateLimiter::for('uploads', function (Request $request) {
    return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
});
```

## Create

```bash
php artisan make:middleware HandleLocale
```

## Example

```php
class HandleLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
```
