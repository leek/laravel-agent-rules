# Livewire

> Targets Livewire 4 (current major).

## `wire:model` modifiers

**`wire:model` is deferred by default** — sync happens on the next server request, not per keystroke (the default since v3; v2's per-keystroke binding is gone).

- **MUST** default to plain `wire:model` for form fields — defers until submit/action.
- **SHOULD** use `wire:model.live` only when you genuinely need live sync (e.g. live-validation, dependent dropdowns).
- **SHOULD** use `wire:model.live.debounce.300ms` for search/filter inputs (note: `.live` must come first; bare `.debounce` does nothing on a deferred model).
- **SHOULD** use `wire:model.live.blur` when you want a single server roundtrip on blur. **In v4.1+, bare `.blur` (and `.change`) only control client-side state syncing — you must prefix `.live` to trigger a network request.**
- **SHOULD** use `wire:model.live.change` on `<select>` when you want a server roundtrip on option change rather than blur.

```blade
<input wire:model="title">                              {{-- deferred --}}
<input wire:model.live="search">                        {{-- per keystroke --}}
<input wire:model.live.debounce.300ms="search">         {{-- debounced live --}}
<input wire:model.live.blur="email">                    {{-- server roundtrip on blur --}}
```

## Authorization

- **MUST** call `$this->authorize(...)` in `mount()` AND inside every action method that mutates state. A `mount()`-only check is bypassable — the component is alive in the browser and any public method is callable directly.

❌ Authorized only in `mount()` — `publish()` is a public method callable directly from the browser, unchecked:

```php
public function mount(Post $post): void
{
    $this->authorize('update', $post);
    $this->post = $post;
}

public function publish(): void
{
    $this->post->update(['published' => true]);   // no authorize() — anyone can call this
}
```

✅ Authorize in `mount()` AND every mutating action:

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

## URL state — `#[Url]`

**PREFER** `#[Url]` per-property over the legacy `$queryString` array. Sync component state to the URL for filter/search/sort UIs so refresh and back/forward preserve state:

```php
use Livewire\Attributes\Url;

#[Url(except: '')]
public string $search = '';

#[Url(except: 'all')]
public string $status = 'all';

#[Url(history: true)]
public int $page = 1;

public function updatingSearch(): void
{
    $this->resetPage();
}
```

- `except` — keep value out of the URL when it equals this (clean URLs on defaults).
- `history: true` — `pushState` so the back button restores previous values; default `replaceState`.
- `as: 'q'` — rename the query-string key.
- **MUST** reset pagination when a filter changes via `updating{Property}()` — otherwise stale `page=N` runs against a smaller filtered set and returns an empty page.

## Property-level validation — `#[Validate]`

**PREFER** `#[Validate]` over a `rules()` method when rules live on the property:

```php
use Livewire\Attributes\Validate;

#[Validate('required|min:3')]
public string $title = '';

#[Validate(['required', 'email'])]
public string $email = '';
```

Then `$this->validate()` runs all attribute-defined rules. Combine with `wire:model.blur` for real-time validation.

## Computed properties — `#[Computed]`

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

`#[Computed(cache: true)]` persists across renders; `#[Computed(persist: 60)]` caches in the cache store for N seconds.

## Event listeners — `#[On]`

**PREFER** `#[On('event-name')]` on a handler method over the legacy `protected $listeners = [...]` array:

```php
use Livewire\Attributes\On;

#[On('order-placed')]
public function refreshTotals(int $orderId): void
{
    // ...
}
```

Wildcards: `#[On('order-*')]`. Dispatch from Blade: `$dispatch('order-placed', orderId: 42)`.

## Layout + title — `#[Layout]` / `#[Title]`

For full-page Livewire components, pin layout and `<title>` via attributes instead of returning a layout view:

```php
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    // ...
}
```

## Tamper-proof properties — `#[Locked]`

**MUST** mark any property that holds an authorization-relevant identifier (e.g. `$userId`, `$tenantId`) as `#[Locked]`. Public Livewire properties are otherwise client-mutable.

```php
use Livewire\Attributes\Locked;

#[Locked]
public int $userId;
```

Mutating a locked property from the client throws.

## Parent → child binding — `#[Reactive]` / `#[Modelable]`

- **`#[Reactive]`** on a child property — child re-renders when the parent's bound value changes.
- **`#[Modelable]`** on a child property — lets the parent use `wire:model` against the child component:

```php
// child
#[Modelable]
public string $value = '';

// parent
<livewire:custom-input wire:model="title" />
```

## Auto-save patterns

- `updated()` fires on every field change — add server-side throttling (timestamp comparison) to prevent write storms during fast typing.
- **AVOID** `$model->refresh()` in auto-save paths — unnecessary `SELECT` every save. Only refresh on explicit user actions.
- Use dirty-field tracking: compare current data to last-saved snapshot, skip the DB write when nothing changed.

## Event chain contract

- When dispatching events, verify ALL dependent components listen and re-query. List listeners explicitly via `#[On]` so the contract is greppable.

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
