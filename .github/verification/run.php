<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';
require __DIR__.'/check.php';

use App\Support\MoneyValue;
use Faker\Generator;
use Illuminate\Bus\Queueable;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Casts\AsFluent;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notification;
use Illuminate\Routing\Route;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Pennant\Feature;
use Laravel\Pennant\PennantServiceProvider;
use Tests\TestCase;

function snippet(string $path, string $needle): string
{
    $text = file_get_contents(dirname(__DIR__, 2).'/'.$path);
    preg_match_all('/```php\n(.*?)\n```/s', $text, $matches);
    $blocks = array_values(array_filter($matches[1], fn ($block) => str_contains($block, $needle)));

    if (count($blocks) !== 1) {
        throw new RuntimeException("Expected one snippet containing {$needle} in {$path}.");
    }

    return $blocks[0];
}

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

// Run the documented Pest closures with a small assertion adapter.
function expect(mixed $value): object
{
    return new class($value)
    {
        public function __construct(private mixed $value) {}

        public function toBeTrue(): void
        {
            check($this->value === true, 'Expected true.');
        }

        public function toBe(mixed $expected): void
        {
            check($this->value === $expected, 'Unexpected value.');
        }
    };
}

function it(string $name, Closure $test): object
{
    if ((new ReflectionFunction($test))->getNumberOfParameters() === 0) {
        $test();
    }

    return new class($test)
    {
        public function __construct(private Closure $test) {}

        public function with(array $dataset): void
        {
            foreach ($dataset as $value) {
                ($this->test)(...(is_array($value) ? $value : [$value]));
            }
        }
    };
}

function test(?string $name = null, ?Closure $closure = null): ?TestCase
{
    if ($closure !== null) {
        $closure();

        return null;
    }

    return $GLOBALS['case'];
}

$fixture = __DIR__.'/fixture';
if (! is_dir($fixture.'/bootstrap/cache')) {
    mkdir($fixture.'/bootstrap/cache', 0777, true);
}
$app = Application::configure(basePath: $fixture)
    ->withProviders([PennantServiceProvider::class])
    ->withMiddleware()
    ->withExceptions()
    ->withRouting(using: function (): void {
        Illuminate\Support\Facades\Route::get('/users', function () {
            abort_unless(auth()->user()?->admin, 403);

            return response('users');
        })->middleware('auth')->name('users.index');
    })
    ->create();
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error): void {
    fwrite(STDERR, (string) $error.PHP_EOL);
    exit(1);
});
$app['config']->set([
    'app' => ['name' => 'Rules fixture', 'env' => 'testing', 'debug' => true, 'url' => 'http://localhost',
        'locale' => 'en', 'fallback_locale' => 'en', 'cipher' => 'AES-256-CBC',
        'key' => 'base64:'.base64_encode(str_repeat('a', 32))],
    'database' => ['default' => 'sqlite', 'connections' => ['sqlite' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
    ]]],
    'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]],
    'auth' => ['defaults' => ['guard' => 'web'], 'guards' => ['web' => [
        'driver' => 'session', 'provider' => 'users',
    ]], 'providers' => ['users' => ['driver' => 'eloquent', 'model' => User::class]]],
    'session' => ['driver' => 'array', 'path' => '/', 'domain' => null, 'secure' => false],
    'hashing' => ['driver' => 'bcrypt', 'bcrypt' => ['rounds' => 4]],
    'pennant' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]],
]);
Facade::setFacadeApplication($app);
$app->instance('request', Request::create('/'));
$app->singleton(Generator::class, fn () => Faker\Factory::create());
$app['db']->connection()->getSchemaBuilder()->create('users', function (Blueprint $table): void {
    $table->id();
    $table->boolean('admin')->default(false);
    $table->string('email')->nullable();
    $table->string('password')->nullable();
});

class User extends Illuminate\Foundation\Auth\User
{
    protected $guarded = [];

    public $timestamps = false;

    public static function factory(): UserFactory
    {
        return UserFactory::new();
    }

    public function isInternal(): bool
    {
        return false;
    }

    public function isOnPlan(string $plan): bool
    {
        return $plan === 'pro';
    }
}

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return ['admin' => false];
    }
}

$case = new TestCase('fixture');
$case->initialize();
eval(snippet('tests/CLAUDE.md', 'function asAdmin'));
eval('use Illuminate\\Support\\Facades\\Validator;'.snippet('tests/CLAUDE.md', "it('rejects invalid emails'"));
check(User::factory()->create(['email' => ''])->email === '', 'Factory unexpectedly validated input.');

