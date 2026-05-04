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
3. **Additional Cleanup** — Freeform instructions for other post-deployment work (e.g., removing legacy code paths, updating configuration, deleting unused files)

## Lifecycle

1. **Created** — During development, when a developer adds a Feature Flag, temporary migration, or identifies other cleanup work
2. **Committed** — The cleanup task file is committed with the PR that introduces the work
3. **Actioned** — After deployment succeeds, the cleanup tasks are reviewed and executed (typically the next sprint)
4. **Deleted** — Once all items in the cleanup task have been addressed, the file is deleted from the repository

## The `TODO: Cleanup Task` Comment Pattern

When cleanup requires more context than what fits in the cleanup task file alone, use inline code comments with the standardized pattern:

```php
// TODO: Cleanup Task - Details on what changes should be made
```

For multiline instructions:

```php
/*
 * TODO: Cleanup Task - After SomeFeature is removed:
 * - Change this default value from 'legacy' to 'new_format'
 * - Update the config to set 'feature_mode' => true
 * - Remove the fallback query below
 */
```

Adapt the comment syntax for the language of the file (e.g., `<!-- TODO: Cleanup Task -->` in Blade templates).

This pattern makes it easy to search the codebase for `TODO: Cleanup Task` to find all locations that need attention during cleanup.

---

See [Manage Cleanup Tasks](../how-tos/manage-cleanup-tasks.md) for practical guidance on creating and completing Cleanup Tasks.
