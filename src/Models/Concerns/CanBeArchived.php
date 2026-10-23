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

use CanyonGBS\Common\Models\Observers\ArchiveAuditObserver;
use CanyonGBS\Common\Models\Scopes\ArchivingScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Events\QueuedClosure;
use Illuminate\Support\Arr;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @method static Builder<static> onlyArchived()
 * @method static Builder<static> withoutArchived()
 * @method static Builder<static> withoutArchivedAndUnused()
 *
 * @phpstan-ignore trait.unused
 */
trait CanBeArchived
{
    public static function bootCanBeArchived(): void
    {
        static::addGlobalScope(new ArchivingScope());

        if (! is_subclass_of(static::class, Auditable::class)) {
            return;
        }

        static::archived(function (self $model): void {
            $model->recordArchiveAudit('archived');
        });

        static::unarchived(function (self $model): void {
            $model->recordArchiveAudit('unarchived');
        });
    }

    public function initializeCanBeArchived(): void
    {
        if (! isset($this->casts[$this->getArchivedAtColumn()])) {
            $this->casts[$this->getArchivedAtColumn()] = 'datetime';
        }
    }

    public function archive(): bool
    {
        if ($this->fireModelEvent('archiving') === false) {
            return false;
        }

        $query = $this->setKeysForSaveQuery($this->newModelQuery());

        $time = $this->freshTimestamp();

        $columns = [$this->getArchivedAtColumn() => $this->fromDateTime($time)];

        $this->{$this->getArchivedAtColumn()} = $time;

        if ($this->usesTimestamps() && ! is_null($this->getUpdatedAtColumn())) {
            $this->{$this->getUpdatedAtColumn()} = $time;

            $columns[$this->getUpdatedAtColumn()] = $this->fromDateTime($time);
        }

        $query->update($columns);

        $this->changes = Arr::only($this->getDirty(), array_keys($columns));
        $this->previous = array_intersect_key($this->getRawOriginal(), $this->changes);

        $this->syncOriginalAttributes(array_keys($columns));

        $this->fireModelEvent('archived', false);

        return true;
    }

    public function archiveQuietly(): bool
    {
        return static::withoutEvents(fn () => $this->archive());
    }

    public function unarchive(): bool
    {
        if ($this->fireModelEvent('unarchiving') === false) {
            return false;
        }

        $query = $this->setKeysForSaveQuery($this->newModelQuery());

        $columns = [$this->getArchivedAtColumn() => null];

        $this->{$this->getArchivedAtColumn()} = null;

        if ($this->usesTimestamps() && ! is_null($this->getUpdatedAtColumn())) {
            $time = $this->freshTimestamp();

            $this->{$this->getUpdatedAtColumn()} = $time;

            $columns[$this->getUpdatedAtColumn()] = $this->fromDateTime($time);
        }

        $query->update($columns);

        $this->changes = Arr::only($this->getDirty(), array_keys($columns));
        $this->previous = array_intersect_key($this->getRawOriginal(), $this->changes);

        $this->syncOriginalAttributes(array_keys($columns));

        $this->fireModelEvent('unarchived', false);

        return true;
    }

    public function unarchiveQuietly(): bool
    {
        return static::withoutEvents(fn () => $this->unarchive());
    }

    public function isArchived(): bool
    {
        return ! is_null($this->{$this->getArchivedAtColumn()});
    }

    /**
     * @param  QueuedClosure|callable|class-string  $callback
     */
    public static function archiving(QueuedClosure | callable | string $callback): void
    {
        static::registerModelEvent('archiving', $callback);
    }

    /**
     * @param  QueuedClosure|callable|class-string  $callback
     */
    public static function archived(QueuedClosure | callable | string $callback): void
    {
        static::registerModelEvent('archived', $callback);
    }

    /**
     * @param  QueuedClosure|callable|class-string  $callback
     */
    public static function unarchiving(QueuedClosure | callable | string $callback): void
    {
        static::registerModelEvent('unarchiving', $callback);
    }

    /**
     * @param  QueuedClosure|callable|class-string  $callback
     */
    public static function unarchived(QueuedClosure | callable | string $callback): void
    {
        static::registerModelEvent('unarchived', $callback);
    }

    public function getArchivedAtColumn(): string
    {
        return 'archived_at';
    }

    public function getQualifiedArchivedAtColumn(): string
    {
        return $this->qualifyColumn($this->getArchivedAtColumn());
    }

    protected function recordArchiveAudit(string $event): void
    {
        $clone = $this->cloneForArchiveAudit();

        assert($clone instanceof Auditable);

        $column = $this->getArchivedAtColumn();

        if (($clone->original[$column] ?? null) === ($clone->attributes[$column] ?? null)) {
            return;
        }

        app(ArchiveAuditObserver::class)->{$event}($clone);
    }

    /**
     * Audits are built from the difference between `original` and `attributes`,
     * which archiving has already synced, so the audit is taken from a copy that
     * restores the previous archive time without touching this model.
     */
    protected function cloneForArchiveAudit(): static
    {
        $clone = clone $this;

        $column = $this->getArchivedAtColumn();

        // A record created without the column has no original value for it,
        // which is recorded as null, the same as an `updated` audit records it.
        if (array_key_exists($column, $this->getChanges())) {
            $clone->original[$column] = $this->getPrevious()[$column] ?? null;
        }

        return $clone;
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    protected function getArchivedEventAttributes(): array
    {
        return $this->getArchiveAuditAttributes();
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    protected function getUnarchivedEventAttributes(): array
    {
        return $this->getArchiveAuditAttributes();
    }

    /**
     * The auditing package resolves a getter per event name, so both events share this one.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    protected function getArchiveAuditAttributes(): array
    {
        assert($this instanceof Auditable);

        $column = $this->getArchivedAtColumn();

        if (! $this->isAttributeAuditable($column)) {
            return [[], []];
        }

        return [
            [$column => Arr::get($this->original, $column)],
            [$column => Arr::get($this->attributes, $column)],
        ];
    }
}
