# Support classes

**Purpose:** reusable domain helpers that are not specific to one model or controller. Lightweight alternative to a full Service layer.

## Naming

- **MUST** be named after the domain noun, **no `Support` suffix** (`Cart`, `OpeningHours`, `Table`).

## Rules

- **SHOULD** be stateless or own-only-its-own-state; **AVOID** hidden global state.
- **PREFER** a Support class over scattering the same helpers across multiple Actions.

## Create

```bash
php artisan make:class Support/Cart   # starter-kit
```

Or plain class under `app/Support/`.

---

# Caching

Generic caching rules. Apply wherever cache is touched — Support classes are a common home for cache-wrapping helpers, but the rules apply to every call site.

## Cache key conventions

- **MUST** use a structured key: `{entity}:{id}:{aspect}` (e.g. `user:42:profile`, `post:99:render`).
- **MUST** prefix with a schema version when the cached payload's shape changes (e.g. `v2:user:42:profile`). Bumping the version invalidates the whole namespace without a manual flush.
- **AVOID** dynamic, hard-to-invalidate keys (`user:42:posts:filtered:` + serialized filters) — prefer cache tags or fine-grained per-row caching.

## Stale-while-revalidate — `Cache::flexible`

For hot read paths, **PREFER** `Cache::flexible('key', [fresh, stale], fn)` over `remember()`. Returns stale data immediately while a background refresh runs — avoids the stampede when a popular key expires.

```php
$config = Cache::flexible('billing:plans', [60, 300], function () {
    return BillingPlan::query()->orderBy('order_column')->get();
});
```

## Locks — `Cache::lock`

For exclusive critical sections (one-shot imports, dedup, leader election), **MUST** use `Cache::lock` with `try/finally` release. Use `->block($seconds, fn)` to wait up to N seconds for the lock.

```php
$lock = Cache::lock('import:orders', 60);

try {
    $lock->block(5);
    $this->importer->run();
} finally {
    optional($lock)->release();
}
```

## Request-scoped memoization — `Cache::memo`

For values computed many times in a single request, **PREFER** `Cache::memo` over hitting Redis on every call:

```php
$user = Cache::memo()->remember("user:{$id}", 60, fn () => User::find($id));
```

## Tags

`Cache::tags(['posts', "user:{$id}"])->...` — **only works on `redis` and `memcached` drivers**. Don't use with `database` / `file` / `array` stores.

## Null results

- **MUST** handle the case where the underlying lookup returns `null`. Caching `null` for an hour hides newly-created rows until the TTL expires. Either:
  - return early without writing the cache when the value is `null`, or
  - use a short negative-cache TTL deliberately and document why.

## Invalidation

- **SHOULD** invalidate cache in model observers on `saved` / `deleted`. The observer is the right place because invalidation is cheap — but **MUST NOT** *recompute* the cache inside the observer (heavy work blocks the write path; see `app/Models/CLAUDE.md`).

```php
public function saved(Post $post): void
{
    Cache::forget("post:{$post->id}:render");
    Cache::tags(["user:{$post->user_id}"])->flush();
}
```

