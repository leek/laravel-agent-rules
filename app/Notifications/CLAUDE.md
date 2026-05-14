# Notifications

## Naming

- **MUST** be event-like, **no suffix** (e.g. `InvoicePaid`, `OrderShipped`, `PasswordReset`).

## Listener auto-discovery (Laravel 12+)

Laravel 12+ auto-discovers classes in `app/Listeners/**` that define a `handle(Event $event)` method and registers them against the type-hinted event.

- **MUST NOT** also call `Event::listen()` for the same listener in `EventServiceProvider` — registering twice fires the listener twice (silent duplicate side effects: duplicate emails, duplicate rows, etc.).

Verify what's actually registered:

```bash
php artisan tinker --execute 'foreach (app("events")->getRawListeners()["Fully\\Qualified\\Event"] ?? [] as $l) { var_dump($l); }'
```
