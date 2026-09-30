# Controllers

**Purpose:** translate HTTP requests into responses. Thin glue.

## Naming

- **MUST** be `{SingularModel}Controller` (e.g. `UserController`).

## Rules

- **MUST** keep methods thin: validate (via FormRequest) → call Action/Support → return Response.
- **MUST NOT** validate inline (`$request->validate([...])` / `Validator::make(...)`) on routes users hit — input validation lives in a FormRequest. Exception: throwaway internal/debug endpoints may use inline validation; promote to a FormRequest as soon as the route is real product surface.
- **AVOID** building queries inline. Multi-clause `where()`/`join()`/aggregate chains belong in a model scope or query object (see `app/Models/CLAUDE.md`); the controller calls the scope/Action and returns the result.
- **MUST** mass-assign only an allowlisted, trusted attribute set — never raw request input. Prefer `$request->safe()->only([...])`, `safe()->except([...])`, or a Form Request `toDto()` (see `app/Http/Requests/CLAUDE.md`). Bare `$request->validated()` is allowed only when the Form Request rules are already the complete write-allowlist and contain no client-controlled ownership, tenancy, or privilege fields. **MUST NOT** set attributes one-by-one from raw request input.
- **MUST** shape API JSON through an API Resource (or an equivalent explicit contract) — **MUST NOT** return Eloquent models via `response()->json($model)` / `return $model` (see `app/Http/Resources/CLAUDE.md`).
- **PREFER** creating child records through the already-bound relationship (`$team->members()->create(...)`) instead of assigning the foreign key manually.
- **MUST** eager-load (`with(...)`) every relation the response/view will touch — the controller owns N+1 prevention (see `app/Models/CLAUDE.md`).
- **PREFER** scoping ownership before lookup (`whereBelongsTo($request->user())->findOrFail($id)`) when non-owned records should be indistinguishable from missing records. `findOrFail()` then `abort(403)` leaks that the record exists.
- **SHOULD** be a resource controller for CRUD endpoints.
- **SHOULD** be an invokable controller (`__invoke`) for one-off single-action endpoints.
- **AVOID** extracting an Action for trivial CRUD that is just validate → create/update/delete → redirect/return. Extract when the operation has branching, side effects, reuse across entry points, or enough logic to test independently.
- **MUST** namespace by audience when there are multiple variants:

```
app/Http/Controllers/UserController.php           // web
app/Http/Controllers/Api/UserController.php       // public API
app/Http/Controllers/Admin/UserController.php     // admin web
app/Http/Controllers/Api/Admin/UserController.php // admin API
```

- **MUST** nest the **domain** axis *inside* the audience/version axis when both apply — audience/version is matched to the route group, so it stays the outer segment:

```
app/Http/Controllers/Billing/InvoiceController.php        // web, Billing domain
app/Http/Controllers/Api/V1/Billing/InvoiceController.php // API v1, Billing domain
```

See domain sub-namespacing (when to introduce domain folders) in `app/CLAUDE.md`.

## Create

```bash
php artisan make:controller UserController --resource
```

## Controller-level middleware — `HasMiddleware`

When middleware needs to apply to specific controller actions, **MUST** implement the `HasMiddleware` interface and return middleware definitions from a static `middleware()` method. Do NOT use the legacy `$this->middleware(...)` constructor call.

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

Trivial CRUD needs no Action — validate → write → respond is enough (see the AVOID rule above). Reach for an Action when there is branching, side effects, reuse, or logic worth testing on its own.

```php
public function store(StoreUserRequest $request): JsonResponse
{
    $user = User::query()->create(
        $request->safe()->only(['name', 'email', 'password'])
    );

    return (new UserResource($user))
        ->response()
        ->setStatusCode(201);
}
```

With an Action (non-trivial operation):

```php
public function store(StoreOrderRequest $request, CreateOrderAction $createOrder): JsonResponse
{
    $order = $createOrder->handle($request->toDto());

    return (new OrderResource($order))
        ->response()
        ->setStatusCode(201);
}
```

For successful API mutations with no response body, **PREFER** `response()->noContent()` over `response()->json(null, 204)`:

```php
public function destroy(User $user): Response
{
    Gate::authorize('delete', $user);
    $user->delete();

    return response()->noContent();
}
```

Import `Illuminate\Support\Facades\Gate`. The modern skeleton's base controller has no `authorize()` method; `$this->authorize()` requires explicitly adding `Illuminate\Foundation\Auth\Access\AuthorizesRequests` to the controller.
