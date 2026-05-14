# Livewire

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
