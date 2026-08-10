# API Resources

**Purpose:** shape an Eloquent model (or collection) into a JSON response payload. Keeps controllers free of array-building logic.

## Naming

- **MUST** be `{SingularModel}Resource` (e.g. `UserResource`, `ProjectResource`).
- Collection wrappers: `{SingularModel}Collection` only when you need to override pagination/meta defaults — otherwise call `Resource::collection(...)` directly.

## Rules

- **MUST** be the single source of truth for the JSON shape of a model — controllers don't hand-roll arrays.
- **MUST NOT** trigger queries inside `toArray()` (no N+1). Eager-load required relations in the controller / query object before passing the model to the resource.
- **SHOULD** keep field selection explicit — return only the fields the consumer needs, not `$this->toArray()`.

## Create

```bash
php artisan make:resource UserResource
```

## Conditional fields

Use the `when*` helpers to keep payloads lean and avoid leaking relations that weren't loaded:

- **`$this->whenLoaded('relation')`** — include a relation only when the controller eager-loaded it. Prevents accidental N+1 from the resource layer.
- **`$this->whenCounted('relation')`** / **`$this->whenAggregated(...)`** — include counts and aggregates only when the query loaded them via `withCount()` / `withAggregate()`. Do not emit fake `null` counters.
- **`$this->when($condition, $value)`** — include a field only when a condition holds (e.g. show `email` only to the user themself or an admin; show heavy `content` on `show` but not `index`).
- **`$this->whenPivotLoaded('table', fn () => $this->pivot->role)`** — include pivot data only when the pivot row is hydrated.

```php
return [
    'id'       => $this->id,
    'title'    => $this->title,
    'content'  => $this->when($request->routeIs('*.show'), $this->content),
    'email'    => $this->when($request->user()?->can('view-email', $this->resource), $this->email),
    'author'   => UserResource::make($this->whenLoaded('author')),
    'comments' => CommentResource::collection($this->whenLoaded('comments')),
    'comments_count' => $this->whenCounted('comments'),
    'role'     => $this->whenPivotLoaded('team_user', fn () => $this->pivot->role),
];
```

## Example

```php
final class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'     => $this->id,
            'name'   => $this->name,
            'status' => $this->status,
            'owner'  => UserResource::make($this->whenLoaded('owner')),
        ];
    }
}
```

## Pagination — Laravel default (preferred)

**PREFER** Laravel's built-in resource pagination: pass the paginator to `Resource::collection(...)` and return it. Laravel wraps `data` + pagination `links` / `meta` for you.

```php
return ProjectResource::collection(
    Project::query()->active()->paginate(25)
);
```

Optional shared top-level keys (API version, deprecation) via `with(Request $request)` on the resource.

## Project response envelope (optional convention)

Some APIs adopt an explicit envelope such as:

```json
{ "success": true, "data": ..., "error": null, "meta": ... }
```

That shape is a **project API contract**, not Laravel's default Resource pagination. Use it only when the project's public API already (or deliberately) standardizes on it — document the contract once for the app and keep every endpoint consistent. Do **not** invent a custom envelope on top of Laravel Resources "because the rules said so."

When the project *does* require that envelope, build it deliberately (often a thin response helper or base resource) and pin the shape in feature tests with `assertJsonStructure`. Example of a hand-rolled paginated envelope:

```php
$projects = Project::query()->active()->paginate(25);

return response()->json([
    'success' => true,
    'data'    => ProjectResource::collection($projects->items()),
    'error'   => null,
    'meta'    => [
        'page'     => $projects->currentPage(),
        'per_page' => $projects->perPage(),
        'total'    => $projects->total(),
    ],
]);
```
