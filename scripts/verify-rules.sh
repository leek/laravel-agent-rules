#!/usr/bin/env zsh
# Structural checks for laravel-agent-rules CLAUDE.md content (v0.17+).
# Exit 0 only when audit acceptance criteria hold.
set -euo pipefail
cd "$(dirname "$0")/.."
fail=0
note() { print -- "✓ $1"; }
bad()  { print -- "✗ $1"; fail=1; }

# 1 — Livewire Computed API (persist/cache bools; TTL via seconds)
if rg -q 'persist: 60|persist: [0-9]' app/Livewire/CLAUDE.md; then
  bad "Livewire must not use numeric persist: N (persist is bool; use seconds:)"
else
  note "no numeric persist: N"
fi
if ! rg -q 'persist: true' app/Livewire/CLAUDE.md; then
  bad "Livewire missing #[Computed(persist: true"
else
  note "Computed persist: true present"
fi
if ! rg -q 'seconds:' app/Livewire/CLAUDE.md; then
  bad "Livewire missing seconds: for Computed TTL"
else
  note "Computed seconds: present"
fi
if rg -q 'cache: true\].*persist|persist.*cache in the cache store' app/Livewire/CLAUDE.md; then
  bad "Livewire still has wrong cache/persist prose"
else
  note "no wrong cache/persist prose"
fi

# 2 — wire:model blur consistency (server-side needs .live)
if rg -q 'Combine with `wire:model\.blur`|wire:model\.blur` for real-time' app/Livewire/CLAUDE.md; then
  bad "Validate section still recommends bare wire:model.blur for server/real-time"
else
  note "no bare blur as real-time validation"
fi
if ! rg -q 'wire:model\.live\.blur' app/Livewire/CLAUDE.md; then
  bad "Livewire missing wire:model.live.blur guidance"
else
  note "wire:model.live.blur present"
fi

# 3 — HasFactory still provides ::factory()
if rg -q 'no longer pull in `HasFactory`|no longer pull in HasFactory|dropped HasFactory' app --glob '**/CLAUDE.md'; then
  bad "false HasFactory-dropped claim still present"
else
  note "no false HasFactory-dropped claim"
fi
if ! rg -q 'HasFactory.*factory\(\)|::factory\(\)|HasFactory` still' app/Models/CLAUDE.md; then
  bad "Models missing HasFactory / ::factory() still-needed wording"
else
  note "HasFactory still-needed wording present"
fi

# 4 — arch env() ignores config
if ! rg -q "expect\('env'\)" tests/Architecture/CLAUDE.md; then
  bad "Architecture missing env() ban"
elif ! rg -A5 "expect\('env'\)" tests/Architecture/CLAUDE.md | rg -q "ignoring\('config'\)|ignoring\(\"config\"\)"; then
  bad "Architecture env() ban missing ->ignoring('config')"
else
  note "arch env() ignores config"
fi

# 5 — README no auto-save; pin tag
if rg -q 'auto-save' README.md; then
  bad "README still claims auto-save"
else
  note "README has no auto-save"
fi
if ! rg -q '@v0\.17\.0' README.md; then
  bad "README install pin should use @v0.17.0"
else
  note "README pin is v0.17.0"
fi

# 6 — Listeners failed() aligned with Jobs (conditional, not absolute all-queued)
if rg -q 'MUST\*\* declare `failed\(\)` on queued listeners|MUST declare `failed\(\)` on queued listeners' app/Listeners/CLAUDE.md; then
  bad "Listeners still absolute-MUST failed() on every queued listener"
else
  note "Listeners failed() not absolute on all queued"
fi
if ! rg -q 'failed\(' app/Listeners/CLAUDE.md; then
  bad "Listeners lost failed() guidance entirely"
else
  note "Listeners still documents failed()"
fi
if ! rg -q 'side effects worth ops|ops attention|side effect needs ops' app/Listeners/CLAUDE.md app/Jobs/CLAUDE.md; then
  bad "Jobs/Listeners missing conditional ops-attention language for failed()"
else
  note "failed() conditional language present"
fi

# 7 — optional audit adds
if ! rg -q 'Gate::define' app/Policies/CLAUDE.md; then
  bad "Policies missing Gate::define for non-model abilities"
else
  note "Gate::define present"
fi
if ! rg -q 'store\(|storeAs|File uploads|named disk' app/Http/Requests/CLAUDE.md; then
  bad "Requests missing file-upload / Storage guidance"
else
  note "upload/Storage guidance present"
fi
if ! rg -q 'app/Support/CLAUDE.md|Caching' app/CLAUDE.md; then
  bad "app/CLAUDE.md missing caching cross-link"
else
  note "caching cross-link present"
fi

# 8 — high-signal preserved (thinning must not drop these)
for needle in 'Gotchas (silent failures)' 'extends Pivot' 'HasUlids' 'withPivotValue' '#[Locked]' 'authorize' 'Coverage matrix' 'ShouldDispatchAfterCommit' 'afterCommit'; do
  if ! rg -q -F -g '**/CLAUDE.md' -- "$needle"; then
    bad "high-signal missing: $needle"
  fi
done
note "high-signal content present"

# 9 — Action entrypoint is handle() (current convention; do not reintroduce run()-only)
if rg -q 'exactly one public method: `run\(' app/Actions/CLAUDE.md; then
  bad "Actions still standardize on run() — expected handle()"
elif ! rg -q 'exactly one public method: `handle\(' app/Actions/CLAUDE.md; then
  bad "Actions missing handle() entrypoint rule"
else
  note "Actions use handle()"
fi

# 10 — no root agent rules
if [[ -f CLAUDE.md || -f AGENTS.md ]]; then
  bad "root CLAUDE.md/AGENTS.md must not exist"
else
  note "no root CLAUDE.md/AGENTS.md"
fi

if (( fail )); then
  print -- "\nverify-rules: FAILED"
  exit 1
fi
print -- "\nverify-rules: OK"
exit 0
