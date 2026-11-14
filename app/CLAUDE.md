# Laravel Agent Rules

Cross-cutting conventions for the whole project. Per-class-type rules live in the matching directory's `CLAUDE.md` — read both when editing a file.

All rules below are **MUST** unless tagged **SHOULD** / **PREFER** / **AVOID**.

## Code naming (cross-cutting)

| Entity         | Pattern                          | Examples                                            |
| -------------- | -------------------------------- | --------------------------------------------------- |
| Method         | `camelCase`                      | `store`, `massDestroy`, `handle`                    |
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

## Organize by domain (sub-namespacing)

Group classes by **business domain** in a subfolder *inside* the class-type folder: `app/Models/Billing/Invoice.php`, `app/Jobs/Billing/SyncInvoiceToErp.php`, `app/Policies/Billing/InvoicePolicy.php`.

- **SHOULD** start flat. A class-type folder serving one domain stays flat (`app/Models/User.php`).
- **MUST** group by domain subfolder when a folder holds classes from **2+ distinct business domains**. Domain count decides, not file count.
- **MUST** match the namespace to the directory (PSR-4) and pass the sub-path to `make:*`: `php artisan make:model Billing/Invoice`.
- **MUST** use **one** domain name across every folder: `Billing/` in Models, Jobs, Policies and Requests alike, taken from the app's own vocabulary.
- **Controllers** nest the domain *inside* the audience/version axis (`Api/V1/Billing/InvoiceController.php`) — see `app/Http/Controllers/CLAUDE.md`.
- **Factories** mirror the model's subfolder — see `database/factories/CLAUDE.md`.
- **Migrations stay flat** — see `database/migrations/CLAUDE.md`.
- **Filament resources group by model**; the domain axis there is a Cluster with the same name (`app/Filament/Clusters/Billing/`). See `filament-agent-rules`.
- **Tests** mirror the source path under `app/` (`tests/Feature/Http/Controllers/Api/V1/Billing/InvoiceControllerTest.php`).

## Code style (cross-cutting)

- **MUST** keep each method doing one thing. Extract a well-named `private`/`protected` helper instead of nesting conditionals or growing a method past one screen.
- **PREFER** descriptive method and variable names over comments. Write a comment only to explain *why* (non-obvious intent, an edge case), never *what* the code already says.
- **MUST** use native type hints and return types; **AVOID** DocBlocks that merely restate them. Use a DocBlock only for types PHP can't express natively (array shapes, `iterable`-of) — e.g. `/** @var OrderItemData[] */`.
- **PREFER** modern PHP syntax where it improves clarity: constructor property promotion, `readonly`, enums, `match`, nullsafe `?->`, named arguments, first-class callables.
- **PREFER** the shortest readable Laravel syntax: `session('cart')` over `Session::get('cart')`; `$request->name` over `$request->input('name')`; `now()`/`today()`; `back()`; `$model->relation?->id`; `compact('user')`; `->latest()`/`->oldest()`; `->where('active', 1)`; `->get(['id', 'name'])`; `->value('name')`.
- **MUST** use community-standard Laravel and first-party tools; **AVOID** alien replacements. Eloquent (not Doctrine), Blade (not Twig), Policies (not Entrust), Sanctum/Passport for API auth, Migrations for schema, built-in localization, the Task Scheduler, Collections over plain arrays. Don't import patterns from other ecosystems (generic ORMs, repository-per-model, alternate templating/DI) when an idiomatic Laravel approach exists.
- **MUST NOT** override or reimplement standard framework features. Extend via the documented seams (service providers, macros, events, contracts) instead of replacing core behaviour.
- **MUST NOT** build HTML strings inside PHP classes (controllers, actions, services, mailables, notifications). Render markup in Blade and pass data in. (A Blade `render()` heredoc in a class-based component is the only sanctioned exception — see `app/View/Components/CLAUDE.md`.)

## Dependency injection (IoC)

- **MUST** resolve classes that carry their own dependencies from the container — constructor-inject them, or use `app(Foo::class)`. **AVOID** `new SomeClass(...)` for anything with dependencies or an interface binding; hard-wiring concretes couples callers to implementations and blocks testing/mocking. (`new` is fine for plain value objects / DTOs.)
- **MUST** depend on the interface, not the concrete, when one is bound — see `app/Providers/CLAUDE.md`.

❌ Hard-wired concrete — untestable:

