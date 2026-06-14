# Jobs (Queues)

**Purpose:** background or deferred work.

## Naming

- **MUST** be `{Verb}{Noun}Job` (e.g. `SendEmailJob`, `ProcessPaymentJob`, `GenerateReportJob`).

## Rules

- **SHOULD** wrap a single Action — the job is the queue boundary; the Action is the logic.
- **MUST** be idempotent or safe to retry.
- **MUST** declare failure handling: a `failed(Throwable $e)` method that logs context (job id, model id, message); never silently swallow.
- **MUST** set retry policy: `$tries`, `$backoff`, `$timeout`, `$maxExceptions`.
- **SHOULD** prefer an array `$backoff` for exponential retries: `public int|array $backoff = [60, 120, 300];`

## Dispatching inside a DB transaction

- **MUST** dispatch with `->afterCommit()` when the job depends on rows written in the current transaction. Without it, the worker can pick the job up before the parent transaction commits and observe missing rows.

❌ Dispatched mid-transaction — a fast worker runs before the commit and can't find the order:

```php
DB::transaction(function () use ($order) {
    $order->save();
    ProcessOrderJob::dispatch($order);
});
```

✅ Defer until the transaction commits:

```php
DB::transaction(function () use ($order) {
    $order->save();
    ProcessOrderJob::dispatch($order)->afterCommit();
});
```

Alternatively, set `public bool $afterCommit = true;` on the job class to default every dispatch.

## Uniqueness — `ShouldBeUnique`

Use when re-dispatching the same logical job would cause duplicate side effects (double-charge, duplicate email, repeated webhook). Provide an explicit lock key:

```php
final class ProcessPaymentJob implements ShouldQueue, ShouldBeUnique
{
    public int $uniqueFor = 60; // seconds the lock is held

    public function __construct(public readonly Order $order) {}

    public function uniqueId(): string
    {
        return (string) $this->order->id;
    }
}
```

- Use `ShouldBeUniqueUntilProcessing` when duplicates only matter while the job is queued (lock releases once a worker picks it up).

## No-overlap — `WithoutOverlapping`

Use when concurrent runs of the same job against the same resource are unsafe (mutating shared state, calling a single-flight external API):

```php
public function middleware(): array
{
    return [new WithoutOverlapping($this->order->id)];
}
```

## Rate limiting external calls

```php
public function middleware(): array
{
    return [(new RateLimited('mailgun'))->releaseAfterOneMinute()];
}
```

For Redis-backed throttling inside the handler:

```php
Redis::throttle('stripe')->allow(10)->every(60)->then(
    fn () => $this->callStripe(),
    fn () => $this->release(10),
);
```

## Batches and chains

- **`Bus::batch([...])`** — parallel jobs needing aggregate completion (`then`/`catch`/`finally`).
- **`Bus::chain([...])`** — ordered jobs where each depends on the previous.

```php
Bus::batch([
    new ImportRowJob($a),
    new ImportRowJob($b),
])
->then(fn (Batch $batch) => Log::info('done', ['id' => $batch->id]))
->catch(fn (Batch $batch, Throwable $e) => report($e))
->allowFailures()
->dispatch();
```

## Conditional dispatch

```php
ProcessOrderJob::dispatchIf($order->isPaid(), $order);
ProcessOrderJob::dispatchUnless($order->isCancelled(), $order);
```

## Time-bounded retries — `retryUntil()`

Instead of a fixed `$tries`, expire retries at a wall-clock deadline:

```php
public function retryUntil(): DateTime
{
    return now()->addHours(2);
}
```

## Short-circuiting — `$this->fail()`

For unrecoverable errors (validation failure, missing prerequisite), call `$this->fail('msg')` to stop further retries without throwing:

```php
public function handle(): void
{
    if (! $this->order->paymentMethod) {
        $this->fail('Order has no payment method — manual intervention required.');
        return;
    }
    // ...
}
```

## Repeated-exception backoff — `ThrottlesExceptions`

When the same error keeps recurring (a downstream service flapping), back off retries instead of hammering:

```php
public function middleware(): array
{
    // After 3 exceptions, sleep 5 minutes before retrying
    return [new ThrottlesExceptions(3, 5)];
}
```

## Idempotency — atomic claim pattern

For "exactly-once" side effects against a row, **MUST** use an atomic conditional update and check the affected count rather than read-then-write:

```php
$affected = Order::query()
    ->whereKey($this->order->id)
    ->whereNull('paid_at')
    ->update(['paid_at' => now(), 'payment_id' => $this->paymentId]);

if ($affected === 0) {
    // Another worker won the race — already processed.
    return;
}
```

## Batching

- **MUST** add the `Illuminate\Bus\Batchable` trait to jobs intended to run inside `Bus::batch([...])`.
- Inside the job, guard with `if ($this->batch()?->cancelled()) return;` to bail out cleanly when the batch is cancelled.

```php
final class ImportRowJob implements ShouldQueue
{
    use Batchable, Queueable, Dispatchable, InteractsWithQueue, SerializesModels;

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }
        // ...
    }
}
```

## Queue selection

Reserve named queues for priority bands; dispatch with `->onQueue('high')`:

```php
SendWelcomeEmailJob::dispatch($user)->onQueue('high');
```

## Trim serialized payload — `#[WithoutRelations]`

When a job constructor accepts a model that was eager-loaded upstream, the relations get serialized into the queue payload — bloating Redis/DB and risking stale relations at run time. Annotate the param with `#[WithoutRelations]` to strip them before serialization:

```php
use Illuminate\Queue\Attributes\WithoutRelations;

public function __construct(
    #[WithoutRelations] public readonly Order $order,
) {}
```

Class-wide form: add the `Illuminate\Queue\Attributes\WithoutRelations` attribute on the class, or `use SerializesModels;` with `protected $deleteWhenMissingModels = true;` to bail cleanly when the model has been deleted before the worker picks the job up.

## Missing-model handling — `#[DeleteWhenMissingModels]`

Set `public bool $deleteWhenMissingModels = true;` (or annotate the class with `#[DeleteWhenMissingModels]` on queued listeners) so the job/listener is silently dropped if its serialized model row was deleted between dispatch and execution — instead of failing every retry with `ModelNotFoundException`.
