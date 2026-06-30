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

## Conditional and dynamic rules

- **`Rule::when($condition, ['required', 'string'])`** — apply a rule chain only when a condition holds.
- **`'sometimes'`** — apply remaining rules only if the field is present.
- **`'bail'`** as the first rule — stop on first failure (avoid expensive checks running after a cheap one already failed).
- **`'required_if:type,company'`** / **`'exclude_if:type,individual'`** / **`'prohibited_if'`** — keep conditional dependencies in the rule definition, not in PHP `if`s.
- **MUST** scope `exists` / `unique` rules to the authenticated tenant, owner, or parent record when the value must belong to that boundary. Global scopes and policies do not automatically protect validation lookups.

```php
'tax_id' => ['required_if:type,company', 'nullable', 'string'],
'email'  => ['bail', 'required', 'email:rfc,dns'],
'vessel_id' => [
    'required',
    Rule::exists('vessels', 'id')->where('company_id', $this->user()->company_id),
],
```

## Enums — `Rule::enum`

```php
'status' => ['required', Rule::enum(Status::class)->only([Status::Active, Status::Pending])],
```

## Cross-field validation — `after(): array`

For business rules that need every standard rule to have passed first (and access to the validator), return closures from `after()`:

```php
public function after(): array
{
    return [
        function (Validator $validator): void {
            if ($this->date('start_at')->gte($this->date('end_at'))) {
                $validator->errors()->add('end_at', 'End must be after start.');
            }
        },
    ];
}
```

## Custom messages with array `:position`

```php
public function messages(): array
{
    return [
        'items.*.product_id.exists' => 'Product #:position does not exist.',
        'items.*.quantity.min'      => 'Item #:position must have at least :min unit(s).',
    ];
}
```

## Safe input — `$request->safe()`

After validation, **PREFER** `$request->safe()->only([...])` / `->except([...])` / `->merge([...])` over `$request->validated()` when filtering or adding trusted server-side fields:

```php
$attributes = $request->safe()->except(['confirm_password']);
$attributes = $request->safe()->merge(['created_by_id' => $request->user()->id]);
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

When uniqueness must hold across more than one table, **SHOULD** stack multiple `Rule::unique()` rules and provide one domain message:

```php
'email' => [
    'required',
    'email:rfc,dns',
    Rule::unique(User::class, 'email')->ignore($this->user()),
    Rule::unique(Invitation::class, 'email'),
],
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
