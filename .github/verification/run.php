<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';
require __DIR__.'/check.php';

use Faker\Generator;
use Illuminate\Bus\Queueable;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Casts\AsFluent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

echo 'Runtime examples passed on Laravel '.Application::VERSION.PHP_EOL;
