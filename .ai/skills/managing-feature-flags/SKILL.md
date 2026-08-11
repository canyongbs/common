---
name: managing-feature-flags
description: 'Use when creating, activating, gating code behind, or removing a class-based Feature Flag in a Canyon GBS app — Laravel Pennant flags under `App\Features` extending `App\Support\AbstractFeatureFlag`, generated with `make:ff`. Trigger whenever you introduce a schema or data change that code depends on and must be guarded for zero-downtime deploys, add or edit an `active()` / `resolve()` check, activate a flag inside a migration, or clean up a flag after a deployment. Covers the make:ff command and its cleanup-task prompt, the resolve() default, activation-in-migration patterns, the active/inactive code split, and documenting non-obvious flag removals with inline cleanup comments. Do not use for: the mechanics of cleanup task files themselves (use `managing-cleanup-tasks`), writing the migrations that carry the change (use `writing-data-migrations`), subscription/license feature ADDON gating (app-specific), or Pennant scoped/config-driven flags.'
user-invocable: false
license: Elastic-2.0
metadata:
    author: canyongbs
---

# Managing Feature Flags

Feature Flags gate code that must not run — or must run differently — until a deployment's migrations have completed. They exist for **zero-downtime deploys**: code ships and is reachable before migrations run (per tenant), so any code depending on a schema or data change must be guarded. See the `zero-downtime` guideline for the deployment context.

These are **class-based Laravel Pennant flags**: one class per flag under `App\Features`. A tenant-scoped flag extends `App\Support\AbstractFeatureFlag`; a landlord/central-scoped flag extends `App\Support\LandlordAbstractFeatureFlag` (which resolves against the `landlord` Pennant store). The base class provides the static `active()`, `activate()`, `deactivate()`, and `purge()` helpers. A flag is temporary by design — once its deploy succeeds, a later release removes it, tracked by a cleanup task.

Run every command through the `pls` guideline (these apps run inside the `app` container).

## Creating a Feature Flag

```bash
php artisan make:ff SomeFeature
```

This generates the flag class under `App\Features` and then prompts you to create or attach a **cleanup task** that tracks its removal (see the `managing-cleanup-tasks` skill). Skip that prompt with `--no-cleanup`. The class name is suffixed with `Feature` automatically, so `make:ff Some` produces `SomeFeature`.

The generated class uses Pennant's default stub — a bare class whose `resolve()` returns `false`, so the flag is **inactive** until a migration activates it. You must make it **extend the project base class** yourself: `App\Support\AbstractFeatureFlag` for a tenant flag, or `App\Support\LandlordAbstractFeatureFlag` for a landlord/central flag. Static analysis enforces both the base class and the `Feature` suffix, so `composer lint` fails until you add the `extends`.

```php
namespace App\Features;

use App\Support\AbstractFeatureFlag;

class SomeFeature extends AbstractFeatureFlag
{
    public function resolve(mixed $scope): mixed
    {
        return false;
    }
}
```

You may add conditions to `resolve()` and return `true`, but you **must still** create an activation migration — code may parse the flag before your condition is met.

Pennant's own `php artisan pennant:feature SomeFeature` also works but skips the cleanup-task integration; prefer `make:ff`.

## Gating code with a Feature Flag

Keep the pre-migration behaviour on the inactive branch:

```php
use App\Features\SomeFeature;

if (SomeFeature::active()) {
    // Requires the migration to have run — safe only once active.
} else {
    // Old behaviour that does not depend on the new schema/data.
}
```

## Activating a Feature Flag

### Preferred — activate in the same migration

Wrap the change and the activation in one transaction so they commit or roll back together:

```php
use App\Features\SomeFeature;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        DB::transaction(function () {
            Schema::table('some_table', function (Blueprint $table) {
                $table->string('new_column')->nullable();
            });

            SomeFeature::activate();
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            SomeFeature::deactivate();

            Schema::table('some_table', function (Blueprint $table) {
                $table->dropColumn('new_column');
            });
        });
    }
};
```

### Alternative — activate in a later migration

When several migrations must complete first, activate in a separate migration timestamped to run after all of them:

```php
public function up(): void
{
    SomeFeature::activate();
}

public function down(): void
{
    SomeFeature::deactivate();
}
```

See the `writing-data-migrations` skill for migration rules (idempotency, required `down()`, permanent vs `tmp_`).

### Never activate a flag in a test

The test suite runs your migrations (`RefreshDatabase`), so the activation migration **already activates the flag** — it is active in every test by default. **Do not call `SomeFeature::activate()` in a test, `beforeEach()`, or a helper** to "turn on" a flag; it is redundant and hides a broken activation migration (if the migration fails to activate, the test must fail).

To exercise the **inactive** (pre-migration) branch, call `SomeFeature::deactivate()` for that case only:

```php
it('uses the old behaviour when the flag is inactive', function () {
    SomeFeature::deactivate();

    // assert the inactive-path behaviour
});
```

## Cleaning up a Feature Flag

After the deploy has succeeded everywhere, a later release removes the flag:

- Delete every reference to the flag class, **keeping the active-path code and deleting the inactive path**.
- Delete the activation migration and the flag class.
- Resolve its cleanup task entry (see `managing-cleanup-tasks`).

The flag is purged from the database automatically once the class is gone.

### Documenting non-obvious cleanup

Most flag removals are obvious and need **no note at all**: in an `if` / ternary / `match` on `SomeFeature::active()`, cleanup just means keeping the active branch and deleting the inactive one. **Never** write out instructions for these, and never list them in a cleanup task's Additional Cleanup section.

Only when a removal is **not** obvious (a default must change, a fallback query must be dropped, and so on), co-locate the instructions at the change site with the shared inline comment convention:

```php
// TODO: Cleanup Task (some-feature): drop the fallback below and default $mode to 'new'.
```

The `TODO: Cleanup Task` root finds every cleanup site; the `(some-feature)` tag scopes to one task. The cleanup task file only needs to name the tag to search for — see the `managing-cleanup-tasks` skill for the full convention.

---

Related: `managing-cleanup-tasks`, `writing-data-migrations`.
