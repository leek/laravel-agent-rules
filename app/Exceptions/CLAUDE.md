# Exceptions

**Purpose:** domain-specific exception classes; exception handling configuration.

## Naming

- **MUST** be `{Cause}Exception` — e.g. `DuplicateEntryException`, `PaymentGatewayException`.

## Rules

- **MUST** throw a domain exception (not a bare `\Exception` or `\RuntimeException`) when callers need to catch it specifically or when the failure carries domain meaning.
- **PREFER** named static constructors that capture context over raw `new` + string message:

```php
final class CouldNotChargeCard extends Exception
{
    public static function gatewayTimedOut(Order $order): self
    {
        return new self("Gateway timed out charging order {$order->id}.");
    }
}
```

- **AVOID** catching `\Throwable`/`\Exception` broadly and continuing silently. Catch the narrowest type you can handle; otherwise let it propagate — the handler reports it.
- `abort(404)` / `abort(403)` are fine for HTTP-boundary failures in controllers; domain code throws domain exceptions and lets a `render()` hook translate them.

## Handler configuration

There is no `app/Exceptions/Handler.php` in the modern skeleton. Reporting and rendering are configured in `bootstrap/app.php` via `withExceptions()`:

```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->report(function (PaymentGatewayException $e) {
        // custom reporting — log technical detail here
    });

    $exceptions->render(function (CouldNotChargeCard $e, Request $request) {
        // Client payload: stable code + translated message — not $e->getMessage() or internal ids
        return response()->json([
            'message' => __('errors.payment_failed'),
            'code'    => 'payment_failed',
        ], 422);
    });

    $exceptions->shouldRenderJsonWhen(
        fn (Request $request, Throwable $e): bool => $request->is('api/*') || $request->expectsJson(),
    );

    $exceptions->dontReport(StaleStateException::class);
})
```

- **MUST NOT** expose `$e->getMessage()`, stack traces, or internal identifiers in client-facing responses. Log the technical detail; return a stable error code and a translated user message.

- **MUST** force JSON rendering for API routes with `shouldRenderJsonWhen(...)` so clients still receive JSON when they forget the `Accept: application/json` header.
- **PREFER** `report()` / `render()` methods on the exception class itself when the behaviour belongs to that exception — keeps `bootstrap/app.php` small.
- **PREFER** marking never-reported exceptions with the `ShouldntReport` interface on the class over a `dontReport()` list in bootstrap.

## Create

```bash
php artisan make:exception CouldNotChargeCard
```
