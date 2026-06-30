# Service Providers

**Purpose:** wire the container — bind interfaces to implementations, register singletons, register macros, boot package configuration.

## Naming

- **MUST** be `{Domain}Provider` (e.g. `PaymentProvider`, `StorageProvider`, `EmailProvider`) for your own wiring providers.
- Providers that a framework or package names by its own convention are **exempt** — e.g. Filament's `{PanelId}PanelProvider` (`AdminPanelProvider`) and `FilamentServiceProvider` (see `filament-agent-rules`).
- App-wide wiring lives in the default `AppServiceProvider`.

## Rules

- **MUST** bind interfaces to concrete implementations in `register()` — never in `boot()`.
- **SHOULD** put side-effecting setup (Blade directives, validators, observers) in `boot()`.
- **MUST NOT** resolve services from the container during `register()` — other providers may not be registered yet.
- **SHOULD** prefer `bind` for per-resolve instances and `singleton` for shared state.
- **AVOID** View Composers. Pass data explicitly from the controller/Action, or use a class-based component — both are clearer and more testable. Reach for a composer only when identical data must bind to many unrelated views and no other path fits.

## App-wide safety defaults

These belong in `AppServiceProvider::boot()` unless a package documents a different home:

- **MUST** prohibit destructive database commands in production with `DB::prohibitDestructiveCommands(app()->isProduction())`.
- **SHOULD** define `Password::defaults(...)` once so Form Requests can use `Password::default()` instead of duplicating password policy chains. Keep production strict; local/test may return a lighter rule when the project accepts that tradeoff.

## Example: interface → implementation

```php
use App\Repositories\EloquentOrderRepository;
use App\Repositories\OrderRepository;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OrderRepository::class, EloquentOrderRepository::class);
    }
}
```

## Example: contextual binding

```php
$this->app->when(ProcessPodcast::class)
    ->needs(Filesystem::class)
    ->give(fn () => Storage::disk('s3'));
```

## Create

```bash
php artisan make:provider PaymentProvider
```

Register the provider in `bootstrap/providers.php` (Laravel 11+) or `config/app.php` (Laravel 10 and below).
