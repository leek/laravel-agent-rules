# Database

Schema design + naming conventions. Sub-directory rules:

- `database/migrations/CLAUDE.md` — migration workflow.
- `database/factories/CLAUDE.md` — factory rules.
- `database/seeders/CLAUDE.md` — seeder rules.

## Naming

| Entity       | Pattern                                              | Examples                               |
| ------------ | ---------------------------------------------------- | -------------------------------------- |
| Table        | `snake_case`, plural                                 | `users`, `products`, `categories`      |
| Pivot table  | `snake_case`, singular names, alphabetical           | `category_product`, `order_user`       |
| Column       | `snake_case`, no table prefix                        | `title`, `price`, `description`        |
| Primary key  | always `id`                                          | `id`                                   |
| Foreign key  | `{singular_model}_id`                                | `user_id`, `product_id`, `category_id` |

**MUST** avoid SQL [reserved words](https://dev.mysql.com/doc/refman/8.0/en/keywords.html) (e.g. `order`, `group`, `desc`, `key`, `range`) as identifiers. Prefer a more specific name (`order_column`, `group_name`).

## Primary keys

- **MUST** name the PK `id`.
- **SHOULD** use auto-incrementing big integer for internal-only tables.
- **SHOULD** use UUID (or ULID) PK when the row will be exposed in a public URL or contains sensitive data (orders, invoices, bank accounts).

## Foreign keys

- **MUST** name `{singular_model}_id`.
- **SHOULD** declare with `foreignId(...)->constrained()` and an explicit `onDelete()` policy.

## Column type → name patterns

- **boolean** → prefix `is_` / `has_` (`is_enabled`, `has_reviews`).
- **timestamp** → suffix `_at` / `_from` / `_for` (`published_at`, `valid_from`, `scheduled_for`).
- **PREFER** a nullable timestamp over a boolean when the moment of state-change is also useful: replace `is_finished` with `finished_at` — encodes two facts (whether + when) in one column.

## Column sorting

Order columns top-to-bottom as:

1. `id`
2. Foreign keys
3. Domain columns, ordered by priority and **grouped by context**
4. Native timestamps (`created_at`, `updated_at`, `deleted_at`)

> When adding columns in later migrations, **MUST** use `after()` / `before()` to preserve this ordering.

## Frequently-used column names

To stay consistent across projects, **SHOULD** use:

- `order_column` — sort position
- `company_number` — Company Identification Number (CIN)
- `vat_number` — VAT ID
- `zip_code` — postal code

## Indexes

- **MUST** add an index to any column used in `WHERE`, `ORDER BY`, or `JOIN`.
- **MUST** add a composite index when multiple columns are queried together.
- **SHOULD** index every foreign key column (Laravel's `foreignId()` adds one automatically).
- **AVOID** redundant single-column indexes that are already covered by a composite index's leading column.

## UUIDs

- **SHOULD** use UUID/ULID for publicly addressable rows: external order numbers, public profile pages, signed invoices.
- **SHOULD** keep `id` as an internal bigint and add a separate `uuid` column for external use, unless the project explicitly switches to UUID PKs.
