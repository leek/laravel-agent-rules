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
| Collection var | descriptive `camelCase`, **plural**   | `$activeUsers`, `$pendingOrders`              |
| Object var     | descriptive `camelCase`, **singular** | `$activeUser`, `$pendingOrder`                |

## Where does logic go?

| Situation                                                        | Use                                  |
| ---------------------------------------------------------------- | ------------------------------------ |
| Synchronous business operation — the caller needs the result     | Action (`app/Actions/`)              |
| Work that can run later, off the request path                    | Job (`app/Jobs/`)                    |
| One occurrence, several unrelated side effects (fan-out)         | Event + Listeners (`app/Events/`)    |
| Telling a user something via mail / database / SMS / broadcast   | Notification (`app/Notifications/`)  |
| Wrapping an external system / SDK, or a cohesive multi-method capability | Service (`app/Services/`)    |
| Stateless helper with no side effects                            | Support class (`app/Support/`)       |

## Code style (cross-cutting)

- **MUST** keep each method doing one thing. Extract a well-named `private`/`protected` helper instead of nesting conditionals or growing a method past one screen.
- **PREFER** descriptive method and variable names over comments. Write a comment only to explain *why* (non-obvious intent, an edge case), never *what* the code already says.
- **MUST** use native type hints and return types; **AVOID** DocBlocks that merely restate them. Use a DocBlock only for types PHP can't express natively (array shapes, `iterable`-of) — e.g. `/** @var OrderItemData[] */`.
- **PREFER** modern PHP syntax where it improves clarity: constructor property promotion, `readonly`, enums, `match`, nullsafe `?->`, named arguments, first-class callables. Never sacrifice readability for cleverness.
- **PREFER** the shortest readable Laravel syntax: `session('cart')` over `Session::get('cart')`; `$request->name` over `$request->input('name')`; `now()`/`today()`; `back()`; `$model->relation?->id`; `compact('user')`; `->latest()`/`->oldest()`; `->where('active', 1)`; `->get(['id', 'name'])`; `->value('name')`.
- **MUST** use community-standard Laravel and first-party tools; **AVOID** alien replacements. Eloquent (not Doctrine), Blade (not Twig), Policies (not Entrust), Sanctum/Passport for API auth, Migrations for schema, built-in localization, the Task Scheduler, Collections over plain arrays. Don't import patterns from other ecosystems (generic ORMs, repository-per-model, alternate templating/DI) when an idiomatic Laravel approach exists.
- **MUST NOT** override or reimplement standard framework features. Extend via the documented seams (service providers, macros, events, contracts) instead of replacing core behaviour.
- **MUST NOT** build HTML strings inside PHP classes (controllers, actions, services, mailables, notifications). Render markup in Blade and pass data in. (A Blade `render()` heredoc in a class-based component is the only sanctioned exception — see `app/View/Components/CLAUDE.md`.)

## Dependency injection (IoC)

- **MUST** resolve classes that carry their own dependencies from the container — constructor-inject them, or use `app(Foo::class)`. **AVOID** `new SomeClass(...)` for anything with dependencies or an interface binding; hard-wiring concretes couples callers to implementations and blocks testing/mocking. (`new` is fine for plain value objects / DTOs.)
- **MUST** depend on the interface, not the concrete, when one is bound — see `app/Providers/CLAUDE.md`.

❌ Hard-wired concrete — untestable:

```php
public function run(array $data): User
{
    $mailer = new MailgunMailer(config('services.mailgun.key'));
    // ...
}
```

✅ Injected via the container:

```php
public function __construct(private readonly Mailer $mailer) {}
```

## Constants & strings

- **MUST NOT** hard-code magic string/number literals for statuses, types, roles, or keys. Use an enum (`OrderStatus::Pending` — see `app/Enums/CLAUDE.md`) or a class constant (`Article::TYPE_NORMAL`).
- **MUST** keep user-facing copy in translation files and read it via `__('app.article_added')` / `trans_choice(...)`. Never inline literal user-facing strings in PHP or Blade.

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
