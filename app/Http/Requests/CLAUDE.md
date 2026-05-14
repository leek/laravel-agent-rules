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
