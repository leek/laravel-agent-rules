# Query objects

- Name classes for the query they express, such as `OutstandingInvoicesQuery`.
- Encapsulate reusable multi-condition reads that outgrow one model scope. Keep writes and business workflows in Actions.
- Call the model scope that owns a predicate (`->active()`); never retype its conditions. A restated subset drifts from the scope the rest of the app uses — see **Sweep the siblings** in `app/CLAUDE.md`.
- A soft-deleting table reached through `join()` or raw SQL needs its own `deleted_at` predicate, and a historical read needs `withTrashed()` — see **Soft deletes** in `app/Models/CLAUDE.md`.
- Declare the return shape (builder, collection, or paginator) and apply tenant/owner constraints before returning data.
- If offering immutable fluent query methods, clone the contained mutable builder as well as the wrapper before adding constraints.
- Read `app/Models/CLAUDE.md` for SQL grouping, allowlisted sort expressions, and eager-loading conventions.
