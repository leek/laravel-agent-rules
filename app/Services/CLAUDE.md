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

## External side effects

These apply to every remote write, wherever it is called from.

- **An uncertain result is not an absence.** A timeout or lost response means the remote write may have happened. **MUST NOT** create a replacement, release the hold, or forget the idempotency key until the outcome is resolved; retry with the same key. A lookup that returns nothing proves absence only when it could have found the earlier attempt.
- **MUST** clean up a remote or stored resource you created on any `Throwable`, not only on a validation failure or a null return. Everything after the create is inside the `try`.

```php
$event = $this->client->createEvent($payload);

try {
    $link = $this->client->waitForMeetingLink($event->id);   // can time out
} catch (Throwable $e) {
    $this->client->deleteEvent($event->id);

    throw $e;
}
```

- **MUST** check the result before the step that depends on it. A refused remote delete must stop the replacement; an `update()` that enforces an invariant must have its affected-row count checked. A call that did not throw has not necessarily succeeded.
- **MUST** lock and re-read local state after the call before writing what it returned — see **Lock, re-read, then decide** in `app/CLAUDE.md`.
- **MUST** send the complete object to an endpoint that replaces what it receives. Where the remote clears every field the payload omits, a minimal payload built for one change blanks the rest (phones, email, language). Read the provider's update semantics before reusing a partial payload builder.

## Inbound imports and syncs

These apply to every feed, file import and provider sync, wherever the code lives.

- **A blank incoming value is not an instruction to clear.** **MUST** keep the held value when the feed sends an empty string or null, unless the feed's contract says blank means removed. Erasing a field no audit log records is unrecoverable.
- **A fallback match must be unique.** When the primary key (NPI, external id) finds nothing and the import falls back to a weaker one (name, email), **MUST** link only when exactly one row fits; two or more candidates is "no match", recorded for review.
- **MUST** decide per field who owns a row a user has edited: the feed or the user. Record that provenance on the row, apply it in one place, and report a skipped refresh as a discrepancy the user can see.
- **MUST** hold a value the importer recognises but cannot validate (an out-of-range score, a formula with no cached result) for review. It is neither saved as clean nor dropped.
- A lookup made under `withoutGlobalScopes()` also finds soft-deleted and merged-away rows — see **Soft deletes** in `app/Models/CLAUDE.md`.

## Action vs Service

❌ One business operation with no external system — it's an Action, not a Service:

```php
final class CreateUserService
{
    public function create(array $data): User
    {
        return User::create($data);   // single op → app/Actions/CreateUserAction
    }
}
```

✅ Wraps an external system, cohesive multi-method surface (often bound to a contract in a provider):

```php
final class StripeService implements PaymentGateway
{
    public function __construct(private readonly StripeClient $client) {}

    public function charge(MoneyValue $amount, string $token): Charge { /* ... */ }
}

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
