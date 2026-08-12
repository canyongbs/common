---
name: authoring-agent-guidance
description: "Use when creating, editing, or reviewing this project's agent guidance — Laravel Boost guidelines (.ai/guidelines/**/*.blade.php), skills (.ai/skills/<name>/SKILL.md), the boost.json / mcp.json config, or the canyongbs/common publish and override system. Trigger whenever someone asks to add or change a guideline or skill, tweak what Boost injects into AGENTS.md, exclude a bundled/third-party guideline or skill, wire up an override (boost.override.json, .vscode/mcp.override.json, .ai/overrides/**), or understand how common:publish assembles a consuming app's guidance. Applies both inside the common package itself and inside apps that depend on canyongbs/common. Do not use for ordinary Laravel feature code, or for writing automated tests (use the writing-tests skill)."
user-invocable: false
license: Elastic-2.0
metadata:
    author: canyongbs
---

# Authoring Agent Guidance (Guidelines, Skills & the Publish/Override System)

`canyongbs/common` owns a shared set of agent guidance — Boost **guidelines** and **skills** plus the `boost.json`/`mcp.json` config — and ships it to every consuming app through the `common:publish` Artisan command. Apps then **add to** or **override** that guidance locally. Understand which side you are on before editing.

## Two contexts, one flow

- **In the `common` package (the source of truth):** author guidelines and skills under `.ai/guidelines/` and `.ai/skills/`, and edit `boost.json` / `mcp.json`. Everything here is published to _every_ app. Changes are released through common's own PR.
- **In a consuming app:** never edit the published copies (`.ai/skills/`, `.ai/guidelines/`, `boost.json`, `.vscode/mcp.json`, `AGENTS.md` — all git-ignored and regenerated). Add or override guidance through the app's **override** inputs (`.ai/overrides/**`, `boost.override.json`, `.vscode/mcp.override.json`), then run `common:publish`.

If a change belongs in common but you are working from an app, follow the `local-common-development` skill to link a local checkout before editing common.

## Where things live (in the `common` package)

```
.ai/
  guidelines/<key>.blade.php      # Boost guideline templates → compiled into AGENTS.md
                                  #   key = path minus extension; may be nested, e.g. `laravel/core`
  skills/<name>/SKILL.md          # one skill per folder (+ optional rules/ or reference/ supporting files)
boost.json                        # base Boost config (agents, packages, skills/guidelines toggles)
mcp.json                          # base MCP server config
src/Console/Commands/Publish.php         # the common:publish command
src/CommonBoostServiceProvider.php       # excludes/overrides bundled & third-party Boost content
```

Don't rely on a hardcoded file list — run `ls .ai/guidelines` and `ls .ai/skills` for the current set before adding or referencing one.

## Guidelines

Guidelines are **Blade templates** that Laravel Boost compiles into the app's single `AGENTS.md`. They are always in the agent's context, so keep them short and high-signal — reserve long, on-demand material for a skill.

