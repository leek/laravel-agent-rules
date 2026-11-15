# Laravel project rules

Before editing project code, read the cross-cutting rules in `app/CLAUDE.md`, including when the file is under `bootstrap/`, `routes/`, `resources/`, `tests/`, `database/`, `config/`, or `lang/`. Then read the directory rules along the edited file's path; the most specific applicable rules take precedence.

Rule references use this repository's canonical `CLAUDE.md` paths. In an installed project, resolve each reference in the same directory using your selected agent's installed filename: `CLAUDE.md`, `AGENTS.md`, `GEMINI.md`, `.cursorrules`, `.windsurfrules`, or `.clinerules`. The installer renames files but does not rewrite their contents. Read the referenced file explicitly when it is outside the edited file's ancestry.

For Livewire single-file or multi-file components under `resources/views/` (including their PHP, JavaScript, and CSS companions), also read `app/Livewire/CLAUDE.md`. Component-owned PHP and scoped assets use Livewire's component format; ordinary Blade-only restrictions do not prohibit that format.

Check the installed Laravel and package versions before using version-specific APIs. These rules support Laravel 12 and 13; guidance marked Laravel 13 must not be copied into a Laravel 12 project.

## Before you report the work as done

Re-read your whole diff, new files included (`git status`, `git diff`), as a reviewer who did not write it. Fix what you find before reporting:

- **Siblings:** every guard, status, predicate or permission you added holds on every path that has to agree, including jobs, commands and list endpoints (see **Sweep the siblings** in `app/CLAUDE.md`).
- **One definition:** each business predicate (expired, visible, available) is defined once, as a scope with its in-memory twin where both exist, and every caller uses it.
- **One owner:** each permission check has one owner (see **Security** in `app/CLAUDE.md`).
- **Lock first:** every state change the spec requires to be atomic takes its row lock before it reads (see **Lock, re-read, then decide** in `app/CLAUDE.md`).
- **Tests prove the edges:** each branch you wrote has a test, denial paths and repeated calls included, with fixtures that reach that branch.

