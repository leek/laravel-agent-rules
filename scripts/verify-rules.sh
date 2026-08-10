#!/usr/bin/env zsh
# Structural checks for laravel-agent-rules CLAUDE.md content.
# Exit 0 only when audit acceptance criteria hold.
set -euo pipefail
cd "$(dirname "$0")/.."
fail=0
note() { print -- "✓ $1"; }
bad()  { print -- "✗ $1"; fail=1; }

# 1 — no repository-per-model binding example
if rg -q 'App\\Repositories|EloquentOrderRepository' --glob '**/CLAUDE.md'; then
  bad "Providers/rules must not exemplify App\\Repositories"
else
  note "no App\\Repositories examples"
fi
if ! rg -q 'repository-per-model' app/CLAUDE.md; then
  bad "anti-repo guidance missing from app/CLAUDE.md"
else
  note "anti-repo guidance present"
fi
if ! rg -q 'PaymentGateway' app/Providers/CLAUDE.md; then
  bad "Providers should exemplify a real seam (PaymentGateway)"
else
  note "Providers uses PaymentGateway seam"
fi
# App\Services\* implementations in examples must end in Service (arch matrix + naming)
if rg -q 'App\\Services\\[A-Za-z0-9_]+' app/Providers/CLAUDE.md app/Contracts/CLAUDE.md app/Services/CLAUDE.md; then
  if rg -n 'App\\Services\\[A-Za-z0-9_]+' app/Providers/CLAUDE.md app/Contracts/CLAUDE.md app/Services/CLAUDE.md \
    | rg -v 'Service(;|,|\.|::|$)'; then
    bad "App\\Services\\* examples must use {Noun}Service suffix"
  else
    note "App\\Services examples use Service suffix"
  fi
fi
if rg -q 'StripeGateway|class [A-Za-z0-9_]+ implements PaymentGateway' app --glob '**/CLAUDE.md'; then
  if rg -q 'class StripeGateway|StripeGateway::class' app --glob '**/CLAUDE.md'; then
    bad "StripeGateway conflicts with {Noun}Service naming — use StripeService"
  elif ! rg -q 'class StripeService implements PaymentGateway|StripeService::class' app --glob '**/CLAUDE.md'; then
    bad "PaymentGateway impl should be StripeService in Services"
  else
    note "PaymentGateway bound to StripeService"
  fi
fi

# 2 — after/before and indexes not absolute MUST for all DBs/columns
if rg -q 'MUST.*after\(|MUST use `after|MUST.*use `after\(\)' database --glob '**/CLAUDE.md'; then
  bad "after()/before() must not be absolute MUST"
else
  note "after()/before() not absolute MUST"
fi
if rg -q 'index to any column used in' database --glob '**/CLAUDE.md'; then
  bad "blanket index-every-WHERE rule still present"
else
  note "blanket index rule removed"
fi
if ! rg -q 'MySQL/MariaDB' database/CLAUDE.md database/migrations/CLAUDE.md; then
  bad "MySQL/MariaDB note for column order missing"
else
  note "MySQL/MariaDB column-order note present"
fi

# 3 — Jobs: WithoutRelations and missing-model are separate
if ! rg -q '## Trim serialized payload — `\#\[WithoutRelations\]`' app/Jobs/CLAUDE.md; then
  bad "WithoutRelations section heading missing"
else
  note "WithoutRelations section present"
fi
if ! rg -q '## Missing-model handling — `\#\[DeleteWhenMissingModels\]`' app/Jobs/CLAUDE.md; then
  bad "DeleteWhenMissingModels section heading missing"
else
  note "DeleteWhenMissingModels section present"
fi
# WithoutRelations section body must not define deleteWhenMissingModels as the class-wide form of WithoutRelations
without_block=$(awk '/## Trim serialized payload/{p=1} /## Missing-model/{p=0} p' app/Jobs/CLAUDE.md)
if print -- "$without_block" | rg -q 'deleteWhenMissingModels'; then
  bad "deleteWhenMissingModels still nested under WithoutRelations as same feature"
else
  note "missing-model not nested under WithoutRelations"
fi

# 4 — Action entrypoint is run() only
if rg -q 'execute\(\)|run\(\) or' app/Actions/CLAUDE.md; then
  bad "Actions still allow execute() or dual names"
else
  note "Actions standardize on run()"
fi
if rg -q 'function handle\(' app/Data/CLAUDE.md; then
  bad "Data example still uses handle() for Action"
