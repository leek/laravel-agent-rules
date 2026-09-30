# Laravel project rules

Before editing project code, read the cross-cutting rules in `app/CLAUDE.md`, including when the file is under `bootstrap/`, `routes/`, `resources/`, `tests/`, `database/`, `config/`, or `lang/`. Then read the directory rules along the edited file's path; the most specific applicable rules take precedence.

Rule references use this repository's canonical `CLAUDE.md` paths. In an installed project, resolve each reference in the same directory using your selected agent's installed filename: `CLAUDE.md`, `AGENTS.md`, `GEMINI.md`, `.cursorrules`, `.windsurfrules`, or `.clinerules`. The installer renames files but does not rewrite their contents. Read the referenced file explicitly when it is outside the edited file's ancestry.

For Livewire single-file or multi-file components under `resources/views/` (including their PHP, JavaScript, and CSS companions), also read `app/Livewire/CLAUDE.md`. Component-owned PHP and scoped assets use Livewire's component format; ordinary Blade-only restrictions do not prohibit that format.

Check the installed Laravel and package versions before using version-specific APIs. These rules support Laravel 12 and 13; guidance marked Laravel 13 must not be copied into a Laravel 12 project.
