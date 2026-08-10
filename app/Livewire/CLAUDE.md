# Livewire

> Targets Livewire 4 (current major).

## `wire:model` modifiers

**`wire:model` is deferred by default** — sync happens on the next server request, not per keystroke (the default since v3; v2's per-keystroke binding is gone).

- **MUST** default to plain `wire:model` for form fields — defers until submit/action.
- **SHOULD** use `wire:model.live` only when you genuinely need live sync (e.g. live-validation, dependent dropdowns).
- **SHOULD** use `wire:model.live.debounce.300ms` for search/filter inputs (note: `.live` must come first; bare `.debounce` does nothing on a deferred model).
- **SHOULD** use `wire:model.live.blur` when you want a single server roundtrip on blur. **In Livewire 4, bare `.blur` (and `.change`) only control client-side state syncing — you must include `.live` to trigger a network request** (e.g. `wire:model.live.blur`).
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

## Property-level validation — `#[Validate]`

**PREFER** `#[Validate]` on the property over a `rules()` method when rules are property-local:

```php
use Livewire\Attributes\Validate;

#[Validate('required|min:3')]
public string $title = '';

#[Validate(['required', 'email'])]
public string $email = '';
```

Then `$this->validate()` runs all attribute-defined rules. For server-side validation on blur, pair with `wire:model.live.blur` (bare `wire:model.blur` does **not** hit the server — see modifiers above).

## Computed properties — `#[Computed]`

Use `#[Computed]` for derived values accessed many times per render — Livewire memoizes for the **current request** only by default:

```php
use Livewire\Attributes\Computed;

#[Computed]
public function unreadCount(): int
{
    return $this->user->notifications()->whereNull('read_at')->count();
}
```

Read in Blade as `{{ $this->unreadCount }}`. Without `#[Computed]`, the method runs once per access — death-by-N-queries. Clear a stale memo with `unset($this->unreadCount)` after the underlying data changes.

Cross-request options (`persist` / `cache` are **bools**; TTL is `seconds`, default 3600):

```php
#[Computed(persist: true)]                    // this component instance, across requests
#[Computed(persist: true, seconds: 60)]       // same, custom TTL
#[Computed(cache: true)]                      // shared across all component instances
#[Computed(cache: true, seconds: 300, key: 'homepage-posts')]
```

## Other attributes (short)

| Attribute | Use |
| --- | --- |
| `#[Url(except: '', history: true, as: 'q')]` | Sync property to query string; **PREFER** over legacy `$queryString`. **MUST** reset pagination in `updating{Property}()` when a filter changes. |
| `#[On('order-placed')]` | Event listener on a method; **PREFER** over `$listeners`. Wildcards: `#[On('order-*')]`. Blade: `$dispatch('order-placed', orderId: 42)`. |
| `#[Layout('layouts.app')]` / `#[Title('Dashboard')]` | Full-page layout and `<title>`. |
| `#[Reactive]` | Child re-renders when parent-bound value changes. |
| `#[Modelable]` | Child property the parent can `wire:model` against. |

## Tamper-proof properties — `#[Locked]`

**MUST** mark any property that holds an authorization-relevant identifier (e.g. `$userId`, `$tenantId`) as `#[Locked]`. Public Livewire properties are otherwise client-mutable.

```php
use Livewire\Attributes\Locked;

#[Locked]
public int $userId;
```

Mutating a locked property from the client throws.

## DOM morphing

Use `wire:key` on both branches of `@if/@else` blocks with structurally different DOM trees — without keys, morphdom bleeds elements across states:

```blade
@if ($items->isNotEmpty())
    <div wire:key="panel-{{ $id }}"> ... </div>
@else
    <div wire:key="panel-empty"> ... </div>
@endif
```

## Component parameters + package aliases

- `@livewire(Component::class, ['key' => $value])` params match `mount()` **by array key name** — a mismatch silently resolves to `null`. Verify Blade calls after refactoring.
- `Livewire::component('package::name', ...)` is not enough for `package::...` resolution (finder treats `::` as namespaces first). Register: `Livewire::addNamespace('package', classNamespace: 'Vendor\\Package\\Livewire')`.
