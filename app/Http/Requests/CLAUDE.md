# Form Requests

**Purpose:** authorize + validate incoming input before it reaches the controller.

## Naming

- **MUST** be `{Method}{SingularModel}Request` (e.g. `StoreUserRequest`, `UpdateProductRequest`, `DestroyCategoryRequest`).

## Rules

- **MUST** return a real authorization decision from `authorize()` — do not leave `return true` if the route can be hit by users who shouldn't be allowed.
- **SHOULD** keep validation rules colocated in `rules()`; do not validate ad-hoc in the controller.

## Create

```bash
php artisan make:request StoreUserRequest
```
