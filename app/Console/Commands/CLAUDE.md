# Console Commands

**Purpose:** Artisan-invoked recurring or one-off tasks (imports, exports, cleanups, schedules).

## Naming

- **MUST** be `{Verb}{Noun}Command` (e.g. `GenerateReportCommand`, `ImportDataCommand`).

## Rules

- **MUST** have a meaningful `$signature` (e.g. `app:fetch-users`, not `command:name`).
- **MUST** set `$description` to something useful — it appears in `php artisan list`.
- **MUST** return `Command::SUCCESS` / `Command::FAILURE` / `Command::INVALID` explicitly.
- **SHOULD** delegate work to an Action; the command parses arguments and reports.
- **MUST** write a sweep so a row changed since it was read is left alone: iterate with `chunkById()` / `eachById()`, and put the selection predicate in the UPDATE itself (or re-read under a lock) instead of saving the chunk's model. See **Lock, re-read, then decide** in `app/CLAUDE.md`.

```php
// ❌ Saves a model read at chunk time: a subscription renewed since then is marked expired.
$subscription->update(['status' => SubscriptionStatus::Expired]);

// ✅ The predicate travels with the write.
Subscription::query()->whereKey($subscription->id)->where('expires_at', '<', now())
    ->update(['status' => SubscriptionStatus::Expired]);
```

- **SHOULD** use `Validator::make(...)` for complex option / argument / prompt validation instead of hand-written `if` trees. Print validation errors and return `Command::INVALID`.

## Create

```bash
php artisan make:command FetchUsersCommand
```

## Example

```php
final class FetchUsersCommand extends Command
{
    protected $signature = 'app:fetch-users {--since=}';
    protected $description = 'Fetch users updated since the given timestamp.';

    public function handle(FetchUsersAction $action): int
    {
        $action->handle($this->option('since'));

        return self::SUCCESS;
    }
}
```

## Scheduling

Scheduling rules live in `routes/CLAUDE.md` — see the "Task scheduling" section. Schedules are defined in `routes/console.php` via the `Schedule` facade, not in `app/Console/Kernel.php` (removed from the modern skeleton).
