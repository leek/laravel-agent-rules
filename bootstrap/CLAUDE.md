# Application bootstrap

**Purpose:** configure routing, middleware, exceptions, and application providers in the modern Laravel 12/13 skeleton.

- Register routing in `bootstrap/app.php` with `withRouting()`. Read `routes/CLAUDE.md` for binding, authorization, and scheduling conventions.
- Register middleware aliases and group additions with `withMiddleware()`. Read `app/Http/Middleware/CLAUDE.md`; preserve the framework priority list with `prependToPriorityList()` / `appendToPriorityList()` unless deliberately replacing the complete list.
- Configure reporting and rendering with `withExceptions()`. Read `app/Exceptions/CLAUDE.md`; API responses use stable error codes and translated messages, with technical details confined to logs.
- Register application providers in `bootstrap/providers.php`; keep bindings and boot-time setup in providers (`app/Providers/CLAUDE.md`).
- Preserve existing routing and middleware configuration when adding a hook. The modern skeleton has no application `Http/Kernel.php`, `Console/Kernel.php`, or `Exceptions/Handler.php`; configure the bootstrap hooks instead.
- API and broadcasting route files are optional. Use `php artisan install:api` / `php artisan install:broadcasting` when the feature is required, then inspect the generated bootstrap configuration before adding duplicate wiring.
