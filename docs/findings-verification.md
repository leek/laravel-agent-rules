# Model findings verification

Audit against the worktree starting at `19bdbc8`, September 30, 2026. Claude and Codex supplied identical reports; agreement is not independent verification. IDs follow the deduplicated tier list in the conversation. Each finding is checked before its fix; rejected claims are retained here.

## Evidence snapshots

Primary source checkouts used for this audit:

- [Laravel 12 framework](https://github.com/laravel/framework/tree/ce3027e2664ff9c2602f9dca2ecfdf0a8089d241), [Laravel 13 framework](https://github.com/laravel/framework/tree/e5f3d6265f4b9d3d9efc466de83f79c54e14dd5d).
- [Laravel 12 skeleton](https://github.com/laravel/laravel/tree/e90c74ca717e9082d7463a2db50814fe565a3e44).
- [Pest Laravel preset](https://github.com/pestphp/pest/blob/5b2293f67adcf1b2320b33f521b94a692d18f360/src/ArchPresets/Laravel.php).
- [Installer](https://github.com/leek/apply-agent-rules/tree/6344e33efbb724b66d6de51dd19b17c374b0ee31).
- [Livewire](https://github.com/livewire/livewire/tree/2750bdd142ad91167f384f39421acb486477e0fe).
- [Pennant](https://github.com/laravel/pennant/tree/d49771e646a7c598509c85f33755e1353bccc7dc).
- [Spatie Data](https://github.com/spatie/laravel-data/tree/ce296f22861dc1237ce468754cc7f46d3ac34ad5).

Paths in the tables refer to those snapshots. Documentation links supplement source checks. Runtime verification uses Composer releases on both Laravel 12 and 13; it does not assume branch tips and tagged releases are identical.

## Tier 1

| ID | Disposition | Evidence and resulting change |
| --- | --- | --- |
| T1-01 | Confirmed; corrected | `Illuminate/Bus/Queueable.php` declares untyped `$afterCommit`; PHP rejects incompatible trait properties. Jobs and Notifications now call `afterCommit()` rather than redeclaring it. |
| T1-02 | Confirmed; corrected | Skeleton `app/Http/Controllers/Controller.php` is empty. Controller and policy guidance use `Gate::authorize()` and explain `AuthorizesRequests` explicitly. |
| T1-03 | Confirmed; corrected | `Illuminate/Validation/Validator.php::passes()` invokes all after callbacks; `InteractsWithData::date()` returns null for an absent field. Example guards errors and states both required/date prerequisites. |
| T1-04 | Confirmed; corrected | `Queue/Middleware/ThrottlesExceptions.php` constructor accepts seconds, while `backoff()` uses minutes. Example now uses 300 seconds for five minutes. |
| T1-05 | Confirmed; corrected | `Eloquent/Concerns/HasAttributes.php` primitive casts do not include `asFluent`; `Casts/AsFluent.php` is a class cast. Guidance now uses `AsFluent::class`. |
| T1-06 | Confirmed; corrected | `Eloquent/Attributes/Hidden.php` exists in the 13 snapshot, not 12. Attribute alternative is explicitly labeled Laravel 13. |
| T1-07 | Confirmed; corrected | Pennant `Drivers/Decorator.php` resolves returned Lottery objects after invoking the feature closure. Closure must permit `bool\|Lottery` before Pennant can evaluate it. |
| T1-08 | Confirmed; corrected | `Testing/Concerns/InteractsWithAuthentication::actingAs()` returns the test case. Helper return type now matches `Tests\TestCase`, preserving its chained request call. |
| T1-09 | Confirmed; corrected | `Eloquent/Factories/Factory::create()` saves models without Form Request validation. Dataset now exercises a real validator; the questionable no-TLD case is removed because RFC email validation need not reject it. |
| T1-10 | Confirmed; corrected | Relation objects proxy query methods, not arbitrary model instance methods (`Eloquent/Relations/Relation::__call()`). Observer accesses the related invoice model. |
| T1-11 | Confirmed; corrected | `Routing/ImplicitRouteBinding::resolveForRoute()` scopes the child to the parent, not the parent to the user. Rules now require separate tenant/owner scoping and authorization. |
| T1-12 | Confirmed with qualification; corrected | Missing parameter names skip implicit binding; `Routing/ResolvesRouteDependencies::transformDependency()` creates a required model through the container. Optional parameters can resolve to null. Rules distinguish both. |
| T1-13 | Confirmed; corrected | `Routing/Route::prepareForSerialization()` wraps closure actions in SerializableClosure. Removed the obsolete closure-cache prohibition. |
| T1-14 | Confirmed; corrected | Pest preset enforces ServiceProvider suffix, listener `handle()`, and queued mailables. This repo deliberately permits other shapes. Architecture guidance uses a compatible custom matrix instead of enabling the whole preset. |
| T1-15 | Confirmed; corrected | A literal `I` prefix also matches Invoice/Idempotency. Replaced blanket prefix check with a reflection example checking `^I[A-Z]`. |
| T1-16 | Confirmed; corrected | Spatie's `Data` base is not readonly; PHP forbids a readonly child of a non-readonly parent. Check payload properties instead and make the Spatie example's payload properties readonly. |
| T1-17 | Confirmed; corrected | Pest itself excludes `App\Models\Scopes` from model inheritance expectations. Added scope and custom-builder namespace exceptions to the custom model matrix. |
| T1-18 | Confirmed; corrected | Framework `__()` resolves the translator; `Factory::withFaker()` resolves Faker from the container. Translated labels and factory-based tests now belong in Feature tests. |
| T1-19 | Partly confirmed; corrected | `ArrayStore extends TaggableStore`; Grok's array-store assertion is false. DatabaseStore/FileStore do not support tags, and skeleton cache defaults to database. Corrected store support and made invalidation sample use `forget()`. |
| T1-20 | Confirmed; corrected | Repository `add()` delegates to stores' atomic add operation where available. Replaced has/put claim with `Cache::add()`. Use a shared appropriate store for cross-process coordination. |
| T1-21 | Confirmed; corrected | `Foundation/Configuration/Middleware::priority()` assigns the complete list. Example uses `appendToPriorityList()` to preserve framework ordering. |
| T1-22 | Partly confirmed; corrected | Framework EventServiceProvider still exists; it is absent only from the modern application skeleton. Listener guidance refers to AppServiceProvider and prevents duplicate discovery/manual registration. |
| T1-23 | Confirmed; corrected | Local DTO whitelist excluded date/value objects while model rules retained Carbon. DTOs now allow immutable dates/value objects and require conversion of mutable Carbon at the boundary. |
| T1-24 | Confirmed; corrected | Local Casts, Models, Contracts, and Unit examples overloaded Money with incompatible roles/APIs. Standardized Money as adapter, MoneyValue as immutable minor-unit/currency value, with a shared API description. |
| T1-25 | Confirmed; corrected | Compared example identifiers against the same files' naming rules. Added Exception/Command suffixes and changed enum case NEW to New. |
| T1-26 | Confirmed; corrected | `WithoutOverlapping` defaults release delay and lock expiry to zero and releases conflicting jobs. [Queue docs](https://laravel.com/docs/12.x/queues#preventing-job-overlaps) explain attempt consumption. Added workload-specific release/expiry and retry guidance. |
| T1-27 | Confirmed; corrected | Initial tree had no root rules; app is not an ancestor of routes/resources/tests/etc. Added root loader requiring shared app rules across the project. |
| T1-28 | Confirmed; corrected | Installer `src/apply.js` maps destination names and uses `copyFileSync`, without content rewriting. Root loader explicitly resolves canonical paths using the selected installed filename. |
| T1-29 | Confirmed with qualification; corrected | Installer emits legacy files (including .cursorrules, contrary to README's .mdc claim). [Windsurf](https://docs.windsurf.com/windsurf/cascade/memories) and [Cline](https://docs.cline.bot/customization/cline-rules) document native rule discovery/activation. README no longer guarantees universal nested discovery and describes required wiring. |
| T1-30 | Confirmed; corrected | bootstrap directory initially had no rules. Added scoped routing/middleware/exception/provider guidance and pointers. |
| T1-31 | Confirmed; corrected | Local Models guidance recommended helpers without clear directories. Added query, builder, and scope homes and directory rules; architecture matrix excludes non-model helpers. |
| T1-32 | Confirmed; corrected | [Livewire components docs](https://livewire.laravel.com/docs/4.x/components) place SFC/MFC under resources/views. Added cross-directory loader, Blade-format exceptions, and stable loop key guidance. |
| T1-33 | Confirmed; corrected | Skeleton ships only web/console routes and no lang directory. Framework install:api/install:broadcasting/lang:publish commands exist. Added optional setup instructions without implying installing rules installs those features. |
| T1-34 | Confirmed with qualification; corrected | [Laravel 13 release notes](https://laravel.com/docs/13.x/releases), framework composer PHP floor, Eloquent attributes, factory UseModel, and JSON:API source confirm additions. JSON:API also exists in the current 12 snapshot/release, so it must not be labeled exclusively Laravel 13. Added availability/version-gated coverage. Composer resolved 13.34.0 and 12.69.3 during this audit. |
| T1-35 | Confirmed; verification added | Initial CI consisted only of links.yml. Added canonical reference/fence checks and a Laravel 12/13 runtime-example CI matrix. Runtime scope is selected executable examples; this does not claim to execute every illustrative fragment. |

## Verification commands

```sh
python3 .github/verification/references.py
composer install --working-dir=.github/verification --no-interaction --no-progress
php .github/verification/run.php
```

CI resolves each supported Laravel major separately. Runtime tests use isolated in-memory SQLite only; no application database is reset or modified. Tier commits are made only after their verification passes.
