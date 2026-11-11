# Jobs (Queues)

**Purpose:** background or deferred work.

## Naming

- **MUST** be `{Verb}{Noun}Job` (e.g. `SendEmailJob`, `ProcessPaymentJob`, `GenerateReportJob`).

## Rules

- **SHOULD** wrap a single Action — the job is the queue boundary; the Action is the logic.
- **MUST** be idempotent or safe to retry.
- **MUST** be intentional about failure — either declare a retry/failure policy on the job or accept framework defaults deliberately. Never silently swallow unrecoverable errors.
- **MUST** length-check strings that reach the job from outside without passing a FormRequest (a webhook payload, an imported file, an API response) against the column they are written to. PostgreSQL raises `value too long for type character varying(n)` instead of truncating, so the job fails every retry, after the sender was already told the delivery succeeded.
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

Alternatively, call `$this->afterCommit()` in the constructor of a job using `Queueable` to default every dispatch. Never redeclare the trait's `$afterCommit` property (see **Never redeclare a `Queueable` property** below). For a `ShouldBeUnique` job, see **`->afterCommit()` does not defer a `ShouldBeUnique` lock**.

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
- **MUST** use a cache store that supports atomic locks for unique jobs, with a shared backend accessible to every dispatcher and worker. Process-local array caches cannot coordinate multiple processes. Configure `uniqueVia()` when the unique lock should use a different repository.

## No-overlap — `WithoutOverlapping`

Use when concurrent runs of the same job against the same resource are unsafe (mutating shared state, calling a single-flight external API):

```php
public function middleware(): array
{
    return [(new WithoutOverlapping((string) $this->order->id))
        ->releaseAfter(30)
        ->expireAfter(180)];
}
```

Choose delays for the workload: the expiry must exceed the job's maximum runtime. Overlapping jobs are released and consume attempts; budget `$tries` or `retryUntil()` accordingly. Use `dontRelease()` only when discarding an overlap is intentional.

## Rate limiting, batches, and retries (cheatsheet)

| Need | Use |
| ---- | --- |
| Cap external API rate | Job middleware `RateLimited('mailgun')`, or `Redis::throttle(...)->then(...)` in `handle` |
| Parallel work + aggregate completion | `Bus::batch([...])->then(...)->catch(...)->dispatch()`; job **MUST** `use Batchable` and guard `if ($this->batch()?->cancelled()) return;` |
| Ordered sequential work | `Bus::chain([...])` |
| Conditional dispatch | `dispatchIf` / `dispatchUnless` |
| Wall-clock retry budget | `retryUntil(): DateTime` instead of fixed `$tries` |
| Unrecoverable stop (no more retries) | `$this->fail('reason')` |
| Downstream flapping | middleware `new ThrottlesExceptions(3, 300)` (3 exceptions → throttle for 300 seconds); `backoff()` uses minutes |

## Idempotency — atomic claim pattern

For an idempotent row transition, **MUST** use an atomic conditional update and check the affected count rather than read-then-write:

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

This query bypasses model events, observers, and attribute setters/casts. Apply required domain effects explicitly after a successful claim, or use a transactional row lock plus a model `save()` when lifecycle hooks are required. The conditional update alone does not guarantee exactly-once external charges or messages; use provider idempotency keys or an outbox for those effects and account for failures between the claim and delivery.

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

## Payload compatibility across deploys

Jobs queued before a deploy are unserialized into the new class **without running its constructor**, so a promoted constructor parameter's default is never applied. A typed property added that way stays uninitialized in every in-flight copy, and the first read throws `Typed property … must not be accessed before initialization`.

- **MUST** declare a property added to an existing queued job as a class property with a default, and assign it in the constructor. Do not add it as a promoted parameter.
- **MUST NOT** rename, remove, or retype a property until queued copies have drained: the longest delay plus the retry and backoff window. Keep the old property until then.
- The same applies to queued listeners, notifications, and mailables.

❌ Added as a promoted parameter. Copies queued before the deploy throw on `$this->force`:

```php
public function __construct(public Order $order, public bool $force = false) {}
```

✅ The class default survives unserialization:

```php
public bool $force = false;

public function __construct(public Order $order, bool $force = false)
{
    $this->force = $force;
}
```

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

## Never redeclare a `Queueable` property

`Illuminate\Bus\Queueable` declares `$connection`, `$queue`, `$delay`, `$afterCommit`, `$middleware`, `$chained`, `$chainConnection`, `$chainQueue` and `$chainCatchCallbacks`. A class that redeclares one with a different type **or** a different default (`public bool $afterCommit = true;`, or the untyped `public $afterCommit = true;`) is a fatal error at class composition, so every dispatch, worker and test that loads it dies, often as a test run with no output. Use the trait's methods (`onQueue()`, `onConnection()`, `delay()`, `$this->afterCommit()`), the queue attributes, or a `middleware()` method. `$tries`, `$timeout`, `$backoff` and `$maxExceptions` are not on the trait, so declaring them is safe.

## `->afterCommit()` does not defer a `ShouldBeUnique` lock

On a `ShouldBeUnique` job, `dispatch($x)->afterCommit()` defers only the queue push: `PendingDispatch` acquires the unique lock when it is destructed, still inside the open transaction. Laravel releases that lock if the transaction rolls back, but while the first transaction is still open a concurrent, committed dispatch can see the lock and be silently discarded.

- **MUST** defer the whole dispatch for a unique job written inside a transaction: `DB::afterCommit(fn () => SyncAccountJob::dispatch($accountId));`.
- **MUST** build the payload before the closure and capture it. A key read inside the closure sees the model as it is at commit time, so two dispatches in one transaction collapse into one stale job.
- **SHOULD** wrap this in a named static method on the job (`dispatchOnCommit()`) so callers cannot get it wrong. Outside a transaction `DB::afterCommit()` runs the closure immediately.
