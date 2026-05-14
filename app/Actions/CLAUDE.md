# Actions

**Purpose:** encapsulate one business operation. Single responsibility.

## Naming

- **MUST** be `{Verb}{Noun}Action` (e.g. `CreateUserAction`, `DeleteProductAction`, `UpdateCategoryAction`).

## Rules

- **MUST** expose exactly one public method (`run()` or `execute()`, project-consistent).
- **MUST** keep helper methods `private` / `protected`.
- **SHOULD** be the default home for any new business logic before considering events/jobs/services.
- **SHOULD** be invoked from controllers via the container (`app(VerifyUserAction::class)->run(...)`) or constructor injection.

## Example

```php
class VerifyUserAction
{
    public function run(User $user): void
    {
        // ...
    }
}
```

## Create

```bash
php artisan make:class Actions/VerifyUserAction    # L12+ native
php artisan make:action VerifyUserAction           # only if rockero-cz/laravel-starter-kit installed
```

Or just add a plain class under `app/Actions/`.
