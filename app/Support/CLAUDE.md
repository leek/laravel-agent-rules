# Support classes

**Purpose:** reusable domain helpers that are not specific to one model or controller. Lightweight alternative to a full Service layer.

## Naming

- **MUST** be named after the domain noun, **no `Support` suffix** (`Cart`, `OpeningHours`, `Table`).

## Rules

- **SHOULD** be stateless or own-only-its-own-state; **AVOID** hidden global state.
- **PREFER** a Support class over scattering the same helpers across multiple Actions.

## Create

```bash
php artisan make:class Support/Cart   # starter-kit
```

Or plain class under `app/Support/`.
