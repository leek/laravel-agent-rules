# States

> Targets `spatie/laravel-model-states`. Skip this directory if the project models finite state with a plain backed enum (see `app/Enums/`) — reach for state classes only when transitions carry rules or behaviour.

**Purpose:** model a value that moves through a defined lifecycle with guarded transitions and per-state behaviour.

## Naming

- **MUST** put each model's states in `app/States/{Model}/`.
- **MUST** name the abstract base `{Model}State` and each concrete state `{Name}State` (e.g. `PatientState` + `NewState`, `QualifiedState`, `ClosedState`).

## Rules

- **MUST** declare the allowed transition graph in the base class's `config()` — every transition not listed is rejected at runtime.
- **MUST** cast the column to the base state class in the model's `casts()` and add the `HasStates` trait.
- **MUST** change state via `transitionTo()` — **MUST NOT** write the column directly (bypasses the guard).
- **SHOULD** attach a Transition class when a transition has side effects (writing a timestamp, dispatching an event) rather than scattering that logic at call sites.
- **SHOULD** keep per-state behaviour (labels, colours, predicates) as methods on the state classes, not a `match()` on a string elsewhere.

## Base class + transitions

```php
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/** @extends State<Patient> */
abstract class PatientState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(NewState::class)
            ->allowTransition(NewState::class, QualifiedState::class)
            ->allowTransition(QualifiedState::class, ClosedState::class);
    }
}

final class NewState extends PatientState
{
    public function label(): string { return 'New'; }
}
```

## Model wiring

```php
use Spatie\ModelStates\HasStates;

final class Patient extends Model
{
    use HasStates;

    protected function casts(): array
    {
        return ['status' => PatientState::class];
    }
}
```

## Transition via the guard, never the column

❌ Writes the column directly — skips the transition graph and any side effects:

```php
$patient->status = ClosedState::class;
$patient->save();
```

✅ Go through `transitionTo()` — an illegal transition throws `TransitionNotFound`:

```php
$patient->status->transitionTo(ClosedState::class);
```

## Create

No artisan generator ships with the package — add plain classes under `app/States/{Model}/`.
