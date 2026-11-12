# Notifications

## Naming

- **MUST** be event-like, **no suffix** (e.g. `InvoicePaid`, `OrderShipped`, `PasswordReset`).
- **MUST NOT** use a `Mail` / `Mailable` suffix — those names belong to `app/Mail/` classes. Prefer a Notification for "tell this user something happened"; reach for a `*Mail` mailable only for non-user recipients or bespoke single-channel mail — see `app/Mail/CLAUDE.md`.
- When an Event and a Notification both describe the same occurrence, **SHOULD** keep class names distinct across namespaces so `use` imports stay clear — see `app/Events/CLAUDE.md`.

## Channels

- **MUST** declare delivery channels via `via($notifiable): array`. Common channels: `mail`, `database`, `broadcast`, `slack`, custom.
- **MUST** implement `ShouldQueue` and use `Queueable` for notifications doing network delivery (mail, SMS, Slack, external custom channels) from web/Livewire requests. Database-only notifications may be synchronous. `viaQueues()` selects queues but does not enable queueing without `ShouldQueue`.
- When already on a worker, choose the delivery boundary deliberately: a queued notification adds jobs; `Notification::sendNow()` delivers synchronously there when another queue hop is unnecessary. Do not use `sendNow()` to block a web request on external delivery.

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

**MUST NOT** keep per-send state on the channel instance (a resolved tenant, branding, a locale). `ChannelManager` builds each channel once and reuses it for every later notification in the process, so on a queue worker one send's state leaks into the next. Pass the value through the message or view data, or clear it in a `finally` after each send.

## Snapshot what the notification describes

A queued notification's model properties are re-fetched fresh when it is delivered. **MUST** capture as scalars in the constructor the values that describe the event (who did it, old and new value, the time), when that state can change before delivery. Otherwise a second change made before delivery is reported under the first actor, with the wrong values.

```php
public function __construct(public Booking $booking, public string $movedBy, public CarbonImmutable $movedTo) {}
```

Use the model only for routing and links.

## Properties on a queued notification

A queued notification is unserialized without its constructor running, so a property added after payloads are already on the queue stays uninitialized for them. **MUST** declare new properties outside the constructor with a default, **MUST NOT** make them `readonly` (a readonly property cannot have a default), and **SHOULD** make snapshot properties nullable and fall back to a live read when null. See **Payload compatibility across deploys** in `app/Jobs/CLAUDE.md`.
