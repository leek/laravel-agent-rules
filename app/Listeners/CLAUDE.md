# Listeners

**Purpose:** handle a dispatched event. Default home for fan-out side effects (sending mail, recording analytics, mirroring data to a downstream system).

## Naming

- **MUST** be a descriptive verb-phrase class (e.g. `SendOrderConfirmation`, `RecordPaymentMetric`). No `Listener` suffix required.

## Auto-discovery (Laravel 11+)

Laravel auto-discovers classes under `app/Listeners/` that define a `handle*` method with a **type-hinted** event parameter:

```php
final class SendOrderConfirmation
{
    public function handle(OrderPlaced $event): void
    {
        // ...
    }
}
```

- **MUST** type-hint the event parameter. An untyped `handle($event)` is silently skipped by auto-discovery.

❌ Untyped — auto-discovery can't match it to an event, so it never fires:

```php
public function handle($event): void { /* ... */ }
```

✅ Type-hint the event:

```php
public function handle(OrderPlaced $event): void { /* ... */ }
```

- **MUST NOT** also call `Event::listen(OrderPlaced::class, SendOrderConfirmation::class)` in `EventServiceProvider` — double registration fires the listener twice (silent duplicate side effects).

Verify what's actually registered:

```bash
php artisan tinker --execute 'foreach (app("events")->getRawListeners()["App\\Events\\OrderPlaced"] ?? [] as $l) { var_dump($l); }'
```

## Multi-method listeners

A single class may expose multiple `handle*` methods, each typed to a different event:

```php
final class OrderActivityListener
{
    public function handleOrderPlaced(OrderPlaced $event): void { /* ... */ }
    public function handleOrderShipped(OrderShipped $event): void { /* ... */ }
}
```

Each method is registered against its own type-hinted event.

## Queued listeners

For slow listeners (HTTP calls, mail, downstream writes), **MUST** implement `ShouldQueue`:

```php
final class SendOrderConfirmation implements ShouldQueue
{
    public int $tries = 3;
    public int $backoff = 60;

    public function handle(OrderPlaced $event): void { /* ... */ }

    public function failed(OrderPlaced $event, Throwable $e): void
    {
        logger()->error('SendOrderConfirmation failed', [
            'order' => $event->order->id,
            'error' => $e->getMessage(),
        ]);
    }
}
```

- **`ShouldQueueAfterCommit`** — like `ShouldQueue` but the listener is only queued after the outer DB transaction commits. Use whenever the event is dispatched from inside a transaction and the listener depends on rows written there.
- **`viaQueue(): string`** — choose a non-default queue.
- **`viaConnection(): string`** — choose a non-default connection.
- **`shouldQueue(Event $event): bool`** — conditional queueing; return `false` to skip.

## Rules

- **SHOULD** keep listeners thin — wrap a single Action when logic grows beyond a few lines.
- **SHOULD** prefer a queued listener over a synchronous one when the side effect is not user-facing.
- **MUST** declare `failed()` on queued listeners; never silently swallow failures.
