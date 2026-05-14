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

- **MUST** keep URL segments lowercase and plural.
- **MUST** give every route a name; use dot notation.
- **MUST** group routes by entity, then nest middleware/prefix groups outside the entity group.
- **SHOULD** prefer resource routes for CRUD: `Route::resource('users', UserController::class)`.
- **SHOULD** use route-model binding (`/users/{user}`) over manual lookups.
- **MUST** use `Route::scopeBindings()` (or `->scopeBindings()` on a group) for nested routes — enforces the parent-child relationship and prevents cross-tenant access.
- **MUST** use a single parameter name that matches the bound model (`{conversation}` resolves to `Conversation`). Reusing a different name silently resolves to `null`.

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

For complex limiters, define them in `App\Providers\RouteServiceProvider::configureRateLimiting()` and reference by name (`throttle:api`).

## Custom parameter → model binding

To resolve `{conversation}` to a class with a different name, register an explicit binding:

```php
Route::model('conversation', AiConversation::class);
```

For non-trivial resolution logic, use `Route::bind()` or override `resolveRouteBinding()` on the model.

> Middleware rules live in `app/Http/Middleware/CLAUDE.md`.
