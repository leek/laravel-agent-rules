# Custom Eloquent Casts

**Purpose:** map database columns to richer types (value objects) on get/set.

## Naming

- **MUST** name after the value it produces, **no suffix** — e.g. `Money`, `Coordinates`. The paired value object lives in `app/Support/` or `app/Data/`.

## Rules

- **MUST** exhaust built-in casts before writing a custom one: `datetime`, `decimal:n`, `encrypted`, enum casts, `AsCollection`, `AsArrayObject`, `AsStringable`, `hashed`. A custom cast that re-implements a built-in is wrong.
- **MUST** implement `CastsAttributes` with `get()` and `set()`. `set()` may return an array keyed by column name when one value object spans multiple columns.
- **SHOULD** make the value object immutable (readonly properties) and replace the whole attribute to change it — Eloquent caches the cast object instance, so in-place mutation makes model state hard to reason about.
- **PREFER** an inbound-only cast (`CastsInboundAttributes`) when only writes need transforming — it has no `get()`.
- For value objects used across many models, **PREFER** the `Castable` interface on the value object (`castUsing()`) so models can cast with `Money::class` directly.

```php
final class Money implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): MoneyValue
    {
        return new MoneyValue(amount: $attributes['amount'], currency: $attributes['currency']);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        return [
            'amount'   => $value->amount,
            'currency' => $value->currency,
        ];
    }
}
```

Register in the model's `casts()`:

```php
protected function casts(): array
{
    return ['price' => Money::class];
}
```

## Create

```bash
php artisan make:cast Money
php artisan make:cast TruncatedString --inbound
```
