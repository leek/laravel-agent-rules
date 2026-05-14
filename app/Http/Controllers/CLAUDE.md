# Controllers

**Purpose:** translate HTTP requests into responses. Thin glue.

## Naming

- **MUST** be `{SingularModel}Controller` (e.g. `UserController`).

## Rules

- **MUST** keep methods thin: validate (via FormRequest) → call Action/Support → return Response.
- **SHOULD** be a resource controller for CRUD endpoints.
- **SHOULD** be an invokable controller (`__invoke`) for one-off single-action endpoints.
- **MUST** namespace by audience when there are multiple variants:

```
app/Http/Controllers/UserController.php           // web
app/Http/Controllers/Api/UserController.php       // public API
app/Http/Controllers/Admin/UserController.php     // admin web
app/Http/Controllers/Api/Admin/UserController.php // admin API
```

## Create

```bash
php artisan make:controller UserController --resource
```

## Controller-level middleware — `HasMiddleware`

When middleware needs to apply to specific controller actions, **MUST** implement the `HasMiddleware` interface and return middleware definitions from a static `middleware()` method. Do NOT use the legacy `$this->middleware(...)` constructor call — it is deprecated as of Laravel 11.

```php
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

final class PostController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth',
            new Middleware('subscribed', only: ['create', 'store']),
            new Middleware('throttle:uploads', only: ['store', 'update']),
        ];
    }
}
```

## Example

```php
public function store(StoreUserRequest $request): JsonResponse
{
    $user = User::query()->create($request->validated());

    return response()->json($user);
}
```
