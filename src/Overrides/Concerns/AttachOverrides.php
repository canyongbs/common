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

namespace CanyonGBS\Common\Overrides\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Events\AuditCustom;
use ReflectionClass;

trait AttachOverrides
{
    /**
     * @param array<array-key, mixed> $attributes
     * @param mixed $id
     * @param mixed $touch
     */
    public function attach($id, array $attributes = [], $touch = true)
    {
        /** @var Auditable $parentModel */
        $parentModel = $this->getParent();

        if (! $this->isAuditable($parentModel::class)) {
            parent::attach($id, $attributes, $touch);

            return;
        }

        $relationName = $this->relationName;

        $old = $this->relationAuditSnapshot($parentModel, $relationName);

        $this->runWithoutPivotAuditing(function () use ($id, $attributes, $touch): void {
            parent::attach($id, $attributes, $touch);
        });

        $new = $this->relationAuditSnapshot($parentModel, $relationName);

        $this->dispatchRelationAuditEvent($parentModel, 'attach', $old, $new);
    }

    public function detach($ids = null, $touch = true)
    {
        /** @var Auditable $parentModel */
        $parentModel = $this->getParent();

        if (! $this->isAuditable($parentModel::class)) {
            return parent::detach($ids, $touch);
        }

        $relationName = $this->relationName;

        $old = $this->relationAuditSnapshot($parentModel, $relationName);

        $results = $this->runWithoutPivotAuditing(fn () => parent::detach($ids, $touch));

        $new = $this->relationAuditSnapshot($parentModel, $relationName);

        $this->dispatchRelationAuditEvent($parentModel, 'detach', $old, $new);

        return empty($results) ? 0 : $results;
    }

    /**
     * @param array<array-key, mixed>|Model|Collection<array-key, mixed> $ids
     * @param mixed $detaching
     *
     * @return array<string, mixed>
     */
    public function sync($ids, $detaching = true)
    {
        /** @var Auditable $parentModel */
        $parentModel = $this->getParent();

        if (! $this->isAuditable($parentModel::class)) {
            return parent::sync($ids, $detaching);
        }

        $relationName = $this->relationName;

        $old = $this->relationAuditSnapshot($parentModel, $relationName);

        /** @var array<string, mixed> $changes */
        $changes = $this->runWithoutPivotAuditing(fn () => parent::sync($ids, $detaching));

        $new = $this->relationAuditSnapshot($parentModel, $relationName);

        $this->dispatchRelationAuditEvent($parentModel, 'sync', $old, $new);

        return $changes;
    }

    private function isAuditable(string $class): bool
    {
        $reflection = new ReflectionClass($class);

        return $reflection->implementsInterface(Auditable::class);
    }

    /**
     * Runs the pivot mutation with the pivot model's own auditing suppressed when that pivot is
     * itself auditable, so a single relationship change is not recorded twice.
     */
    private function runWithoutPivotAuditing(Closure $callback): mixed
    {
        $pivotClass = $this->getPivotClass();

        if ($pivotClass !== Pivot::class && is_a($pivotClass, Auditable::class, true)) {
            return $pivotClass::withoutAuditing($callback); /** @phpstan-ignore staticMethod.notFound */
        }

        return $callback();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function relationAuditSnapshot(Auditable $parentModel, string $relationName): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Model> $related */
        $related = $parentModel->{$relationName}()->get();

        $snapshot = [];

        foreach ($related as $model) {
            $snapshot[(string) $model->getKey()] = $model->toArray();
        }

        return $snapshot;
    }

    /**
     * Records only the rows that were added, removed, or had their (pivot) attributes changed,
     * rather than the full relationship on both the old and new side.
     *
     * @param array<string, array<string, mixed>> $old
     * @param array<string, array<string, mixed>> $new
     */
    private function dispatchRelationAuditEvent(Auditable $parentModel, string $event, array $old, array $new): void
    {
        [$oldDiff, $newDiff] = $this->diffRelationSnapshots($old, $new);

        $hasChanges = $oldDiff !== [] || $newDiff !== [];
        $relationName = $this->relationName;

        $parentModel->auditEvent = $event; /** @phpstan-ignore property.notFound */
        $parentModel->auditCustomOld = $hasChanges ? [$relationName => $oldDiff] : []; /** @phpstan-ignore property.notFound */
        $parentModel->auditCustomNew = $hasChanges ? [$relationName => $newDiff] : []; /** @phpstan-ignore property.notFound */
        $parentModel->isCustomEvent = true; /** @phpstan-ignore property.notFound */

        try {
            Event::dispatch(new AuditCustom($parentModel));
        } finally {
            $parentModel->isCustomEvent = false; /** @phpstan-ignore property.notFound */
            $parentModel->auditCustomOld = []; /** @phpstan-ignore property.notFound */
            $parentModel->auditCustomNew = []; /** @phpstan-ignore property.notFound */
        }
    }

    /**
     * @param array<string, array<string, mixed>> $old
     * @param array<string, array<string, mixed>> $new
     *
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function diffRelationSnapshots(array $old, array $new): array
    {
        $removedOrChanged = [];

        foreach ($old as $key => $row) {
            if (! array_key_exists($key, $new) || $new[$key] !== $row) {
                $removedOrChanged[] = $row;
            }
        }

        $addedOrChanged = [];

        foreach ($new as $key => $row) {
            if (! array_key_exists($key, $old) || $old[$key] !== $row) {
                $addedOrChanged[] = $row;
            }
        }

        return [$removedOrChanged, $addedOrChanged];
    }
}
