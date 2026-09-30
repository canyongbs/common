<?php

/*
<COPYRIGHT>

    Copyright © 2016-2026, Canyon GBS LLC. All rights reserved.

    Canyon GBS Common is licensed under the Elastic License 2.0. For more details,
    see https://github.com/canyongbs/common/blob/main/LICENSE.

    Notice:

    - You may not provide the software to third parties as a hosted or managed
      service, where the service provides users with access to any substantial set of
      the features or functionality of the software.
    - You may not move, change, disable, or circumvent the license key functionality
      in the software, and you may not remove or obscure any functionality in the
      software that is protected by the license key.
    - You may not alter, remove, or obscure any licensing, copyright, or other notices
      of the licensor in the software. Any use of the licensor’s trademarks is subject
      to applicable law.
    - Canyon GBS LLC respects the intellectual property rights of others and expects the
      same in return. Canyon GBS™ and Canyon GBS Common are registered trademarks of
      Canyon GBS LLC, and we are committed to enforcing and protecting our trademarks
      vigorously.
    - The software solution, including services, infrastructure, and code, is offered as a
      Software as a Service (SaaS) by Canyon GBS LLC.
    - Use of this software implies agreement to the license terms and conditions as stated
      in the Elastic License 2.0.

    For more information or inquiries please visit our website at
    https://www.canyongbs.com or contact us via email at legal@canyongbs.com.

</COPYRIGHT>
*/

use CanyonGBS\Common\Filament\Forms\Components\PermissionsMatrix;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Workbench\App\Filament\Resources\PermissionsMatrixRoles\Pages\EditPermissionsMatrixRole;
use Workbench\App\Models\Permission;
use Workbench\App\Models\PermissionGroup;
use Workbench\App\Models\PermissionsMatrixRole;
use Workbench\App\Models\User;

$makePermissionsMatrix = function (): PermissionsMatrix {
    return PermissionsMatrix::make('permissions')
        ->guard('web')
        ->permissionGroupModel(PermissionGroup::class);
};

beforeEach(function () {
    foreach (['Article', 'Project', 'Task'] as $permissionGroupName) {
        $permissionGroup = PermissionGroup::query()->create(['name' => $permissionGroupName]);
        $permissionPrefix = str($permissionGroupName)->slug('_');

        $permissionGroup->permissions()->create(['name' => "{$permissionPrefix}.view-any", 'guard_name' => 'web']);
        $permissionGroup->permissions()->create(['name' => "{$permissionPrefix}.create", 'guard_name' => 'web']);
    }
});

it('lists every permission group when no permission groups are hidden', function () use ($makePermissionsMatrix) {
    expect($makePermissionsMatrix()->getAvailablePermissions())
        ->toHaveKeys(['Article', 'Project', 'Task'])
        ->and($makePermissionsMatrix()->getAvailablePermissions()['Article'])
        ->toHaveKeys(['view-any', 'create']);
});

it('leaves hidden permission groups out of the matrix', function () use ($makePermissionsMatrix) {
    $availablePermissions = $makePermissionsMatrix()
        ->hiddenPermissionGroups(['Project', 'Task'])
        ->getAvailablePermissions();

    expect(array_keys($availablePermissions))->toBe(['Article']);
});

it('resolves hidden permission groups from a closure', function () use ($makePermissionsMatrix) {
    $availablePermissions = $makePermissionsMatrix()
        ->hiddenPermissionGroups(fn (): array => ['Task'])
        ->getAvailablePermissions();

    expect(array_keys($availablePermissions))->toBe(['Article', 'Project']);
});

describe('saving', function () {
    beforeEach(function () {
        Filament::setCurrentPanel('testing');

        $this->actingAs(User::create(['name' => 'Ada', 'email' => 'ada@example.com']));
    });

    it('keeps permissions from hidden permission groups when the record is saved', function () {
        $visiblePermission = Permission::query()->where('name', 'article.view-any')->firstOrFail();
        $hiddenPermission = Permission::query()->where('name', 'task.view-any')->firstOrFail();

        $role = PermissionsMatrixRole::query()->create(['name' => 'Advisors']);
        $role->permissions()->attach([$visiblePermission->getKey(), $hiddenPermission->getKey()]);

        expect($role->permissions()->pluck('permissions.id')->all())
            ->toEqualCanonicalizing([$visiblePermission->getKey(), $hiddenPermission->getKey()]);

        Livewire::test(EditPermissionsMatrixRole::class, ['record' => $role->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($role->permissions()->pluck('permissions.id')->all())
            ->toEqualCanonicalizing([$visiblePermission->getKey(), $hiddenPermission->getKey()]);
    });

    it('keeps permissions from hidden permission groups when visible permissions are removed', function () {
        $visiblePermission = Permission::query()->where('name', 'article.view-any')->firstOrFail();
        $hiddenPermission = Permission::query()->where('name', 'task.view-any')->firstOrFail();

        $role = PermissionsMatrixRole::query()->create(['name' => 'Advisors']);
        $role->permissions()->attach([$visiblePermission->getKey(), $hiddenPermission->getKey()]);

        $component = Livewire::test(EditPermissionsMatrixRole::class, ['record' => $role->getRouteKey()]);

        // Mirrors the matrix UI: start from the loaded state and deselect only the visible permission.
        $component
            ->set('data.permissions', array_values(array_diff($component->get('data.permissions'), [$visiblePermission->getKey()])))
            ->call('save')
            ->assertHasNoFormErrors();

        expect($role->permissions()->pluck('permissions.id')->all())
            ->toBe([$hiddenPermission->getKey()]);
    });
});