else
  note "Data Action example uses run()"
fi
if ! rg -q 'function run\(' app/Data/CLAUDE.md app/Actions/CLAUDE.md; then
  bad "run() examples missing"
else
  note "run() examples present"
fi

# 5 — i18n: user-facing strings use __() or documented exceptions
if ! rg -q 'MUST.*user-facing copy' app/CLAUDE.md; then
  bad "i18n MUST missing"
else
  note "i18n MUST present"
fi
for f in app/Enums/CLAUDE.md app/States/CLAUDE.md app/Mail/CLAUDE.md; do
  if ! rg -q '__\(' "$f"; then
    bad "$f missing __() in examples/rules"
  else
    note "$f uses __()"
  fi
done

# 6 — stale footer gone
if rg -q 'For class types without a dedicated CLAUDE|without a dedicated CLAUDE' app/CLAUDE.md; then
  bad "stale class-types footer still present"
else
  note "stale footer removed"
fi

# 7 — Mail vs Notification naming disambiguated
if ! rg -q '\{Subject\}Mail|\*Mail|Mail` suffix' app/Mail/CLAUDE.md; then
  bad "Mail naming must require Mail suffix"
else
  note "Mail uses Mail suffix"
fi
if ! rg -q 'MUST NOT.*Mail' app/Notifications/CLAUDE.md; then
  bad "Notifications must forbid Mail suffix"
else
  note "Notifications forbid Mail suffix"
fi

# 8 — tests parent does not re-host arch matrix
if rg -q "arch\(\)->preset|arch\('actions'\)" tests/CLAUDE.md; then
  bad "tests/CLAUDE.md still hosts arch() preset/matrix dump"
else
  note "arch() dump only in Architecture/"
fi
if ! rg -q "arch\(\)->preset|Coverage matrix" tests/Architecture/CLAUDE.md; then
  bad "Architecture coverage matrix missing"
else
  note "Architecture matrix present"
fi

# 9 — Jobs batching guidance present (cheatsheet or section; no duplicate ## Batch* dumps)
batch_heads=$(rg -c '^## Batch' app/Jobs/CLAUDE.md || true)
if [[ "${batch_heads:-0}" -gt 1 ]]; then
  bad "expected at most one ## Batch* heading in Jobs, got ${batch_heads}"
elif ! rg -q 'Bus::batch|Batchable' app/Jobs/CLAUDE.md; then
  bad "Jobs batching guidance (Bus::batch / Batchable) missing"
else
  note "Jobs batching guidance present (no duplicate ## Batch* dumps)"
fi

# 10 — Resources envelope is project convention
if ! rg -q 'project API contract|optional convention' app/Http/Resources/CLAUDE.md; then
  bad "Resources envelope not framed as project convention"
else
  note "Resources envelope is project convention"
fi
if ! rg -q 'Resource::collection|Laravel.*pagination|built-in resource pagination' app/Http/Resources/CLAUDE.md; then
  bad "Laravel default pagination preference missing"
else
  note "Laravel pagination preferred"
fi

# 11 — additive: README L11+/Pest, security, final, Features skip
if ! rg -q 'Laravel 11\+' README.md; then bad "README missing L11+"; else note "README L11+"; fi
if ! rg -q 'Pest' README.md; then bad "README missing Pest"; else note "README Pest"; fi
if ! rg -q '## Security' app/CLAUDE.md; then bad "security section missing"; else note "security section"; fi
if ! rg -q 'PREFER.*`final`|PREFER `final`' app/CLAUDE.md; then bad "prefer final missing"; else note "prefer final"; fi
if ! rg -q 'Skip this directory' app/Features/CLAUDE.md; then bad "Features skip banner missing"; else note "Features skip banner"; fi

# 12 — high-signal preserved
high_ok=1
for needle in 'Gotchas (silent failures)' 'afterCommit' 'ShouldDispatchAfterCommit' '#[Locked]' 'Where does logic go?' 'Coverage matrix'; do
  if ! rg -q -F -g '**/CLAUDE.md' -- "$needle"; then
    bad "high-signal missing: $needle"
    high_ok=0
  fi
done
(( high_ok )) && note "high-signal content present"

# 13 — Mailables in arch matrix
if ! rg -q "toHaveSuffix\('Mail'\)" tests/Architecture/CLAUDE.md; then
  bad "arch matrix missing Mail suffix"
