# Validation Rules

**Purpose:** custom, reusable validation logic that doesn't fit a built-in rule.

## Naming

- **MUST** name after the rule's meaning, **no `Rule` suffix** (e.g. `ValidPhoneNumber`, `ValidBankAccount`, `Uppercase`).

## Rules

- **MUST** implement `Illuminate\Contracts\Validation\ValidationRule` (Laravel 10+); legacy `Rule` interface is deprecated.
- **MUST** report failures via the `$fail` closure — do NOT throw or `return false`.
- **SHOULD** pull external state (services, repos) via constructor injection; resolve via the container at call site if needed.

## Create

```bash
php artisan make:rule ValidPhoneNumber
```

## Shape

```php
use Illuminate\Contracts\Validation\ValidationRule;

final class ValidPhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! preg_match('/^\+?[1-9]\d{1,14}$/', (string) $value)) {
            $fail('The :attribute must be a valid E.164 phone number.');
        }
    }
}
```

## Use

```php
public function rules(): array
{
    return [
        'phone' => ['required', new ValidPhoneNumber()],
    ];
}
```

## When NOT to write a custom Rule

- **PREFER** an inline closure rule for one-shot logic used in one place:

```php
'slug' => [
    'required',
    function (string $attribute, mixed $value, Closure $fail) {
        if (Post::where('slug', $value)->exists()) {
            $fail('Slug already taken.');
        }
    },
],
```

- **PREFER** a FormRequest `after(): array` closure for cross-field business rules — see `app/Http/Requests/CLAUDE.md`.
- **PREFER** a built-in rule when one fits (`Rule::unique`, `Rule::enum`, `Rule::in`, `Rule::when`, etc.).
