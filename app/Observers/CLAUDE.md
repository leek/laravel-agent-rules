# Observers

**Purpose:** react to model lifecycle events (`creating`, `created`, `updating`, `saved`, `deleting`, `deleted`, etc.).

## Naming

- **MUST** be `{SingularModel}Observer` (e.g. `UserObserver`, `ProductObserver`).

## Rules

- **SHOULD** keep observer methods short — call into an Action when logic grows beyond a few lines.
- **MUST** be aware that observers are **not triggered** by Eloquent mass updates (`Model::query()->update(...)`). Use individual updates or dispatch the effect manually when you need observer behaviour on a bulk update.

> Use sparingly. Default home for new logic is an **Action** (`app/Actions/`).

## Create

```bash
php artisan make:observer UserObserver --model=User
```

## Typical uses

```php
public function saved(InvoiceItem $invoiceItem): void
{
    $invoiceItem->invoice()->recalculate();
}

public function creating(Order $order): void
{
    $order->state = OrderState::NEW;
}

public function deleting(Order $order): void
{
    $order->products()->delete();
}
```
