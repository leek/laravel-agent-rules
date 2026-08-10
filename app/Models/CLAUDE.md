# Models

**Purpose:** represent a database table; expose Eloquent-native concerns only.

## Naming

- **MUST** be singular noun, **no `Model` suffix** (`User`, not `UserModel`).

## Rules

- **MUST NOT** put multi-step workflows, external I/O, or cross-aggregate orchestration on the model — push those to `app/Actions/` or `app/Support/`. Small attribute-local behaviour is fine: predicates (`isPaid()`), single-field transitions that only touch this row, and presentation helpers that read own attributes.
- **MUST** prefer Eloquent over the Query Builder, and the Query Builder over raw SQL. Drop to `DB::table()` / `DB::raw()` only when Eloquent genuinely can't express the query — Eloquent gives casts, scopes, events, and soft deletes for free.
- **MUST** remember that `DB::table()` bypasses Eloquent soft-delete scopes, casts, accessors, model events, and observers. If you drop to the Query Builder for a soft-deletable table, manually add `whereNull('deleted_at')` or document why trashed rows are included.
- **PREFER** Laravel Collections over plain arrays for in-memory data manipulation (`map`/`filter`/`reduce`/`pluck` over `array_*` + loops).
- **MUST NOT** redundantly set `$table`, `$primaryKey`, `$keyType`, `$incrementing`, `$connection`, `CREATED_AT`/`UPDATED_AT`, or explicit pivot / foreign-key names when Laravel's conventions already produce that exact value (convention over configuration). Configure only genuine exceptions — e.g. a `Pivot` subclass whose table isn't the singular default (see Gotchas).
- **SHOULD** declare `$fillable` (or `$guarded = []` with care) — never leave mass-assignment unconfigured.
- **MUST NOT** put columns that decide ownership, tenancy, or privilege into `$fillable` when those values can arrive from client input. Set them via relationship creates (`$user->projects()->create(...)`), `safe()->merge([...])` from the authenticated context, or an Action — never from a request allowlist the client controls.
- **MUST** hide secrets and credentials from array/JSON serialization (`$hidden` / `#[Hidden([...])]` — e.g. passwords, tokens, API keys). **MUST NOT** treat model serialization as the public API contract — use API Resources for external JSON (see `app/Http/Resources/CLAUDE.md`).
- **MUST** cast every date/time column via `casts()` (`'ordered_at' => 'datetime'`, or `'datetime:Y-m-d'` to pin a format) so it hydrates as a Carbon instance. **MUST NOT** store or pass dates as preformatted strings — keep Carbon objects throughout and format only in the display layer.
- **SHOULD** declare casts for every other non-scalar column (enums, JSON, money / value objects). Use the `casts()` method — see below.
- **SHOULD** use enums for finite state columns (`status`, `role`, `tier`) rather than free-form strings.

## Create

```bash
php artisan make:model Product
```

## Configuration

```php
final class Project extends Model
{
    // Ownership is set via $user->projects()->create(...), not mass assignment.
    protected $fillable = ['name', 'status'];

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

> **`HasFactory` still provides `Model::factory()`.** Keep the trait on models that need factories. Use `#[UseFactory(SomeFactory::class)]` only to pin a non-conventional factory class — it does not replace `HasFactory`. Fresh skeletons and `make:model` still include the trait.

## Casts and attributes

**PREFER** the `casts()` method over the legacy `protected $casts = [...]` property — class references and type-safety. Exhaust built-ins before custom casts: `'array'`, `'collection'`, `'encrypted'`, `'encrypted:array'`, `'hashed'`, `'datetime'`, `AsStringable`, `AsEnumCollection`, `AsCollection::of(...)`, `'json:unicode'`, `'asFluent'`.

```php
protected function casts(): array
{
    return [
        'status'      => ProjectStatus::class,
        'archived_at' => 'datetime',
    ];
}

// Value-object via Attribute accessor/mutator when a cast class is overkill:
protected function budgetCents(): Attribute
{
    return Attribute::make(
        get: fn (int $value) => Money::fromCents($value),
        set: fn (Money $money) => $money->toCents(),
    );
}
```

## Model wiring — PHP attributes

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

## Query scopes — `#[Scope]`

**PREFER** `#[Scope]` over the legacy `scopeXxx` naming. Both work; the attribute is explicit and IDE-friendly.

```php
use Illuminate\Database\Eloquent\Attributes\Scope;

#[Scope]
protected function ownedBy(Builder $query, int $userId): void
{
    $query->where('owner_id', $userId);
}

// Call site (unchanged): Project::ownedBy($user->id)->get();
```

**MUST** push reusable / multi-condition query logic into a scope (or query object) rather than leaving it inline in a controller or Action.

### Query expression rules

- **MUST** group `orWhere()` clauses inside a closure before chaining more filters — otherwise SQL precedence turns `a OR b AND c` into the wrong query.
- **PREFER** relationship helpers: `whereBelongsTo($user)`, `whereRelation(...)`, `whereHasMorph(...)`.
- **MUST** whitelist user-selected SQL fragments (sorts, aggregates, dimensions) with an enum or map before `selectRaw()` / `orderByRaw()`. Never concatenate request strings into SQL.
- **SHOULD** select only needed columns when eager-loading large relations: `with('reviews:id,product_id,rating,text')` — always include PK + FK used to match the parent.

```php
Email::query()
    ->where(function (Builder $query) use ($search): void {
        $query->where('subject', 'like', "%{$search}%")
            ->orWhere('body', 'like', "%{$search}%");
    })
    ->where('active', true)
    ->get();
```