- **Key = path** relative to `.ai/guidelines/` without extension, and may be nested (e.g. `pls`, `laravel/core`, `pest/core`). These keys are what `boost.guidelines.exclude` and the service provider reference.
- To drop a common-authored guideline from a **single** app, add its key to `boost.guidelines.exclude` — `common:publish` then skips copying it, so it never reaches `AGENTS.md`.
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
- **Index by capability, not by filename.** When a `SKILL.md` acts as an index over `rules/` (or `reference/`) files — e.g. a "Quick Reference" — each entry must **summarise what that file teaches**, giving the agent a reason to open it; a bare list that only mirrors the filenames adds no navigational value and silently drifts. Whatever form the index takes, keep it in sync in the **same change** as the files: add or drop entries, keep any section numbering contiguous, make each summary reflect the file's actual headings, and fix cross-references (e.g. "§N") plus the frontmatter `description`. A stale index that points at a deleted file or misdescribes a rule sends the agent to the wrong place.
- Required YAML frontmatter (common's own skills use these values):
    ```yaml
    ---
    name: <kebab-case-name> # must match the folder name
    description: 'Use when …' # the activation trigger — see below
    user-invocable: false # for model-only skills; hides from the `/` menu (see invocation axes below)
    license: Elastic-2.0
    metadata:
        author: canyongbs
    ---
    ```
- **Two independent invocation axes — set them by who the skill is for.** `user-invocable` (default `true`) controls whether the skill shows in the `/` slash-command menu; `disable-model-invocation` (default `false`) controls whether the agent may auto-load it by `description` match. Choose per skill:
    - _Model-only convention/guideline skill_ (the common case today — the agent loads it automatically and no human runs it): set `user-invocable: false` and leave model invocation on. This is why every current common skill sets it.
    - _Human-invoked "run this" skill_ (a person deliberately triggers it via `/` and the model should **not** pull it in on its own): keep `user-invocable: true` and set `disable-model-invocation: true`. That opts the skill out of automatic `description`-match loading, leaving the `/` slash command as its only trigger.
    - A skill may of course be both model- and human-invocable — then set neither. Decide deliberately rather than copying a sibling.
- **The `description` is the most important line.** It decides whether the agent activates the skill. Write it as trigger conditions: when to use, what tasks/files it covers, and explicit "do not use for …" boundaries to avoid overlap with sibling skills. Study `writing-tests` and `local-common-development` for the expected shape.
- **Frontmatter must satisfy the [Agent Skills spec](https://agentskills.io/specification) — the CLI silently drops a skill that violates it.** The Copilot CLI loader enforces the spec's hard constraints, and a violation makes the **whole skill vanish** at load with only a brief startup notice — no error tying it to the offending field. The limits are measured in **Unicode code points** (not bytes): `description` non-empty and ≤ **1024**, `name` ≤ **64** (plus the kebab-case/folder-match rules already noted above), and `compatibility` ≤ **500** if present. Keep `description` comfortably under 1024; when trimming, preserve the trigger keywords and the "do not use for …" boundaries (that is how the over-length `writing-data-migrations` and `structuring-filament-code` descriptions were cut to ~1016). These spec limits are distinct from the `license` / `metadata.author` values shown above, which are **common's own convention, not spec** — an app authoring its own skill need not adopt them, but it must still obey the spec limits.
- **Enforcement stops at common's own skills.** A spec-compliance Pest guard (`tests/SkillSpecComplianceTest.php`, with the house-convention checks in `tests/SkillConventionsTest.php`) validates every skill under `.ai/skills/`, so an over-length or malformed common skill fails CI. **App-authored skills (`.ai/overrides/skills/**` in a consuming app) are NOT covered by that guard** — the app is responsible for keeping its own local skills within the spec limits, or they silently disappear with no test to catch it.
- No `boost.json` entry is needed for a common-authored skill — presence in `.ai/skills/` is what publishes and activates it. The `boost.json` `skills` array is only for enabling **Boost's bundled** skills.
- To drop a common-authored skill from a **single** app, add its `name` to `boost.skills.exclude` — the same exclude list used for Boost's bundled skills. `common:publish` skips copying any common skill whose name appears there, so an app can opt out of shared skills it does not need (for example, an alpha app that does not yet use feature flags).

## The `common:publish` command

`php artisan common:publish` (in apps: `pls exec app php artisan common:publish`) assembles each app's agent guidance:

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

Excluding **common-authored** content works the same way: `boost.skills.exclude` drops shared skills and `boost.guidelines.exclude` drops shared guidelines at publish time (`common:publish` skips copying them), not only Boost's bundled content. `CommonBoostServiceProvider` reads these arrays from the app's `boost.override.json` into `config('boost.*.exclude')`, so an app needs no extra config wiring.

## Removing bundled/third-party content across all apps

`CommonBoostServiceProvider` controls what common strips from Boost for every app:

- `$excludedGuidelines` — Boost guideline keys removed everywhere (e.g. `deployments`).
- `$excludedSkills` — Boost bundled/third-party skill keys removed everywhere, typically because a common skill supersedes them (e.g. `pest-testing` → `writing-tests`).
- `$overridablePackageGuidelines` — package guideline keys that are excluded only while common ships a replacement template (see the guideline override section above).

Edit these lists when a change must apply to **every** app; use an app's `boost.override.json` when it applies to **one** app.

## After editing — always regenerate

- **In common:** run the test suite / checks, then release through common's PR. Apps pick it up on their next `composer update` + `common:publish`.
- **In an app (or a linked local common):** run `common:publish` so `AGENTS.md`, `.ai/skills`, `.ai/guidelines`, `boost.json`, and `.vscode/mcp.json` reflect the change. Skipping this leaves the app's agent guidance stale.

## Do / Don't

- **Do** decide guideline vs. skill deliberately: guideline for short, always-relevant rules compiled into `AGENTS.md`; skill for long, on-demand, domain-specific knowledge with a precise activation `description`.
- **Do** keep a skill's `name` identical to its folder, and give it explicit "do not use for …" boundaries.
- **Do** author shared content in `common` and app-specific content in the app's overrides.
- **Don't** edit generated files in an app (`.ai/skills/`, `.ai/guidelines/`, `boost.json`, `.vscode/mcp.json`, `AGENTS.md`) — they are overwritten by `common:publish`.
- **Don't** expect an override array to replace a base array — merges are additive; exclude via the `*.exclude` config instead.
- **Don't** forget `pls exec app` for commands in these Docker-based apps.

---

Related: `local-common-development` (working against a local, editable checkout of common).
