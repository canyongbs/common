---
name: writing-data-migrations
description: 'Use when writing or altering a Laravel migration that changes data (not only schema) in a Canyon GBS app — data back-fills, transformations, clean-ups, seeding, or activating a Feature Flag. Trigger whenever you create a migration with `make:migration` or `make:tmp-migration`, decide between a permanent and a temporary (`tmp_`) migration, need it idempotent and safe to re-run, add a required `down()`, or target the landlord versus tenant databases. Covers permanent-migration rules (DB facade only, no removable classes), temporary `tmp_` migration rules and their cleanup task, and running migrations. Do not use for: permission seeding (use `creating-permissions`), creating the Feature Flag class (use `managing-feature-flags`), cleanup task file mechanics (use `managing-cleanup-tasks`), or a schema-only migration — adding/altering/dropping columns or tables (optionally Feature-Flag-guarded) with no data back-fill or transformation; write those as plain, unconditional migrations without existence guards.'
user-invocable: false
license: Elastic-2.0
metadata:
    author: canyongbs
---

# Writing Data Migrations

Migrations may contain schema changes, data changes, or both — there is no requirement to separate them. Data migrations typically move data between formats, clean up or back-fill data, seed data for a new feature, or activate a Feature Flag after a schema change.

- Migrations in `database/migrations` run on **every tenant**.
- Migrations in `database/landlord` run only on the **landlord** database.

Any data migration whose result the application code depends on must be paired with a Feature Flag — see the `zero-downtime` guideline and the `managing-feature-flags` skill.

Run every command through the `pls` guideline (these apps run inside the `app` container).

## Permanent vs temporary migrations

### Permanent migrations

Permanent migrations stay in the codebase indefinitely. When they include data changes:

- **Do not reference classes that may later be removed** — no Eloquent models, no app facades. The only classes permitted are `Illuminate\Support\Facades\DB` and Feature Flag classes under `App\Features` (extending `App\Support\AbstractFeatureFlag`).
- **Handle every possible SQL error** (e.g. `UniqueConstraintViolationException`) and wrap changes in a transaction so a failure cannot corrupt the query connection.
- **Make _data_ writes idempotent by checking data state; keep _schema_ changes unconditional.** A permanent migration is recorded and runs exactly once per database, so "safe to re-run" / idempotency concerns only data writes that could be re-applied — never the schema. Before a data write, check the state of the data (e.g. `whereNull(...)`, match only the rows still needing conversion, skip already-migrated rows) so a re-run cannot duplicate or corrupt rows. Do **not** make a schema change conditional to "protect" a re-run: no `hasTable`/`hasColumn` existence guards, and never wrap a schema change in a `try`/`catch` that swallows "already exists" / "does not exist" errors — both silently skip a real problem. A migration owns the schema of the tables it manages: its schema changes are unconditional, and if the structure is not what you expect, let it fail loudly.
- **Always include `down()`.** Production migrations run once and are not rolled back; if a shipped migration is wrong, write a new migration to fix it. `down()` is still required to support testing.

### Temporary migrations

Temporary migrations are one-time work deleted after they have run across all environments — seeding for existing tenants, one-off clean-ups, or back-fills. **If the whole file should be deleted afterwards, prefix its name with `tmp_`.**

Prefer the dedicated command, which adds the prefix and prompts for a cleanup task:

```bash
php artisan make:tmp-migration backfill_user_preferences
```

This creates `YYYY_MM_DD_HHMMSS_tmp_backfill_user_preferences.php` and attaches it to a cleanup task (see `managing-cleanup-tasks`). Use `--no-cleanup` to skip the prompt, and `--module=<module>` for modular projects.

Temporary migrations **may** use Eloquent and other removable classes because the file will be deleted, but still wrap changes in a transaction, include `down()`, and stay idempotent where possible. Prefer the `DB` facade even here; if you do use a model, account for its side effects (observers, global scopes).

## Creating a migration

```bash
php artisan make:migration convert_orders_reference_to_citext
php artisan make:tmp-migration seed_default_settings_for_existing_tenants
```

## Running migrations

```bash
# Landlord database
php artisan migrate --database=landlord --path=database/landlord

# All tenants
php artisan tenants:artisan "migrate"
```

---

Related: `managing-feature-flags`, `managing-cleanup-tasks`, `creating-permissions`.
