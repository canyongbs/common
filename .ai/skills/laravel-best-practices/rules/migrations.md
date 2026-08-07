# Migration Best Practices

## Generate Migrations with Artisan

Always use `php artisan make:migration` for consistent naming and timestamps.

Incorrect (manually created file):

```php
// database/migrations/posts_migration.php  ← wrong naming, no timestamp
```

Correct (Artisan-generated):

```bash
php artisan make:migration create_posts_table
php artisan make:migration add_slug_to_posts_table
```

## PostgreSQL Schema Conventions

These apps run on PostgreSQL via `tpetry/laravel-postgresql-enhanced`. Import the package's `Blueprint` and `Schema` facade in every migration so the enhanced methods are available:

```php
use Tpetry\PostgresqlEnhanced\Schema\Blueprint;
use Tpetry\PostgresqlEnhanced\Support\Facades\Schema;
```

- **UUID primary keys** on every table — never auto-increment. Pair with `use HasUuids` on the model (see `models.md`). For foreign keys and polymorphic columns, use the UUID helpers (`foreignUuid()`, `uuidMorphs()` / `nullableUuidMorphs()`) — never `foreignId()`/`morphs()`/`nullableMorphs()` (which are integer-based) or hand-rolled `*_id` + `*_type` columns.

    ```php
    $table->uuid('id')->primary();
    $table->foreignUuid('property_id')->constrained()->cascadeOnDelete();
    $table->uuidMorphs('subject');
    $table->nullableUuidMorphs('commentable');
    ```

- **`caseInsensitiveText()` (citext) for case-insensitive columns** — names, emails, slugs, anything compared without case. Enable the extension once in its own migration (`DB::statement('CREATE EXTENSION IF NOT EXISTS citext')`). A `citext` column makes lookups and `unique` validation case-insensitive automatically, with no extra rule configuration.

    ```php
    $table->caseInsensitiveText('name');
    ```

- **Always `uniqueIndex()`, never `unique()`** — the enhanced builder's `uniqueIndex()` supports the partial indexes and NULL handling below that `unique()` cannot.

    ```php
    $table->uniqueIndex(['organization_id', 'name']);
    ```

- **Scope unique indexes to live rows on soft-deleting tables**, so a soft-deleted row does not keep blocking the value:

    ```php
    $table->uniqueIndex(['property_id', 'name'])
        ->where(fn (Builder $condition) => $condition->whereNull('deleted_at'));
    ```

- **NULLs are distinct by default** — PostgreSQL permits multiple NULLs in a unique index. Use `->nullsNotDistinct()` when NULLs must collide (e.g. only one row may have a NULL `parent_id`), or a partial `->where(fn (Builder $condition) => $condition->whereNotNull('col'))` to constrain only non-NULL values.

- **Match unique validation to the index.** When the index is scoped to `whereNull('deleted_at')`, the validation rule must be too, or soft-deleted rows cause false conflicts:
    - Filament / model-resolved rules: `->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->withoutTrashed())`.
    - Raw-table `Rule::unique('table', 'column')`: `->whereNull('deleted_at')` (plus `->ignore($record)` on update).
    - Add `->where('other_column', $get('other_column'))` for composite / scoped indexes.

## Never Use `->after()`

Never add `->after('column')` (or `->first()`) to any column definition. PostgreSQL does not support positioning columns, so the enhanced builder silently ignores it — the column is always appended to the end of the table regardless. Including it adds no value and misleads readers into thinking the column order is controlled.

Incorrect:

```php
$table->string('slug')->after('title'); // ->after() is ignored on PostgreSQL
```

Correct:

```php
$table->string('slug');
```

## Use `constrained()` for Foreign Keys

Automatic naming and referential integrity.

```php
$table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

// Non-standard names
$table->foreignUuid('author_id')->constrained('users');
```

## Never Modify Deployed Migrations

Once a migration has run in production, treat it as immutable. Create a new migration to change the table.

