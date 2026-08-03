---
name: code-style-and-static-analysis
description: "Use when running or fixing code style and static analysis for a Canyon GBS app — the `composer format` / `lint` / `checks` scripts and the tools behind them (PHP CS Fixer, Prettier including Blade, PHPStan/Larastan, Rector, Laravel IDE Helper). Trigger whenever you need to format code, resolve a style or static-analysis failure, run the pre-completion checks before finishing a change, or regenerate IDE helper files after model or schema changes. Do not use for: writing tests (use writing-tests) or authoring AI guidelines and skills (use authoring-ai-content). For editor/IDE setup, see the app's local-setup docs rather than this skill."
license: Elastic-2.0
metadata:
    author: canyongbs
---

# Code Style & Static Analysis

Every change must pass the same checks CI runs before it is considered complete. Apply style fixes with `composer format`, then run `composer lint` (PHPStan/Larastan) and resolve **everything** it reports — never leave violations for CI. Because `composer checks` is just `format-dryrun` + `lint`, running `format` (which writes the fixes) and then `lint` covers the same ground without re-checking formatting you just applied — do not run `format` followed by `checks`.

Run every command through the project's command-execution guideline (these apps run inside the `app` container).

## Commands

- `composer format` — auto-applies fixes: Rector, then PHP CS Fixer, then Prettier (write mode).
- `composer format-dryrun` — reports the same without changing files.
- `composer lint` — PHPStan/Larastan static analysis.
- `composer checks` — `format-dryrun` + `lint`; this is the gate CI enforces.
- `composer generate-helper-files` — regenerates Laravel IDE Helper files; run after changing models or schema so static analysis stays accurate.

## The tools

- **PHP CS Fixer** (`php-cs-fixer.php`) — PHP code style.
- **Prettier** with the Blade plugin (`.prettierrc.json`) — CSS, JS, Vue, and `*.blade.php` formatting.
- **PHPStan / Larastan** (`phpstan.neon.dist`) — static analysis; it boots the service container, so it understands Laravel types.
- **Rector** (`rector.php`) — automated refactors and framework upgrades applied as part of `format`.
- **Laravel IDE Helper** — generates autocompletion/meta files that keep PHPStan accurate for models and facades.

## Resolving failures

- **Style** (PHP CS Fixer / Prettier): run `composer format` to auto-fix.
- **Static analysis** (PHPStan/Larastan): fix the actual type problem — add precise type hints, generics, or PHPDoc. Do not silence errors with blanket ignores or `mixed`; a baseline or ignore is a last resort and should be deliberate.
- **Model magic not recognised**: run `composer generate-helper-files` and re-run `composer lint` before investigating further.

---

Related: the `code-style` guideline (the always-on reminder to run these checks) and the `writing-tests` skill.
