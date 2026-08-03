---
name: authoring-ai-content
description: "Use when creating, editing, or reviewing this project's AI guidance content — Laravel Boost guidelines (.ai/guidelines/**/*.blade.php), skills (.ai/skills/<name>/SKILL.md), the boost.json / mcp.json config, or the canyongbs/common publish and override system. Trigger whenever someone asks to add or change a guideline or skill, tweak what Boost injects into AGENTS.md, exclude a bundled/third-party guideline or skill, wire up an override (boost.override.json, .vscode/mcp.override.json, .ai/overrides/**), or understand how common:publish assembles a consuming app's AI content. Applies both inside the common package itself and inside apps that depend on canyongbs/common. Do not use for ordinary Laravel feature code, or for writing automated tests (use the writing-tests skill)."
license: Elastic-2.0
metadata:
    author: canyongbs
---

# Authoring AI Content (Guidelines, Skills & the Publish/Override System)

`canyongbs/common` owns a shared set of AI guidance — Boost **guidelines** and **skills** plus the `boost.json`/`mcp.json` config — and ships it to every consuming app through the `common:publish` Artisan command. Apps then **add to** or **override** that content locally. Understand which side you are on before editing.

## Two contexts, one flow

- **In the `common` package (the source of truth):** author guidelines and skills under `.ai/guidelines/` and `.ai/skills/`, and edit `boost.json` / `mcp.json`. Everything here is published to _every_ app. Changes are released through common's own PR.
- **In a consuming app:** never edit the published copies (`.ai/skills/`, `.ai/guidelines/`, `boost.json`, `.vscode/mcp.json`, `AGENTS.md` — all git-ignored and regenerated). Add or override content through the app's **override** inputs (`.ai/overrides/**`, `boost.override.json`, `.vscode/mcp.override.json`), then run `common:publish`.

If a change belongs in common but you are working from an app, follow the `local-common-development` skill to link a local checkout before editing common.

## Directory map (in the `common` package)

```
.ai/
  guidelines/                 # Boost guideline Blade templates → compiled into AGENTS.md
    foundation.blade.php
    pls.blade.php
    boost/core.blade.php
    laravel/core.blade.php
    livewire/core.blade.php
    pest/core.blade.php
    php/core.blade.php
    filament/filament.blade.php            # overrides Boost's first-party filament/filament guideline
    spatie/laravel-medialibrary.blade.php  # overrides Boost's spatie/laravel-medialibrary guideline
  skills/
    <skill-name>/
      SKILL.md                # required
      rules/ | reference/     # optional supporting files
boost.json                    # base Boost config (agents, packages, skills, guidelines toggles)
mcp.json                      # base MCP server config
src/Console/Commands/Publish.php        # the common:publish command
src/CommonBoostServiceProvider.php      # excludes/overrides bundled & third-party Boost content
```

## Guidelines

Guidelines are **Blade templates** that Laravel Boost compiles into the app's single `AGENTS.md`. They are always in the agent's context, so keep them short and high-signal — reserve long, on-demand material for a skill.

- **Key = path** relative to `.ai/guidelines/` without extension: `foundation`, `pls`, `boost/core`, `filament/filament`. These keys are what `boost.guidelines.exclude` and the service provider reference.
- Start every template with the `$assist` type hint and use its helpers so commands render correctly per app:
    ```blade
    @php
        /** @var \Laravel\Boost\Install\GuidelineAssist $assist */
    @endphp
    ```
    Common helpers: `{{ $assist->artisanCommand('...') }}`, `{{ $assist->composerCommand('...') }}`, `{{ $assist->nodePackageManagerCommand('...') }}`, and guards like `@if($assist->hasMcpEnabled())` / `@if($assist->hasSkillsEnabled())`.
- Wrap literal code samples that contain Blade-like syntax in `@verbatim ... @endverbatim`.
- Prefer pointing at a skill for depth ("follow the `writing-tests` skill") rather than inlining a lot of detail into a guideline.
- **`pls.blade.php` is authoritative for command execution:** these apps run in Docker, so every shell/Artisan/Composer/Node command is prefixed with `pls exec app`. Any command shown in a guideline or skill must respect that.

### Overriding a third-party (package) guideline

Boost injects first-party guidelines for detected packages (e.g. `filament/filament`, `spatie/laravel-medialibrary`). Common replaces these by:

1. Adding a replacement template at the **same key** (e.g. `.ai/guidelines/filament/filament.blade.php`).
2. Listing the key in `CommonBoostServiceProvider::$overridablePackageGuidelines`, which excludes Boost's original **only while** the replacement file exists.

## Skills

Skills are on-demand knowledge modules loaded when their `description` matches the task. Use a skill (not a guideline) for anything long, procedural, or domain-specific.

