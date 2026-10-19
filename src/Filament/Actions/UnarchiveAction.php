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

use CanyonGBS\Common\Filament\Actions\Concerns\ResolvesArchiveAbility;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\Concerns\CanCustomizeProcess;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

class UnarchiveAction extends Action
{
    use CanCustomizeProcess;
    use ResolvesArchiveAbility;

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Unarchive');

        $this->modalHeading(fn (): string => "Unarchive {$this->getRecordTitle()}");

        $this->modalSubmitActionLabel('Unarchive');

        $this->successNotificationTitle('Unarchived');

        $this->defaultColor('gray');

        $this->groupedIcon(Heroicon::ArchiveBoxXMark);

        $this->modalIcon(Heroicon::OutlinedArchiveBoxXMark);

        $this->hidden(function (Model $record): bool {
            if (! method_exists($record, 'isArchived')) {
                throw new Exception('The [UnarchiveAction] requires the model to use the [CanBeArchived] trait.');
            }

            return ! $record->isArchived();
        });

        $this->requiresConfirmation();

        $this->action(function (): void {
            $result = $this->process(function (Model $record): bool {
                if (! method_exists($record, 'unarchive')) {
                    throw new Exception('The [UnarchiveAction] requires the model to use the [CanBeArchived] trait.');
                }

                return $record->unarchive();
            });

            if (! $result) {
                $this->failure();

                return;
            }

            $this->success();
        });

        $this->authorize(function (Model $record, Component $livewire): bool {
            if ((! $livewire instanceof EditRecord) && (! $livewire instanceof ViewRecord)) {
                throw new Exception('Unsupported Livewire component for [UnarchiveAction] authorization. It must be used within [EditRecord] or [ViewRecord], or a custom [authorize()] function must be used.');
            }

            return $livewire::getResource()::can($this->getArchiveAbility($record), $record);
        });
    }

    public static function getDefaultName(): ?string
    {
        return 'unarchive';
    }
}