$app['db']->connection()->getSchemaBuilder()->create('invitations', function (Blueprint $table): void {
    $table->id();
    $table->string('email');
});
$admin = auth()->user();
$admin->update(['email' => 'admin@example.com']);
$target = User::factory()->create(['email' => 'target@example.com']);
class Invitation extends Model {}
class_alias(User::class, 'App\\Models\\User');
class_alias(Invitation::class, 'App\\Models\\Invitation');
$emailRules = snippet('app/Http/Requests/CLAUDE.md', 'Rule::unique(User::class');
eval('use Illuminate\\Validation\\Rule; use App\\Models\\User; use App\\Models\\Invitation; class EmailRequest extends \\Illuminate\\Foundation\\Http\\FormRequest { public function rules(): array { return ['.$emailRules.']; } }');
$boundRoute = new Route('PUT', 'users/{user}', fn () => null);
$boundRoute->bind(Request::create('/users/'.$target->id, 'PUT'));
$boundRoute->setParameter('user', $target);
$emailRequest = new EmailRequest;
$emailRequest->setRouteResolver(fn () => $boundRoute);
check(! Validator::make(['email' => $target->email], $emailRequest->rules())->fails(), 'Edited user was not excluded.');
check(Validator::make(['email' => $admin->email], $emailRequest->rules())->fails(), 'Authenticated user was wrongly excluded.');

class HashedUser extends User
{
    protected $table = 'users';

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }
}
$hashedUser = HashedUser::create(['password' => 'test-password']);
check(Hash::check('test-password', $hashedUser->password), 'Password cast failed.');

$after = snippet('app/Http/Requests/CLAUDE.md', 'public function after()');
eval('use Illuminate\\Validation\\Validator; class DateRequest extends \\Illuminate\\Foundation\\Http\\FormRequest {'.$after.'}');
foreach ([[], ['start_at' => 'nonsense', 'end_at' => '2026-09-30'],
    ['start_at' => '2026-10-01', 'end_at' => '2026-09-30'],
    ['start_at' => '2026-09-29', 'end_at' => '2026-09-30']] as $index => $input) {
    $request = new DateRequest($input);
    $validator = Validator::make($input, ['start_at' => ['required', 'date'], 'end_at' => ['required', 'date']]);
    foreach ($request->after() as $callback) {
        $validator->after($callback);
    }
    check($validator->fails() === ($index !== 3), 'Cross-field validation result is wrong.');
}

eval('use Laravel\\Pennant\\Feature; use Illuminate\\Support\\Lottery;'.snippet('app/Features/CLAUDE.md', "Feature::define('new-checkout'"));
check(Feature::for(null)->active('new-checkout') === false, 'Guest feature result is wrong.');
check(is_bool(Feature::for(new User)->active('new-checkout')), 'Pennant lottery did not resolve to bool.');

class CastFixture extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['settings' => AsFluent::class];
    }
}
check((new CastFixture(['settings' => ['enabled' => true]]))->settings->enabled === true, 'AsFluent failed.');

eval('namespace App\\Support; final readonly class MoneyValue {
    public function __construct(public int $amount, public string $currency) {}
    public static function fromCents(int $amount, string $currency): self { return new self($amount, $currency); }
    public function toCents(): int { return $this->amount; }
    public function roundedDollars(): int { return (int) round($this->amount / 100, 0, PHP_ROUND_HALF_EVEN); }
}');
eval('namespace App\\Casts; use Illuminate\\Database\\Eloquent\\Model; use Illuminate\\Contracts\\Database\\Eloquent\\CastsAttributes;'.snippet('app/Casts/CLAUDE.md', 'final class Money'));
eval('use App\\Casts\\Money; class PriceFixture extends \\Illuminate\\Database\\Eloquent\\Model { protected $guarded = []; '.snippet('app/Casts/CLAUDE.md', 'protected function casts()').'}');
$price = new PriceFixture(['amount' => 1234, 'currency' => 'USD']);
check($price->price->amount === 1234, 'Virtual price did not read amount.');
$price->price = new MoneyValue(5678, 'EUR');
check($price->getAttributes()['amount'] === 5678 && $price->getAttributes()['currency'] === 'EUR', 'Virtual price did not write both columns.');
$nullPrice = new PriceFixture(['amount' => null, 'currency' => null]);
check($nullPrice->price === null, 'Virtual price did not return null for unset values.');
$nullPrice->price = null;
check($nullPrice->getAttributes()['amount'] === null && $nullPrice->getAttributes()['currency'] === null, 'Virtual price did not write null columns.');
eval('use App\\Support\\MoneyValue;'.snippet('tests/Unit/CLAUDE.md', "it('rounds half to even'"));

