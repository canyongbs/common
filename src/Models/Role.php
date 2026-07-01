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

namespace CanyonGBS\Common\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids as HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use RuntimeException;

class Role extends Model
{
    use HasUuids;

    protected $table = 'roles';

    protected $fillable = [
        'name',
        'guard_name',
        'description',
    ];

    /**
     * @return HasMany<RolePermission, $this>
     */
    public function rolePermissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    /**
     * @return MorphToMany<Model, $this, RoleAssignment>
     */
    public function users(): MorphToMany
    {
        $guard = $this->getAttribute('guard_name') ?? config('auth.defaults.guard');

        /** @var class-string<Model> $userModel */
        $userModel = static::getModelForGuard($guard);

        return $this->morphedByMany(
            $userModel,
            'model',
            'role_assignments',
            'role_id',
            'model_id',
        )->using(RoleAssignment::class);
    }

    public static function getModelForGuard(string $guard): string
    {
        $provider = config("auth.guards.{$guard}.provider");

        $model = $provider
            ? config("auth.providers.{$provider}.model")
            : null;

        if (! is_string($model)) {
            throw new RuntimeException("Unable to resolve a model for the [{$guard}] guard.");
        }

        return $model;
    }
}
