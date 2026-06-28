# Blade Views

**Purpose:** render markup. The display layer only — no business logic, no data fetching.

> Component-specific rules live in `resources/views/components/CLAUDE.md` (anonymous) and `app/View/Components/CLAUDE.md` (class-based).

## Naming

- **MUST** name view files `kebab-case.blade.php` (`show-filtered.blade.php`, `user-profile.blade.php`) — not `showFiltered` or `show_filtered`.

## Rules

- **MUST NOT** run queries or lazy-load relations from a view (no `Model::where(...)`, no `$post->comments` triggering a query inside `@foreach`). Receive already eager-loaded data from the controller — the N+1 problem. `Model::preventLazyLoading()` (see `app/Models/CLAUDE.md`) turns any violation into an exception in dev.
- **MUST NOT** put inline `<script>` or `<style>` blocks in Blade. Keep JS/CSS in Vite-compiled assets.
- **MUST** pass server data to JS via `data-*` attributes or `@json($data)` — never interpolate PHP into a `<script>` body.
- **AVOID** `@php` blocks and non-trivial logic in Blade. Shape data beforehand (controller, Action, view model, or class-based component) and pass ready-to-render values; reserve `@php` for trivial presentational mapping only.
- **MUST NOT** format dates with `Carbon::createFromFormat(...)` in the view — cast the column to `datetime` on the model (see `app/Models/CLAUDE.md`) and format the Carbon instance: `{{ $order->ordered_at->format('m-d') }}`.

## Passing data to JS

❌ PHP interpolated into a script body:

```blade
<script>
    let article = `{{ json_encode($article) }}`;
</script>
```

✅ Data attribute or `@json`, read from a compiled JS file:

```blade
<button class="js-fav-article" data-article='@json($article)'>{{ $article->name }}</button>
```

**MUST** wrap `@json` in **single** quotes — its output keeps literal `"` around every key/value, which would terminate a double-quoted attribute. (`@json` hex-escapes inner `'` via `JSON_HEX_APOS`, so single quotes stay safe.)
