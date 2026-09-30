# Testing (Pest)

Default test framework: [Pest](https://pestphp.com/). **Pest is required** for new tests in projects using this ruleset; PHPUnit-style assertions still work under the hood.

## Database

- **SHOULD** run the suite against an in-memory SQLite database (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` in `phpunit.xml` / `.env.testing`) for fast, isolated tests. Prefer a real MySQL/Postgres test DB when the feature under test depends on engine-specific behaviour (full-text, certain JSON ops, advisory locks).
- **MUST** use `LazilyRefreshDatabase`, `RefreshDatabase`, or `DatabaseTransactions` so each database-using test starts from a clean schema. **PREFER** `LazilyRefreshDatabase` in `tests/Pest.php` for Pest suites because tests that never touch the database avoid needless migration work.

## When to write tests

- **MUST** write a test for every class that contains logic (Actions, Support, Jobs, Commands, Livewire components, Controllers, Policies).
- **MUST** write a test for every route exposed to users (auth/redirect/permission boundaries).
- **SHOULD** use coverage reports to find untested behaviour, not as a numeric gate agents grind toward.

## What to test

- Code logic (Actions, Support classes, Jobs, Commands)
- HTTP layer (Controllers, Livewire components, route auth boundaries)
- Routing (named routes, redirects, middleware behaviour)
- Edge cases — happy path is necessary but **not** sufficient.

## Test types

- **Feature tests** are the default. They exercise the full stack and catch the largest class of regressions for the smallest amount of code. Place under `tests/Feature/` — see `tests/Feature/CLAUDE.md`.
- **Unit tests** are used only for genuinely isolated logic (pure functions, complex calculations). Place under `tests/Unit/` — see `tests/Unit/CLAUDE.md`. **AVOID** unit tests that mock the framework just to bypass it.
- **SHOULD** mirror the source path under `app/` in the test path, preserving class type, audience/version, and domain — `App\Http\Controllers\Billing\InvoiceController` maps to `tests/Feature/Http/Controllers/Billing/InvoiceControllerTest.php`; its `Api\V1\Billing` variant maps to `tests/Feature/Http/Controllers/Api/V1/Billing/InvoiceControllerTest.php`. See `app/CLAUDE.md`.
- **Architecture tests** enforce structural rules with Pest's `arch()` — naming, layering, no debug leftovers. Place under `tests/Architecture/` — full matrix and rules in `tests/Architecture/CLAUDE.md`.

## How to write tests

- **MUST** use lowercase, descriptive names that read like sentences.
- **MUST** only assert things relevant to the class under test — keep assertions focused.
- **SHOULD** cover at least: happy path, validation failure, authorization failure, and one edge case per branch.

```php
// PREFER — lowercase, descriptive
it('has a welcome page', function () {
    $response = $this->get('/');

    expect($response->status())->toBe(200);
});

// AVOID — uppercase, vague
test('PAYMENT', fn () => /* ... */);
```

## Create

```bash
php artisan make:test Actions/VerifyUserActionTest
```

## Pest hooks

- `beforeEach()` / `afterEach()` — per test
- `beforeAll()` / `afterAll()` — once per file

## Helpers and custom methods

- **SHOULD** extract repeated setup into a helper function in the test file.
- **SHOULD** promote a helper to `tests/Pest.php` when it is useful across multiple files.

```php
function asAdmin(): Tests\TestCase
{
    $user = User::factory()->create(['admin' => true]);
    return test()->actingAs($user);
}

it('can manage users', function () {
    asAdmin()->get('/users')->assertOk();
});
```

## Datasets — parameterized tests

For boundary/validation tests that vary only by inputs, **SHOULD** use a Pest dataset rather than copy-pasting near-identical tests:

```php
it('rejects invalid emails', function (string $email) {
    $validator = Validator::make(['email' => $email], ['email' => ['required', 'email:rfc']]);

    expect($validator->fails())->toBeTrue();
})->with([
    'empty'       => '',
    'no at sign'  => 'not-an-email',
    'spaces'      => 'a b@c.com',
]);
```

Import `Illuminate\Support\Facades\Validator`. This checks validation rules in a Feature test with a booted app; use HTTP assertions against the actual Form Request endpoint when testing the request boundary. Factories persist attributes and do not perform request validation.

## Useful assertions

- **`assertSoftDeleted('posts', ['id' => $post->id])`** — soft delete happened.
- **`assertModelExists` / `assertModelMissing`** — model-aware existence; clearer than bare `assertDatabaseHas`.
- **`assertJsonStructure([...])`** — pin response shape (including any project pagination envelope).

## Auth — Sanctum + abilities

When testing token-scoped endpoints, **MUST** test both granted and missing abilities:

```php
use Laravel\Sanctum\Sanctum;

Sanctum::actingAs($user, ['posts:write']);
$this->postJson('/api/posts', $payload)->assertCreated();

Sanctum::actingAs($user, []); // no abilities
$this->postJson('/api/posts', $payload)->assertForbidden();
```

## Selective fakes

Prefer allowlisted fakes over blanket fakes so unrelated listeners stay live:

```php
Event::fake([OrderShipped::class]);
Notification::fake();
Storage::fake('s3');

Notification::assertSentTo($user, OrderShipped::class);
Storage::disk('s3')->assertExists("invoices/{$order->id}.pdf");
```

## Mocking

Mock external boundaries (HTTP, mail, queue, filesystem, third-party SDKs). **AVOID** mocking your own application classes — refactor instead.

```php
Http::fake([
    'google.com/*' => Http::response('foo@gmail.com', 200),
]);

$response = resolve(FetchGoogleUserEmailAction::class)->handle();

expect($response)->toBe('foo@gmail.com');
```

**MUST** call `Http::preventStrayRequests()` in test setup when mocking an external API — any unmatched request will throw instead of silently hitting the real endpoint.

```php
use function Pest\Laravel\mock;

mock(Client::class)
    ->shouldReceive('acceptOrder')
    ->withArgs(fn ($givenOrder) => $givenOrder->is($order))
    ->once()
    ->andReturn(true);
```

## Fixtures

Static files used as test input (XML payloads, JSON snapshots, sample uploads).

- **MUST** place under `tests/fixtures/`.
- **SHOULD** reference via `base_path('tests/fixtures/...')` rather than relative paths.

```php
$payment = base_path('tests/fixtures/payment.xml');

app(ProcessPaymentAction::class)->handle($payment, /* ... */);
```
