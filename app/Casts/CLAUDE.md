# Custom Eloquent Casts

**Purpose:** map database columns to richer types (value objects) on get/set.

## Naming

- **MUST** name after the value it produces, **no suffix** — e.g. `Money`, `Coordinates`. The paired value object lives in `app/Support/` or `app/Data/`.

## Rules

- **MUST** exhaust built-in casts before writing a custom one: `datetime`, `decimal:n`, `encrypted`, enum casts, `AsCollection`, `AsArrayObject`, `AsStringable`, `hashed`. A custom cast that re-implements a built-in is wrong.
- **MUST** implement `CastsAttributes` with `get()` and `set()`. `set()` may return an array keyed by column name when one value object spans multiple columns.
- **SHOULD** make the value object immutable (readonly properties) and replace the whole attribute to change it — Eloquent caches the cast object instance, so in-place mutation makes model state hard to reason about.
- **PREFER** an inbound-only cast (`CastsInboundAttributes`) when only writes need transforming — it has no `get()`.
- For value objects used across many models, **PREFER** the `Castable` interface on the value object (`castUsing()`) so models can cast with `MoneyValue::class` directly when it implements that contract. The example below instead registers the separate `App\Casts\Money` adapter and returns `App\Support\MoneyValue`.

```php
use App\Support\MoneyValue;

final class Money implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?MoneyValue
    {
        if (! isset($attributes['amount'], $attributes['currency'])) {
            return null;
        }

        return new MoneyValue(amount: (int) $attributes['amount'], currency: $attributes['currency']);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [
                'amount'   => null,
                'currency' => null,
            ];
        }

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

`price` is a logical cast attribute backed by the `amount` and `currency` columns; it does not need a physical `price` column. Read `$model->price` to construct the value (or null when underlying columns are unset), and assign a MoneyValue (or null) to write both columns. Import `App\Casts\Money` in the model. This is Laravel's supported multi-column value-object cast pattern.

## Create

```bash
php artisan make:cast Money
php artisan make:cast TruncatedString --inbound
```
