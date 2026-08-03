# Cleanup Tasks

Cleanup Tasks are structured markdown files that track post-deployment cleanup work. They provide a single, searchable source of truth for what needs to be cleaned up after a deployment has successfully completed.

## Why Cleanup Tasks Exist

In a zero-downtime deployment system, Feature Flags and temporary data migrations are introduced to safely bridge the gap between code deployment and migration completion. Once those deployments succeed, the flags, temporary migrations, and any related scaffolding need to be removed.

Previously, this cleanup was tracked manually in pull request descriptions and Jira tickets—a process that was tedious, error-prone, and often resulted in cleanup being forgotten or delayed.

Cleanup Tasks solve this by:

- Providing a **consistent, machine-searchable location** (`.cleanup-tasks/`) for all cleanup work
- Being **created automatically** alongside the entities they track (via `make:ff` and `make:tmp-migration` commands)
- Supporting **freeform instructions** for cleanup work that doesn't fit neatly into a Feature Flag or migration category

## What Goes in a Cleanup Task

A cleanup task file can track three types of work:

1. **Feature Flags** — Class references to Feature Flag classes that should be removed once active in production
2. **Temporary Migrations** — File paths of `tmp_`-prefixed migrations that should be deleted after they've run across all environments
3. **Additional Cleanup** — Freeform instructions for post-deployment work that has no home in code. This section is deliberately small: it must not restate obvious Feature Flag removals or list file paths and line numbers (see the comment pattern below)

## Lifecycle

1. **Created** — During development, when a developer adds a Feature Flag, temporary migration, or identifies other cleanup work
2. **Committed** — The cleanup task file is committed with the PR that introduces the work
3. **Actioned** — After deployment succeeds, the cleanup tasks are reviewed and executed (typically the next sprint)
4. **Deleted** — Once all items in the cleanup task have been addressed, the file is deleted from the repository

## The `TODO: Cleanup Task` Comment Pattern

Cleanup that is more than an obvious Feature Flag removal is documented **at the change site**, not in the cleanup task file. Co-locating the instructions with the code keeps them accurate — file paths and line numbers drift, co-located comments do not — and keeps the cleanup task file small.

Mark each non-obvious change site with a comment built from a **stable root plus a unique tag**:

```php
// TODO: Cleanup Task (some-feature): details on what to change here
```

For multiline instructions:

```php
/*
 * TODO: Cleanup Task (some-feature): after SomeFeature is removed:
 * - change this default value from 'legacy' to 'new_format'
 * - remove the fallback query below
 */
```

Adapt the comment syntax for the language of the file (e.g., `{{-- TODO: Cleanup Task (some-feature): ... --}}` in Blade templates).

The stable `TODO: Cleanup Task` root lets you find **every** cleanup site in the codebase; the `(some-feature)` tag — a short, unique identifier for one cleanup task — narrows the search to a single task. The cleanup task file then only needs to name the tag to search for, instead of duplicating instructions or referencing volatile file paths and line numbers.

Obvious Feature Flag removals need no comment at all: when a flag guards an `if`, ternary, or `match`, cleanup simply means keeping the active branch and discarding the inactive one.

---

See the `managing-cleanup-tasks` skill for practical guidance on creating and completing Cleanup Tasks.
