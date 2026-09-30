# Custom Eloquent builders

- Extend `Illuminate\Database\Eloquent\Builder`; name the class `{Model}Builder`.
- Register with `#[UseEloquentBuilder(...)]` on the model.
- Use for reusable query operations that benefit from a fluent model-specific builder. Keep workflows and external I/O in Actions.
- Read `app/Models/CLAUDE.md` for query grouping, tenant boundaries, eager loading, and bulk-write semantics. Builder classes are not models; exclude this namespace from model base-type architecture assertions.
