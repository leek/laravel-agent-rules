# laravel-agent-rules

Directory-scoped agent rules for Laravel projects. Each `CLAUDE.md` lives next to the code it governs and mirrors the [laravel/laravel](https://github.com/laravel/laravel) skeleton. The root rules direct agents to shared conventions and any guidance outside the edited file's directory.

## Requirements

- **Laravel 12 or 13** (bootstrap `withMiddleware` / `withExceptions`, attribute model wiring, `casts()` method, schedule in `routes/console.php`). Laravel 12 requires PHP 8.2+; Laravel 13 requires PHP 8.3+. Version-specific examples are labeled; check the project's installed version before copying them. See [Laravel release notes](https://laravel.com/docs/13.x/releases).
- **Pest** for new tests (`tests/CLAUDE.md`). PHPUnit-style assertions still work under Pest.
- **Optional package dirs** — install rules only matter when the package is present:
  - `app/Livewire/` — Livewire 4
  - `app/Features/` — `laravel/pennant` (skip banner in file)
  - `app/States/` — `spatie/laravel-model-states` (skip banner in file)
- Companion rulesets (not in this repo): Filament → `filament-agent-rules`.

## Install

Use [apply-agent-rules](https://github.com/leek/apply-agent-rules) to drop the rules into a Laravel project:

```bash
# Preview what will be written
npx apply-agent-rules list leek/laravel-agent-rules --agents claude

# Install into the current project, interactive agent picker
npx apply-agent-rules apply leek/laravel-agent-rules

# Non-interactive (pick agents explicitly)
npx apply-agent-rules apply leek/laravel-agent-rules --agents claude,codex

# Pin to a release tag
npx apply-agent-rules apply leek/laravel-agent-rules@v0.18.3 --agents claude

# Re-pull later, preserving local edits and pruning removed files
npx apply-agent-rules update
```

Supported agents: `claude`, `codex`, `gemini`, `cursor`, `windsurf`, `cline`.

## How install works

Every `CLAUDE.md` in this repo is the canonical source. The installer walks the tree and, for each `CLAUDE.md` it finds, writes one file per selected agent into the **same directory** under that agent's expected filename:

| Agent     | Filename written          |
| --------- | ------------------------- |
| `claude`  | `CLAUDE.md`               |
| `codex`   | `AGENTS.md`               |
| `gemini`  | `GEMINI.md`               |
| `cursor`  | `.cursorrules`            |
| `windsurf`| `.windsurfrules`          |
| `cline`   | `.clinerules`             |

So picking `--agents claude,codex` against this repo produces, for example:

```
app/Models/CLAUDE.md      ← Claude reads this
app/Models/AGENTS.md      ← Codex reads this
app/Http/Controllers/CLAUDE.md
app/Http/Controllers/AGENTS.md
database/migrations/CLAUDE.md
database/migrations/AGENTS.md
...
```

Subdirectory rules ship as separate files, not concatenated into the root. The root rule file instructs agents to read the cross-cutting `app/` rules for every project directory, and resolves canonical `CLAUDE.md` references to the selected agent's filename. The installer copies content unchanged; it does not rewrite references.

Claude, Codex, and Gemini support hierarchical context files, subject to their context-loading settings. Do not assume every supported output filename has the same discovery behavior. The installer currently emits legacy `.cursorrules`, `.windsurfrules`, and `.clinerules` files rather than native glob-scoped rules. Verify the root loader is active and explicitly reads the relevant nested files. For automatic scoping, configure native rules using [Cursor project rules](https://docs.cursor.com/context/rules), [Windsurf rules or AGENTS.md](https://docs.windsurf.com/windsurf/cascade/memories), or [Cline conditional rules](https://docs.cline.bot/customization/cline-rules). Merely writing a legacy rule file into each subdirectory does not guarantee it is loaded.

Laravel's optional directories need setup independently of installing agent rules: `php artisan install:api` creates API routing, `php artisan install:broadcasting` configures broadcasting, and `php artisan lang:publish` publishes language files. Install only the features the project needs.

## What you get

| Path                              | Covers                                                    |
| --------------------------------- | --------------------------------------------------------- |
| `app/CLAUDE.md`                   | Cross-cutting naming, code style (one-thing methods, no DocBlocks, short syntax, standard tools, prefer `final`), IoC/DI, constants & i18n, security bullets, domain sub-namespacing, class-type → directory index |
| `app/Models/`                     | Eloquent: casts, relationships, scopes, eager loading, transactions |
| `app/Models/Scopes/` / `app/Models/Builders/` | Global scope classes and custom Eloquent builders |
| `app/Queries/`                    | Reusable query objects                                    |
| `bootstrap/`                      | Routing, middleware, exception hooks, and provider registration |
| `app/Enums/`                      | Backed enums: string backing, `casts()`, `Rule::enum()`, label methods |
| `app/Casts/`                      | Custom Eloquent casts: value objects, `CastsAttributes`, inbound-only |
| `app/Data/`                       | DTOs: `{Verb}{Model}Data` naming, immutable, built at the boundary (`toDto()`), plain vs `spatie/laravel-data` |
| `app/Http/Controllers/`           | Controller rules + audience/domain namespacing            |
| `app/Http/Requests/`              | Form Request rules + `toDto()` pattern                    |
| `app/Http/Resources/`             | API Resources: conditional fields, Laravel pagination preferred, optional project envelope |
| `app/Http/Middleware/`            | Middleware rules: terminate, variadic params, bootstrap registration |
| `app/Policies/`                   | Policy auto-discovery, `before()` fall-through, `Response::deny*` helpers |
| `app/Rules/`                      | Custom `ValidationRule` classes vs closure rules vs FormRequest `after()` |
| `app/Actions/`                    | Action rules (default home for business logic)            |
| `app/Support/`                    | Support classes + caching (`flexible`, `lock`, `memo`, keys, invalidation) |
| `app/Services/`                   | Service layer: external-system/3rd-party wrappers; when to use vs Action/Support |
| `app/Concerns/`                   | Traits: one capability per trait, `boot`/`initialize` hooks, declared host contracts |
| `app/Contracts/`                  | Interfaces: ISP, depend-on-abstraction, bind in a provider |
| `app/States/`                     | State machines (`spatie/laravel-model-states`): transition graph, guarded transitions |
| `app/Exceptions/`                 | Domain exceptions, static constructors, `withExceptions()` config |
| `app/Observers/`                  | Observer rules + `#[ObservedBy]` attribute registration   |
| `app/Events/`                     | Event rules (`ShouldDispatchAfterCommit`); listeners in `app/Listeners/` |
| `app/Broadcasting/`               | Channel authorization classes (`make:channel`), `channels.php` registration, presence vs private, `ShouldBroadcast` events |
| `app/Listeners/`                  | `ShouldQueue` / `ShouldQueueAfterCommit`, auto-discovery, multi-method listeners |
| `app/Jobs/`                       | Queue jobs: retries, afterCommit, unique/overlapping, batching, idempotency |
| `app/Livewire/`                   | Livewire 4: morphing, deferred `wire:model` (`.live`/`.live.blur`), `#[Computed]`, `#[Locked]`, authorize-in-action |
| `app/Notifications/`              | Channels, `viaQueues`, `shouldSend`, bulk send, on-demand routing, custom channels |
| `app/Mail/`                       | Mailables (`*Mail` suffix): envelope/content API, markdown, queueing vs Notification |
| `app/Features/`                   | Feature flags with Laravel Pennant (closure + class features, rollouts, cleanup) |
| `app/View/Components/`            | Class-based Blade components                              |
| `app/Console/Commands/`           | Artisan command rules                                     |
| `app/Providers/`                  | Container bindings (interface → implementation)           |
| `config/`                         | `env()` / `config()` rules                                |
| `routes/`                         | Routing, named routes, API versioning, scheduling (`routes/console.php`) |
| `resources/views/`                | Blade views: kebab-case naming, no inline JS/CSS, `@json`/`data-*`, no queries in views, dates formatted in the display layer |
| `resources/views/components/`     | Anonymous Blade components: `@props`, `$attributes`, `@class`/`@style`/`@pushOnce`/`@fragment` |
| `database/`                       | Schema, keys, indexes, table/column naming                |
| `database/migrations/`            | Migration workflow, expand-then-contract for production   |
| `database/factories/`             | Factory rules                                             |
| `database/seeders/`               | Seeder rules                                              |
| `lang/`                           | Localization: file layout, snake_case keys, placeholders  |
| `tests/`                          | Pest testing: architecture tests (`arch()`), datasets, Sanctum abilities, soft-delete asserts, allowlisted fakes |
| `tests/Architecture/`             | What belongs in `arch()` tests: structural-only, the naming/type/layering coverage matrix, `->ignoring()` discipline |
| `tests/Feature/`                  | The default test type: full-stack HTTP/Livewire/console, allow+deny boundaries, fake external I/O, shape-not-strings |
| `tests/Unit/`                     | Genuinely isolated logic only: no DB/HTTP/container, no `RefreshDatabase`, when NOT to use a unit test |

## Verification

Run `python3 .github/verification/references.py` to check rule pointers and code fences. For the selected runtime examples, run `composer install --working-dir=.github/verification` then `php .github/verification/run.php`. The checks boot a complete Laravel fixture with isolated in-memory SQLite, exercising the admin helper through a real HTTP route as well as validation, Pennant, casts, route serialization, cache invalidation, and reflection rules. CI runs these checks separately on Laravel 12 and 13. Illustrative snippets with application-specific classes are not all standalone programs. Verification code lives under `.github/` so the installer excludes it from target applications.

## Versioning

Releases are tagged. Pin with `leek/laravel-agent-rules@v0.18.3` if you want reproducible installs.

## License

MIT
