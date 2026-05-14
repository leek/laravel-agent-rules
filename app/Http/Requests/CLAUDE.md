# Form Requests

**Purpose:** authorize + validate incoming input before it reaches the controller.

## Naming

- **MUST** be `{Method}{SingularModel}Request` (e.g. `StoreUserRequest`, `UpdateProductRequest`, `DestroyCategoryRequest`).

## Rules

- **MUST** return a real authorization decision from `authorize()` — do not leave `return true` if the route can be hit by users who shouldn't be allowed.
- **SHOULD** delegate authorization to a Policy (`$this->user()?->can('create', Order::class) ?? false`) rather than inlining logic.
- **SHOULD** keep validation rules colocated in `rules()`; do not validate ad-hoc in the controller.
- **SHOULD** expose a `toDto()` method that returns a typed DTO when the action downstream expects structured input — keeps the action free of `$request->input(...)` calls.

## Create

```bash
php artisan make:request StoreUserRequest
```

## `prepareForValidation()` — normalize input

Use to normalize or derive fields **before** rules run. Common cases: trim whitespace, lowercase emails, derive `slug` from `title`, coerce string booleans:

```php
protected function prepareForValidation(): void
{
    $this->merge([
        'slug' => Str::slug($this->input('title', '')),
    ]);
}
```

## Update requests — `Rule::unique()->ignore()`

When validating uniqueness on update, **MUST** exempt the current row or every update fails:

```php
public function rules(): array
{
    return [
        'slug' => ['required', 'string', Rule::unique('posts', 'slug')->ignore($this->route('post'))],
    ];
}
```

## Example with `toDto()`

```php
final class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Order::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'customer_id'      => ['required', 'integer', 'exists:customers,id'],
            'items'            => ['required', 'array', 'min:1'],
            'items.*.sku'      => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function toDto(): CreateOrderData
    {
        return new CreateOrderData(
            customerId: (int) $this->validated('customer_id'),
            items: $this->validated('items'),
        );
    }
}
```
