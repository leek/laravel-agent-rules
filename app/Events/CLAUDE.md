# Events & Listeners

**Purpose:** fan-out notifications, broadcasting, decoupling cross-cutting side effects.

## Naming

- **MUST** name events `{Subject}{PastTense}Event` — e.g. `UserCreatedEvent`, `OrderShippedEvent`. (Or the unsuffixed form if the team has standardised on it — pick one and stay consistent.)
- Listeners live in `app/Listeners/`.

## Rules

- **PREFER** an Action over an Event for synchronous business logic — Events are reserved for genuine one-to-many notifications.
- **SHOULD** use Events for: broadcasting (WebSockets), webhooks/notifications, plug-in points for unrelated features.

## Create

```bash
php artisan make:event OrderCreated
php artisan make:listener SendOrderCreatedNotification --event=OrderCreated
```

## Dispatch

```php
OrderCreated::dispatch();
// or
event(new OrderCreated());
```

## Transaction safety — `ShouldDispatchAfterCommit`

When the event is fired from inside a DB transaction and listeners depend on rows written there, **MUST** implement `ShouldDispatchAfterCommit` on the event so dispatch is deferred until the outer transaction commits.

```php
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final class OrderCreated implements ShouldDispatchAfterCommit
{
    public function __construct(public readonly Order $order) {}
}
```

Without it, a sync listener can run mid-transaction and observe a partial state; a queued listener can be picked up before the parent transaction commits. The job-side analogue is `->afterCommit()` / `$afterCommit = true` (see `app/Jobs/CLAUDE.md`).