Incorrect (editing a deployed migration):

```php
// 2024_01_01_create_posts_table.php — already in production
$table->string('slug'); // ← added after deployment
```

Correct (new migration to alter):

```php
// 2024_03_15_add_slug_to_posts_table.php
Schema::table('posts', function (Blueprint $table) {
    $table->string('slug');
    $table->uniqueIndex('slug');
});
```

Immutability applies to the migration's schema/data logic, not to these deletions that are part of the normal lifecycle:

- **Deleting a temporary (`tmp_`) data migration** once it has run across all environments — that is the point of the `tmp_` prefix. Track the deletion in a cleanup task (see the `writing-data-migrations` and `managing-cleanup-tasks` skills).
- **Removing a Feature Flag's activation/deactivation** when the flag is cleaned up after a successful deploy — delete the activation migration along with the flag class (see the `managing-feature-flags` skill).
- **Removing a one-off data change embedded in an otherwise permanent migration** when a cleanup task (or a cleanup-task note) marks it as no longer needed — e.g. a step that back-fills or fixes existing data mid-migration. Delete only that data step once it has run everywhere; the migration's schema/structural logic must remain intact.

## Add Indexes in the Migration

Add indexes when creating the table, not as an afterthought. Columns used in `WHERE`, `ORDER BY`, and `JOIN` clauses need indexes.

Incorrect:

```php
Schema::create('orders', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id')->constrained();
    $table->string('status');
    $table->timestamps();
});
```

Correct:

```php
Schema::create('orders', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id')->constrained()->index();
    $table->string('status')->index();
    $table->timestamp('shipped_at')->nullable()->index();
    $table->timestamps();
});
```

## Mirror Column Defaults in the Model

When a migration adds a column with a database default, mirror that default in the model's `$attributes` so new instances have the correct value before saving — see `models.md`.

```php
$table->string('status')->default('pending');
```

## Write Reversible `down()` Methods by Default

Implement `down()` for schema changes that can be safely reversed so `migrate:rollback` works in CI and failed deployments.

```php
public function down(): void
{
    Schema::table('posts', function (Blueprint $table) {
        $table->dropColumn('slug');
    });
}
```

For intentionally irreversible migrations (e.g., destructive data backfills), leave a clear comment and require a forward fix migration instead of pretending rollback is supported.

## Keep Migrations Focused

Keep each migration to a single concern. Mixing schema changes (DDL) and data changes (DML) is expected when they are **inseparable parts of the same change** — and when they are, wrap the whole `up()` (and `down()`) in a `DB::transaction` so a partial failure rolls back cleanly. See the `writing-data-migrations` skill for the data-change rules that apply.

Legitimately mixed migrations include:

- **Renaming or re-typing a column while preserving its data** — add the new column, `UPDATE` to copy the values across, then drop the old one. These steps cannot be split without leaving a broken intermediate state.
- **Fixing dependent data after a structural change** — e.g. `Schema::rename` a table, then `UPDATE` the polymorphic `auditable_type` strings that referenced the old name.
- **Activating a Feature Flag alongside the schema change it guards** — the zero-downtime convention activates the flag from the same migration that makes the change (see `managing-feature-flags`).

The one split worth keeping is **creating a brand-new table and seeding it** — separate the `create` from the `insert`, because there is no atomicity requirement and the seed belongs in its own (often temporary `tmp_`) data migration.

Incorrect (create and seed in one migration — split these):

```php
public function up(): void
{
    Schema::create('settings', function (Blueprint $table) { ... });
    DB::table('settings')->insert(['key' => 'version', 'value' => '1.0']);
}
```

Correct (separate migrations):

```php
// Migration 1: create_settings_table
Schema::create('settings', function (Blueprint $table) { ... });

// Migration 2: seed_default_settings
DB::table('settings')->insert(['key' => 'version', 'value' => '1.0']);
```
