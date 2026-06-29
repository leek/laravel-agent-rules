# Architecture Tests

**Purpose:** Pest `arch()` tests that assert the project's *structure* — naming, base types, layering, finalness, no debug/env leftovers. They encode the conventions the per-directory `CLAUDE.md` files define so those conventions can't silently rot.

> Refines `tests/CLAUDE.md` for this directory. Behavioural tests never live here — see `tests/Feature/CLAUDE.md` and `tests/Unit/CLAUDE.md`.

## What belongs here

- **MUST** contain only structural assertions — `arch()` expectations and small `test()` closures that read the filesystem / reflection. **No database, no HTTP, no factories, no container boot.** Anything that needs a request, a model row, or a booted app is a Feature/Unit test.
- **MUST** stay near-instant. Arch tests run on every push as the cheapest guardrail; keep them free of I/O beyond reading source files.
- **SHOULD** be split into focused files by concern (`SuffixTest`, `TypesTest`, `ConventionsTest`, `GlobalsTest`, `LayeringTest`) rather than one monolith.

## Rules

- **MUST** name every rule after the convention it guards, so a red test points straight at the violated rule (`arch('actions have an Action suffix')`, not `arch('test1')`).
- **SHOULD** cite the `CLAUDE.md` each rule enforces in a one-line comment above it — a reviewer can trace the rule to its source.
- **MUST** treat a failing arch test as a real defect: either the code drifted (fix the code) or the rule changed (fix the rule). **Never** silence the suite or delete a rule to make CI green.
- **MUST** use `->ignoring(...)` for deliberate, documented exceptions, each with a one-line reason comment (and a tracking note if it's a deferred rename). An un-commented `->ignoring()` is indistinguishable from hiding a bug.
- **SHOULD** start from the presets, then layer the rules this ruleset adds on top.

```php
arch()->preset()->laravel();   // framework naming + structure conventions
arch()->preset()->security();  // flags eval, md5, mt_rand, extract, etc.
arch()->preset()->php();       // flags debug-ish builtins
```

## Coverage matrix

The conventions below are mechanically checkable. Each maps to the directory `CLAUDE.md` that states the rule.

**Naming — required suffix:**

```php
arch('actions')->expect('App\Actions')->toHaveSuffix('Action');
arch('controllers')->expect('App\Http\Controllers')->toHaveSuffix('Controller');
arch('jobs')->expect('App\Jobs')->toHaveSuffix('Job');
arch('commands')->expect('App\Console\Commands')->toHaveSuffix('Command');
arch('requests')->expect('App\Http\Requests')->toHaveSuffix('Request');
arch('resources')->expect('App\Http\Resources')->toHaveSuffix('Resource');
arch('policies')->expect('App\Policies')->toHaveSuffix('Policy');
arch('observers')->expect('App\Observers')->toHaveSuffix('Observer');
arch('services')->expect('App\Services')->toHaveSuffix('Service');
arch('data')->expect('App\Data')->toHaveSuffix('Data');
arch('channels')->expect('App\Broadcasting')->toHaveSuffix('Channel');
```

**Naming — forbidden suffix/prefix:**

```php
arch('models')->expect('App\Models')->not->toHaveSuffix('Model');
arch('enums')->expect('App\Enums')->not->toHaveSuffix('Enum');
arch('concerns')->expect('App\Concerns')->not->toHaveSuffix('Trait');
arch('support')->expect('App\Support')->not->toHaveSuffix('Support');
arch('events')->expect('App\Events')->not->toHaveSuffix('Event');
arch('middleware')->expect('App\Http\Middleware')->not->toHaveSuffix('Middleware');
arch('contracts')->expect('App\Contracts')->not->toHaveSuffix('Interface')->not->toHavePrefix('I');
```

**Base type / shape:**

```php
arch('models')->expect('App\Models')->toExtend('Illuminate\Database\Eloquent\Model');
arch('enums')->expect('App\Enums')->toBeEnums();
arch('contracts')->expect('App\Contracts')->toBeInterfaces();
arch('concerns')->expect('App\Concerns')->toBeTraits();
arch('requests')->expect('App\Http\Requests')->toExtend('Illuminate\Foundation\Http\FormRequest');
arch('resources')->expect('App\Http\Resources')->toExtend('Illuminate\Http\Resources\Json\JsonResource');
arch('rules')->expect('App\Rules')->toImplement('Illuminate\Contracts\Validation\ValidationRule');
arch('providers')->expect('App\Providers')->toExtend('Illuminate\Support\ServiceProvider');
arch('commands')->expect('App\Console\Commands')->toExtend('Illuminate\Console\Command');
arch('jobs')->expect('App\Jobs')->toHaveMethod('handle');
```

**Layering (the highest-value rules — keep the architecture honest):**

```php
// app/Support — stateless, framework-agnostic helpers (app/Support/CLAUDE.md)
arch('support stays out of the HTTP layer')->expect('App\Support')->not->toUse('App\Http');

// app/Data — DTOs are immutable and hold no Eloquent (app/Data/CLAUDE.md)
arch('data is immutable')->expect('App\Data')->toBeReadonly();
arch('data holds no models')->expect('App\Data')->not->toUse('Illuminate\Database\Eloquent\Model');

// app/Jobs — must not read request/session/auth state (it isn't there on a worker)
arch('jobs are request-agnostic')
    ->expect(['request', 'session', 'auth', Illuminate\Support\Facades\Request::class])
    ->each->not->toBeUsedIn('App\Jobs');
```

**Hygiene:**

```php
arch('no debug leftovers')
    ->expect(['dd', 'dump', 'ray', 'ddd', 'var_dump', 'die', 'exit'])
    ->not->toBeUsed();

// config/CLAUDE.md — env() only inside config/*.php
arch('no env outside config')->expect('env')->not->toBeUsed();
```

## When the rule isn't statically checkable

Some `CLAUDE.md` rules need more than namespace/reflection (e.g. "a `*RelationManager` name must match its parent relation method", "migrations must be anonymous classes"). Write a small `test()` that tokenises / globs the source files rather than forcing it into an `arch()` chain — still no DB, still in this directory.