```php
public function handle(array $data): User
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
- **MUST** keep user-facing copy in translation files and read it via `__('app.article_added')` / `trans_choice(...)`. Never inline literal user-facing strings in PHP or Blade. That includes enum/state `label()` methods, mailable subjects, notification lines, validation messages, and Blade text.
- **Exception — Filament:** admin-panel component `->label()`s, modal headings, `Notification` titles, and enum `getLabel()` follow the Filament ruleset (inline by default; lifted to `__()` via a global `configureUsing` default when the panel is localized) — see `filament-agent-rules`.
- **Exception — developer-only strings:** log messages, exception messages for internal failures, and Artisan `$description` / signatures may stay inline English.

## Security (cross-cutting)

- **MUST** authorize before acting — policies / `Gate::authorize()` / route `->can(...)` before mutations or sensitive reads. `$this->authorize()` requires the `AuthorizesRequests` trait. Never trust a client-supplied id alone for ownership or tenancy.
- **MUST** scope lookups to the authenticated boundary (tenant, owner, team) in queries and validation (`exists` / `unique` rules) — global scopes and policies do not automatically protect every query.
- **MUST NOT** concatenate request input into SQL, shell commands, or raw cache/key fragments. Whitelist sorts, filters, and report dimensions with an enum or explicit map.
- **MUST** read secrets only via `config(...)` (never `env()` outside `config/*.php`) — see `config/CLAUDE.md`.
- **MUST NOT** mass-assign unvalidated request data; use Form Requests and `$fillable` / `$guarded` deliberately — see `app/Http/Requests/CLAUDE.md` and `app/Models/CLAUDE.md`.

## Correctness (cross-cutting)

These rules apply wherever the code lives: Actions, Services, Livewire components, Commands, Jobs, Observers, Queries.

### Sweep the siblings

A rule enforced on one path and missed on its twin is the most common defect found in review. When you add or change a guard, eligibility rule, authorization check, masking rule, enum case, status value or mode, **MUST** find every other path that has to agree and change it in the same commit:

- **Every entry point of the operation:** create and update, send and resend, UI and API and job and command. Put the guard in the shared callee (the Action or the model) so no caller can skip it. An effect applied on create (a provider setting, a derived column) also applies on update.
- **Every reader of the status, enum or key you extended:** grep the old cases (`=== Status::X`, `where('status', ...)`, `match`, scopes, notification and banner copy). A reader that was never updated treats the new case as "not the old one". The same sweep applies when you re-key a relation or make a foreign key nullable: readers of the old key, and every early return on its null.
- **A preview or dry-run calls the runtime's own decision code,** never a copy. A copy drifts, and the preview then promises what the write refuses.
- **A predicate that a model scope owns is not restated** in a report, export or new query: call the scope. When a scope has an in-memory twin (`scopeOverdue()` and `isOverdue()`), change both and test that they agree on the same rows.
- **Find the sibling first.** Before writing the logic, look for the existing implementation of the same operation or quantity and diff its guards against yours. The correct rule is often already next door.

```php
// ❌ Restates half of the scope: dismissed alerts now count as active.
Alert::query()->where('is_resolved', false)->count();

// ✅ One owner.
Alert::query()->active()->count();
```

### Lock, re-read, then decide

**MUST** take the row lock first, re-read the row under it, and only then check and write. A check made on a model loaded before the lock (status, eligibility, optimistic version, access token) proves nothing: a concurrent request passes the same check.

```php
// ❌ Checked before the lock: two concurrent approvals both pass.
if ($invoice->status !== InvoiceStatus::Draft) {
    return;
}
DB::transaction(fn () => $invoice->update(['status' => InvoiceStatus::Approved]));

// ✅ Lock, re-read, re-check, write.
DB::transaction(function () use ($invoiceId): void {
    $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoiceId);

    if ($invoice->status !== InvoiceStatus::Draft) {
        return;
    }

    $invoice->update(['status' => InvoiceStatus::Approved]);
});
```

- Repeat **every** pre-lock check on the locked row, including time-derived ones (`isUpcoming()`). A version check compares the record's identity and its version, not a counter alone.
- Build outgoing content (mail, API payloads, before/after snapshots) from the locked row, inside the transaction.
- Never save a whole JSON or array column from a copy read before the lock. Change the one key under the lock, or atomically: `->update(['meta->cursor' => $next])`.
- In a chunked sweep the chunk's model is already stale. Put the predicate in the UPDATE's `where` and check the affected count, or re-read under the lock before saving.
- After an HTTP call, everything loaded before it is stale. Lock and re-read before writing what the call returned, and confirm the credential or version the call used is still the current one.
- All writers of one invariant take the same lock. A cache lock and a row lock do not exclude each other.
- A check-then-create has no row to lock. **MUST** back it with a unique index on the identity columns and write with `createOrFirst()` or `firstOrCreate()`, which recover from the violation only when that index exists. Where no index can express the identity, lock the parent row first.

`lockForUpdate()` only locks inside a transaction — see **Transactions** in `app/Models/CLAUDE.md`.

### Derived state

A stamp, flag, fingerprint, cache key, snapshot or denormalised total is computed from inputs. **MUST** name those inputs where it is computed, and make every writer of an input reset or recompute it in the same write.

- A cache key, memo key or fingerprint includes every input that changes the result. `drive:{from}:{to}` reused across departure times returns the morning duration for a rush-hour trip.
- A recompute handles the empty set by writing zero or null. Returning early leaves the old total in place after the last child is removed.
- A flag set on loss or failure needs the path that clears it on recovery. A column that only applies to one type or foreign key is cleared when that type or key changes.
- Retracting, replacing or re-dating a parent closes the open work it spawned (tasks, reminders, scheduled sends) in the same write. Otherwise the replacement spawns its own and the user sees both.

### Null and numeric edges

- `??` cannot tell an omitted key from an explicit null. Where null means "clear this field", **MUST** use `array_key_exists()`:

```php
// ❌ A cleared room comes back as the saved one.
$roomId = $data['room_id'] ?? $booking->room_id;

// ✅
$roomId = array_key_exists('room_id', $data) ? $data['room_id'] : $booking->room_id;
```

- **MUST** guard the empty case before `random_int(0, count($items) - 1)` or `array_rand($items)`. Both throw `ValueError` on an empty set.
- **MUST** compare thresholds and caps on unrounded values and round only for display. When a rounded amount is bounded by a hard cap, round toward the safe side.
- **MUST** validate the raw parts before a normaliser combines or discards them (sums components, maps an unknown value to null). Format-only normalisation (trim, lowercase) may run first.
- A regex length bound on user text needs the `/u` modifier. Without it `{1,10}` counts bytes and rejects multibyte text.

### Tenant-local dates

"Today" belongs to the tenant or user the record belongs to. **MUST** compute today, a default date, a date picker's max, and any past/future check in that timezone:

```php
// ❌ Server "today": at 03:00 UTC a Los Angeles clinic is still on yesterday.
$isFuture = $visitDate->isAfter(today());

// ✅
$isFuture = $visitDate->toDateString() > now($company->timezone)->toDateString();
```

- A stored UTC timestamp is converted to that timezone before it is shown, in Blade and in JavaScript alike.
- **MUST** test at an instant where the two dates differ: `$this->travelTo('2026-03-10 03:00:00')` with the tenant in `America/Los_Angeles`.

## Caching

Cache keys, `Cache::flexible` / locks / memo, tags, null results, and invalidation live in **`app/Support/CLAUDE.md`** — apply those rules at every call site (Jobs, Models, Actions), not only under Support classes.

## Class shape

- **PREFER** `final` on application classes (Actions, Jobs, DTOs, Support, Services, Policies, etc.) unless the class is intentionally designed for extension (abstract base, package seam, or framework subclass that docs require leaving open).

## Per-class-type rules

Naming and rules for each class type live in the `CLAUDE.md` of the directory that holds it. Homes that are not Laravel defaults: `app/Actions/`, `app/Data/` (DTOs), `app/Queries/`, `app/Models/Builders/`, `app/Models/Scopes/`, `app/Concerns/` (traits), `app/Contracts/` (interfaces), `app/Features/` (Pennant), `app/States/`, `app/Services/`, `app/Support/`.

## Default or fail

When code can either fall back to a sane default or throw, **default** when a wrong value only changes how something looks: a display format, a label, an optional setting, a page size, a display timezone. A missing format should never take down a request.

**Fail loudly instead** when a plausible-but-wrong value gets persisted, billed or grants access:

- authorization and permission decisions (a default widens access, so fail closed)
- tenant or owner scoping (an unscoped default leaks another tenant's data)
- money, quantities, rates and anything billed
- domain values a person will act on

`config()` returns `mixed`, and `config('key', 'default')` uses the default **only when the key is absent**: a key that exists with a `null` value still returns `null`. Declare every config key in `config/*.php` with a real default, and guard the call site with `??` when a null would break the caller.
