# Configuration

## Rules

- **MUST NOT** call `env()` outside `config/*.php`. Application code reads via `config('foo.bar')`.
- **SHOULD** keep custom application settings in a dedicated config file (e.g. `config/project.php`).
- **MUST** store third-party service credentials / API keys in `config/services.php`.

## Naming

- **MUST** name config files and all config / lang array index keys in `snake_case` — `config/google_calendar.php`, `'default_currency' => ...`, `articles_enabled` (not `googleCalendar.php`, `google-calendar.php`, or `ArticlesEnabled`).
- **Provider** classes (under `app/Providers/`) follow `{Domain}Provider` (e.g. `PaymentProvider`, `StorageProvider`, `EmailProvider`).
