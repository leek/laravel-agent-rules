# Livewire

## `wire:model` modifiers

- **MUST** default to `wire:model.defer` for form fields — batches updates until submit. Plain `wire:model` sends a network roundtrip on every keystroke.
- **SHOULD** use `wire:model.debounce.300ms` for search/filter inputs that should react live without flooding the server.
- **SHOULD** use `wire:model.lazy` when you want a single roundtrip on blur.

## Authorization

- **MUST** call `$this->authorize(...)` in `mount()` AND inside every action method that mutates state. A `mount()`-only check is bypassable — the component is alive in the browser and any public method is callable directly.

```php
public function mount(Post $post): void
{
    $this->authorize('update', $post);
    $this->post = $post;
}

public function publish(): void
{
    $this->authorize('publish', $this->post);
    // ...
}
```

## URL state with `$queryString`

Sync component state to the URL for filter/search/sort UIs so refresh and back/forward preserve state:

```php
public array $queryString = [
    'search' => ['except' => ''],
    'status' => ['except' => 'all'],
    'page'   => ['except' => 1],
];

public function updatingSearch(): void
{
    $this->resetPage();
}
```

- **MUST** reset pagination when a filter changes via `updating{Property}()` — otherwise stale `page=N` runs against a smaller filtered set and returns an empty page.

## Computed properties

Use `#[Computed]` for derived values accessed many times per render — Livewire memoizes the result for the lifetime of the render pass:

```php
use Livewire\Attributes\Computed;

#[Computed]
public function unreadCount(): int
{
    return $this->user->notifications()->whereNull('read_at')->count();
}
```

Read in Blade as `{{ $this->unreadCount }}`. Without `#[Computed]`, the method runs once per access — death-by-N-queries.

## Auto-save patterns

- `updated()` fires on every field change — add server-side throttling (timestamp comparison) to prevent write storms during fast typing.
- **AVOID** `$model->refresh()` in auto-save paths — unnecessary `SELECT` every save. Only refresh on explicit user actions.
- Use dirty-field tracking: compare current data to last-saved snapshot, skip the DB write when nothing changed.

## Event chain contract

- When dispatching events, verify ALL dependent components listen and re-query. List listeners explicitly in the component.

## Double-refresh

- **AVOID** redundant event dispatch (e.g. calendar date change + separate refresh event). One event, one re-query.

## Filter propagation

- Filter changes must propagate to all dependent UI panels. Test each panel after changing a filter.

## DOM morphing

- Use `wire:key` on both branches of `@if/@else` blocks with structurally different DOM trees.
- Without keys, morphdom bleeds elements from the old state into the new.
- Pattern:

```blade
@if ($items->isNotEmpty())
    <div wire:key="panel-{{ $id }}"> ... </div>
@else
    <div wire:key="panel-empty"> ... </div>
@endif
```

## Component parameter names

- `@livewire(Component::class, ['key' => $value])` params are matched by array key name.
- `mount()` parameter names **MUST** match array keys exactly — a mismatch silently resolves to `null`.
- Always verify Blade `@livewire` calls match the target `mount()` signature after refactoring.

## Component aliases

- `Livewire::component('package::name', ...)` is not enough for `package::...` resolution — the finder treats `::` names as namespaces before checking explicit registrations.
- When a package uses `::` aliases, register the namespace:

```php
Livewire::addNamespace('package', classNamespace: 'Vendor\\Package\\Livewire');
```
