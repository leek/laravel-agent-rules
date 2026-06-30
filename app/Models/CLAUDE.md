# Models

**Purpose:** represent a database table; expose Eloquent-native concerns only.

## Naming

- **MUST** be singular noun, **no `Model` suffix** (`User`, not `UserModel`).

## Rules

- **MUST NOT** contain business logic — push to `app/Actions/` or `app/Support/`.
- **MUST** prefer Eloquent over the Query Builder, and the Query Builder over raw SQL. Drop to `DB::table()` / `DB::raw()` only when Eloquent genuinely can't express the query — Eloquent gives casts, scopes, events, and soft deletes for free.
- **MUST** remember that `DB::table()` bypasses Eloquent soft-delete scopes, casts, accessors, model events, and observers. If you drop to the Query Builder for a soft-deletable table, manually add `whereNull('deleted_at')` or document why trashed rows are included.
- **PREFER** Laravel Collections over plain arrays for in-memory data manipulation (`map`/`filter`/`reduce`/`pluck` over `array_*` + loops).
- **MUST NOT** redundantly set `$table`, `$primaryKey`, `$keyType`, `$incrementing`, `$connection`, `CREATED_AT`/`UPDATED_AT`, or explicit pivot / foreign-key names when Laravel's conventions already produce that exact value (convention over configuration). Configure only genuine exceptions — e.g. a `Pivot` subclass whose table isn't the singular default (see Gotchas).
- **SHOULD** declare `$fillable` (or `$guarded = []` with care) — never leave mass-assignment unconfigured.
- **MUST** cast every date/time column via `casts()` (`'ordered_at' => 'datetime'`, or `'datetime:Y-m-d'` to pin a format) so it hydrates as a Carbon instance. **MUST NOT** store or pass dates as preformatted strings — keep Carbon objects throughout and format only in the display layer.
- **SHOULD** declare casts for every other non-scalar column (enums, JSON, money / value objects). Use the `casts()` method (L11+) — see below.
- **SHOULD** use enums for finite state columns (`status`, `role`, `tier`) rather than free-form strings.

## Create

```bash
php artisan make:model Product
```

## Configuration

```php
final class Project extends Model
{
    protected $fillable = ['name', 'owner_id', 'status'];

    protected function casts(): array
    {
        return [
            'status'      => ProjectStatus::class,
            'archived_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
```

> Fresh L11+ skeletons no longer pull in `HasFactory` by default — factories are resolved via convention or `#[UseFactory]` (see below).

## Casts and attributes

`casts()` method (L11+) is preferred over the legacy `protected $casts = [...]` property because it allows runtime class references and is type-safe. The property still works.

Built-in casts include `'array'`, `'collection'`, `'encrypted'`, `'encrypted:array'`, `'hashed'`, `'datetime'`, `AsStringable::class`, `AsEnumCollection::of(...)`, `AsCollection::of(SomeClass::class)` (L12+ — maps each item into a class), `'json:unicode'` (L12+ — JSON without escaping unicode), `'asFluent'` (L12+).

```php
protected function casts(): array
{
    return [
        'status' => ProjectStatus::class,
    ];
}
```

Custom value-object cast via accessor/mutator:

```php
protected function budgetCents(): Attribute
{
    return Attribute::make(
        get: fn (int $value) => Money::fromCents($value),
        set: fn (Money $money) => $money->toCents(),
    );
}
```

## Model wiring — PHP attributes (L11+)

**PREFER** PHP attributes over magic conventions or `booted()` registrations. They make the wiring greppable and explicit.

```php
use Illuminate\Database\Eloquent\Attributes\CollectedBy;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;

#[ObservedBy(OrderObserver::class)]
#[ScopedBy(ActiveScope::class)]
#[CollectedBy(OrderCollection::class)]
#[UseFactory(OrderFactory::class)]
#[UsePolicy(OrderPolicy::class)]
#[UseEloquentBuilder(OrderBuilder::class)]
final class Order extends Model
{
    // ...
}
```

| Attribute | Replaces |
| --- | --- |
| `#[ObservedBy]` | `Model::observe(...)` in a provider |
| `#[ScopedBy]` | `static::booted()` + `addGlobalScope(...)` |
| `#[CollectedBy]` | `newCollection()` override |
| `#[UseFactory]` | `HasFactory::newFactory()` override / naming convention |
| `#[UsePolicy]` | `Gate::policy(Model::class, Policy::class)` |
| `#[UseEloquentBuilder]` | `newEloquentBuilder()` override |
| `#[UseResource]` / `#[UseResourceCollection]` | Resource convention lookup |

