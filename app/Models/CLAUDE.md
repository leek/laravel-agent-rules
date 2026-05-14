# Models

**Purpose:** represent a database table; expose Eloquent-native concerns only.

## Naming

- **MUST** be singular noun, **no `Model` suffix** (`User`, not `UserModel`).

## Rules

- **MUST NOT** contain business logic — push to `app/Actions/` or `app/Support/`.

## Create

```bash
php artisan make:model Product
```
