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

use Illuminate\Support\Carbon;
use Workbench\App\Models\Project;
use Workbench\App\Models\Task;

it('can archive a model', function () {
    $project = Project::factory()->create();

    expect($project->isArchived())->toBeFalse();
    expect($project->archived_at)->toBeNull();

    $result = $project->archive();

    expect($result)->toBeTrue();
    expect($project->isArchived())->toBeTrue();
    expect($project->archived_at)->toBeInstanceOf(Carbon::class);
});

it('can exclude archived models with `withoutArchived` scope', function () {
    $activeProject = Project::factory()->create();
    $archivedProject = Project::factory()->create();
    $archivedProject->archive();

    $projects = Project::withoutArchived()->get();

    expect($projects)->toHaveCount(1);
    expect($projects->first()->id)->toBe($activeProject->id);
});

it('can get only archived models with `onlyArchived` scope', function () {
    Project::factory()->create();
    $archivedProject = Project::factory()->create();
    $archivedProject->archive();

    $projects = Project::onlyArchived()->get();

    expect($projects)->toHaveCount(1);
    expect($projects->first()->id)->toBe($archivedProject->id);
});

it('can unarchive a model', function () {
    $project = Project::factory()->create();
    $project->archive();

    expect($project->isArchived())->toBeTrue();

    $result = $project->unarchive();

    expect($result)->toBeTrue();
    expect($project->isArchived())->toBeFalse();
    expect($project->archived_at)->toBeNull();
});

it('fires archiving and archived events when archiving', function () {
    $archivingFired = false;
    $archivedFired = false;

    Project::archiving(function () use (&$archivingFired): void {
        $archivingFired = true;
    });

    Project::archived(function () use (&$archivedFired): void {
        $archivedFired = true;
    });

    $project = Project::factory()->create();
    $project->archive();

    expect($archivingFired)->toBeTrue();
    expect($archivedFired)->toBeTrue();
});

it('fires unarchiving and unarchived events when unarchiving', function () {
    $unarchivingFired = false;
    $unarchivedFired = false;

    Project::unarchiving(function () use (&$unarchivingFired): void {
        $unarchivingFired = true;
    });

    Project::unarchived(function () use (&$unarchivedFired): void {
        $unarchivedFired = true;
    });

    $project = Project::factory()->create();
    $project->archive();
    $project->unarchive();

    expect($unarchivingFired)->toBeTrue();
    expect($unarchivedFired)->toBeTrue();
});

it('can archive quietly without firing events', function () {
    $archivingFired = false;
    $archivedFired = false;

    Project::archiving(function () use (&$archivingFired): void {
        $archivingFired = true;
    });

    Project::archived(function () use (&$archivedFired): void {
        $archivedFired = true;
    });

    $project = Project::factory()->create();
    $project->archiveQuietly();

    expect($project->isArchived())->toBeTrue();
    expect($archivingFired)->toBeFalse();
    expect($archivedFired)->toBeFalse();
});

it('can unarchive quietly without firing events', function () {
    $unarchivingFired = false;
    $unarchivedFired = false;

    Project::unarchiving(function () use (&$unarchivingFired): void {
        $unarchivingFired = true;
    });

    Project::unarchived(function () use (&$unarchivedFired): void {
        $unarchivedFired = true;
    });

    $project = Project::factory()->create();
    $project->archiveQuietly();
    $project->unarchiveQuietly();

    expect($project->isArchived())->toBeFalse();
    expect($unarchivingFired)->toBeFalse();
    expect($unarchivedFired)->toBeFalse();
});

it('can prevent archiving by returning false from archiving event', function () {
    Project::archiving(function (): bool {
        return false;
    });

    $project = Project::factory()->create();
    $result = $project->archive();

    expect($result)->toBeFalse();
    expect($project->isArchived())->toBeFalse();
});

it('can prevent unarchiving by returning false from unarchiving event', function () {
    Project::unarchiving(function (): bool {
        return false;
    });

    $project = Project::factory()->create();
    $project->archiveQuietly();
    $result = $project->unarchive();

    expect($result)->toBeFalse();
    expect($project->isArchived())->toBeTrue();
});

it('updates the `updated_at` timestamp when archiving', function () {
    $project = Project::factory()->create();
    $originalUpdatedAt = $project->updated_at;

    Carbon::setTestNow(now()->addMinute());

    $project->archive();

    expect($project->updated_at->gt($originalUpdatedAt))->toBeTrue();

    Carbon::setTestNow();
});

it('can bulk archive models using query builder', function () {
    Project::factory()->count(3)->create();

    Project::query()->archive();

    expect(Project::withoutArchived()->count())->toBe(0);
    expect(Project::onlyArchived()->count())->toBe(3);
});

it('can bulk unarchive models using query builder', function () {
    Project::factory()->count(3)->create();
    Project::query()->archive();

    expect(Project::onlyArchived()->count())->toBe(3);

    Project::onlyArchived()->unarchive();

    expect(Project::withoutArchived()->count())->toBe(3);
    expect(Project::onlyArchived()->count())->toBe(0);
});

it('includes archived models in queries by default', function () {
    Project::factory()->create();
    $archivedProject = Project::factory()->create();
    $archivedProject->archive();

    expect(Project::count())->toBe(2);
});

it('excludes archived and unused models with `withoutArchivedAndUnused` scope', function () {
    $activeProject = Project::factory()->create();

    $archivedUsedProject = Project::factory()->create();
    Task::factory()->create(['project_id' => $archivedUsedProject->id]);
    $archivedUsedProject->archive();

    $archivedUnusedProject = Project::factory()->create();
    $archivedUnusedProject->archive();

    $projects = Project::query()->withoutArchivedAndUnused()->get();

    expect($projects)->toHaveCount(2);
    expect($projects->pluck('id')->all())->toEqualCanonicalizing([
        $activeProject->id,
        $archivedUsedProject->id,
    ]);
});

it('throws `BadMethodCallException` when using `withoutArchivedAndUnused` without a `used` method', function () {
    Task::query()->withoutArchivedAndUnused();
})->throws(BadMethodCallException::class);
