# Testing (Pest)

Default test framework: [Pest](https://pestphp.com/). PHPUnit-style works under the hood; new tests use Pest.

## When to write tests

- **MUST** write a test for every class that contains logic (Actions, Support, Jobs, Commands, Livewire components, Controllers, Policies).
- **MUST** write a test for every route exposed to users (auth/redirect/permission boundaries).
- **SHOULD** target coverage ≥ 70%; aim for ≥ 80%. Verify with `php artisan test --coverage --min=80`.

## What to test

- Code logic (Actions, Support classes, Jobs, Commands)
- HTTP layer (Controllers, Livewire components, route auth boundaries)
- Routing (named routes, redirects, middleware behaviour)
- Edge cases — happy path is necessary but **not** sufficient.

## Test types

- **Feature tests** are the default. They exercise the full stack and catch the largest class of regressions for the smallest amount of code. Place under `tests/Feature/`.
- **Unit tests** are used only for genuinely isolated logic (pure functions, complex calculations). Place under `tests/Unit/`. **AVOID** unit tests that mock the framework just to bypass it.

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

test('payment can be processed', function () {
    // ...
});

// AVOID — uppercase, vague
test('PAYMENT', fn () => /* ... */);
it('sums', fn () => /* ... */);
```

## Create

```bash
php artisan make:test Actions/VerifyUserActionTest
```

## Pest hooks

Use these to set up / tear down shared state inside a test file:

- `beforeEach()` — runs before every test in the file
- `afterEach()` — runs after every test in the file
- `beforeAll()` — runs once before the file
- `afterAll()` — runs once after the file

## Helpers and custom methods

- **SHOULD** extract repeated setup into a helper function in the test file.
- **SHOULD** promote a helper to `tests/Pest.php` when it is useful across multiple files.

Local helper:

```php
function asAdmin(): User
{
    $user = User::factory()->create(['admin' => true]);
    return test()->actingAs($user);
}

it('can manage users', function () {
    asAdmin()->get('/users')->assertOk();
});
```

Global helper (in `tests/Pest.php`):

```php
function mockPayments(): object
{
    return Mockery::mock(PaymentClient::class);
}
```

## Mocking

Mock external boundaries (HTTP, mail, queue, filesystem, third-party SDKs). **AVOID** mocking your own application classes — refactor instead.

HTTP fakes:

```php
Http::fake([
    'google.com/*' => Http::response('foo@gmail.com', 200),
]);

$response = resolve(FetchGoogleUserEmailAction::class)->run();

expect($response)->toBe('foo@gmail.com');
```

**MUST** call `Http::preventStrayRequests()` in test setup when mocking an external API — any unmatched request will throw instead of silently hitting the real endpoint.

Class mocking with Pest:

```php
use function Pest\Laravel\mock;

mock(Client::class)
    ->shouldReceive('acceptOrder')
    ->withArgs(fn ($givenOrder) => $givenOrder->is($order))
    ->once()                  // also: ->never(), ->twice(), ->times(3)
    ->andReturn(true);
```

## Fixtures

Static files used as test input (XML payloads, JSON snapshots, sample uploads).

- **MUST** place under `tests/fixtures/`.
- **SHOULD** reference via `base_path('tests/fixtures/...')` rather than relative paths.

```php
$payment = base_path('tests/fixtures/payment.xml');

app(ProcessPaymentAction::class)->run($payment, /* ... */);
```
