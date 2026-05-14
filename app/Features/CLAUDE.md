# Feature Flags (Pennant)

**Purpose:** gradual rollouts, A/B variants, kill switches. Backed by `laravel/pennant`.

## Install

```bash
composer require laravel/pennant
php artisan vendor:publish --provider="Laravel\Pennant\PennantServiceProvider"
php artisan migrate
```

Set `PENNANT_STORE=database` in `.env` (or `redis` for high-traffic).

## Defining flags

### Closure feature (in `AppServiceProvider::boot()`)

```php
Feature::define('new-checkout', function (?User $user): bool {
    return match (true) {
        $user?->isInternal()         => true,
        $user?->isOnPlan('pro')      => Lottery::odds(1, 10),
        default                      => false,
    };
});
```

### Class feature (under `app/Features/`)

```php
final class NewCheckout
{
    public function resolve(?User $user): bool
    {
        // ...
    }
}
```

Reference by class: `Feature::active(NewCheckout::class)`.

## Rules

- **MUST** type-hint the scope parameter as nullable (`?User $user`) so the flag works for guests.
- **MUST** eager-load flags when checking many on the same request — avoids N+1 against the features table:

```php
Feature::for($user)->load(['new-checkout', 'beta-search', 'invoice-redesign']);
// or:
Feature::for($user)->loadAll();
```

- **SHOULD** use `Lottery::odds(1, 10)` for gradual rollout (random 10% bucket).
- **SHOULD** use deterministic assignment when stickiness matters (`$user->id % N`) — same user always gets the same bucket.
- **SHOULD** prefer rich values (`'blue-button'`, `'red-button'`) over booleans when running A/B variants; read with `Feature::value()`, not `active()`.

## Reading flags

```php
if (Feature::active('new-checkout')) { /* ... */ }

$variant = Feature::value('checkout-cta-color'); // string|bool
```

## Route middleware

```php
Route::middleware([EnsureFeaturesAreActive::using('new-checkout')])->group(/* ... */);
```

Custom inactive response:

```php
EnsureFeaturesAreActive::whenInactive(fn () => response()->json(['error' => 'Not available'], 404));
```

## Testing

- Set `PENNANT_STORE=array` in `.env.testing` — flag state lives only in memory per-test.
- Use `Feature::activate('flag')` / `Feature::deactivate('flag')` in test setup.

```php
beforeEach(fn () => Feature::activate('new-checkout'));
```

## Cleanup

After a flag is fully rolled out, **MUST** delete the flag definition AND purge stored state:

```bash
php artisan pennant:purge new-checkout
```

Stale flag definitions accumulate as technical debt — clean them up.
