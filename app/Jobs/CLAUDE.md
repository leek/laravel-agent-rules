# Jobs (Queues)

**Purpose:** background or deferred work.

## Naming

- **MUST** be `{Verb}{Noun}Job` (e.g. `SendEmailJob`, `ProcessPaymentJob`, `GenerateReportJob`).

## Rules

- **SHOULD** wrap a single Action — the job is the queue boundary; the Action is the logic.
- **MUST** be idempotent or safe to retry. Use unique-job features (`ShouldBeUnique`) when re-running would cause duplicate side effects.
- **MUST** type-hint and declare what failure looks like (`failed()` method, `$tries`, `$backoff`).
