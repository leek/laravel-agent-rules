# Laravel Agent Rules

Cross-cutting conventions for the whole project. Per-class-type rules live in the matching directory's `CLAUDE.md` — read both when editing a file.

All rules below are **MUST** unless tagged **SHOULD** / **PREFER** / **AVOID**.

## Code naming (cross-cutting)

| Entity         | Pattern                          | Examples                                            |
| -------------- | -------------------------------- | --------------------------------------------------- |
| Method         | `camelCase`                      | `store`, `massDestroy`, `run`                       |
| Model property | `snake_case`                     | `is_active`, `created_at`                           |
| Class property | `camelCase`                      | `$isActive`, `$createdAt`                           |
| Variable       | `camelCase`                      | `$isActive`, `$createdAt`                           |

## Class naming index

Per-class-type naming rules are colocated with the directory that holds the class. Quick map:

| Class type   | Directory                       |
| ------------ | ------------------------------- |
| Action       | `app/Actions/`                  |
| Command      | `app/Console/Commands/`         |
| Controller   | `app/Http/Controllers/`         |
| Data (DTO)   | `app/Data/`                     |
| Event        | `app/Events/`                   |
| Exception    | `app/Exceptions/`               |
| Factory      | `database/factories/`           |
| Job          | `app/Jobs/`                     |
| Mail         | `app/Mail/`                     |
| Middleware   | `app/Http/Middleware/`          |
| Migration    | `database/migrations/`          |
| Model        | `app/Models/`                   |
| Notification | `app/Notifications/`            |
| Observer     | `app/Observers/`                |
| Policy       | `app/Policies/`                 |
| Provider     | `app/Providers/`                |
| Request      | `app/Http/Requests/`            |
| Rule         | `app/Rules/`                    |
| Seeder       | `database/seeders/`             |
| Support      | `app/Support/`                  |
| Test         | `tests/`                        |

For class types without a dedicated CLAUDE.md, defaults:

- **Data (DTO)** — `{SingularModel}Data` (e.g. `UserData`).
- **Exception** — `{Cause}Exception` (e.g. `ValidationException`, `DuplicateEntryException`).
- **Interface** — adjective/noun, **no suffix** (e.g. `Loggable`, `Configurable`).
- **Mail** — event-like, **no suffix** (e.g. `InvoicePaid`, `OrderShipped`).
- **Notification** — event-like, **no suffix** (e.g. `InvoicePaid`, `PasswordReset`).
- **Policy** — `{SingularModel}Policy`.
- **Provider** — `{Domain}Provider` (e.g. `PaymentProvider`).
- **Rule** — rule meaning, **no suffix** (e.g. `ValidPhoneNumber`, `Uppercase`).
- **Scope** — `{Adjective}Scope` (e.g. `ActiveScope`).
- **Trait** — adjective or `With{Feature}`, no suffix (e.g. `Sortable`, `WithForm`).
