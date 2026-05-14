# Policies

**Purpose:** authorize a user's ability to perform an action on a model.

## Naming

- **MUST** be `{SingularModel}Policy` (e.g. `PostPolicy`, `OrderPolicy`).

## Discovery

Three ways to bind a model to a policy, in order of preference:

1. **`#[UsePolicy]` attribute on the model (L11+)** — explicit, greppable, no convention magic.

   ```php
   use Illuminate\Database\Eloquent\Attributes\UsePolicy;

   #[UsePolicy(PostPolicy::class)]
   final class Post extends Model {}
   ```

2. **Auto-discovery** — keep policies under `App\Policies\` with `{Model}Policy` naming and Laravel resolves them. No registration needed.

3. **`Gate::policy(Model::class, Policy::class)`** in `AppServiceProvider::boot()` — only when the model lives outside the convention and you can't (or won't) put an attribute on it.

## Method shape

```php
final class PostPolicy
{
    public function viewAny(User $user): bool { /* ... */ }
    public function view(User $user, Post $post): bool { /* ... */ }
    public function create(User $user): bool { /* ... */ }
    public function update(User $user, Post $post): bool { /* ... */ }
    public function delete(User $user, Post $post): bool { /* ... */ }
}
```

## `before()` — global allow / fall-through

```php
public function before(User $user, string $ability): ?bool
{
    if ($user->isAdmin()) {
        return true; // allow everything
    }

    return null; // fall through to the specific method
}
```

- **MUST** return `null` from `before()` to fall through. Returning `false` denies **everything**, including abilities you never intended to override.

## Denials — `Response::deny*`

Return a `Response` instead of `false` when the caller needs a custom message or non-403 status:

```php
use Illuminate\Auth\Access\Response;

public function update(User $user, Post $post): Response
{
    return $post->user_id === $user->id
        ? Response::allow()
        : Response::denyAsNotFound(); // 404 — hides existence
}
```

- `Response::deny('You cannot edit this post.')` — 403 with message.
- `Response::denyWithStatus(402)` — custom HTTP status.
- `Response::denyAsNotFound()` — 404; useful for IDOR-resistant endpoints.

## Calling a policy

| Need | API |
| ---- | --- |
| Throw `AuthorizationException` | `Gate::authorize('update', $post)` / `$this->authorize('update', $post)` |
| Boolean check | `Gate::allows('update', $post)`, `$user->can('update', $post)` |
| Need denial message | `$response = Gate::inspect('update', $post); $response->allowed(); $response->message();` |

## Rules

- **MUST NOT** inline ad-hoc checks (`if (auth()->id() !== $post->user_id) abort(403);`) — funnel through a policy.
- **MUST** prefer route-level `->can('update', 'post')` middleware over a repeated `$this->authorize(...)` inside the controller action.
- **SHOULD** test denial paths, not just allow paths.

```php
Route::put('/posts/{post}', [PostController::class, 'update'])
    ->can('update', 'post');
```

## Testing helpers

- `Gate::before(fn () => true)` — bypass authorization in non-auth-focused tests (faster than seeding admin users).
- For dedicated policy tests, assert both allow and deny paths and at least one `before()` short-circuit case.