$app['db']->connection()->getSchemaBuilder()->create('bulk_products', function (Blueprint $table): void {
    $table->id();
    $table->string('sku')->unique();
    $table->integer('price');
});
class BulkProduct extends Model
{
    protected $table = 'bulk_products';

    protected $guarded = [];

    public $timestamps = false;

    protected function price(): Attribute
    {
        return Attribute::make(set: fn (int $value) => $value * 100);
    }
}
$saves = 0;
BulkProduct::saved(function () use (&$saves): void {
    $saves++;
});
BulkProduct::create(['sku' => 'first', 'price' => 2]);
BulkProduct::upsert([['sku' => 'first', 'price' => 3]], ['sku'], ['price']);
check($saves === 1 && BulkProduct::where('sku', 'first')->value('price') === 3, 'Bulk write lifecycle semantics changed.');

class QueueFixture
{
    use Queueable;

    public function __construct()
    {
        $this->afterCommit();
    }
}
check((new QueueFixture)->afterCommit === true, 'Queueable constructor failed.');
$notification = new class extends Notification
{
    use Queueable;
};
check($notification->afterCommit()->afterCommit === true, 'Notification deferral failed.');

class Invoice extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    public static int $recalculations = 0;

    public function recalculate(): void
    {
        self::$recalculations++;
    }
}
class InvoiceItem extends Model
{
    protected $guarded = [];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
$app['db']->connection()->getSchemaBuilder()->create('invoices', fn (Blueprint $table) => $table->id());
$invoice = Invoice::create();
$item = new InvoiceItem(['invoice_id' => $invoice->id]);
$item->load('invoice');
eval('class InvoiceObserver {'.snippet('app/Observers/CLAUDE.md', 'public function saved').'}');
(new InvoiceObserver)->saved($item);
check(Invoice::$recalculations === 1, 'Observer did not call the related model.');

$container = $app;
$router = $app['router'];
$router->get('/users/{wrong}', function (User $user) {
    return $user->exists ? 'persisted' : 'empty';
});
check($router->dispatch(Request::create('/users/123'))->getContent() === 'empty', 'Binding mismatch changed.');
$route = new Route('GET', '/', fn () => 'cached');
$route->prepareForSerialization();
check(is_string($route->getAction('uses')), 'Closure route did not serialize.');

Gate::define('delete', fn (User $user, User $target) => $user->admin);
check(Gate::authorize('delete', new User)->allowed(), 'Gate authorization failed.');
check(Cache::supportsTags(), 'Array store should support tags.');
Cache::put('post:1:render', 'cached');
eval('use Illuminate\\Support\\Facades\\Cache; class CacheObserver {'.snippet('app/Support/CLAUDE.md', 'public function saved').'}');
class Post extends Model
{
    protected $guarded = [];
}
(new CacheObserver)->saved(new Post(['id' => 1, 'user_id' => 2]));
check(Cache::get('post:1:render') === null, 'Observer failed to invalidate cache.');
check(Cache::add('repeat', true, 60) && ! Cache::add('repeat', true, 60), 'Repeat guard did not reject a duplicate.');
$databaseCache = new Repository(new DatabaseStore($app['db']->connection(), 'cache'));
check(! $databaseCache->supportsTags(), 'Database store unexpectedly supports tags.');
$middleware = new Middleware;
$middleware->appendToPriorityList(after: StartSession::class, append: 'HandleLocale');
check($middleware->getMiddlewarePriority() === [], 'Custom addition replaced default priority.');
check($middleware->getMiddlewarePriorityAppends()['HandleLocale'] === StartSession::class, 'Priority addition missing.');

foreach (['tenants', 'products', 'product_categories'] as $tableName) {
    $app['db']->connection()->getSchemaBuilder()->create($tableName, function (Blueprint $table) use ($tableName): void {
        $table->id();
        if ($tableName !== 'tenants') {
            $table->integer('tenant_id');
        }
        if ($tableName === 'product_categories') {
            $table->integer('product_id');
        }
    });
}
class Tenant extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    public static function factory(): TenantFactory
    {
        return TenantFactory::new();
    }
}
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [];
    }
}
class Product extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    public static function factory(): ProductFactory
    {
        return ProductFactory::new();
    }

    public function productCategories(): HasMany
    {
        return $this->hasMany(ProductCategory::class);
    }
}
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return ['tenant_id' => Tenant::factory()];
    }
}
class ProductCategory extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    public static function factory(): ProductCategoryFactory
    {
        return ProductCategoryFactory::new();
    }
}
class ProductCategoryFactory extends Factory
{
    protected $model = ProductCategory::class;

