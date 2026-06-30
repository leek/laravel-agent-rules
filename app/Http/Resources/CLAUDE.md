# API Resources

**Purpose:** shape an Eloquent model (or collection) into a JSON response payload. Keeps controllers free of array-building logic.

## Naming

- **MUST** be `{SingularModel}Resource` (e.g. `UserResource`, `ProjectResource`).
- Collection wrappers: `{SingularModel}Collection` only when you need to override pagination/meta defaults — otherwise call `Resource::collection(...)` directly.

## Rules

- **MUST** be the single source of truth for the JSON shape of a model — controllers don't hand-roll arrays.
- **MUST NOT** trigger queries inside `toArray()` (no N+1). Eager-load required relations in the controller / query object before passing the model to the resource.
- **SHOULD** keep field selection explicit — return only the fields the consumer needs, not `$this->toArray()`.

## Response envelope

Use a consistent envelope across all API responses:

```json
{ "success": true, "data": ..., "error": null, "meta": ... }
```

- `data` — payload (object, array, or paginated collection items)
- `meta` — pagination + counters (`page`, `per_page`, `total`)
- `error` — `null` on success, error object on failure

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

## Global response meta — `with()`

Inject fields into every response from a resource (API version, server time, deprecation notice) via `with(Request $request)`:

```php
public function with(Request $request): array
{
    return [
        'api_version' => '2024-05',
    ];
}
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

## Paginated response

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
