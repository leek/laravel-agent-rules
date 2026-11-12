# Actions

**Purpose:** encapsulate one business operation. Single responsibility.

## Naming

- **MUST** be `{Verb}{Noun}Action` (e.g. `CreateUserAction`, `DeleteProductAction`, `UpdateCategoryAction`).

## Rules

- **MUST** expose exactly one public operation: `handle(...)`. A public `__construct(...)` for dependency injection is allowed; keep helper methods `private` / `protected`. Matches the [Laravel Actions](https://www.laravelactions.com/2.x/one-class-one-task.html) convention and mirrors Jobs/Listeners.
- **SHOULD** be the default home for any new business logic before considering events/jobs/services.
- **SHOULD** be invoked from controllers via the container (`app(VerifyUserAction::class)->handle(...)`) or constructor injection.
- **MUST** own the operation's guards (eligibility, authorization for every record it mutates) so no entry point can skip them, and re-check them on the locked row before writing — see **Sweep the siblings** and **Lock, re-read, then decide** in `app/CLAUDE.md`.
- **MUST** iterate with `eachById()` / `chunkById()` when the loop writes — see **Large datasets & bulk writes** in `app/Models/CLAUDE.md`.
- **AVOID** extracting an Action for simple CRUD that is only one model write plus a redirect/response. Extract when the operation is reused from multiple entry points, has side effects, branches, transactions, external I/O, or enough logic to test independently.

## Example

```php
final class VerifyUserAction
{
    public function handle(User $user): void
    {
        // ...
    }
}
```

## Create

```bash
php artisan make:class Actions/VerifyUserAction
php artisan make:action VerifyUserAction           # only if rockero-cz/laravel-starter-kit installed
```

Or just add a plain class under `app/Actions/`.
