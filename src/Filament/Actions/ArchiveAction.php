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

namespace CanyonGBS\Common\Filament\Actions;

use Exception;
use Filament\Actions\Action;
use Filament\Actions\Concerns\CanCustomizeProcess;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class ArchiveAction extends Action
{
    use CanCustomizeProcess;

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(fn (Model $record): string => $this->shouldDeleteInsteadOfArchive($record) ? 'Delete' : 'Archive');

        $this->modalHeading(fn (Model $record): string => ($this->shouldDeleteInsteadOfArchive($record) ? 'Delete' : 'Archive') . " {$this->getRecordTitle()}");

        $this->modalSubmitActionLabel(fn (Model $record): string => $this->shouldDeleteInsteadOfArchive($record) ? 'Delete' : 'Archive');

        $this->successNotificationTitle(function (?Model $record): string {
            // The record is no longer resolvable once it has been deleted,
            // which only happens after a successful deletion.
            if (! $record instanceof Model) {
                return 'Deleted';
            }

            return $this->shouldDeleteInsteadOfArchive($record) ? 'Deleted' : 'Archived';
        });

        $this->defaultColor(fn (Model $record): string => $this->shouldDeleteInsteadOfArchive($record) ? 'danger' : 'warning');

        $this->groupedIcon(fn (Model $record): Heroicon => $this->shouldDeleteInsteadOfArchive($record) ? Heroicon::Trash : Heroicon::ArchiveBox);

        $this->modalIcon(fn (Model $record): Heroicon => $this->shouldDeleteInsteadOfArchive($record) ? Heroicon::OutlinedTrash : Heroicon::OutlinedArchiveBox);

        $this->hidden(function (Model $record): bool {
            if ($this->shouldDeleteInsteadOfArchive($record)) {
                if (! method_exists($record, 'trashed')) {
                    return false;
                }

                return $record->trashed();
            }

            if (! method_exists($record, 'isArchived')) {
                throw new Exception('The [ArchiveAction] requires the model to use the [CanBeArchived] trait.');
            }

            return $record->isArchived();
        });

        $this->requiresConfirmation();

        $this->action(function (): void {
            $result = $this->process(function (Model $record): bool {
                if ($this->shouldDeleteInsteadOfArchive($record)) {
                    return (bool) $record->delete();
                }

                if (! method_exists($record, 'archive')) {
                    throw new Exception('The [ArchiveAction] requires the model to use the [CanBeArchived] trait.');
                }

                return $record->archive();
            });

            if (! $result) {
                $this->failure();

                return;
            }

            $this->success();
        });

        $this->authorize(function (Model $record, Component $livewire): bool {
            if ((! $livewire instanceof EditRecord) && (! $livewire instanceof ViewRecord)) {
                throw new Exception('Unsupported Livewire component for [ArchiveAction] authorization. It must be used within [EditRecord] or [ViewRecord], or a custom [authorize()] function must be used.');
            }

            if ((! $this->shouldDeleteInsteadOfArchive($record)) && $this->hasArchivePolicyMethod($record)) {
                return $livewire::getResource()::can('archive', $record);
            }

            return $livewire::getResource()::can('delete', $record);
        });

        $this->successRedirectUrl(function (Component $livewire): string {
            if ((! $livewire instanceof EditRecord) && (! $livewire instanceof ViewRecord)) {
                throw new Exception('Unsupported Livewire component for [ArchiveAction] redirect. It must be used within [EditRecord] or [ViewRecord], or a custom [successRedirectUrl()] function must be used.');
            }

            return $livewire::getResource()::getUrl('index');
        });
    }

    public static function getDefaultName(): ?string
    {
        return 'archive';
    }

    /**
     * Archiving exists to preserve records that are still referenced elsewhere.
     * A model may define an optional `isUsed()` method to report whether the
     * individual record is still referenced, in which case unreferenced records
     * are safe to delete outright instead of being archived.
     */
    public function shouldDeleteInsteadOfArchive(?Model $record = null): bool
    {
        $record ??= $this->getRecord();

        if (! $record instanceof Model) {
            return false;
        }

        if (! method_exists($record, 'isUsed')) {
            return false;
        }

        return ! $record->isUsed();
    }

    protected function hasArchivePolicyMethod(Model $record): bool
    {
        $policy = Gate::getPolicyFor($record::class);

        return filled($policy) && method_exists($policy, 'archive');
    }
}
