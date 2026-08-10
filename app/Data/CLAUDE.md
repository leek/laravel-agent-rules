# Data Transfer Objects (DTOs)

**Purpose:** carry typed, structured data across a boundary (Request → Action, Action → Job) so downstream code never touches `$request`, loose arrays, or untyped payloads.

## Naming

- **MUST** be `{Verb}{Model}Data` for the input to an operation (e.g. `CreateOrderData`, `UpdateUserData`) or `{Model}Data` for a plain projection (e.g. `UserData`). **No `Dto` suffix.**

## Rules

- **MUST** be immutable — `readonly` promoted constructor properties, every property typed.
- **MUST** contain only scalars, enums, other DTOs, or collections of those. **Never an Eloquent model instance** — a DTO that wraps a model is just the model, so pass the model directly.
- **MUST** build the DTO at the boundary: a Form Request's `toDto()` (see `app/Http/Requests/CLAUDE.md`) or a named static constructor (`fromArray()`, `fromModel()`). Actions and Jobs receive a finished DTO; they don't assemble one from request input.
- **MUST NOT** put behavior with side effects here — no DB queries, no I/O, no dispatching. Read-only accessors computed purely from the DTO's own properties are fine.
- **PREFER** a plain `readonly` PHP class. Reach for `spatie/laravel-data` only when you actually need its extras — validation, wrapping, casts, lazy properties, or TypeScript transformers. Don't pull a package in for a three-field struct.

## Plain DTO

```php
final readonly class CreateOrderData
{
    public function __construct(
        public int $customerId,
        public array $items,
        public ?string $note = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            customerId: (int) $data['customer_id'],
            items: $data['items'],
            note: $data['note'] ?? null,
        );
    }
}
```

Consumed by an Action without any knowledge of HTTP:

```php
public function handle(CreateOrderData $data): Order { /* ... */ }
```

## `spatie/laravel-data` variant

Use when the DTO doubles as a validated request payload or an API response contract:

```php
final class CreateOrderData extends Data
{
    public function __construct(
        public int $customerId,
        /** @var OrderItemData[] */
        public array $items,
        public ?string $note = null,
    ) {}
}
```

> Value objects produced by custom casts (`app/Casts/CLAUDE.md`) also live here or in `app/Support/` — a DTO crosses a boundary, a value object models a single typed value.
