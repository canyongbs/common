# Manage Cleanup Tasks

This guide covers creating, updating, and completing Cleanup Tasks.

For background on why Cleanup Tasks exist, see [Cleanup Tasks](../explanations/cleanup-tasks.md).

---

## Creating a Cleanup Task

There are three commands that can create or interact with cleanup task files:

### Standalone: `make:cleanup`

Create a cleanup task file directly:

```bash
php artisan make:cleanup my_feature_cleanup
```

If no name is provided, you will be prompted interactively:

```bash
php artisan make:cleanup
```

This creates a file at `.cleanup-tasks/YYYY_MM_DD_my_feature_cleanup.md`.

### With a Feature Flag: `make:ff`

Create a Feature Flag and associate it with a cleanup task in one step:

```bash
php artisan make:ff SomeFeature
```

After creating the Feature Flag class, the command will interactively prompt you to:

1. **Create a new cleanup task** — suggests a name based on the feature flag
2. **Add to an existing cleanup task** — select from existing files in `.cleanup-tasks/`
3. **Skip** — do not create or modify a cleanup task

The selected Feature Flag class will be automatically added to the `## Feature Flags` section of the cleanup task.

To skip the prompt entirely:

```bash
php artisan make:ff SomeFeature --no-cleanup
```

### With a Temporary Migration: `make:tmp-migration`

Create a temporary (one-time) migration and associate it with a cleanup task:

```bash
php artisan make:tmp-migration backfill_user_preferences
```

This automatically prefixes the migration name with `tmp_`, creating a file like `YYYY_MM_DD_HHMMSS_tmp_backfill_user_preferences.php`. The command then prompts for cleanup task association (same interactive flow as `make:ff`).

The migration file path is automatically added to the `## Temporary Migrations` section.

To skip the cleanup prompt:

```bash
php artisan make:tmp-migration backfill_user_preferences --no-cleanup
```

Supports the `--module` flag for modular projects:

```bash
php artisan make:tmp-migration backfill_user_preferences --module=student-data-model
```

---

## Cleanup Task File Structure

Files are created in `.cleanup-tasks/` with the format `YYYY_MM_DD_<name>.md`:

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

- Remove the legacy fallback in `app/Services/UserService.php`
- Delete `resources/views/old-dashboard.blade.php` after confirming no routes reference it
```

### Sections

- **Feature Flags** — List the fully qualified class names of Feature Flag classes to remove
- **Temporary Migrations** — List the relative file paths of `tmp_` migrations to delete
- **Additional Cleanup** — Freeform markdown describing any other cleanup work (file deletions, code moves, config changes, etc.)

### YAML Frontmatter

- `title` — Human-readable name for the cleanup task
- `created` — Date the cleanup task was created (YYYY-MM-DD format)

---

## Documenting Additional Cleanup in Code

When cleanup requires context that lives near the code being changed, add inline comments using the standardized pattern:

```php
// TODO: Cleanup Task - Details on what changes should be made
```

Or for more complex instructions:

```php
/*
 * TODO: Cleanup Task - After SomeFeature is removed:
 * - Change this default value from 'legacy' to 'new_format'
 * - Remove the fallback query below
 */
```

These comments supplement the cleanup task file — they provide implementation context at the point of change.

When you add `TODO: Cleanup Task` comments in code, **you must note their existence in the cleanup task file's `## Additional Cleanup` section**. Include what to search for so the person doing the cleanup knows to look for them. For example:

```markdown
## Additional Cleanup

- Search for `TODO: Cleanup Task` in the codebase for inline cleanup instructions
- The legacy dashboard view can be deleted once the feature flag is removed
```

This ensures that inline comments are not overlooked — the cleanup task file serves as the single entry point, and it should always point to any related code comments.

---

## Completing a Cleanup Task

After the deployment containing your changes has successfully run across all environments:

1. Open the cleanup task file in `.cleanup-tasks/`
2. Work through each item:
    - **Feature Flags**: Remove all references to the flag, keep the active-path code, delete inactive-path code, delete the flag class, delete the activation migration
    - **Temporary Migrations**: Delete the migration file
    - **Additional Cleanup**: Follow the instructions provided
3. Search for `TODO: Cleanup Task` in the codebase to find any related inline comments
4. Delete the cleanup task file
5. Commit all changes as a single cleanup PR

---

## Tips

- **One cleanup task per logical unit of work** — If a feature introduces a flag AND a temporary migration, they should share one cleanup task file
- **Add to existing tasks** — If you're adding to an in-progress feature that already has a cleanup task, select "Add to existing" when prompted
- **Keep instructions actionable** — Write cleanup instructions as if someone unfamiliar with the feature will execute them
- **Don't duplicate** — If using `TODO: Cleanup Task` comments in code, keep the cleanup task file's "Additional Cleanup" section as a summary pointing to the relevant areas, not a full repeat of the inline comments
