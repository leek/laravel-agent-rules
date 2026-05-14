# Middleware

**Purpose:** filter or mutate requests for a defined group of routes.

## Naming

- **MUST** name middleware descriptively, **no suffix** (`Authentication`, `RateLimit`, `Cors`, `HandleLocale`).

## Rules

- **SHOULD** create middleware when the same pre/post-processing applies to a *set* of routes. For one-off logic, put it in the controller/action.
- **MUST** return a `Response` from `handle()`; either call `$next($request)` or short-circuit explicitly.

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
