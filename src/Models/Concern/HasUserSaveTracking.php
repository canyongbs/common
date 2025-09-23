<?php

/*
<COPYRIGHT>

    Copyright © 2016-2025, Canyon GBS LLC. All rights reserved.

    Advising App™ is licensed under the Elastic License 2.0. For more details,
    see https://github.com/canyongbs/advisingapp/blob/main/LICENSE.

    Notice:

    - You may not provide the software to third parties as a hosted or managed
      service, where the service provides users with access to any substantial set of
      the features or functionality of the software.
    - You may not move, change, disable, or circumvent the license key functionality
      in the software, and you may not remove or obscure any functionality in the
      software that is protected by the license key.
    - You may not alter, remove, or obscure any licensing, copyright, or other notices
      of the licensor in the software. Any use of the licensor's trademarks is subject
      to applicable law.
    - Canyon GBS LLC respects the intellectual property rights of others and expects the
      same in return. Canyon GBS™ and Advising App™ are registered trademarks of
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

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

trait HasUserSaveTracking /** @phpstan-ignore trait.unused */
{
    /**
     * @return BelongsTo<Authenticatable, $this>
     */
    public function createdBy(): BelongsTo
    {
        $authenticatable = app(Authenticatable::class);

        if ($authenticatable) {
            return $this->belongsTo($authenticatable::class, 'created_by_id');
        }

        $userClass = app()->getNamespace() . 'Models\\User';

        if (! class_exists($userClass)) {
            throw new LogicException('No [' . $userClass . '] model found. Please bind an authenticatable model to the [Illuminate\\Contracts\\Auth\\Authenticatable] interface in a service provider\'s [register()] method.');
        }

        return $this->belongsTo($userClass, 'created_by_id');
    }

    /**
     * @return BelongsTo<Authenticatable, $this>
     */
    public function lastUpdatedBy(): BelongsTo
    {
        /** @var ?Authenticatable $authenticatable */
        $authenticatable = app(Authenticatable::class);

        if ($authenticatable) {
            return $this->belongsTo($authenticatable::class, 'last_updated_by_id');
        }

        $userClass = app()->getNamespace() . 'Models\\User';

        if (! class_exists($userClass)) {
            throw new LogicException('No [' . $userClass . '] model found. Please bind an authenticatable model to the [Illuminate\\Contracts\\Auth\\Authenticatable] interface in a service provider\'s [register()] method.');
        }

        return $this->belongsTo($userClass, 'last_updated_by_id');
    }

    protected static function bootHasUserSaveTracking(): void
    {
        static::creating(function (Model $model): void {
            $user = auth()->user();

            if (! $user) {
                return;
            }

            if (! $model->createdBy) {
                $model->createdBy()->associate($user);
            }

            if (! $model->lastUpdatedBy) {
                $model->lastUpdatedBy()->associate($user);
            }
        });

        static::updating(function (Model $model): void {
            $user = auth()->user();

            if (! $user) {
                return;
            }

            $model->lastUpdatedBy()->associate($user);
        });
    }
}
