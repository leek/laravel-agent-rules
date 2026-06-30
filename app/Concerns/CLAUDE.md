# Concerns (Traits)

**Purpose:** share a single capability across multiple unrelated classes (models, jobs, commands) via a trait.

## Naming

- **MUST** be `Has{Feature}`, `With{Feature}`, or an adjective — **no `Trait` suffix** (`HasSlug`, `WithForm`, `Sortable`).

## Rules

- **SHOULD** exist only when behaviour is reused across 3+ classes. Two uses can stay inline until the abstraction proves stable; for a single host, inline the code instead.
- **MUST** keep one capability per trait — **AVOID** grab-bag traits.
- **SHOULD** avoid trait state; when the host must supply data, declare it as an `abstract` method so a missing contract fails loudly at compile time, not silently at runtime.
- **SHOULD** use Eloquent's `boot{TraitName}()` / `initialize{TraitName}()` hooks rather than overriding the host's `boot()` — the framework calls them automatically.
- **AVOID** method/property name collisions across composed traits — PHP fatals unless resolved with `insteadof` / `as`.
- **PREFER** composition (traits) over inheritance for cross-cutting behaviour.

## Declare the host contract

❌ Silently assumes the host has a `title` — breaks on any model without one:

```php
trait HasSlug
{
    public function generateSlug(): string
    {
        return Str::slug($this->title);
    }
}
```

✅ Make the requirement explicit and boot via the trait hook:

```php
trait HasSlug
{
    abstract protected function slugSource(): string;

    protected static function bootHasSlug(): void
    {
        static::saving(fn (Model $model) => $model->slug ??= Str::slug($model->slugSource()));
    }
}
```

## Create

```bash
php artisan make:trait Concerns/HasSlug
```

Or add a plain `trait` under `app/Concerns/`.
