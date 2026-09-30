# Global Eloquent scopes

- Implement `Illuminate\Database\Eloquent\Scope`; type `apply(Builder $builder, Model $model): void`.
- Register with `#[ScopedBy(...)]` on the model. Keep the filter minimal because it applies to every query.
- Scope tenant/owner access to the authenticated boundary; separately enforce policies before sensitive reads or mutations. A global scope does not protect validation queries or raw query-builder calls automatically.
- Local `#[Scope]` methods remain on the model. Read `app/Models/CLAUDE.md` for query and scope conventions.