## Relationships

- **MUST** type-hint return types on relation methods (`BelongsTo`, `HasMany`, `MorphTo`, etc.).
- **MUST** name to-one relations singular (`owner`, `latestPost`) and to-many relations plural (`comments`, `roles`, `orderItems`).
- **SHOULD** name `belongsTo` methods after the parent model singular (`owner()` → `User`).
- **MUST** name role-specific relationships after the role, not the target model, when the foreign key carries domain meaning (`inviter()` for `invited_by`, `approver()` for `approved_by`, not another vague `user()`).
- **PREFER** relationship-aware writes over manual foreign keys: `$team->members()->create($data)` instead of `Member::create(['team_id' => $team->id] + $data)`. The relationship call keeps ownership, events, and future relation constraints in one place.
- **SHOULD** add custom relationship methods for reusable filtered/sorted subsets (`completedOrders()`, `activeSubscriptions()`) rather than repeating the same `where()` chain in controllers.

## Query scopes — `#[Scope]` (L11+)

**PREFER** the `#[Scope]` attribute over the legacy `scopeXxx` method-naming convention. Both work; the attribute is more explicit and chainable without IDE magic.

```php
use Illuminate\Database\Eloquent\Attributes\Scope;

#[Scope]
protected function ownedBy(Builder $query, int $userId): void
{
    $query->where('owner_id', $userId);
}

// Call site (unchanged):
$projects = Project::ownedBy($user->id)->get();
```

Legacy form still supported:

```php
public function scopeOwnedBy(Builder $query, int $userId): Builder
{
    return $query->where('owner_id', $userId);
}
```

**MUST** push reusable or multi-condition query logic into a scope / query object rather than leaving it inline in a controller or Action. Add a scope for any filter used in more than one place.

### Query expression rules

- **MUST** group `orWhere()` clauses inside a closure before chaining additional filters; otherwise SQL precedence turns `a OR b AND c` into a different query than `(a OR b) AND c`.
- **PREFER** relationship helpers over manual foreign-key predicates: `whereBelongsTo($user)`, `whereRelation(...)`, `whereHasMorph(...)`. They read as model relationships and avoid hard-coded `*_id` / morph-type branches.
- **MUST** whitelist user-selected SQL fragments (sorts, aggregates, report dimensions) with an enum or explicit map before using `selectRaw()` / `orderByRaw()`. Never concatenate request strings into SQL.
- **SHOULD** select only needed columns when eager-loading large relations: `with('reviews:id,product_id,rating,text')`. Always include the related model's primary key and the foreign key needed to match it back to the parent.

```php
Email::query()
    ->where(function (Builder $query) use ($search): void {
        $query
            ->where('subject', 'like', "%{$search}%")
            ->orWhere('body', 'like', "%{$search}%");
    })
    ->where('active', true)
    ->get();
```

## Global scopes — `#[ScopedBy]` (L11+)

Use for a filter that **always** applies (soft deletes, multi-tenant scoping):

```php
final class ActiveScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereNull('archived_at');
    }
}

#[ScopedBy(ActiveScope::class)]
final class Project extends Model {}
```

Legacy `booted()` form still supported:

```php
protected static function booted(): void
{
    static::addGlobalScope('active', function (Builder $builder): void {
        $builder->whereNull('archived_at');
    });
}
```

- **MUST** pick global scope OR named scope for the same filter — not both, unless layered behaviour is intended.
- **SHOULD** keep global scopes minimal — they apply to every query and are easy to forget.

## Prevent lazy loading in dev

In `AppServiceProvider::boot()`, throw on any lazy-loaded relation in non-production environments so N+1 surfaces during development instead of in prod logs:

```php
Model::preventLazyLoading(! app()->isProduction());
```

## Eager loading (avoid N+1)

- **MUST** eager-load relations the caller will touch.

❌ Lazy access inside a loop — one query per row (N+1):

```php
$orders = Order::latest()->paginate(25);
foreach ($orders as $order) {
    echo $order->customer->name;   // a fresh SELECT every iteration
}
```

✅ Eager-load up front:

```php
$orders = Order::query()
    ->with(['customer', 'items.product'])
    ->latest()
    ->paginate(25);
```

- **SHOULD** use `withCount()` for aggregates rather than counting in a loop.

## Query objects

For multi-condition filters that don't fit in a single scope, wrap an immutable builder:

