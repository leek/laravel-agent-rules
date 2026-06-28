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

## Where does logic go?

| Situation                                                        | Use                                  |
| ---------------------------------------------------------------- | ------------------------------------ |
| Synchronous business operation — the caller needs the result     | Action (`app/Actions/`)              |
| Work that can run later, off the request path                    | Job (`app/Jobs/`)                    |
| One occurrence, several unrelated side effects (fan-out)         | Event + Listeners (`app/Events/`)    |
| Telling a user something via mail / database / SMS / broadcast   | Notification (`app/Notifications/`)  |
| Wrapping an external system / SDK, or a cohesive multi-method capability | Service (`app/Services/`)    |
| Stateless helper with no side effects                            | Support class (`app/Support/`)       |

## Class naming index

Per-class-type naming rules are colocated with the directory that holds the class. Quick map:

| Class type   | Directory                       |
| ------------ | ------------------------------- |
| Action       | `app/Actions/`                  |
| Cast         | `app/Casts/`                    |
| Channel      | `app/Broadcasting/`             |
| Command      | `app/Console/Commands/`         |
| Concern (Trait) | `app/Concerns/`              |
| Contract (Interface) | `app/Contracts/`       |
| Controller   | `app/Http/Controllers/`         |
| Data (DTO)   | `app/Data/`                     |
| Enum         | `app/Enums/`                    |
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
| Service      | `app/Services/`                 |
| State        | `app/States/`                   |
| Support      | `app/Support/`                  |
| Test         | `tests/`                        |

For class types without a dedicated CLAUDE.md, defaults:

- **Notification** — event-like, **no suffix** (e.g. `InvoicePaid`, `PasswordReset`).
- **Policy** — `{SingularModel}Policy`.
- **Provider** — `{Domain}Provider` (e.g. `PaymentProvider`).
- **Rule** — rule meaning, **no suffix** (e.g. `ValidPhoneNumber`, `Uppercase`).
- **Scope** — `{Adjective}Scope` (e.g. `ActiveScope`).
