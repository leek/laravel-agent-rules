# Testing (Pest)

Default test framework: [Pest](https://pestphp.com/). PHPUnit-style works under the hood; new tests use Pest.

## Database

- **SHOULD** run the suite against an in-memory SQLite database (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` in `phpunit.xml` / `.env.testing`) for fast, isolated tests.
- **MUST** use `LazilyRefreshDatabase`, `RefreshDatabase`, or `DatabaseTransactions` so each database-using test starts from a clean schema. **PREFER** `LazilyRefreshDatabase` in `tests/Pest.php` for Pest suites because tests that never touch the database avoid needless migration work.

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
- **SHOULD** mirror the app's domain sub-namespacing in the test path — a test for `App\Http\Controllers\Billing\InvoiceController` lives at `tests/Feature/Billing/InvoiceControllerTest.php` (`make:test Billing/InvoiceControllerTest`). See `app/CLAUDE.md`.
- **Architecture tests** enforce structural rules — naming suffixes, layering, finalness, no debug leftovers — with Pest's `arch()`. No database, near-instant; see *Architecture tests* below.

## Architecture tests

`arch()` tests assert the project's *structure* instead of its behaviour, so the conventions this ruleset defines (naming, layering, where logic lives) can't silently rot as the app grows. They run inside the normal Pest suite, hit no database, and finish in milliseconds.

- **SHOULD** add architecture tests once the structure stabilises — they're the cheapest way to keep a growing codebase on-convention.
- **MUST** treat a failing arch test as a real defect: either the code drifted or the rule is wrong — fix one, never silence the suite.
- **SHOULD** name each rule so a failure points straight at the violated convention, and use `->ignoring(...)` for deliberate, documented exceptions rather than deleting the rule.

Start with the presets, then layer rules that enforce *this* ruleset:

```php
arch()->preset()->laravel();   // framework naming + structure conventions
arch()->preset()->security();  // flags eval, md5, mt_rand, etc.

// No debug/dump leftovers shipped to production
arch('no debug helpers')->expect(['dd', 'dump', 'ray', 'var_dump', 'die'])->not->toBeUsed();

// Conventions defined elsewhere in these rules
arch('models')->expect('App\Models')->toExtend('Illuminate\Database\Eloquent\Model');
arch('enums')->expect('App\Enums')->toBeEnums();              // see app/Enums/
arch('contracts')->expect('App\Contracts')->toBeInterfaces(); // see app/Contracts/
arch('actions')->expect('App\Actions')->toHaveSuffix('Action');
arch('controllers')->expect('App\Http\Controllers')->toHaveSuffix('Controller');

// Layering: stateless support code must not reach into the HTTP layer
arch('support stays framework-agnostic')
    ->expect('App\Support')
    ->not->toUse('App\Http');
```

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

## Datasets — parameterized tests

For boundary/validation tests that vary only by inputs, **SHOULD** use a Pest dataset rather than copy-pasting near-identical tests:

```php
it('rejects invalid emails', function (string $email) {
    expect(fn () => User::factory()->create(['email' => $email]))
        ->toThrow(ValidationException::class);
})->with([
    'empty'       => '',
    'no at sign'  => 'not-an-email',
    'no tld'      => 'user@example',
    'spaces'      => 'a b@c.com',
]);
```

## Useful assertions

- **`assertSoftDeleted('posts', ['id' => $post->id])`** — verify a soft delete happened.
- **`$this->assertModelExists($post)` / `$this->assertModelMissing($post)`** — model-aware existence checks; clearer failures than `assertDatabaseHas`.
- **`assertJsonStructure([...])`** — pin response shape, including pagination envelope:

```php
$response->assertJsonStructure([
    'success',
    'data' => ['*' => ['id', 'title']],
    'meta' => ['page', 'per_page', 'total'],
]);
```

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

// ...

Notification::assertSentTo($user, OrderShipped::class);
Storage::disk('s3')->assertExists("invoices/{$order->id}.pdf");
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
