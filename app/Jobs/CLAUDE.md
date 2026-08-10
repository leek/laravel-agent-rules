# Jobs (Queues)

**Purpose:** background or deferred work.

## Naming

- **MUST** be `{Verb}{Noun}Job` (e.g. `SendEmailJob`, `ProcessPaymentJob`, `GenerateReportJob`).

## Rules

- **SHOULD** wrap a single Action — the job is the queue boundary; the Action is the logic.
- **MUST** be idempotent or safe to retry.
- **MUST** be intentional about failure — either declare a retry/failure policy on the job or accept framework defaults deliberately. Never silently swallow unrecoverable errors.
- **MUST** implement `failed(Throwable $e)` when the job has side effects worth ops attention (charges, emails, external writes) — log context (job id, model id, message). One-shot internal jobs may omit it.
- **SHOULD** set `$tries`, `$backoff`, and `$timeout` for jobs that call external I/O or may hang. Prefer an array `$backoff` for exponential retries: `public int|array $backoff = [60, 120, 300];`. Use `$maxExceptions` when you need a different budget for exception-driven failures vs total attempts.

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

## Rate limiting, batches, and retries (cheatsheet)

| Need | Use |
| ---- | --- |
| Cap external API rate | Job middleware `RateLimited('mailgun')`, or `Redis::throttle(...)->then(...)` in `handle` |
| Parallel work + aggregate completion | `Bus::batch([...])->then(...)->catch(...)->dispatch()`; job **MUST** `use Batchable` and guard `if ($this->batch()?->cancelled()) return;` |
| Ordered sequential work | `Bus::chain([...])` |
| Conditional dispatch | `dispatchIf` / `dispatchUnless` |
| Wall-clock retry budget | `retryUntil(): DateTime` instead of fixed `$tries` |
| Unrecoverable stop (no more retries) | `$this->fail('reason')` |
| Downstream flapping | middleware `new ThrottlesExceptions(3, 5)` (exceptions → sleep minutes) |

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

Class-wide form: put `#[WithoutRelations]` on the job class so every serialized model property is stripped of relations.

This only controls what is stored in the payload. It does **not** decide what happens when the model row is gone at run time — see missing-model handling below.

## Missing-model handling — `#[DeleteWhenMissingModels]`

When the serialized model may be deleted between dispatch and execution, **MUST** drop the job instead of retrying forever on `ModelNotFoundException`:

- Property / class: `public bool $deleteWhenMissingModels = true;` (jobs that `use SerializesModels`)
- Attribute form (queued listeners and jobs): `#[DeleteWhenMissingModels]`

```php
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

#[DeleteWhenMissingModels]
final class ProcessOrderJob implements ShouldQueue
{
    use Queueable, Dispatchable, InteractsWithQueue, SerializesModels;

    public function __construct(
        #[WithoutRelations] public readonly Order $order,
    ) {}
}
```