# Feature Tests

**Purpose:** the default test type. Exercise the full stack through its real entry points — HTTP, Livewire, console, queue — against a real (test) database. They catch the largest class of regressions for the least test code.

> Refines `tests/CLAUDE.md` for this directory. Structural rules → `tests/Architecture/CLAUDE.md`. Isolated pure logic → `tests/Unit/CLAUDE.md`.

## What belongs here

- HTTP endpoints — status, redirect, validation, and **authorization boundaries** (every route exposed to users).
- Livewire / Filament components — mounting, actions, state, authorization.
- Console commands — invoked via `$this->artisan(...)`.
- Jobs / listeners / notifications exercised end-to-end (dispatched, then asserted via fakes or persisted effects).
- Policies through the HTTP layer (prefer this over isolated policy unit tests — it proves the route is actually guarded).

## Rules

- **MUST** be the default. Reach for a Unit test only for genuinely isolated logic (see `tests/Unit/CLAUDE.md`); reach for an arch test only for structure.
- **MUST** mirror the app's domain sub-namespacing in the path — a test for `App\Http\Controllers\Billing\InvoiceController` lives at `tests/Feature/Billing/InvoiceControllerTest.php`. See `app/CLAUDE.md`.
- **MUST** rely on the database refresh trait wired in `tests/Pest.php` (`RefreshDatabase` / `LazilyRefreshDatabase`) so each test starts from a clean schema — don't re-`use` it per file.
- **MUST** assert behaviour and outcomes — persisted data, validation errors, redirects, dispatched jobs/notifications, side effects, record-level scoping/authorization. **MUST NOT** assert on presentation (layout, copy, labels, nav, element order, CSS classes); those are change-detectors.
- **MUST** test both the allow **and** deny paths of every authorization boundary — a test that only proves the happy path leaves the lock untested.
- **SHOULD** cover, per endpoint: happy path, validation failure, authorization failure, and one edge case per branch.
- **AVOID** mocking your own application classes — exercise them for real and mock only external boundaries. If a class is hard to exercise, refactor it, don't mock it.

## External boundaries — fake, don't hit

- **MUST** fake outbound I/O: `Mail::fake()`, `Notification::fake()`, `Queue::fake()`, `Bus::fake()`, `Storage::fake()`, `Http::fake([...])`.
- **SHOULD** prefer allowlisted fakes (`Event::fake([OrderShipped::class])`) so unrelated listeners stay live.
- **MUST** call `Http::preventStrayRequests()` whenever you fake an HTTP client — an unmatched request then throws instead of silently hitting the real endpoint.

## Auth & abilities

- **MUST** act as a user with `actingAs(...)` (or `Sanctum::actingAs($user, [...abilities])`) and, for token-scoped endpoints, test both granted and missing abilities.

```php
Sanctum::actingAs($user, ['posts:write']);
$this->postJson('/api/posts', $payload)->assertCreated();

Sanctum::actingAs($user, []);                          // no abilities
$this->postJson('/api/posts', $payload)->assertForbidden();
```

## Shape, not strings

- **SHOULD** pin response **shape** with `assertJsonStructure([...])` (including the pagination envelope), not exact copy.
- **SHOULD** use model-aware assertions — `assertModelExists` / `assertModelMissing` / `assertSoftDeleted` — over hand-rolled `assertDatabaseHas` where they read clearer.

## Create

```bash
php artisan make:test Billing/InvoiceControllerTest
```
