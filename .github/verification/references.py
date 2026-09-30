"""Validate canonical rule pointers, including their installed agent filenames."""

from pathlib import Path
import re

root = Path(__file__).resolve().parents[2]
rules = sorted(root.rglob("CLAUDE.md"))
rules = [path for path in rules if "vendor" not in path.parts and ".git" not in path.parts]
errors = []

for rule in rules:
    text = rule.read_text()
    for pointer in re.findall(r"(?:app|bootstrap|config|database|lang|resources|routes|tests)/[\w/.-]*CLAUDE\.md", text):
        if not (root / pointer).is_file():
            errors.append(f"{rule.relative_to(root)}: missing {pointer}")
    if text.count("```") % 2:
        errors.append(f"{rule.relative_to(root)}: unbalanced code fences")

loader = (root / "CLAUDE.md").read_text()
for name in ("CLAUDE.md", "AGENTS.md", "GEMINI.md", ".cursorrules", ".windsurfrules", ".clinerules"):
    if name not in loader:
        errors.append(f"Root loader does not resolve {name}")

if errors:
    raise SystemExit("\n".join(errors))

print(f"Rule references and fences passed ({len(rules)} rule files).")
