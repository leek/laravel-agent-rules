# Contracts (Interfaces)

**Purpose:** define a role/capability that multiple implementations satisfy — the seam for swapping implementations and for faking dependencies in tests.

## Naming

- **MUST** be a role or adjective noun, **no suffix** (`PaymentGateway`, `Loggable`, `OutboundEventHandler`).
- **MUST NOT** prefix `I` or suffix `Interface`.

## Rules

- **SHOULD** introduce an interface only when there is — or credibly will be — more than one implementation, or to break a hard dependency for testing. **AVOID** speculative single-implementation interfaces.
- **MUST** keep interfaces small and segregated (ISP) — several focused contracts beat one fat one.
- **MUST** type-hint the interface, never the concrete, at call sites and in constructors.
- **MUST** bind the interface to its implementation in a Service Provider's `register()` — see `app/Providers/CLAUDE.md`. Use `bind` for per-resolution, `singleton` for a shared instance.
- **SHOULD** declare return types and `@throws` on every method so all implementations share one contract.

## Depend on the contract, not the concrete

❌ Locked to one vendor; impossible to fake in a test:

```php
public function __construct(private StripeClient $stripe) {}
```

✅ Depend on the contract; bind the implementation once:

```php
interface PaymentGateway
{
    public function charge(Money $amount, string $token): Charge;
}

public function __construct(private PaymentGateway $gateway) {}

// AppServiceProvider::register()
$this->app->bind(PaymentGateway::class, StripeGateway::class);
```

## Create

```bash
php artisan make:interface Contracts/PaymentGateway
```

Or add a plain `interface` under `app/Contracts/`.
