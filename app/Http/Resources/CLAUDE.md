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
