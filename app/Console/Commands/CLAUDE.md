# Console Commands

**Purpose:** Artisan-invoked recurring or one-off tasks (imports, exports, cleanups, schedules).

## Naming

- **MUST** be `{Verb}{Noun}Command` (e.g. `GenerateReportCommand`, `ImportDataCommand`).

## Rules

- **MUST** have a meaningful `$signature` (e.g. `app:fetch-users`, not `command:name`).
- **MUST** set `$description` to something useful — it appears in `php artisan list`.
- **MUST** return `Command::SUCCESS` / `Command::FAILURE` / `Command::INVALID` explicitly.
- **SHOULD** delegate work to an Action; the command parses arguments and reports.

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

Wire scheduled commands in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('telescope:prune')->daily();
}
```
