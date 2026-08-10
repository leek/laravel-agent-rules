# Factories

**Purpose:** generate fake / parameterised model instances for tests and seeders.

## Naming

- **MUST** be `{SingularModel}Factory` (e.g. `UserFactory`, `ProductFactory`).

## Rules

- **MUST** define every required column in `definition()`.
- **SHOULD** use related factories for foreign keys (`'category_id' => Category::factory()`).
- **PREFER** `#[UseFactory]` on the model over the legacy `HasFactory::newFactory()` override / naming convention.

```php
use Illuminate\Database\Eloquent\Attributes\UseFactory;

#[UseFactory(ProductFactory::class)]
final class Product extends Model {}
```

## Domain sub-namespacing

Factory resolution strips the model namespace and re-prefixes `Database\Factories\`, so a domain-namespaced model resolves to a matching subfolder:

| Model                        | Resolved factory                              |
| ---------------------------- | --------------------------------------------- |
| `App\Models\Billing\Invoice` | `Database\Factories\Billing\InvoiceFactory`   |

- **MUST** mirror the model's domain subfolder under `database/factories/` (or pin explicitly with `#[UseFactory(...)]`). A factory left at the flat path won't be found by `Invoice::factory()`. See domain sub-namespacing in `app/CLAUDE.md`.

## Create

```bash
php artisan make:factory UserFactory
```

## Example

```php
/** @extends Factory<Product> */
final class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'        => fake()->name(),
            'category_id' => Category::factory(),
        ];
    }
}
```

## Recycle (shared relationship)

**MUST** use `recycle()` when multiple nested factories share the same parent — otherwise each call creates a new parent row.

```php
$tenant = Tenant::factory()->create();

// AVOID — creates extra tenant rows via nested factories
Product::factory()->create(['tenant_id' => $tenant]);
ProductCategory::factory()->create(['tenant_id' => $tenant]);

// PREFER — single tenant is reused everywhere
Product::factory()
    ->recycle($tenant)
    ->has(ProductCategory::factory())
    ->create();
```

## Custom state methods

**SHOULD** add a state method when the same combination of overrides is reused in multiple tests, or when the name reads in business language (`published()`, `expired()`, `trialing()`):

```php
public function published(): self
{
    return $this->state([
        'status' => JobStatus::Published,
        'published_at' => now(),
        'deadline' => now()->addMonth(),
    ]);
}
```

```php
JobListing::factory()->published()->create();
```

## Sequences (when useful)

Use `sequence()` when each row needs different attributes or a derived index value — see [Laravel factory docs](https://laravel.com/docs/eloquent-factories#sequences). Prefer a named state method when the combination is reused.

## Callbacks

Use `configure()` for after-create / after-make side effects only when the factory itself owns that setup (not business logic that belongs in an Action):

```php
public function configure(): static
{
    return $this
        ->afterMaking(function (User $user) { /* ... */ })
        ->afterCreating(function (User $user) { /* ... */ });
}
```
