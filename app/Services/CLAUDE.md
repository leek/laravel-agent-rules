# Services

**Purpose:** wrap an external system (SDK, HTTP API, 3rd-party client) or a cohesive capability spanning several related methods, with I/O or injected dependencies. Not the default home for business logic — that's an Action.

## Naming

- **MUST** be `{Noun}Service` (e.g. `StripeService`, `GeocodingService`, `ReportingService`). The suffix keeps Services visually distinct from suffix-less `Support` classes.

## Where a Service fits

| Need                                                                     | Use                              |
| ------------------------------------------------------------------------ | -------------------------------- |
| One synchronous business operation, caller needs the result              | Action (`app/Actions/`)          |
| Wrap an external system / SDK / 3rd-party client                         | **Service** (`app/Services/`)    |
| Cohesive capability across several related methods, with I/O or deps     | **Service** (`app/Services/`)    |
| Stateless, pure helper — no I/O, no side effects                         | Support (`app/Support/`)         |

## Rules

- **MUST** justify the layer: a single operation belongs in an Action; a stateless pure helper belongs in `app/Support/`. Reach for a Service only when there's an external dependency or a genuine multi-method surface.
- **SHOULD** inject clients and config through the constructor; **MUST NOT** call `env()` (read from `config()` only — see `config/CLAUDE.md`).
- **SHOULD** be effectively stateless beyond injected dependencies — **MUST NOT** carry per-request or per-user state across calls.
- **SHOULD** depend on (and be bound via) an interface when callers shouldn't know the concrete implementation — see `app/Contracts/CLAUDE.md` and `app/Providers/CLAUDE.md`.
- **SHOULD** translate vendor/SDK exceptions into a domain exception (see `app/Exceptions/CLAUDE.md`) so callers don't couple to the vendor.

## Action vs Service

❌ One business operation with no external system — it's an Action, not a Service:

```php
class CreateUserService
{
    public function create(array $data): User
    {
        return User::create($data);   // single op → app/Actions/CreateUserAction
    }
}
```

✅ Wraps an external system, cohesive multi-method surface:

```php
final class GeocodingService
{
    public function __construct(private readonly GeocodeClient $client) {}

    public function forAddress(string $address): Coordinates { /* ... */ }

    public function reverse(float $lat, float $lng): Address { /* ... */ }
}
```

## Create

```bash
php artisan make:class Services/GeocodingService
```

Or add a plain class under `app/Services/`.
