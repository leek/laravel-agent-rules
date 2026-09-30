# Query objects

- Name classes for the query they express, such as `OutstandingInvoicesQuery`.
- Encapsulate reusable multi-condition reads that outgrow one model scope. Keep writes and business workflows in Actions.
- Declare the return shape (builder, collection, or paginator) and apply tenant/owner constraints before returning data.
- If offering immutable fluent query methods, clone the contained mutable builder as well as the wrapper before adding constraints.
- Read `app/Models/CLAUDE.md` for SQL grouping, allowlisted sort expressions, and eager-loading conventions.
