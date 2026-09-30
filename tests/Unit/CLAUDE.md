# Unit Tests

**Purpose:** test genuinely isolated logic in complete isolation — no framework, no database, no HTTP. Fast, deterministic, dependency-free.

> Refines `tests/CLAUDE.md` for this directory. Anything that needs the app booted is a Feature test — see `tests/Feature/CLAUDE.md`.

## What belongs here

- Pure functions and calculations (money math, date arithmetic, scoring, formatting).
- Value objects and DTOs (`app/Data/`, `app/Support/`) — construction, mapping, derived accessors.
- Pure enum behaviour — `color()` / predicate methods (`isFinal()`, `canTransitionTo()`). Translated `label()` methods use the application translator and belong in Feature tests.
- Cast transform logic and other small, self-contained units with no I/O.

## Rules

- **MUST NOT** touch the database, the container, the queue, HTTP, mail, or the filesystem. If a test needs any of those, it's a Feature test — move it. A unit test that boots the framework isn't a unit test.
- **MUST NOT** use `RefreshDatabase` / `LazilyRefreshDatabase` or Eloquent factories here — even `factory()->make()` resolves Faker through the container. Construct plain objects directly; factory-based tests belong in `tests/Feature/`.
- **AVOID** mocking the framework just to bypass it. Heavy mocking to make a "unit" test pass is a signal the logic should be tested as a Feature test, or extracted into a pure class first.
- **SHOULD** instantiate the subject directly (`new MoneyCalculator()`), not resolve it from the container.
- **SHOULD** use a Pest **dataset** for boundary/validation cases that vary only by input, rather than copy-pasting near-identical tests.

```php
it('rounds half to even', function (int $cents, int $expected) {
    expect(MoneyValue::fromCents($cents, 'USD')->roundedDollars())->toBe($expected);
})->with([
    'rounds down' => [149, 1],
    'half to even' => [150, 2],
    'rounds up' => [151, 2],
]);
```

## When NOT to write a Unit test

- Eloquent persistence, scopes, relationships → **Feature** (they need a database).
- Anything reached over HTTP, queue, mail, or broadcast → **Feature**.
- Naming / layering / structure → **Architecture** (`tests/Architecture/CLAUDE.md`).

## Create

```bash
php artisan make:test --unit Support/MoneyValueTest
```
