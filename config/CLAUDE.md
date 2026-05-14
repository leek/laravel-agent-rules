# Configuration

## Rules

- **MUST NOT** call `env()` outside `config/*.php`. Application code reads via `config('foo.bar')`.
- **SHOULD** keep custom application settings in a dedicated config file (e.g. `config/project.php`).
- **MUST** store third-party service credentials / API keys in `config/services.php`.

## Naming

- **Provider** classes (under `app/Providers/`) follow `{Domain}Provider` (e.g. `PaymentProvider`, `StorageProvider`, `EmailProvider`).
