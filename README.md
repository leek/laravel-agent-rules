# laravel-agent-rules

Directory-scoped agent rules for Laravel projects. Each `CLAUDE.md` lives next to the code it governs and mirrors the [laravel/laravel](https://github.com/laravel/laravel) skeleton — agents pick up the rules for whatever file you're editing.

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
npx apply-agent-rules apply leek/laravel-agent-rules@v0.2.0 --agents claude

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
| `cursor`  | `.cursor/rules/*.mdc`     |
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

Each agent picks up the rules colocated with the file it's editing — no central rules file, no manual wiring. Subdirectory rules ship as separate files in their own subdirectories, not concatenated into the root.

## What you get

| Path                              | Covers                                                    |
| --------------------------------- | --------------------------------------------------------- |
| `app/CLAUDE.md`                   | Cross-cutting naming, code style (one-thing methods, no DocBlocks, short syntax, standard tools), IoC/DI, constants & i18n, domain sub-namespacing, class-type → directory index |
| `app/Models/`                     | Eloquent: casts, relationships, scopes, eager loading, transactions |
| `app/Enums/`                      | Backed enums: string backing, `casts()`, `Rule::enum()`, label methods |
| `app/Casts/`                      | Custom Eloquent casts: value objects, `CastsAttributes`, inbound-only |
| `app/Data/`                       | DTOs: `{Verb}{Model}Data` naming, immutable, built at the boundary (`toDto()`), plain vs `spatie/laravel-data` |
| `app/Http/Controllers/`           | Controller rules + audience/domain namespacing            |
| `app/Http/Requests/`              | Form Request rules + `toDto()` pattern                    |
| `app/Http/Resources/`             | API Resource pattern + paginated envelope                 |
| `app/Http/Middleware/`            | Middleware rules: terminate, variadic params, bootstrap registration |
| `app/Policies/`                   | Policy auto-discovery, `before()` fall-through, `Response::deny*` helpers |
| `app/Rules/`                      | Custom `ValidationRule` classes vs closure rules vs FormRequest `after()` |
| `app/Actions/`                    | Action rules (default home for business logic)            |
| `app/Support/`                    | Support classes + caching (`flexible`, `lock`, `memo`, keys, invalidation) |
| `app/Services/`                   | Service layer: external-system/3rd-party wrappers; when to use vs Action/Support |
| `app/Concerns/`                   | Traits: one capability per trait, `boot`/`initialize` hooks, declared host contracts |
| `app/Contracts/`                  | Interfaces: ISP, depend-on-abstraction, bind in a provider |
| `app/States/`                     | State machines (`spatie/laravel-model-states`): transition graph, guarded transitions |
| `app/Exceptions/`                 | Domain exceptions, static constructors, L11+ `withExceptions()` config |
| `app/Observers/`                  | Observer rules + `#[ObservedBy]` attribute registration   |
| `app/Events/`                     | Event + listener rules                                    |
| `app/Broadcasting/`               | Channel authorization classes (`make:channel`), `channels.php` registration, presence vs private, `ShouldBroadcast` events |
| `app/Listeners/`                  | `ShouldQueue` / `ShouldQueueAfterCommit`, auto-discovery, multi-method listeners |
| `app/Jobs/`                       | Queue jobs: retries, afterCommit, unique/overlapping, batching, idempotency |
| `app/Livewire/`                   | Auto-save, morphing, `wire:model.defer`, `#[Computed]`, `$queryString`, authorize-in-action |
| `app/Notifications/`              | Channels, `viaQueues`, `shouldSend`, bulk send, on-demand routing, custom channels |
| `app/Mail/`                       | Mailables: envelope/content API, markdown, queueing, Mailable vs Notification |
| `app/Features/`                   | Feature flags with Laravel Pennant (closure + class features, rollouts, cleanup) |
| `app/View/Components/`            | Class-based Blade components                              |
| `app/Console/Commands/`           | Artisan command rules                                     |
| `app/Providers/`                  | Container bindings (interface → implementation)           |
| `config/`                         | `env()` / `config()` rules                                |
| `routes/`                         | Routing, named routes, API versioning, scheduling (`routes/console.php`) |
| `resources/views/`                | Blade views: kebab-case naming, no inline JS/CSS, `@json`/`data-*`, no queries in views, dates formatted in the display layer |
| `resources/views/components/`     | Anonymous Blade components: `@props`, `$attributes`, `@class`/`@style`/`@pushOnce`/`@fragment` |
| `database/`                       | Schema, keys, indexes, table/column naming                |
| `database/migrations/`            | Migration workflow                                        |
| `database/factories/`             | Factory rules                                             |
| `database/seeders/`               | Seeder rules                                              |
| `tests/`                          | Pest testing: datasets, Sanctum abilities, soft-delete asserts, allowlisted fakes |

## Versioning

Releases are tagged. Pin with `leek/laravel-agent-rules@v0.2.0` if you want reproducible installs.

## License

MIT