## Global scopes — `#[ScopedBy]`

Use for a filter that **always** applies (soft deletes, multi-tenant). **PREFER** `#[ScopedBy]` over `booted()` + `addGlobalScope`.

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

- **MUST** pick global scope OR named scope for the same filter — not both, unless layered behaviour is intended.
- **SHOULD** keep global scopes minimal — they apply to every query and are easy to forget.

## Strict models in non-production

In `AppServiceProvider::boot()`, enable strict Eloquent behaviour outside production so N+1, missing attributes, and mass-assignment surprises fail loudly in dev/test:

```php
Model::shouldBeStrict(! app()->isProduction());
```

`shouldBeStrict()` turns on `preventLazyLoading()`, `preventSilentlyDiscardingAttributes()`, and `preventAccessingMissingAttributes()`. Prefer it over wiring the three calls separately unless you need only one.

## Eager loading (avoid N+1)

- **MUST** eager-load relations the caller will touch (`with([...])`). Lazy access in a loop is N+1.
- **SHOULD** use `withCount()` for aggregates rather than counting in a loop.

```php
$orders = Order::query()
    ->with(['customer', 'items.product'])
    ->latest()
    ->paginate(25);
```

For multi-condition filters that outgrow a single scope, **SHOULD** extract an immutable query object (clone + chain) rather than growing controller `where()` trees.

## Transactions

Wrap multi-step writes in a transaction. **PREFER** returning the model from the closure over mutating outer scope. For read-modify-write contention, use `lockForUpdate()` **inside** the transaction:

```php
$post = DB::transaction(function () use ($data): Post {
    $post = Post::query()->create($data);
    $post->tags()->sync($data['tags']);

    return $post;
});

DB::transaction(function () use ($accountId, $amount): void {
    $account = Account::query()->lockForUpdate()->findOrFail($accountId);
    $account->balance -= $amount;
    $account->save();
});
```

## Large datasets & bulk writes

- **MUST NOT** load large tables with `all()` / `get()`. **SHOULD** use `chunkById()` (stable under concurrent inserts) or `lazy()` for streaming.
- For mass insert-or-update, **MUST** use `upsert()` instead of looping `firstOrCreate` / `updateOrCreate`:

```php
Order::query()->chunkById(500, fn (Collection $orders) => $orders->each->recompute());

Product::query()->upsert($rows, uniqueBy: ['sku'], update: ['price', 'name']);
```

## Model events — keep them light

- **MUST NOT** do heavy work in `creating`/`updating`/`saved` (HTTP calls, file IO, large recomputations). Dispatch a queued Job instead — observer callbacks block the write path and can balloon request latency.

## Soft deletes

- Use `SoftDeletes` for records that must be recoverable (orders, invoices, user-generated content).
- **AVOID** soft-deleting reference/lookup data — hard delete or archive instead.
- Restore with `$model->restore()` (fires the `restored` event), `restoreQuietly()` to skip events, or query-builder `restore()` on a `withTrashed()` query for bulk restores (no model events, like other mass operations).
- **MUST** treat unique indexes carefully with soft deletes — a plain unique on `email` blocks re-creating a row after soft-delete. Prefer a partial/filtered unique (e.g. Postgres partial index `WHERE deleted_at IS NULL`, or a composite that includes a soft-delete sentinel the project standardizes on). Document the chosen approach in the migration.

## Gotchas (silent failures)

- **`extends Pivot` derives a SINGULAR table name.** `Illuminate\Database\Eloquent\Relations\Pivot::getTable()` singularizes the class name (`ProviderCredential` → `provider_credential`), unlike `Model` which pluralizes. **MUST** set `protected $table = '...';` explicitly on any `Pivot` subclass whose physical table isn't that singular form — otherwise a cryptic `relation "x" does not exist` surfaces only via `hasManyThrough` / direct `Pivot::query()` (anywhere Eloquent reads `getTable()` without a relationship binding to override it). Verify with `(new Foo)->getTable()`.
- **`HasUlids` / `HasUuids` does NOT change the primary key type.** The trait only auto-fills the columns returned by `uniqueIds()`. A model that overrides `uniqueIds(): array { return ['ulid']; }` keeps the bigint `id` as PK and only auto-generates a secondary `ulid` column. Before claiming a morph/FK column should be a string, check `uniqueIds()`, `$primaryKey`, `getKeyType()`, `$incrementing` — not the trait name. Route binding is independent (`getRouteKeyName()`).
- **An accessor shadows a joined column of the same name.** When a model defines an attribute accessor (e.g. `first_name` proxying a `person` relation), a query that `->join(...)->select('people.first_name')` hydrates **null** on access — the accessor short-circuits the raw value. **MUST** read raw joined columns via `DB::table(...)` (or also `->with(...)` the relation) in seeders/reports/lookups; `Model::query()->select('joined.col')` is only safe for columns the model itself owns.
- **`BelongsToMany` with `->withPivotValue('col', $value)` throws when eager-loaded.** Laravel news up a blank model to build the constraint → `The provided value may not be null`. Lazy access on a hydrated instance works, but `preventLazyLoading()` blocks that in dev/test. **MUST** query the pivot directly via a join instead of `->with('thatRelation')`. Note eager-load constraint closures receive a `Relation`, not a `Builder` — write `fn ($query) => $query->...`; don't type-hint the param as `Builder`.
