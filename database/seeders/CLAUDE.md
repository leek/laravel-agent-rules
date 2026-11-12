# Seeders

**Purpose:** populate a database with initial data (reference data, demo data, fixtures for local dev).

## Naming

- **MUST** be `{SingularModel}Seeder` (e.g. `UserSeeder`).

## Rules

- **SHOULD** call factories rather than insert raw rows.
- **SHOULD** use the `WithoutModelEvents` trait when seeders should not trigger observers (e.g. avoid sending welcome emails during a `db:seed`).
- **MUST** make a seeder that runs in a deployed environment safe to run twice: look rows up by a stable seed key (never a mutable attribute such as a date, or a positional index) and include trashed rows in the lookup (`withTrashed()`), or a row a user deleted is recreated on the next run. Test it by running it twice — see `tests/Feature/CLAUDE.md`.
- **PREFER** a trusted SQL fixture file with `DB::unprepared()` when importing a legacy export or large static reference dataset would be clearer than translating thousands of inserts to PHP. Keep the SQL under `database/seeders/sql/` and never run user-supplied SQL.

## Create

```bash
php artisan make:seeder UserSeeder
```

## Run

```bash
php artisan db:seed                       # all
php artisan db:seed --class=UserSeeder    # one
```
