<?php

/*
<COPYRIGHT>

    Copyright © 2016-2025, Canyon GBS LLC. All rights reserved.

    Notice:

    - This software is closed source and the source code is a trade secret.
    - You may not alter, remove, or obscure any licensing, copyright, or other notices
      of the licensor in the software.
    - Canyon GBS LLC respects the intellectual property rights of others and expects the
      same in return. Canyon GBS™ is a registered trademarks of Canyon GBS LLC, and we are
      committed to enforcing and protecting our trademarks vigorously.

    For more information or inquiries please visit our website at
    https://www.canyongbs.com or contact us via email at legal@canyongbs.com.

</COPYRIGHT>
*/

namespace CanyonGBS\Common\Database\Migrations\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait CanModifyPermissions
{
    /**
     * @param array<string, string> $names The keys of the array should be the permission names and the values should be the name of the group they belong to.
     */
    public function createPermissions(array $names, string $guardName): void
    {
        $groups = DB::table('permission_groups')
            ->pluck('id', 'name')
            ->all();

        [$newGroups, $groupsToCreate] = collect($names)
            ->values()
            ->unique()
            ->diff(array_keys($groups))
            ->reduce(function (array $carry, string $name) {
                $id = (string) Str::orderedUuid();

                $carry[0][$name] = $id;
                $carry[1][] = [
                    'id' => $id,
                    'name' => $name,
                    'created_at' => now(),
                ];

                return $carry;
            }, [[], []]);

        DB::table('permission_groups')
            ->insert($groupsToCreate);

        $groups = [
            ...$groups,
            ...$newGroups,
        ];

        DB::table('permissions')
            ->insertOrIgnore(array_map(function (string $name, string $groupName) use ($groups, $guardName): array {
                return [
                    'id' => (string) Str::orderedUuid(),
                    'group_id' => $groups[$groupName],
                    'guard_name' => $guardName,
                    'name' => $name,
                    'created_at' => now(),
                ];
            }, array_keys($names), array_values($names)));
    }

    /**
     * @param array<string> $names
     */
    public function deletePermissions(array $names, string $guardName): void
    {
        DB::table('permissions')
            ->where('guard_name', $guardName)
            ->whereIn('name', $names)
            ->delete();

        // Delete groups that no longer have any permissions
        DB::table('permission_groups')
            ->leftJoin('permissions', 'permission_groups.id', '=', 'permissions.group_id')
            ->whereNull('permissions.id')
            ->delete();
    }

    /**
     * @param array<string, string> $names
     */
    public function renamePermissions(array $names, string $guardName): void
    {
        collect($names)
            ->each(function (string $newName, string $oldName) use ($guardName) {
                DB::table('permissions')
                    ->where('guard_name', $guardName)
                    ->where('name', $oldName)
                    ->update([
                        'name' => $newName,
                    ]);
            });
    }

    /**
     * @param array<string, string> $groups
     */
    public function renamePermissionGroups(array $groups): void
    {
        collect($groups)
            ->each(function (string $newName, string $oldName) {
                DB::table('permission_groups')
                    ->where('name', $oldName)
                    ->update([
                        'name' => $newName,
                    ]);
            });
    }
}
