---
name: creating-permissions
description: 'Use when creating, seeding, renaming, or deleting Spatie Laravel-permission permissions in a Canyon GBS app via a permission migration. Trigger whenever you add permissions for a model or feature, run `make:permission-migration`, use the `CanModifyPermissions` trait (createPermissions / deletePermissions / renamePermissions / renamePermissionGroups), choose a permission group, or decide which of the allowed permission names a model needs. Covers the `seed_permissions_` naming, permission groups, the allowed permission-name format, guards, and the `--module` flag. Do not use for: general data migrations (use writing-data-migrations), or writing the policies / authorization checks that consume the permissions.'
license: Elastic-2.0
metadata:
    author: canyongbs
---

# Creating Permissions

Permissions use the [Spatie Laravel-permission](https://spatie.be/docs/laravel-permission/v5/introduction) package and are created through **permission migrations**.

Run every command through the `pls` guideline (these apps run inside the `app` container).

## Permission groups

Every permission **must** belong to a `PermissionGroup` — a label that organises permissions in the UI. Reuse an existing group where possible, or create a new one. Product usually defines the group name; if it is not specified, confirm with Product or leadership before inventing one.

## Allowed permission names

Use this format when adding permissions for a model or feature:

- `permission-name.view-any`
- `permission-name.create`
- `permission-name.*.view`
- `permission-name.*.update`
- `permission-name.*.delete`
- `permission-name.*.restore`
- `permission-name.*.force-delete`

And, only when requested:

- `permission-name.import`

Not every model needs every permission — for example, omit `*.update` for a model that is never updated. Decide the set before creating it, confirming with Product or leadership when it is unclear.

## Permission migrations

Permissions are created and managed through migrations that follow extra conventions:

- Prefix the migration name with `seed_permissions_`.
- Use the helpers from the `CanyonGBS\Common\Database\Migrations\Concerns\CanModifyPermissions` trait.
- Wrap any additional queries in a transaction and a `try`/`catch` for SQL errors such as `UniqueConstraintViolationException`.

Create one with the dedicated command (supports `--module`):

```bash
php artisan make:permission-migration seed_permissions_add_foo_permissions
```

The generated migration uses the trait and lists permissions keyed by their group. Each app **overrides the stub** (`stubs/permission-migration.stub`) with its own guard(s):

```php
use Illuminate\Database\Migrations\Migration;
use CanyonGBS\Common\Database\Migrations\Concerns\CanModifyPermissions;

return new class extends Migration
{
    use CanModifyPermissions;

    private array $permissions = [
        'foo.view-any' => 'Foo',
        'foo.create' => 'Foo',
        'foo.*.view' => 'Foo',
    ];

    private array $guards = ['web'];

    public function up(): void
    {
        foreach ($this->guards as $guard) {
            $this->createPermissions($this->permissions, $guard);
        }
    }

    public function down(): void
    {
        foreach ($this->guards as $guard) {
            $this->deletePermissions(array_keys($this->permissions), $guard);
        }
    }
};
```

### Trait helpers

- `createPermissions(array $names, string $guard)` — `$names` maps permission name => group name; missing groups are created automatically.
- `deletePermissions(array $names, string $guard)` — deletes the permissions and any groups left with none.
- `renamePermissions(array $names, string $guard)` — `$names` maps old name => new name.
- `renamePermissionGroups(array $groups)` — maps old group name => new group name.

---

Related skill: `writing-data-migrations`.
