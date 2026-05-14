# Models

**Purpose:** represent a database table; expose Eloquent-native concerns only.

## Naming

- **MUST** be singular noun, **no `Model` suffix** (`User`, not `UserModel`).

## Rules

- **MUST NOT** contain business logic — push to `app/Actions/` or `app/Support/`.
- **SHOULD** declare `$fillable` (or `$guarded = []` with care) — never leave mass-assignment unconfigured.
- **SHOULD** declare `$casts` for every column that is not a plain string/int (datetimes, enums, JSON, money objects).
- **SHOULD** use enums for finite state columns (`status`, `role`, `tier`) rather than free-form strings.

## Create

```bash
php artisan make:model Product
```

## Configuration

```php
final class Project extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'owner_id', 'status'];

    protected $casts = [
        'status'      => ProjectStatus::class,
        'archived_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
```

## Casts and attributes

Use enum or value-object casts to keep typing tight:

```php
protected $casts = [
    'status' => ProjectStatus::class,
];
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

## Relationships

- **MUST** type-hint return types on relation methods (`BelongsTo`, `HasMany`, `MorphTo`, etc.).
- **SHOULD** name `belongsTo` methods after the parent model singular (`owner()` → `User`).

## Query scopes

**SHOULD** add a scope for any filter used in more than one place:

```php
public function scopeOwnedBy(Builder $query, int $userId): Builder
{
    return $query->where('owner_id', $userId);
}

// Call site:
$projects = Project::ownedBy($user->id)->get();
```

## Global scopes

Use for a filter that **always** applies (soft deletes, multi-tenant scoping):

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

## Eager loading (avoid N+1)

- **MUST** eager-load relations the caller will touch:

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
