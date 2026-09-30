# Notifications

## Naming

- **MUST** be event-like, **no suffix** (e.g. `InvoicePaid`, `OrderShipped`, `PasswordReset`).
- **MUST NOT** use a `Mail` / `Mailable` suffix — those names belong to `app/Mail/` classes. Prefer a Notification for "tell this user something happened"; reach for a `*Mail` mailable only for non-user recipients or bespoke single-channel mail — see `app/Mail/CLAUDE.md`.
- When an Event and a Notification both describe the same occurrence, **SHOULD** keep class names distinct across namespaces so `use` imports stay clear — see `app/Events/CLAUDE.md`.

## Channels

- **MUST** declare delivery channels via `via($notifiable): array`. Common channels: `mail`, `database`, `broadcast`, `slack`, custom.

```php
public function via(object $notifiable): array
{
    return ['mail', 'database'];
}
```

## Per-channel queues — `viaQueues()`

```php
public function viaQueues(): array
{
    return [
        'mail'     => 'mail-queue',
        'database' => 'default',
    ];
}
```

## Transaction safety — `afterCommit()`

For a queued notification that depends on rows written inside a DB transaction, use `Queueable::afterCommit()`:

```php
$user->notify((new InvoicePaid($invoice))->afterCommit());
```

Alternatively, call `$this->afterCommit()` in the notification constructor. Do not redeclare `Queueable`'s `$afterCommit` property with a type. Without deferral, the worker can deliver before the parent transaction commits and observe missing rows. This only defers queued notifications (`ShouldQueue`), not synchronous delivery.

## Conditional delivery — `shouldSend()`

When delivery depends on user preferences or notifiable state, **MUST** use `shouldSend()` rather than filtering at the call site:

```php
public function shouldSend(object $notifiable, string $channel): bool
{
    return $notifiable->prefersChannel($channel);
}
```

## Bulk send

- **PREFER** `Notification::send($users, new InvoicePaid($invoice))` (or `sendNow`) over a hand-rolled loop — clearer and keeps Laravel's notification pipeline in one place.
- For `ShouldQueue` notifications, Laravel still enqueues **one job per notifiable** either way; `send` is not a bulk-performance optimization.

## On-demand notifications

For notifications to a non-model recipient (one-off email, external Slack channel):

```php
Notification::route('mail', 'ops@example.com')
    ->route('slack', '#alerts')
    ->notify(new SystemAlert($payload));
```

## `toArray()` — database channel payload

- **MUST** return only JSON-serializable scalars / arrays. Do NOT include model instances — the payload is stored raw in the `notifications` table.
- **SHOULD** include the model id + the data needed for the UI; rehydrate the model at read time if more is needed.
- **MUST** use `__()` for any user-facing copy inside mail/database/broadcast content methods — see `app/CLAUDE.md`.

## Custom channels

A custom channel needs:

1. A class with `send($notifiable, Notification $notification): void`.
2. A `routeNotificationFor{Channel}()` method on the notifiable model returning the address/token.
