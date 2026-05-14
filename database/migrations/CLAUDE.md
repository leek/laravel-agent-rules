# Migrations

Migrations are the only sanctioned way to change schema. Treat them as version control for the database.

> Schema-design rules (naming, column types, indexes, PKs/FKs) live in `database/CLAUDE.md`.

## Hard rules

- **MUST** create a new migration for every schema change.
- **MUST NOT** modify the database manually in any shared environment (staging, production). Local-only DBs may be reset freely.
- **MUST NOT** edit a migration that has already been run in any shared environment — write a new migration instead.

## Pre-production vs. production

- **Pre-production / local-only:** editing an unshipped migration and running `php artisan migrate:fresh` is acceptable.
- **Production:** once a migration has been deployed anywhere, it is frozen. Every change ships as a new file.

## When writing a migration

- **MUST** declare migrations as **anonymous classes** (`return new class extends Migration { ... };`). Filename communicates intent; no named class needed.
- **MUST** preserve column sorting. When adding a new column, use `after('existing_column')` or `before('existing_column')`.
- **SHOULD** add `comment('...')` to any column whose name is not self-explanatory (legal/finance/regulatory terms, codes, project-specific jargon).
- **SHOULD** declare an explicit `onDelete` policy on every foreign key (`cascade`, `restrict`, `set null`).
- **MUST** make both `up()` and `down()` work, except where rollback is genuinely impossible (data migrations) — in that case, `down()` throws explicitly with a comment.

## Squashing

When `database/migrations/` grows beyond ~hundreds of files, **SHOULD** squash. See [Laravel docs: squashing migrations](https://laravel.com/docs/migrations#squashing-migrations).

## Create

```bash
php artisan make:migration create_products_table
php artisan make:migration add_published_at_to_products_table
```

## Example — add column

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->timestamp('published_at')
                ->nullable()
                ->after('status')
                ->comment('Time the product became publicly visible.');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('published_at');
        });
    }
};
```

## Example — create table

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->index();
            $table->unsignedInteger('total_cents');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
```
