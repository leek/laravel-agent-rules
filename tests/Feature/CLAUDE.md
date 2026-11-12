# Feature Tests

**Purpose:** the default test type. Exercise the full stack through its real entry points — HTTP, Livewire, console, queue — against a real (test) database. They catch the largest class of regressions for the least test code.

> Refines `tests/CLAUDE.md` for this directory. How to write tests (datasets, fakes, Sanctum, assertions) lives in the parent. Structural rules → `tests/Architecture/CLAUDE.md`. Isolated pure logic → `tests/Unit/CLAUDE.md`.

## What belongs here

- HTTP endpoints — status, redirect, validation, and **authorization boundaries** (every route exposed to users).
- Livewire / Filament components — mounting, actions, state, authorization.
- Console commands — invoked via `$this->artisan(...)`.
- Jobs / listeners / notifications exercised end-to-end (dispatched, then asserted via fakes or persisted effects).
- Policies through the HTTP layer (prefer this over isolated policy unit tests — it proves the route is actually guarded).

## Rules

- **MUST** be the default. Reach for a Unit test only for genuinely isolated logic (see `tests/Unit/CLAUDE.md`); reach for an arch test only for structure.
- **MUST** mirror the source path under `app/`, including class type, audience/version, and domain — `App\Http\Controllers\Billing\InvoiceController` maps to `tests/Feature/Http/Controllers/Billing/InvoiceControllerTest.php`, while the API v1 variant maps to `tests/Feature/Http/Controllers/Api/V1/Billing/InvoiceControllerTest.php`. See `app/CLAUDE.md`.
- **MUST** rely on the database refresh trait wired in `tests/Pest.php` (`RefreshDatabase` / `LazilyRefreshDatabase`) so each test starts from a clean schema — don't re-`use` it per file.
- **MUST** assert behaviour and outcomes — persisted data, validation errors, redirects, dispatched jobs/notifications, side effects, record-level scoping/authorization. **AVOID** assertions on incidental presentation (layout, copy, labels, nav, element order, CSS classes). Rendered content assertions are appropriate when the content is the business contract, such as the correct invoice amount/number or an actionable link in an email; assert those values rather than snapshotting surrounding prose.
- **MUST** test both the allow **and** deny paths of every authorization boundary — a test that only proves the happy path leaves the lock untested.
- **SHOULD** cover, per endpoint: happy path, validation failure, authorization failure, and one edge case per branch.
- **AVOID** mocking your own application classes — exercise them for real and mock only external boundaries (see parent for `Mail::fake` / `Http::fake` / Sanctum abilities / shape assertions).

## Cases that catch drift

Add these when the feature has the shape named; each one fails for a defect the happy path cannot see.

- **Preview and write:** a feature with a preview (or dry-run) and a write path **MUST** have a parity test that runs both on the same fixtures and asserts the preview lists exactly what the write changes, including a row the write refuses.
- **Undo and round-trip:** an undo, restore, or prefill-then-save operation **MUST** have a test that captures the full row and its child rows before, runs the operation and its inverse, and compares everything. Asserting only the field that moved misses the ones the snapshot forgot.
- **Soft-deleted relation:** code that reads through a relation in a historical context (report, export, audit view, edit form showing a stored key) **MUST** have a case with the related row soft-deleted.
- **Run it twice:** a seeder, backfill or one-time operation **MUST** have a test that runs it twice and asserts the second run changes nothing: no duplicates, no exception, same counts.

## Create

```bash
php artisan make:test Http/Controllers/Billing/InvoiceControllerTest
```

## Freeze the clock for relative wall-clock data

A test that builds records as `date => today()` with times like `now()->addHours(2)->format('H:i:s')` wraps past midnight on a late-night run and flips any window logic. **MUST** pin the clock first, e.g. `$this->travelTo(today()->setTime(12, 0))` in `beforeEach`.
