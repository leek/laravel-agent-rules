# Notifications

## Naming

- **MUST** be event-like, **no suffix** (e.g. `InvoicePaid`, `OrderShipped`, `PasswordReset`).

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

## Transaction safety — `$afterCommit`

When dispatching a notification from inside a DB transaction, mirror the Jobs rule:

```php
public bool $afterCommit = true;
```

Without it, the worker can deliver before the parent transaction commits and observe missing rows.

## Conditional delivery — `shouldSend()`

When delivery depends on user preferences or notifiable state, **MUST** use `shouldSend()` rather than filtering at the call site:

```php
public function shouldSend(object $notifiable, string $channel): bool
{
    return $notifiable->prefersChannel($channel);
}
```

## Bulk send

- **MUST NOT** loop `$user->notify(...)` over a collection — issues one queue job per user.
- **MUST** use `Notification::send($users, new InvoicePaid($invoice))` for bulk dispatch.

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

## Custom channels

A custom channel needs:

1. A class with `send($notifiable, Notification $notification): void`.
2. A `routeNotificationFor{Channel}()` method on the notifiable model returning the address/token.
