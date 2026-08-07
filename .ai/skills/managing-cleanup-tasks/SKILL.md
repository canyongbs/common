---
name: managing-cleanup-tasks
description: 'Use when creating, updating, or completing a cleanup task file in `.cleanup-tasks/` — the tracked record of post-deployment cleanup work (Feature Flags to remove, temporary `tmp_` migrations to delete, and other follow-up). Trigger whenever you run or reason about `make:cleanup`, `make:ff`, or `make:tmp-migration` and their cleanup prompts, add an entry to a cleanup task, write an inline `TODO: Cleanup Task` comment, or perform the cleanup after a deploy. Covers the file format and sections, the three entry types, the inline comment convention (stable root plus unique tag), and what must NOT go in the Additional Cleanup section. Do not use for: creating the Feature Flag class (use `managing-feature-flags`) or writing the migration (use `writing-data-migrations`); this skill is about the cleanup tracking itself.'
user-invocable: false
license: Elastic-2.0
metadata:
    author: canyongbs
---

# Managing Cleanup Tasks

A **cleanup task** is a tracked markdown file in `.cleanup-tasks/` that records work to do **after** a deployment succeeds: Feature Flags to remove, temporary (`tmp_`) migrations to delete, and any other follow-up. It is the single, searchable source of truth for post-deploy cleanup, replacing ad-hoc notes in PR descriptions and tickets. See the `zero-downtime` guideline for why flags and temporary migrations exist.

Cleanup tasks are created alongside the things they track and deleted once every item is done.

Run every command through the `pls` guideline (these apps run inside the `app` container).

## Creating a cleanup task

Standalone (omit the name to be prompted):

```bash
php artisan make:cleanup my_feature_cleanup
```

This writes `.cleanup-tasks/YYYY_MM_DD_my_feature_cleanup.md`.

More often a cleanup task is created or extended through the prompts on `make:ff` (see `managing-feature-flags`) and `make:tmp-migration` (see `writing-data-migrations`): each offers to create a new task or add to an existing one, and files the flag class or migration path into the correct section automatically. Keep **one cleanup task per logical unit of work** — if a feature adds a flag and a temporary migration, they share one file.

## File structure

```markdown
---
title: Some Feature Cleanup
created: 2026-04-30
---

## Feature Flags

- App\Features\SomeFeature

## Temporary Migrations

- database/migrations/2026_04_30_120000_tmp_backfill_user_preferences.php

## Additional Cleanup

- Search for `TODO: Cleanup Task (some-feature)` and follow the instructions at each site.
- Make the `addons.exampleFeature` request field required once the external API is updated.
```

- **Feature Flags** — fully-qualified flag class names to remove.
- **Temporary Migrations** — relative paths of `tmp_` migrations to delete.
- **Additional Cleanup** — freeform work with no natural home in code (see the strict rules below).
- **Frontmatter** — `title` (human-readable) and `created` (`YYYY-MM-DD`).

## What must NOT go in Additional Cleanup

Additional Cleanup is the most misused part of a cleanup task. Keep it small and high-level.

- **Do not restate obvious Feature Flag removals.** Removing a flag used in an `if` / ternary / `match` means keeping the active branch and deleting the inactive one — that is always implied and must never be written out.
- **Do not include file paths or line numbers.** They bloat the file, sit far from the code they describe, and go stale before the cleanup is actioned.
- **Do not paste implementation detail** that belongs next to the code.

Instead, **co-locate** non-obvious instructions at the change site with an inline comment (below) and let Additional Cleanup just point to the tag to search for. Reserve prose here for work that genuinely has no code site — e.g. "delete the `old-dashboard` config", "make a request field required after the API ships".

## The inline cleanup-comment convention

Mark non-obvious change sites with a comment built from a **stable root plus a unique tag**:

```php
// TODO: Cleanup Task (some-feature): <what to do here>
```

- `TODO: Cleanup Task` — the stable root; grep it to find **every** cleanup site in the codebase.
- `(some-feature)` — a short, unique tag identifying one cleanup task (typically the feature or flag name); grep it to find every site for **that** task.

Multiline instructions:

```php
/*
 * TODO: Cleanup Task (some-feature): after SomeFeature is removed:
 * - change this default from 'legacy' to 'new_format'
 * - remove the fallback query below
 */
```

Adapt the comment syntax to the language — e.g. `{{-- TODO: Cleanup Task (some-feature): ... --}}` in Blade, `<!-- TODO: Cleanup Task (some-feature): ... -->` in HTML or Markdown.

The cleanup task file then needs only one line pointing at the tag; the details live where the change is made.

## Lifecycle

1. **Created** during development, when a flag, temporary migration, or other cleanup is introduced.
2. **Committed** with the PR that introduces the work.
3. **Actioned** after the deploy succeeds everywhere (usually the next release).
4. **Deleted** once every item is done.

## Completing a cleanup task

1. Work each entry: remove Feature Flags (see `managing-feature-flags`), delete the `tmp_` migration files, and do the Additional Cleanup items.
2. `grep` for `TODO: Cleanup Task (<tag>)` and resolve each site it finds.
3. Delete the cleanup task file and commit the removal as a single cleanup PR.

---

Related: `managing-feature-flags`, `writing-data-migrations`.
