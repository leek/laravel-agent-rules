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
npx apply-agent-rules apply leek/laravel-agent-rules@v0.1.0 --agents claude

# Re-pull later, preserving local edits and pruning removed files
npx apply-agent-rules update
```

Supported agents: `claude`, `codex`, `gemini`, `cursor`, `windsurf`, `cline`. The single canonical `CLAUDE.md` in this repo is rendered to each selected agent's expected filename (`AGENTS.md`, `GEMINI.md`, `.cursorrules`, …) in the same directory.

## What you get

| Path                              | Covers                                                    |
| --------------------------------- | --------------------------------------------------------- |
| `app/CLAUDE.md`                   | Cross-cutting code naming + class-type → directory index  |
| `app/Models/`                     | Model rules                                               |
| `app/Http/Controllers/`           | Controller rules + audience namespacing                   |
| `app/Http/Requests/`              | Form Request rules                                        |
| `app/Http/Middleware/`            | Middleware rules                                          |
| `app/Actions/`                    | Action rules (default home for business logic)            |
| `app/Support/`                    | Support class rules                                       |
| `app/Observers/`                  | Observer rules                                            |
| `app/Events/`                     | Event + listener rules                                    |
| `app/Jobs/`                       | Queue job rules                                           |
| `app/Livewire/`                   | Livewire auto-save, morphing, component aliasing          |
| `app/Notifications/`              | Notification naming + Laravel 12+ listener auto-discovery |
| `app/Console/Commands/`           | Artisan command rules                                     |
| `config/`                         | `env()` / `config()` rules                                |
| `routes/`                         | Routing + route naming                                    |
| `database/`                       | Schema, keys, indexes, table/column naming                |
| `database/migrations/`            | Migration workflow                                        |
| `database/factories/`             | Factory rules                                             |
| `database/seeders/`               | Seeder rules                                              |
| `tests/`                          | Pest testing rules                                        |

## Versioning

Releases are tagged. Pin with `leek/laravel-agent-rules@v0.1.0` if you want reproducible installs.

## License

MIT