    public function definition(): array
    {
        return ['tenant_id' => Tenant::factory()];
    }
}
eval(snippet('database/factories/CLAUDE.md', '$tenant = Tenant::factory()'));
check(Tenant::count() === 3, 'Nested factory example created the wrong number of tenants.');
$recycledProduct = Product::latest('id')->firstOrFail();
check($recycledProduct->tenant_id === $tenant->id && $recycledProduct->productCategories()->firstOrFail()->tenant_id === $tenant->id, 'Recycle did not propagate the shared parent.');
Product::factory()->create(['tenant_id' => $tenant]);
check(Tenant::count() === 3, 'Explicit parent override incorrectly created a tenant.');

$property = (object) ['id' => 42];
$request = Request::create('/');
$processed = 0;
$repeat = snippet('app/Support/CLAUDE.md', '$'.'ipHash = hash');
eval('use Illuminate\\Support\\Facades\\Cache;'.$repeat.'$processed++;');
eval('use Illuminate\\Support\\Facades\\Cache;'.$repeat.'$processed++;');
check($processed === 1 && count(explode(':', $key)) === 3, 'Repeat guard or structured identity failed.');

// Put the reflection example at its documented relative path in the isolated fixture.
foreach (['app/Contracts', 'app/Data', 'tests/Architecture'] as $directory) {
    if (! is_dir($fixture.'/'.$directory)) {
        mkdir($fixture.'/'.$directory, 0777, true);
    }
}
file_put_contents($fixture.'/app/Contracts/InvoiceGateway.php', '<?php namespace App\\Contracts; interface InvoiceGateway {}');
file_put_contents($fixture.'/app/Data/PlainData.php', '<?php namespace App\\Data; final readonly class PlainData { public function __construct(public int $id) {} }');
file_put_contents($fixture.'/app/Data/PackageData.php', '<?php namespace App\\Data; class PackageBase { public int $frameworkState = 0; } final class PackageData extends PackageBase { public function __construct(public readonly int $id) {} }');
foreach (['app/Contracts/InvoiceGateway.php', 'app/Data/PlainData.php', 'app/Data/PackageData.php'] as $file) {
    require $fixture.'/'.$file;
}
file_put_contents($fixture.'/tests/Architecture/Rules.php', '<?php '.snippet('tests/Architecture/CLAUDE.md', 'function ruleClassesIn'));
require $fixture.'/tests/Architecture/Rules.php';

// Render both component-attribute examples through a registered component.
if (! is_dir($fixture.'/storage/views')) {
    mkdir($fixture.'/storage/views', 0777, true);
}
$app['config']->set('view.compiled', $fixture.'/storage/views');
eval('namespace Rules\\Fixture; final class IconComponent extends \\Illuminate\\View\\Component { public function __construct(public string $name) {} public function render(): string { return \'<span {{ $attributes }}></span>\'; } }');
Illuminate\Support\Facades\Blade::component(Rules\Fixture\IconComponent::class, 'icon');
preg_match_all('/```blade\n(.*?)\n```/s', file_get_contents(dirname(__DIR__, 2).'/resources/views/CLAUDE.md'), $bladeBlocks);
$tooltipBlocks = array_values(array_filter($bladeBlocks[1], fn ($block) => str_contains($block, 'x-tooltip')));
check(count($tooltipBlocks) === 2, 'Expected the two component-attribute examples.');
$tip = "Owner's note";
$wrong = Illuminate\Support\Facades\Blade::render($tooltipBlocks[0], ['tip' => $tip]);
$right = html_entity_decode(Illuminate\Support\Facades\Blade::render($tooltipBlocks[1], ['tip' => $tip]), ENT_QUOTES);
check(str_contains($wrong, '@js($tip)'), 'Directive in a component attribute was unexpectedly compiled.');
check(str_contains($right, 'x-tooltip="{ content: '.Illuminate\Support\Js::from($tip)->toHtml().' }"'), 'Js::from echo did not render the JavaScript literal.');

echo 'Runtime examples passed on Laravel '.Application::VERSION.PHP_EOL;
