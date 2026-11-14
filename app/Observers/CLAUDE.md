# Observers

**Purpose:** react to model lifecycle events (`creating`, `created`, `updating`, `saved`, `deleting`, `deleted`, etc.).

## Naming

- **MUST** be `{SingularModel}Observer` (e.g. `UserObserver`, `ProductObserver`) for a model's single observer.
- **MAY** split a model's observers by concern as `{SingularModel}{Concern}Observer` when one model has several distinct, independently registered lifecycle responsibilities that would otherwise bloat a single class. Each observer is registered independently via `#[ObservedBy]` or `Model::observe(...)` and owns one concern. Keep the `{Model}` prefix and the `Observer` suffix.

## Rules

- **SHOULD** keep observer methods short — call into an Action when logic grows beyond a few lines.
- **MUST** be aware that observers are **not triggered** by Eloquent mass updates or deletes (`Model::query()->update(...)`, `Model::query()->delete(...)`). Use `chunkById()` and call `save()` / `delete()` on each model, or dispatch the effect manually when you need observer behaviour on bulk operations.
- **MUST** guard side effects on updates with `isDirty()` in `updating` or `wasChanged()` in `updated`. Do not send notifications, delete files, or write activity logs on every update when only one field matters.
- **MUST** guard on the field that marks the occurrence, not on a summary of it. A second approval leaves `status` at `approved`, so `wasChanged('status')` skips it; guard on what changes each time (`stage_id`, `approved_at`): `wasChanged(['status', 'approved_at'])`.
- **MUST NOT** wrap a write that must happen in `rescue()` or a catch-all `try`. A deadlock or serialization failure is swallowed with every other exception and the write is lost without a trace. Retry concurrency errors a bounded number of times instead: `DB::transaction($callback, 3)` retries them at the outermost transaction level, and `Illuminate\Database\DetectsConcurrencyErrors::causedByConcurrencyError()` identifies them in a hand-written loop. Re-read the models inside each attempt.
- **MUST** reload the row with `lockForUpdate()` inside a transaction before rewriting a JSON or array column on another model; saving a copy read earlier discards a concurrent change. See **Lock, re-read, then decide** in `app/CLAUDE.md`.
- **SHOULD** put model-wide cleanup in `deleting` / `deleted` once instead of duplicating it in every controller, command, or action that can delete the model.

> Use sparingly. Default home for new logic is an **Action** (`app/Actions/`).

## Registration — `#[ObservedBy]` attribute

**PREFER** the `#[ObservedBy(...)]` attribute on the model over `Model::observe(...)` in a service provider:

```php
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy(OrderObserver::class)]
final class Order extends Model
{
    // ...
}
```

When registering observers conditionally, use `AppServiceProvider::boot()`:

```php
Order::observe(OrderObserver::class);
```

## Create

```bash
php artisan make:observer UserObserver --model=User
```

## Typical uses

```php
public function saved(InvoiceItem $invoiceItem): void
{
    $invoiceItem->invoice->recalculate();
}

public function creating(Order $order): void
{
    $order->state = OrderState::New;
}

public function deleting(Order $order): void
{
    // Mass query delete() skips Product observers — delete each model when side effects matter.
    // eachById(), not each(): deleting shifts the offset pages and skips rows.
    $order->products()->eachById(fn (Product $product) => $product->delete());
}

public function updating(Album $album): void
{
    if ($album->isDirty('cover')) {
        Storage::delete($album->getRawOriginal('cover'));
    }
}

public function updated(SupportRequest $supportRequest): void
{
    if ($supportRequest->wasChanged('status')) {
        $oldStatus = $supportRequest->getOriginal('status');

        app(LogRequestStatusChangeAction::class)->handle($supportRequest, $oldStatus);
    }
}
```
