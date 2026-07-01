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

namespace CanyonGBS\Common\Models\Concerns;

use CanyonGBS\Common\Models\Role;
use CanyonGBS\Common\Models\RoleAssignment;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;

/**
 * @property-read Collection<int, Role> $roles
 *
 * @phpstan-ignore trait.unused
 */
trait HasRoles
{
    /**
     * @return MorphToMany<Role, $this, RoleAssignment>
     */
    public function roles(): MorphToMany
    {
        return $this->morphToMany(
            Role::class,
            'model',
            'role_assignments',
            'model_id',
            'role_id',
        )->using(RoleAssignment::class)->withTimestamps()->where('roles.guard_name', $this->getRolesGuardName());
    }

    public function assignRole(string | Role ...$roles): static
    {
        $ids = $this->collectRoleKeys($roles);

        $this->roles()->syncWithoutDetaching($ids);

        $this->unsetRelation('roles');

        return $this;
    }

    public function hasRole(string $name): bool
    {
        return $this->roles->contains('name', $name);
    }

    /**
     * @param array<string> $names
     */
    public function hasAnyRole(array $names): bool
    {
        return $this->roles->contains(fn (Role $role): bool => in_array($role->getAttribute('name'), $names, true));
    }

    public function getRolesGuardName(): string
    {
        foreach (config('auth.guards') as $name => $guard) {
            $provider = $guard['provider'] ?? null;

            if ($provider && config("auth.providers.{$provider}.model") === static::class) {
                return $name;
            }
        }

        return config('auth.defaults.guard');
    }

    /**
     * @param array<string | Role> $roles
     *
     * @return array<int, mixed>
     */
    protected function collectRoleKeys(array $roles): array
    {
        return array_map(fn (string | Role $role) => $this->resolveRole($role)->getKey(), $roles);
    }

    protected function resolveRole(string | Role $role): Role
    {
        if ($role instanceof Role) {
            return $role;
        }

        return Role::query()
            ->where('name', $role)
            ->where('guard_name', $this->getRolesGuardName())
            ->firstOrFail();
    }
}
