# Form Requests

**Purpose:** authorize + validate incoming input before it reaches the controller.

## Naming

- **MUST** be `{Method}{SingularModel}Request` (e.g. `StoreUserRequest`, `UpdateProductRequest`, `DestroyCategoryRequest`).

## Rules

- **MUST** return a real authorization decision from `authorize()` — do not leave `return true` if the route can be hit by users who shouldn't be allowed.
- **SHOULD** delegate authorization to a Policy (`$this->user()?->can('create', Order::class) ?? false`) rather than inlining logic.
- **SHOULD** keep validation rules colocated in `rules()`; do not validate ad-hoc in the controller.
- **SHOULD** expose a `toDto()` method that returns a typed DTO when the action downstream expects structured input — keeps the action free of `$request->input(...)` calls.
- **MUST** put user-facing validation messages through `__()` / lang files (or Laravel's default translated messages) — see `app/CLAUDE.md`.

## File uploads

- **MUST** validate uploads in the Form Request (`file`, `image`, `mimes:`/`mimetypes:`, `max:`) before storing — never trust client-supplied paths or extensions alone.
- **MUST** store via `$file->store(...)` / `storeAs(...)` on a **named disk** (`config/filesystems.php`); **AVOID** writing under `public/` by hand. Use a private disk for sensitive files and authorize downloads.
- **MUST NOT** put binary blobs in the database; persist the path/key (and disk name if multi-disk) on the model.

```php
'avatar' => ['required', 'image', 'mimes:jpg,png,webp', 'max:2048'],

// after validation:
$path = $request->file('avatar')->store('avatars', 's3');
```

## Create

```bash
php artisan make:request StoreUserRequest
```

## `prepareForValidation()` — normalize input

Use to normalize or derive fields **before** rules run (trim, lowercase email, derive `slug` from `title`, coerce string booleans):

```php
protected function prepareForValidation(): void
{
    $this->merge([
        'slug' => Str::slug($this->input('title', '')),
    ]);
}
```

## Tenant-scoped and ownership-aware rules

- **MUST** scope `exists` / `unique` rules to the authenticated tenant, owner, or parent record when the value must belong to that boundary. Global scopes and policies do not automatically protect validation lookups.

```php
'vessel_id' => [
    'required',
    Rule::exists('vessels', 'id')->where('company_id', $this->user()->company_id),
],
```

- Prefer built-in conditional rules (`sometimes`, `required_if`, `exclude_if`, `Rule::when`, `Rule::enum`) over PHP `if` trees in `rules()`. See [Laravel validation docs](https://laravel.com/docs/validation) for the full rule list.

## Cross-field validation — `after(): array`

For business rules that need every standard rule to have passed first (and access to the validator), return closures from `after()`:

```php
public function after(): array
{
    return [
        function (Validator $validator): void {
            if ($this->date('start_at')->gte($this->date('end_at'))) {
                $validator->errors()->add('end_at', __('validation.end_after_start'));
            }
        },
    ];
}
```

## Safe input — `$request->safe()`

After validation, **MUST** mass-assign only a trusted allowlist — prefer `$request->safe()->only([...])` / `->except([...])` / `->merge([...])` over bare `$request->validated()` when filtering, stripping confirmation fields, or adding trusted server-side fields (see Controllers). Bare `validated()` is fine only when every validated key is already safe to write.

```php
$attributes = $request->safe()->except(['confirm_password']);
$attributes = $request->safe()->merge(['created_by_id' => $request->user()->id]);
```

## Update requests — `Rule::unique()->ignore()`

When validating uniqueness on update, **MUST** exempt the current row or every update fails:

```php
'slug' => ['required', 'string', Rule::unique('posts', 'slug')->ignore($this->route('post'))],
```

When uniqueness must hold across more than one table, **SHOULD** stack multiple `Rule::unique()` rules and provide one domain message via `messages()` / `__()`:

```php
'email' => [
    'required',
    'email:rfc,dns',
    Rule::unique(User::class, 'email')->ignore($this->user()),
    Rule::unique(Invitation::class, 'email'),
],
```

## Example with `toDto()`

Foreign keys the client may supply **MUST** be scoped to the authenticated boundary (see tenant-scoped rules above) — never a bare global `exists` alone.

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
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')
                    ->where('company_id', $this->user()->company_id),
            ],
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
