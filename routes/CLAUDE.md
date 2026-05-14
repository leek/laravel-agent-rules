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

> Middleware rules live in `app/Http/Middleware/CLAUDE.md`.