```php
final class ProjectQuery
{
    public function __construct(private Builder $query) {}

    public function ownedBy(int $userId): self
    {
        return new self((clone $this->query)->where('owner_id', $userId));
    }

    public function active(): self
    {
        return new self((clone $this->query)->whereNull('archived_at'));
    }

    public function builder(): Builder
    {
        return $this->query;
    }
}
```

## Transactions

Wrap multi-step writes in a transaction:

```php
DB::transaction(function (): void {
    $order->update(['status' => 'paid']);
    $order->items()->update(['paid_at' => now()]);
});
```

When the caller needs a created/updated model from the transaction, **PREFER** returning it from the transaction closure over mutating outer scope:

```php
$post = DB::transaction(function () use ($data): Post {
    $post = Post::query()->create($data);
    $post->tags()->sync($data['tags']);

    return $post;
});
```

For read-modify-write contention (counter increments, balance updates, claim-a-row patterns), use `lockForUpdate()` **inside** a transaction:

```php
DB::transaction(function () use ($accountId, $amount): void {
    $account = Account::query()->lockForUpdate()->findOrFail($accountId);
    $account->balance -= $amount;
    $account->save();
});
```

## Large datasets — `chunk()` / `lazy()`

- **MUST NOT** load large tables fully into memory with `all()` or `get()`.
- **SHOULD** use `chunkById()` for paged iteration (stable cursor; safe under concurrent inserts).
- **SHOULD** use `lazy()` for streaming a `LazyCollection` when you want collection ergonomics without holding the full result set.

```php
Order::query()->chunkById(500, function (Collection $orders) {
    $orders->each(fn (Order $order) => $order->recompute());
});

Order::query()->lazy()->each(fn (Order $order) => $order->recompute());
```

## Bulk writes — `upsert()`

For mass insert-or-update, **MUST** use `upsert()` instead of looping `firstOrCreate` / `updateOrCreate`:

```php
Product::query()->upsert(
    $rows,                       // array of associative arrays
    uniqueBy: ['sku'],
    update:   ['price', 'name'],
);
```

## Model events — keep them light

- **MUST NOT** do heavy work in `creating`/`updating`/`saved` (HTTP calls, file IO, large recomputations). Dispatch a queued Job instead — observer callbacks block the write path and can balloon request latency.

## Soft deletes

- Use `SoftDeletes` for records that must be recoverable (orders, invoices, user-generated content).
- **AVOID** soft-deleting reference/lookup data — hard delete or archive instead.
- Restore with `$model->restore()` (fires the `restored` event), `restoreQuietly()` to skip events, or query-builder `restore()` on a `withTrashed()` query for bulk restores (no model events, like other mass operations).

## Gotchas (silent failures)

- **`extends Pivot` derives a SINGULAR table name.** `Illuminate\Database\Eloquent\Relations\Pivot::getTable()` singularizes the class name (`ProviderCredential` → `provider_credential`), unlike `Model` which pluralizes. **MUST** set `protected $table = '...';` explicitly on any `Pivot` subclass whose physical table isn't that singular form — otherwise a cryptic `relation "x" does not exist` surfaces only via `hasManyThrough` / direct `Pivot::query()` (anywhere Eloquent reads `getTable()` without a relationship binding to override it). Verify with `(new Foo)->getTable()`.
- **`HasUlids` / `HasUuids` does NOT change the primary key type.** The trait only auto-fills the columns returned by `uniqueIds()`. A model that overrides `uniqueIds(): array { return ['ulid']; }` keeps the bigint `id` as PK and only auto-generates a secondary `ulid` column. Before claiming a morph/FK column should be a string, check `uniqueIds()`, `$primaryKey`, `getKeyType()`, `$incrementing` — not the trait name. Route binding is independent (`getRouteKeyName()`).
- **An accessor shadows a joined column of the same name.** When a model defines an attribute accessor (e.g. `first_name` proxying a `person` relation), a query that `->join(...)->select('people.first_name')` hydrates **null** on access — the accessor short-circuits the raw value. **MUST** read raw joined columns via `DB::table(...)` (or also `->with(...)` the relation) in seeders/reports/lookups; `Model::query()->select('joined.col')` is only safe for columns the model itself owns.
- **`BelongsToMany` with `->withPivotValue('col', $value)` throws when eager-loaded.** Laravel news up a blank model to build the constraint → `The provided value may not be null`. Lazy access on a hydrated instance works, but `preventLazyLoading()` blocks that in dev/test. **MUST** query the pivot directly via a join instead of `->with('thatRelation')`. Note eager-load constraint closures receive a `Relation`, not a `Builder` — write `fn ($query) => $query->...`; don't type-hint the param as `Builder`.
