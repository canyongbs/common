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
use Filament\Actions\BulkAction;
use Filament\Actions\Concerns\CanCustomizeProcess;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Throwable;

class UnarchiveBulkAction extends BulkAction
{
    use CanCustomizeProcess;
    use ResolvesArchiveAbility;

    protected int $unarchivedCount = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Unarchive');

        $this->modalHeading(fn (): string => "Unarchive {$this->getTitleCasePluralModelLabel()}");

        $this->modalSubmitActionLabel('Unarchive');

        $this->successNotificationTitle(fn (): string => $this->getCountsNotificationTitle());

        $this->failureNotificationTitle(fn (): string => $this->getCountsNotificationTitle());

        $this->missingBulkAuthorizationFailureNotificationMessage(fn (int $failureCount): string => ($failureCount === 1)
            ? '1 record could not be processed because you are not authorized to unarchive it.'
            : "{$failureCount} records could not be processed because you are not authorized to unarchive them.");

        $this->missingBulkProcessingFailureNotificationMessage(fn (int $failureCount): string => ($failureCount === 1)
            ? '1 record failed to process.'
            : "{$failureCount} records failed to process.");

        $this->authorize('deleteAny');

        $this->defaultColor('gray');

        $this->icon(Heroicon::ArchiveBoxXMark);

        $this->requiresConfirmation();

        $this->modalIcon(Heroicon::OutlinedArchiveBoxXMark);

        $this->action(function (): void {
            $this->unarchivedCount = 0;

            $this->process(static function (UnarchiveBulkAction $action): void {
                $model = $action->getModel();

                if (! method_exists($model, 'unarchive')) {
                    throw new Exception('The [UnarchiveBulkAction] requires the model to use the [CanBeArchived] trait.');
                }

                if (! $action->shouldFetchSelectedRecords()) {
                    // Initializes the selection counts used by the notifications.
                    $action->getSelectedRecords();

                    try {
                        $action->unarchivedCount = $action->getSelectedRecordsQuery()->onlyArchived()->unarchive(); /** @phpstan-ignore method.notFound */
                    } catch (Throwable $exception) {
                        $action->reportCompleteBulkProcessingFailure();

                        report($exception);
                    }

                    return;
                }

                $records = $action->getIndividuallyAuthorizedSelectedRecords();

                $isFirstException = true;

                $records->each(static function (Model $record) use ($action, &$isFirstException): void {
                    if (! $record->isArchived()) { /** @phpstan-ignore method.notFound */
                        return;
                    }

                    try {
                        $record->unarchive() /** @phpstan-ignore method.notFound */
                            ? $action->unarchivedCount++
                            : $action->reportBulkProcessingFailure();
                    } catch (Throwable $exception) {
                        $action->reportBulkProcessingFailure();

                        if ($isFirstException) {
                            // Only report the first exception to avoid flooding the logs.
                            report($exception);

                            $isFirstException = false;
                        }
                    }
                });
            });
        });

        $this->deselectRecordsAfterCompletion();
    }

    public static function getDefaultName(): ?string
    {
        return 'unarchive';
    }

    public function getIndividualRecordAuthorizationResponse(Model $record): Response
    {
        if ($this->authorizeIndividualRecords === 'delete') {
            return Gate::inspect($this->getArchiveAbility($record), [$record]);
        }

        return parent::getIndividualRecordAuthorizationResponse($record);
    }

    protected function getCountsNotificationTitle(): string
    {
        if (! $this->unarchivedCount) {
            return 'No records were unarchived';
        }

        return "{$this->unarchivedCount} unarchived";
    }
}
