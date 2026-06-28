# Blade Components — anonymous

**Purpose:** reusable UI fragments without a PHP class. The Blade file itself is the component.

## Naming

- `kebab-case.blade.php` (e.g. `alert.blade.php`, `forms/input.blade.php`).
- Rendered as `<x-alert />` / `<x-forms.input />`. Subdirectory paths map directly to dotted/slashed component names.

## Rules

- **MUST** declare every consumed variable in `@props([...])` at the top of the file. Undeclared keys silently spill into `$attributes` and don't render where expected.
- **MUST NOT** run queries or lazy-load relations inside a component — receive already eager-loaded data via props (see `resources/views/CLAUDE.md`).
- **MUST** use `$attributes->merge(['class' => 'default'])` when forwarding HTML attributes. `merge` appends classes; for other attrs the caller's value wins.
- **MUST** prefer `$attributes->class([...])` over manual string-building when applying conditional classes.
- **SHOULD** use `@class([...])` / `@style([...])` directives for conditional classes/styles inside markup.
- **SHOULD** use `@pushOnce('stack', 'unique-key')` (not `@push`) when adding asset `<script>`/`<link>` tags from inside a component — prevents duplicate inclusions when the component is rendered multiple times.
- **SHOULD** use named slots (`$slot`, `$header`, `$footer`) for compositional UIs rather than passing markup via props.

## Example

```blade
{{-- resources/views/components/alert.blade.php --}}
@props([
    'type'  => 'info',
    'title' => null,
])

@php
    $classes = match ($type) {
        'success' => 'bg-green-50 text-green-800',
        'error'   => 'bg-red-50 text-red-800',
        default   => 'bg-blue-50 text-blue-800',
    };
@endphp

<div {{ $attributes->class(['p-4 rounded', $classes]) }} role="alert">
    @isset($title)
        <p class="font-bold">{{ $title }}</p>
    @endisset

    <div>{{ $slot }}</div>
</div>
```

Used as:

```blade
<x-alert type="success" title="Saved">
    Your changes were saved.
</x-alert>
```

## Conditional classes — `@class`

```blade
<button @class([
    'btn',
    'btn-primary' => $primary,
    'btn-disabled' => $disabled,
])>
    {{ $slot }}
</button>
```

## Asset injection — `@pushOnce`

```blade
@pushOnce('scripts', 'datepicker')
    <script src="/vendor/datepicker.js"></script>
@endPushOnce
```

## HTMX / Turbo fragments — `@fragment`

Render only a fragment of the response when called from a fragment-aware controller:

```blade
@fragment('item-list')
    <ul>
        @foreach ($items as $item)
            <li>{{ $item->name }}</li>
        @endforeach
    </ul>
@endfragment
```

Controller: `return view('items.index', ['items' => $items])->fragment('item-list');`
