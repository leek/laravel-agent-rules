# Routing

## Route files

- **web** — browser HTTP, session/cookies, CSRF
- **api** — stateless HTTP, token auth
- **channels** — broadcasting (WebSockets)
- **console** — Artisan command routes

## Naming

| Entity     | Pattern                          | Examples                                            |
| ---------- | -------------------------------- | --------------------------------------------------- |
| Route URL  | lowercase, **plural**            | `/users`, `/products`, `/categories`                |
| Route name | `snake_case` with dot notation   | `users.show`, `products.index`, `categories.create` |

## Rules

- **MUST NOT** put business logic in route files. A route maps a URL to a controller, invokable, or single-action class — **AVOID** closures that contain logic beyond a one-line delegation (route caching breaks on closures, too).
- **MUST** keep URL segments lowercase and plural.
- **MUST** give every route a name; use dot notation.
- **MUST** group routes by entity, then nest middleware/prefix groups outside the entity group.
- **SHOULD** prefer resource routes for CRUD: `Route::resource('users', UserController::class)`.
- **SHOULD** use route-model binding (`/users/{user}`) over manual lookups.
- **MUST** use `Route::scopeBindings()` (or `->scopeBindings()` on a group) for nested routes — enforces the parent-child relationship and prevents cross-tenant access.
- **MUST** use a single parameter name that matches the bound model (`{conversation}` resolves to `Conversation`). Reusing a different name silently resolves to `null`.
- **MUST** constrain free-form route parameters at the route boundary. Prefer typed helpers (`whereNumber()`, `whereUuid()`, `whereAlpha()`, `whereAlphaNumeric()`, `whereIn()`) over regex when they express the rule; invalid values should 404 before the controller runs.

## Example

```php
Route::middleware('auth')->group(function () {
    Route::name('users.')->group(function () {
        Route::get('/', UserIndex::class)->name('index');
        Route::get('/{user}', UserShow::class)->name('show');
    });
});
```

Admin + API combo:

```php
Route::prefix('/admin')
    ->name('admin.')
    ->middleware(HandleLocale::class)
    ->group(function () {
        Route::resource('users', UserController::class);
        Route::resource('orders', OrderController::class);
    });
```

## Parameter constraints

```php
Route::get('/users/{user}', [UserController::class, 'show'])
    ->whereNumber('user')
    ->name('users.show');

Route::get('/docs/{locale}/{page}', [DocumentationController::class, 'show'])
    ->whereIn('locale', ['en', 'es', 'de'])
    ->whereAlphaNumeric('page')
    ->name('docs.show');
```

## Scoped nested routes

```php
Route::middleware('auth:sanctum')->prefix('conversations')->group(function () {
    Route::post('/', [ConversationController::class, 'store'])->name('conversations.store');

    Route::scopeBindings()->group(function () {
        Route::get('/{conversation}', [ConversationController::class, 'show'])
            ->name('conversations.show');

        Route::post('/{conversation}/messages', [MessageController::class, 'store'])
            ->name('conversation-messages.store');

        Route::get('/{conversation}/messages/{message}', [MessageController::class, 'show'])
            ->name('conversation-messages.show');
    });
});
```

## API versioning

Version public APIs via a prefix + namespace group. Per-version controllers live under `App\Http\Controllers\Api\V{N}\`:

```php
Route::prefix('v1')
    ->name('v1.')
    ->namespace('App\Http\Controllers\Api\V1')
    ->middleware(['api', 'auth:sanctum', 'throttle:60,1'])
    ->group(base_path('routes/api/v1.php'));
```

- **MUST** version any externally-consumed API. Breaking changes ship as `v2`, never as silent edits to `v1`.
- **SHOULD** keep per-version route files under `routes/api/v{N}.php` rather than one giant `routes/api.php`.

## Rate limiting

- **MUST** apply `throttle:{max},{minutes}` middleware (or a named limiter) to every public, unauthenticated, or expensive endpoint.

```php
Route::middleware('throttle:5,1')->post('/login', LoginController::class);
```

For complex/named limiters, define them in `App\Providers\AppServiceProvider::boot()` with `RateLimiter::for(...)` and reference by name (`throttle:uploads`). The L11+ skeleton has no `RouteServiceProvider` — see `app/Http/Middleware/CLAUDE.md` for the `RateLimiter::for()` example.

## Custom parameter → model binding

To resolve `{conversation}` to a class with a different name, register an explicit binding:

```php
Route::model('conversation', AiConversation::class);
```

For non-trivial resolution logic, use `Route::bind()` or override `resolveRouteBinding()` on the model.

## Task scheduling (`routes/console.php`)

In Laravel 11+, scheduled tasks live in `routes/console.php` using the `Schedule` facade — **NOT** in `app/Console/Kernel.php` (which is removed from the L11+ skeleton).

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('telescope:prune')->daily();
Schedule::command('app:fetch-users')->everyFiveMinutes();
Schedule::call(fn () => Account::recompute())->hourly()->description('Recompute account balances');
```

Hard rules:

- **MUST** call `->withoutOverlapping()` on any task whose runtime may exceed its interval. Default lock holds 24h — pass `expiresAt: $minutes` to recover faster after a crashed worker.
- **MUST** call `->onOneServer()` in multi-server deployments. Requires a `redis`, `memcached`, or `database` cache driver.
- **SHOULD** chain monitoring hooks: `->before(fn)`, `->after(fn)`, `->onSuccess(fn)`, `->onFailure(fn)`.
- **SHOULD** integrate with a healthcheck service via `->pingOnSuccess('https://...')` / `->pingOnFailure('https://...')`.
- **SHOULD** scope env-specific tasks with `->environments(['production'])`.
- **SHOULD** call `->description('...')` on closure schedules so `php artisan schedule:list` is readable.
- **SHOULD** pin a `->timezone('America/New_York')` on time-sensitive tasks; defaults to the app timezone.
- **`->evenInMaintenanceMode()`** — for must-run-always tasks (billing, queue health pings).

Sub-minute frequencies (L11+): `everySecond()`, `everyTwoSeconds()`, `everyFiveSeconds()`, `everyTenSeconds()`, `everyThirtySeconds()`.

Dry-run a single task:

```bash
php artisan schedule:test
```

> Middleware rules live in `app/Http/Middleware/CLAUDE.md`.
