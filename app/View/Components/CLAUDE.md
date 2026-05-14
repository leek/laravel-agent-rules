# Blade Components — class-based

**Purpose:** reusable UI fragments backed by a PHP class. Use when a component needs computed values, dependency injection, or non-trivial logic.

## Naming

- **PascalCase** class name (e.g. `Alert`, `UserBadge`, `Forms\Input`).
- Rendered in Blade as `<x-alert />` / `<x-user-badge />` / `<x-forms.input />` — Laravel auto-resolves dotted/dashed names.

## Rules

- **MUST** type-hint constructor params with promoted `public` properties — they become available in the view automatically.
- **SHOULD** use class-based components when the component needs DI, computed helpers, or conditional logic. **PREFER** anonymous components for simple markup wrappers — see `resources/views/components/CLAUDE.md`.
- Public methods on the class are callable from the view as `{{ $methodName() }}`.

## Create

```bash
php artisan make:component Alert
# Creates app/View/Components/Alert.php + resources/views/components/alert.blade.php
```

## Example

```php
final class Alert extends Component
{
    public function __construct(
        public string $type = 'info',
        public ?string $title = null,
    ) {}

    public function alertClasses(): string
    {
        return match ($this->type) {
            'success' => 'bg-green-50 text-green-800',
            'error'   => 'bg-red-50 text-red-800',
            default   => 'bg-blue-50 text-blue-800',
        };
    }

    public function render(): View|string
    {
        return view('components.alert');
    }
}
```

## Inline render (no view file)

For trivial wrappers, return a Blade string directly:

```php
public function render(): string
{
    return <<<'blade'
        <div {{ $attributes->class(['p-4', 'rounded']) }}>{{ $slot }}</div>
        blade;
}
```

## Layouts via component

**PREFER** `<x-layouts.app>...</x-layouts.app>` over `@extends('layouts.app')` + `@section` blocks — components support slots and named slots natively and compose better.
