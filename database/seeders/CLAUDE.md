# Seeders

**Purpose:** populate a database with initial data (reference data, demo data, fixtures for local dev).

## Naming

- **MUST** be `{SingularModel}Seeder` (e.g. `UserSeeder`).

## Rules

- **SHOULD** call factories rather than insert raw rows.
- **SHOULD** use the `WithoutModelEvents` trait when seeders should not trigger observers (e.g. avoid sending welcome emails during a `db:seed`).
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
