# Controllers

**Purpose:** translate HTTP requests into responses. Thin glue.

## Naming

- **MUST** be `{SingularModel}Controller` (e.g. `UserController`).

## Rules

- **MUST** keep methods thin: validate (via FormRequest) → call Action/Support → return Response.
- **MUST NOT** validate inline (`$request->validate([...])` / `Validator::make(...)`) — all input validation lives in a FormRequest.
- **AVOID** building queries inline. Multi-clause `where()`/`join()`/aggregate chains belong in a model scope or query object (see `app/Models/CLAUDE.md`); the controller calls the scope/Action and returns the result.
- **MUST** mass-assign validated data — `Model::create($request->validated())` or `$parent->relation()->create($request->validated())`. **MUST NOT** set attributes one-by-one from raw request input.
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

For successful API mutations with no response body, **PREFER** `response()->noContent()` over `response()->json(null, 204)`:

```php
public function destroy(User $user): Response
{
    $user->delete();

    return response()->noContent();
}
```
