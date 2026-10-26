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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Throwable;

class ArchiveBulkAction extends BulkAction
{
    use CanCustomizeProcess;
    use ResolvesArchiveAbility;

    protected int $archivedCount = 0;

    protected int $deletedCount = 0;

    /**
     * @var array<array-key, int> | null
     */
    protected ?array $usedKeys = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(fn (): string => $this->shouldDeleteUnusedRecords() ? 'Archive / Delete' : 'Archive');

        $this->modalHeading(fn (): string => "Archive {$this->getTitleCasePluralModelLabel()}");

        $this->modalSubmitActionLabel('Archive');

        $this->modalDescription(function (): ?string {
            if (! $this->shouldDeleteUnusedRecords()) {
                return null;
            }

            return 'Records that are no longer in use will be permanently deleted instead of archived.';
        });

        $this->successNotificationTitle(fn (): string => $this->getCountsNotificationTitle());

        $this->failureNotificationTitle(fn (): string => $this->getCountsNotificationTitle());

        $this->missingBulkAuthorizationFailureNotificationMessage(fn (int $failureCount): string => ($failureCount === 1)
            ? '1 record could not be processed because you are not authorized to delete it.'
            : "{$failureCount} records could not be processed because you are not authorized to delete them.");

        $this->missingBulkProcessingFailureNotificationMessage(fn (int $failureCount): string => ($failureCount === 1)
            ? '1 record failed to process.'
            : "{$failureCount} records failed to process.");

        $this->authorize('deleteAny');

        $this->defaultColor('warning');

        $this->icon(Heroicon::ArchiveBox);

        $this->requiresConfirmation();

        $this->modalIcon(Heroicon::OutlinedArchiveBox);

        $this->action(function (): void {
            $this->archivedCount = 0;
            $this->deletedCount = 0;
            $this->usedKeys = null;

            $this->process(static function (ArchiveBulkAction $action): void {
                $model = $action->getModel();

                if (! method_exists($model, 'archive')) {
                    throw new Exception('The [ArchiveBulkAction] requires the model to use the [CanBeArchived] trait.');
                }

                $modelInstance = new $model();

                $shouldDeleteUnused = $action->shouldDeleteUnusedRecords();

                if (! $action->shouldFetchSelectedRecords()) {
                    // Initializes the selection counts used by the notifications.
                    $action->getSelectedRecords();

                    // Archives with a single query, which fires no model events, so `archiving` hooks and audits do not run.
                    try {
                        $query = $action->getSelectedRecordsQuery();

                        if ($shouldDeleteUnused) {
                            $action->archivedCount = $query->clone()
                                ->where(fn (Builder $query) => $modelInstance->used($query)) /** @phpstan-ignore method.notFound */
                                ->withoutArchived() /** @phpstan-ignore method.notFound */
                                ->archive();
                            $action->deletedCount = $query->clone()
                                ->whereNot(fn (Builder $query) => $modelInstance->used($query)) /** @phpstan-ignore method.notFound */
                                ->delete();
                        } else {
                            $action->archivedCount = $query->withoutArchived()->archive(); /** @phpstan-ignore method.notFound */
                        }
                    } catch (Throwable $exception) {
                        $action->reportCompleteBulkProcessingFailure();

                        report($exception);
                    }

                    return;
                }

                // Partition before authorizing, as it decides which ability each record is checked with.
                $action->usedKeys = $shouldDeleteUnused
                    ? $action->getSelectedRecordsQuery()
                        ->where(fn (Builder $query) => $modelInstance->used($query)) /** @phpstan-ignore method.notFound */
                        ->pluck($modelInstance->getQualifiedKeyName())
                        ->flip()
                        ->all()
                    : null;

                $records = $action->getIndividuallyAuthorizedSelectedRecords();

                $isFirstException = true;

                $records->each(static function (Model $record) use ($action, &$isFirstException): void {
                    $shouldArchive = ($action->usedKeys === null) || array_key_exists($record->getKey(), $action->usedKeys);

                    if ($shouldArchive && $record->isArchived()) { /** @phpstan-ignore method.notFound */
                        return;
                    }

                    try {
                        if ($shouldArchive) {
                            $record->archive() /** @phpstan-ignore method.notFound */
                                ? $action->archivedCount++
                                : $action->reportBulkProcessingFailure();
                        } else {
                            $record->delete()
                                ? $action->deletedCount++
                                : $action->reportBulkProcessingFailure();
                        }
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
        return 'archive';
    }

    /**
     * Models opt into deleting unused records by defining a `used()` scope.
     */
    public function shouldDeleteUnusedRecords(): bool
    {
        $model = $this->getModel();

        return filled($model) && method_exists($model, 'used');
    }

    public function getIndividualRecordAuthorizationResponse(Model $record): Response
    {
        if (($this->authorizeIndividualRecords === 'delete') && $this->isRecordPlannedToBeArchived($record)) {
            return Gate::inspect($this->getArchiveAbility($record), [$record]);
        }

        return parent::getIndividualRecordAuthorizationResponse($record);
    }

    protected function isRecordPlannedToBeArchived(Model $record): bool
    {
        if (! $this->shouldDeleteUnusedRecords()) {
            return true;
        }

        return array_key_exists($record->getKey(), $this->usedKeys ?? []);
    }

    protected function getCountsNotificationTitle(): string
    {
        $parts = [];

        if ($this->archivedCount) {
            $parts[] = "{$this->archivedCount} archived";
        }

        if ($this->deletedCount) {
            $parts[] = "{$this->deletedCount} deleted";
        }

        if ($parts === []) {
            return 'No records were archived or deleted';
        }

        return implode(', ', $parts);
    }
}
