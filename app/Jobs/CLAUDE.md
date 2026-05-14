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

```php
ProcessOrderJob::dispatch($order)->afterCommit();
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

## Queue selection

Reserve named queues for priority bands; dispatch with `->onQueue('high')`:

```php
SendWelcomeEmailJob::dispatch($user)->onQueue('high');
```