else
  note "arch matrix includes Mail suffix"
fi

# 14 — v0.16 audit: no project-specific glossary / coverage grind / overstrict Jobs retry dump
if rg -q 'company_number|vat_number' database/CLAUDE.md; then
  bad "project-specific company_number/vat_number glossary still in database/"
else
  note "no company_number/vat_number glossary"
fi
if rg -q '≥ 70%|>= 70%|--min=80|coverage ≥|coverage >=' tests/CLAUDE.md; then
  bad "coverage-percent targets still in tests/CLAUDE.md"
else
  note "no coverage-percent targets in tests"
fi
if rg -q 'MUST.*set retry policy|MUST set.*\$tries.*\$backoff.*\$timeout' app/Jobs/CLAUDE.md; then
  bad "Jobs still absolute-MUST full retry surface"
else
  note "Jobs retry policy softened"
fi
if rg -q 'MUST NOT contain business logic' app/Models/CLAUDE.md; then
  bad "Models still absolute-bans all business logic"
else
  note "Models allow attribute-local behaviour"
fi
if ! rg -q 'attribute-local|isPaid\(\)|small attribute' app/Models/CLAUDE.md; then
  bad "Models missing attribute-local allowance wording"
else
  note "Models attribute-local allowance present"
fi

# 15 — Livewire 4 (not v4.1+ alone); scar sections gone
if rg -q 'v4\.1\+' app/Livewire/CLAUDE.md; then
  bad "Livewire still says v4.1+ alone"
else
  note "Livewire version label not v4.1+"
fi
if ! rg -q 'Livewire 4' app/Livewire/CLAUDE.md; then
  bad "Livewire 4 mention missing"
else
  note "Livewire 4 mentioned"
fi
for scar in '## Auto-save patterns' '## Event chain contract' '## Double-refresh' '## Filter propagation'; do
  if rg -q -F -- "$scar" app/Livewire/CLAUDE.md; then
    bad "Livewire scar section still present: $scar"
  fi
done
note "Livewire scar sections removed"

# 16 — routes/API/mail/rules fixes
if rg -q "namespace\('App\\\\Http\\\\Controllers\\\\Api" routes/CLAUDE.md; then
  bad "routes still use string ->namespace() for API versioning"
else
  note "routes API versioning without string namespace()"
fi
if ! rg -q 'resource collections|plural.*resource' routes/CLAUDE.md; then
  bad "routes plural-URL rule not scoped to resource collections"
else
  note "routes plural scoped to resource collections"
fi
if ! rg -q "fail\(__\(" app/Rules/CLAUDE.md; then
  bad "Rules example missing \$fail(__('...'))"
else
  note "Rules fail uses __()"
fi
if ! rg -q 'web or Livewire request|web/Livewire' app/Mail/CLAUDE.md; then
  bad "Mail ShouldQueue not scoped to web/Livewire request path"
else
  note "Mail ShouldQueue scoped to request path"
fi

# 17 — class index + shouldBeStrict + lang + expand-contract migrations
for needle in 'Listener' 'Livewire' 'View Component' 'Feature (Pennant)' 'Resource (API)'; do
  if ! rg -q -F -- "$needle" app/CLAUDE.md; then
    bad "class naming index missing: $needle"
  fi
done
note "class naming index complete"
if ! rg -q 'shouldBeStrict' app/Models/CLAUDE.md app/Providers/CLAUDE.md; then
  bad "shouldBeStrict missing from Models/Providers"
else
  note "shouldBeStrict present"
fi
if [[ ! -f lang/CLAUDE.md ]]; then
  bad "lang/CLAUDE.md missing"
elif ! rg -q 'snake_case|__\(' lang/CLAUDE.md; then
  bad "lang/CLAUDE.md missing key layout / __() guidance"
else
  note "lang/CLAUDE.md present"
fi
if ! rg -q 'expand then contract|Expand.*Contract|zero-downtime' database/migrations/CLAUDE.md; then
  bad "expand/contract migration guidance missing"
else
  note "expand/contract migrations present"
fi
if ! rg -q 'soft.?delete|SoftDeletes' app/Models/CLAUDE.md || ! rg -q 'unique' app/Models/CLAUDE.md; then
  bad "soft-delete + unique footgun guidance incomplete"
else
  note "soft-delete unique guidance present"
fi

# 18 — no root agent rules (Laravel Boost)
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
