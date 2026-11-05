---
name: managing-cleanup-tasks
description: 'Use when creating, updating, or completing a cleanup task file in `.cleanup-tasks/` — the tracked record of post-deployment cleanup work (Feature Flags to remove, temporary `tmp_` migrations to delete, and other follow-up). Trigger whenever you run or reason about `make:cleanup`, `make:ff`, or `make:tmp-migration` and their cleanup prompts, add an entry to a cleanup task, decide or change whether a task is `ready`, write an inline `TODO: Cleanup Task` comment, or perform the cleanup after a deploy. Covers the file format and sections, the `ready` frontmatter flag, the three entry types, the inline comment convention (stable root plus unique tag), and what must NOT go in the Additional Cleanup section. Do not use for: creating the Feature Flag class (use `managing-feature-flags`) or writing the migration (use `writing-data-migrations`); this skill is about the cleanup tracking itself.'
user-invocable: false
license: Elastic-2.0
metadata:
    author: canyongbs
---

# Managing Cleanup Tasks

A **cleanup task** is a tracked markdown file in `.cleanup-tasks/` that records work to do **after** a deployment succeeds: Feature Flags to remove, temporary (`tmp_`) migrations to delete, and any other follow-up. It is the single, searchable source of truth for post-deploy cleanup, replacing ad-hoc notes in PR descriptions and tickets. See the `zero-downtime` guideline for why flags and temporary migrations exist.

Cleanup tasks are created alongside the things they track, marked **ready** once nothing depends on them any more, and deleted once every item is done.

Run every command through the `pls` guideline (these apps run inside the `app` container).

## Creating a cleanup task

Standalone (omit the name to be prompted):

```bash
php artisan make:cleanup my_feature_cleanup
```

This writes `.cleanup-tasks/YYYY_MM_DD_my_feature_cleanup.md`.

More often a cleanup task is created or extended through the prompts on `make:ff` (see `managing-feature-flags`) and `make:tmp-migration` (see `writing-data-migrations`): each offers to create a new task or add to an existing one, and files the flag class or migration path into the correct section automatically. Keep **one cleanup task per logical unit of work** — if a feature adds a flag and a temporary migration, they share one file.

When a **new** task is created, all three commands ask _"Can this cleanup task be actioned once this change is released?"_ (default **No**). Pass `--ready` to answer Yes without the prompt. Adding to an existing task keeps its current value unless `--ready` is passed. See [Marking a cleanup task ready](#marking-a-cleanup-task-ready).

## File structure

```markdown
---
title: Some Feature Cleanup
created: 2026-04-30
ready: false
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
- **Frontmatter** — `title` (human-readable), `created` (`YYYY-MM-DD`), and `ready` (`true` or `false`).

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

## Marking a cleanup task ready

`ready` tells whoever actions cleanup whether anything still depends on what the task removes.

- **`ready: false`** — work in progress still relies on the flag or migration (for example, later pull requests in a stack still gate code behind the flag, or the flag has not been activated yet). Do not action it, even after it has been released.
- **`ready: true`** — the pull request that makes the cleanup safe has merged. Action it once a release containing that pull request has deployed everywhere.
- **No `ready` key** — a task written before the field existed. Treat it as ready.

Set `ready: true` **in the pull request that makes the cleanup safe**:

- A change delivered in a **single pull request** (the flag is created and activated in the same PR): answer Yes when the generator asks, or pass `--ready`.
- A feature delivered over **several pull requests**: leave it `false` when the flag is introduced, and change it to `true` by hand in the pull request that activates the flag (usually the last one). If a later pull request adds more items to the task, keep it `false` until that final pull request.

Never mark a task ready while code on `main` or in an open pull request still depends on what it removes.

## Lifecycle

1. **Created** during development, when a flag, temporary migration, or other cleanup is introduced. New tasks default to `ready: false`.
2. **Committed** with the PR that introduces the work.
3. **Marked ready** in the PR that makes the cleanup safe (often the same PR).
4. **Actioned** after a release containing the ready task has deployed everywhere (usually the next release). Tasks that are not ready are skipped.
5. **Deleted** once every item is done.

## Completing a cleanup task

1. Confirm the task is `ready: true` (or has no `ready` key). If it is `ready: false`, stop — something still depends on it.
2. Work each entry: remove Feature Flags (see `managing-feature-flags`), delete the `tmp_` migration files, and do the Additional Cleanup items.
3. `grep` for `TODO: Cleanup Task (<tag>)` and resolve each site it finds.
4. Delete the cleanup task file and commit the removal as a single cleanup PR.

---

Related: `managing-feature-flags`, `writing-data-migrations`.
