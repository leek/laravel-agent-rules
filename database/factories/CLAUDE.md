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

## Create

```bash
php artisan make:factory UserFactory
```

## Example

```php
class ProductFactory extends Factory
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

## Common operations

```php
User::factory()->create();                     // single, persisted
User::factory()->make();                       // single, in-memory only
User::factory()->count(10)->create();          // many, persisted
User::factory()->count(10)->make();            // many, in-memory only
User::factory()->create(['email' => 'a@b.c']); // override attributes
```

## Sequences

Use a `sequence()` when each row needs different attributes:

```php
Order::factory()
    ->count(10)
    ->sequence(
        ['state' => 'new'],
        ['state' => 'pending'],
    )
    ->create();
```

Callback form for derived values:

```php
Category::factory()
    ->count(10)
    ->sequence(fn (Sequence $sequence) => ['order_column' => $sequence->index])
    ->create();
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

## Relationships

```php
User::factory()->has(Post::factory()->count(3))->create();  // explicit
User::factory()->hasPosts(3)->create();                     // magic shortcut

Post::factory()->count(3)->for(User::factory(['name' => 'A']))->create();
Post::factory()->count(3)->forUser(['name' => 'A'])->create();

User::factory()
    ->hasAttached(Role::factory()->count(3), ['active' => true])
    ->create();

User::factory()->hasRoles(1, ['name' => 'Editor'])->create();
```

## Custom state methods

**SHOULD** add a state method to the factory when the same combination of overrides is reused in multiple tests:

```php
public function github(array $attributes = []): self
{
    return $this->state([
        'platform'     => 'github',
        'email'        => $attributes['email'] ?? fake()->email(),
        'access_token' => $attributes['token'] ?? Str::random(),
    ]);
}
```

Usage:

```php
Client::factory()->github()->create();
```

## Callbacks

Use `configure()` for after-create / after-make side effects:

```php
public function configure(): static
{
    return $this
        ->afterMaking(function (User $user) { /* ... */ })
        ->afterCreating(function (User $user) { /* ... */ });
}
```
