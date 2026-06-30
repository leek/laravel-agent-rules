# Console Commands

**Purpose:** Artisan-invoked recurring or one-off tasks (imports, exports, cleanups, schedules).

## Naming

- **MUST** be `{Verb}{Noun}Command` (e.g. `GenerateReportCommand`, `ImportDataCommand`).

## Rules

- **MUST** have a meaningful `$signature` (e.g. `app:fetch-users`, not `command:name`).
- **MUST** set `$description` to something useful — it appears in `php artisan list`.
- **MUST** return `Command::SUCCESS` / `Command::FAILURE` / `Command::INVALID` explicitly.
- **SHOULD** delegate work to an Action; the command parses arguments and reports.
- **SHOULD** use `Validator::make(...)` for complex option / argument / prompt validation instead of hand-written `if` trees. Print validation errors and return `Command::INVALID`.

## Create

```bash
php artisan make:command FetchUsers
```

## Example

```php
class FetchUsersCommand extends Command
{
    protected $signature = 'app:fetch-users {--since=}';
    protected $description = 'Fetch users updated since the given timestamp.';

    public function handle(FetchUsersAction $action): int
    {
        $action->run($this->option('since'));

        return self::SUCCESS;
    }
}
```

## Scheduling

Scheduling rules live in `routes/CLAUDE.md` — see the "Task scheduling" section. In Laravel 11+, schedules are defined in `routes/console.php` via the `Schedule` facade, not in `app/Console/Kernel.php` (which no longer exists in fresh L11+ skeletons).
