# Enums

**Purpose:** finite, named value sets — model state columns, roles, types, statuses.

## Naming

- **MUST** be a singular descriptive noun, **no `Enum` suffix** — e.g. `OrderStatus`, `UserRole`, `PaymentMethod`.
- **MUST** use PascalCase for case names — e.g. `case PendingReview;`, `case Shipped;`.

## Rules

- **MUST** use **string-backed** enums for values stored in the database — readable in the DB and stable across refactors. Use int-backed only when storage size genuinely matters.
- **MUST** cast model columns to the enum in the model's `casts()` — see `app/Models/CLAUDE.md`.
- **MUST** validate enum input with `Rule::enum()`, not a hand-maintained `in:` list:

```php
use Illuminate\Validation\Rule;

'status' => [Rule::enum(OrderStatus::class)],
'status' => [Rule::enum(OrderStatus::class)->only([OrderStatus::Pending, OrderStatus::Shipped])],
```

- **PREFER** `tryFrom()` over `from()` for untrusted input — `from()` throws `ValueError`; `tryFrom()` returns `null`.
- **SHOULD** put display concerns on the enum as small `match` methods (`label()`, `color()`), keeping the mapping exhaustive — `match` without a default arm fails loudly when a new case is added.
- **AVOID** business logic on enums beyond labels and simple predicates (`isFinal()`, `canTransitionTo()`); multi-step behaviour belongs in an Action.

```php
enum OrderStatus: string
{
    case Pending = 'pending';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending   => 'Pending',
            self::Shipped   => 'Shipped',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isFinal(): bool
    {
        return match ($this) {
            self::Shipped, self::Cancelled => true,
            default => false,
        };
    }
}
```

## Create

```bash
php artisan make:enum OrderStatus --string
```
