# Service Providers

**Purpose:** wire the container — bind interfaces to implementations, register singletons, register macros, boot package configuration.

## Naming

- **MUST** be `{Domain}Provider` (e.g. `PaymentProvider`, `StorageProvider`, `EmailProvider`).
- App-wide wiring lives in the default `AppServiceProvider`.

## Rules

- **MUST** bind interfaces to concrete implementations in `register()` — never in `boot()`.
- **SHOULD** put side-effecting setup (Blade directives, validators, observers, view composers) in `boot()`.
- **MUST NOT** resolve services from the container during `register()` — other providers may not be registered yet.
- **SHOULD** prefer `bind` for per-resolve instances and `singleton` for shared state.

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
