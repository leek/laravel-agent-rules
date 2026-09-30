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
- **SHOULD** use the focused matrix below, and add compatible security/PHP presets after inspecting the installed Pest version. Do not enable `preset()->laravel()` wholesale: its `ServiceProvider` suffix, listener `handle()` requirement, and mandatory `ShouldQueue` mailables conflict with this ruleset. Copy only compatible expectations from that preset. Also inspect its `env()` and `App\Http` restrictions before adoption; configuration, routes, bootstrap, and tests need deliberate allowances.

```php
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
arch('mailables')->expect('App\Mail')->toHaveSuffix('Mail');
arch('queries')->expect('App\Queries')->toHaveSuffix('Query');
arch('builders')->expect('App\Models\Builders')->toHaveSuffix('Builder');
```

**Naming — forbidden suffix/prefix:**

```php
arch('models')->expect('App\Models')->not->toHaveSuffix('Model')
    ->ignoring(['App\Models\Scopes', 'App\Models\Builders']); // query helpers are not models
arch('enums')->expect('App\Enums')->not->toHaveSuffix('Enum');
arch('concerns')->expect('App\Concerns')->not->toHaveSuffix('Trait');
arch('support')->expect('App\Support')->not->toHaveSuffix('Support');
arch('events')->expect('App\Events')->not->toHaveSuffix('Event');
arch('middleware')->expect('App\Http\Middleware')->not->toHaveSuffix('Middleware');
arch('contracts')->expect('App\Contracts')->not->toHaveSuffix('Interface');
```

**Base type / shape:**

```php
arch('models')->expect('App\Models')->toExtend('Illuminate\Database\Eloquent\Model')
    ->ignoring(['App\Models\Scopes', 'App\Models\Builders']); // these implement Scope / extend Builder
arch('builders')->expect('App\Models\Builders')->toExtend('Illuminate\Database\Eloquent\Builder');
arch('scopes')->expect('App\Models\Scopes')->toImplement('Illuminate\Database\Eloquent\Scope');
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

// app/Data — DTOs hold no Eloquent (app/Data/CLAUDE.md)
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
arch('no env outside config')
    ->expect('env')
    ->not->toBeUsed()
    ->ignoring('config');
```

## When the rule isn't statically checkable

For interface prefixes, reject the convention `I` followed by an uppercase letter (`IPaymentGateway`), not every name starting with `I` (`InvoiceGateway`, `IdempotencyStore`). For DTOs, check the class's own public properties rather than requiring a readonly class: Spatie's mutable `Data` base cannot be extended by a readonly class, but a subclass can declare readonly payload properties.

```php
function ruleClassesIn(string $directory): array
{
    $root = dirname(__DIR__, 2);
    $path = $root . '/app/' . $directory;

    if (! is_dir($path)) {
        return [];
    }

    $classes = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $relative = substr($file->getPathname(), strlen($root . '/app/'), -4);
        $classes[] = new ReflectionClass('App\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $relative));
    }

    return $classes;
}

test('contracts do not use the I interface prefix', function () {
    foreach (ruleClassesIn('Contracts') as $class) {
        expect(preg_match('/^I[A-Z]/', $class->getShortName()))->toBe(0);
    }
});

test('data payload properties are readonly', function () {
    foreach (ruleClassesIn('Data') as $class) {
        foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() !== $class->getName()) {
                continue; // framework-owned properties on Spatie Data are outside the payload
            }

            expect($property->isReadOnly())->toBeTrue();
        }
    }
});
```

Keep this helper in one architecture test file (or a shared test helper), not repeated in every file. The example assumes PSR-4 `App\` classes under `app/`; adjust for the project's autoload mapping.

Some `CLAUDE.md` rules need more than namespace/reflection (e.g. "a `*RelationManager` name must match its parent relation method", "migrations must be anonymous classes"). Write a small `test()` that tokenises / globs the source files rather than forcing it into an `arch()` chain — still no DB, still in this directory.