- One folder per skill: `.ai/skills/<name>/SKILL.md`, with optional `rules/` and `reference/` subfolders for supporting docs the SKILL.md links to.
- **Keep a multi-file skill's index in sync with its `rules/` files.** When a `SKILL.md` acts as an index over `rules/` (or `reference/`) files — e.g. a "Quick Reference" that summarises each file — that index is the map the agent navigates by, so it must always match the files on disk. Whenever you add, remove, rename, or restructure a rule file, update the `SKILL.md` in the **same change**: add or drop its entry, keep any section numbering contiguous, make each summary bullet reflect the file's actual headings, and fix cross-references (e.g. "§N") plus the frontmatter `description`. A stale index that points at a deleted file or misdescribes a rule sends the agent to the wrong place.
- Required YAML frontmatter (common's own skills use these values):
    ```yaml
    ---
    name: <kebab-case-name> # must match the folder name
    description: 'Use when …' # the activation trigger — see below
    license: Elastic-2.0
    metadata:
        author: canyongbs
    ---
    ```
- **The `description` is the most important line.** It decides whether the agent activates the skill. Write it as trigger conditions: when to use, what tasks/files it covers, and explicit "do not use for …" boundaries to avoid overlap with sibling skills. Study `writing-tests` and `local-common-development` for the expected shape.
- No `boost.json` entry is needed for a common-authored skill — presence in `.ai/skills/` is what publishes and activates it. The `boost.json` `skills` array is only for enabling **Boost's bundled** skills.
- To drop a common-authored skill from a **single** app, add its `name` to `boost.skills.exclude` — the same exclude list used for Boost's bundled skills. `common:publish` skips copying any common skill whose name appears there, so an app can opt out of shared skills it does not need (for example, an alpha app that does not yet use feature flags).

## The `common:publish` command

`php artisan common:publish` (in apps: `pls exec app php artisan common:publish`) assembles each app's AI content:

1. **Config merge (deep):** `boost.json` = base `boost.json` + app `boost.override.json`; `.vscode/mcp.json` = base `mcp.json` + app `.vscode/mcp.override.json`. Objects merge recursively; **lists are concatenated and de-duplicated** (so overrides add to arrays, they don't replace them).
2. **AI content overlay:** for each type (`skills`, `guidelines`) it wipes the output dir, copies common's `.ai/<type>`, then copies the app's `.ai/overrides/<type>` on top. Files copied by **relative path**, so an override at the same relative path **wins**.
3. **Scaffolds override dirs:** ensures `.ai/overrides/skills/` and `.ai/overrides/guidelines/` exist (with a `.gitkeep`).
4. **Manages `.gitignore`:** maintains a marked block ignoring the generated artifacts (`/boost.json`, `/.vscode/mcp.json`, `/AGENTS.md`, `/.github/skills/`, `/.ai/skills/`, `/.ai/guidelines/`).

Boost then compiles the published guidelines into `AGENTS.md`. Because the published outputs are git-ignored and regenerated, **only the inputs are committed**: in common, the `.ai/` sources and `boost.json`/`mcp.json`; in an app, the `.ai/overrides/**` files and the `*.override.json` files.

## App-side overrides — how to add vs. overwrite

| Goal (in a consuming app)                                                                                            | Where to put it                                                                                              |
| -------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------ |
| Add an app-only skill                                                                                                | `.ai/overrides/skills/<name>/SKILL.md`                                                                       |
| Add an app-only guideline                                                                                            | `.ai/overrides/guidelines/<key>.blade.php` (or `.md`)                                                        |
| Replace one of common's skills/guidelines                                                                            | Same **relative path** under `.ai/overrides/<type>/…` as the common file — the override copies last and wins |
| Change Boost config (enable/disable a skill, add a package, set `boost.guidelines.exclude` / `boost.skills.exclude`) | `boost.override.json`                                                                                        |
| Change MCP servers                                                                                                   | `.vscode/mcp.override.json`                                                                                  |

Remember array-merge semantics: to _disable_ something you exclude it via the appropriate `*.exclude` array, since arrays merge additively rather than being replaced.

Excluding a **common-authored** skill works the same way: `boost.skills.exclude` now also drops shared skills at publish time (`common:publish` skips copying them), not only Boost's bundled ones.

## Removing bundled/third-party content across all apps

`CommonBoostServiceProvider` controls what common strips from Boost for every app:

- `$excludedGuidelines` — Boost guideline keys removed everywhere (e.g. `deployments`).
- `$excludedSkills` — Boost bundled/third-party skill keys removed everywhere, typically because a common skill supersedes them (e.g. `pest-testing` → `writing-tests`).
- `$overridablePackageGuidelines` — package guideline keys that are excluded only while common ships a replacement template (see the guideline override section above).

Edit these lists when a change must apply to **every** app; use an app's `boost.override.json` when it applies to **one** app.

## After editing — always regenerate

- **In common:** run the test suite / checks, then release through common's PR. Apps pick it up on their next `composer update` + `common:publish`.
- **In an app (or a linked local common):** run `common:publish` so `AGENTS.md`, `.ai/skills`, `.ai/guidelines`, `boost.json`, and `.vscode/mcp.json` reflect the change. Skipping this leaves the app's AI content stale.

## Do / Don't

- **Do** decide guideline vs. skill deliberately: guideline for short, always-relevant rules compiled into `AGENTS.md`; skill for long, on-demand, domain-specific knowledge with a precise activation `description`.
- **Do** keep a skill's `name` identical to its folder, and give it explicit "do not use for …" boundaries.
- **Do** author shared content in `common` and app-specific content in the app's overrides.
- **Don't** edit generated files in an app (`.ai/skills/`, `.ai/guidelines/`, `boost.json`, `.vscode/mcp.json`, `AGENTS.md`) — they are overwritten by `common:publish`.
- **Don't** expect an override array to replace a base array — merges are additive; exclude via the `*.exclude` config instead.
- **Don't** forget `pls exec app` for commands in these Docker-based apps.
