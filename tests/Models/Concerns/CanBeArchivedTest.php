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
use OwenIt\Auditing\Models\Audit;
use Workbench\App\Models\Article;
use Workbench\App\Models\AuditableProject;
use Workbench\App\Models\AuditableProjectWithoutArchiveAudits;
use Workbench\App\Models\Project;
use Workbench\App\Models\Review;
use Workbench\App\Models\Task;

it('can archive a model', function (): void {
    $project = Project::factory()->create();

    expect($project->isArchived())->toBeFalse();
    expect($project->archived_at)->toBeNull();

    $result = $project->archive();

    expect($result)->toBeTrue();
    expect($project->isArchived())->toBeTrue();
    expect($project->archived_at)->toBeInstanceOf(Carbon::class);
});

it('can unarchive a model', function (): void {
    $project = Project::factory()->create();
    $project->archive();

    expect($project->isArchived())->toBeTrue();

    $result = $project->unarchive();

    expect($result)->toBeTrue();
    expect($project->isArchived())->toBeFalse();
    expect($project->archived_at)->toBeNull();
});

it('fires archiving and archived events when archiving', function (): void {
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

it('fires unarchiving and unarchived events when unarchiving', function (): void {
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

it('can archive quietly without firing events', function (): void {
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

it('can unarchive quietly without firing events', function (): void {
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

it('can prevent archiving by returning false from archiving event', function (): void {
    Project::archiving(function (): bool {
        return false;
    });

    $project = Project::factory()->create();
    $result = $project->archive();

    expect($result)->toBeFalse();
    expect($project->isArchived())->toBeFalse();
});

it('can prevent unarchiving by returning false from unarchiving event', function (): void {
    Project::unarchiving(function (): bool {
        return false;
    });

    $project = Project::factory()->create();
    $project->archiveQuietly();
    $result = $project->unarchive();

    expect($result)->toBeFalse();
    expect($project->isArchived())->toBeTrue();
});

it('updates the `updated_at` timestamp when archiving', function (): void {
    $project = Project::factory()->create();
    $originalUpdatedAt = $project->updated_at;

    Carbon::setTestNow(now()->addMinute());

    $project->archive();

    expect($project->updated_at->gt($originalUpdatedAt))->toBeTrue();

    Carbon::setTestNow();
});

it('updates the `updated_at` timestamp when unarchiving', function (): void {
    $project = Project::factory()->create();
    $project->archive();
    $originalUpdatedAt = $project->updated_at;

    Carbon::setTestNow(now()->addMinute());

    $project->unarchive();

    expect($project->updated_at->gt($originalUpdatedAt))->toBeTrue();
    expect($project->fresh()->updated_at->gt($originalUpdatedAt))->toBeTrue();

    Carbon::setTestNow();
});

it('does not persist other pending changes when archiving', function (): void {
    $project = Project::factory()->create(['name' => 'Apollo']);

    $project->name = 'Gemini';

    $project->archive();

    expect($project->fresh()->name)->toBe('Apollo');
    expect($project->fresh()->isArchived())->toBeTrue();
    expect($project->isDirty('name'))->toBeTrue();
});

it('does not persist other pending changes when unarchiving', function (): void {
    $project = Project::factory()->create(['name' => 'Apollo']);
    $project->archive();

    $project->name = 'Gemini';

    $project->unarchive();

    expect($project->fresh()->name)->toBe('Apollo');
    expect($project->fresh()->isArchived())->toBeFalse();
    expect($project->isDirty('name'))->toBeTrue();
});

it('reports the archive column as changed after archiving', function (): void {
    $project = Project::factory()->create()->fresh();

    expect($project->wasChanged('archived_at'))->toBeFalse();

    $project->archive();

    expect($project->wasChanged('archived_at'))->toBeTrue()
        ->and($project->getPrevious())->toHaveKey('archived_at')
        ->and($project->getPrevious()['archived_at'])->toBeNull();
});

it('reports the previous archive time after unarchiving', function (): void {
    $project = Project::factory()->create();
    $project->archive();

    $project = $project->fresh();
    $archivedAt = $project->getRawOriginal('archived_at');

    expect($archivedAt)->not->toBeNull();

    $project->unarchive();

    expect($project->getPrevious()['archived_at'])->toBe($archivedAt);
});

it('does not report unsaved attributes as changed after archiving', function (): void {
    $project = Project::factory()->create(['name' => 'Apollo']);

    $project->name = 'Gemini';

    $project->archive();

    expect($project->getChanges())->toHaveKey('archived_at')
        ->not->toHaveKey('name');
});

it('archives a model with a pending attribute that is not a column', function (): void {
    $article = Article::factory()->create();

    $article->reviews_exists = false;

    expect($article->archive())->toBeTrue();
    expect($article->fresh()->isArchived())->toBeTrue();
});

it('unarchives a model with a pending attribute that is not a column', function (): void {
    $article = Article::factory()->create();
    $article->archive();

    $article->reviews_exists = false;

    expect($article->unarchive())->toBeTrue();
    expect($article->fresh()->isArchived())->toBeFalse();
});

it('can bulk archive models using query builder', function (): void {
    Project::factory()->count(3)->create();

    Project::query()->archive();

    expect(Project::withoutArchived()->count())->toBe(0);
    expect(Project::onlyArchived()->count())->toBe(3);
});

it('can bulk unarchive models using query builder', function (): void {
    Project::factory()->count(3)->create();
    Project::query()->archive();

    expect(Project::onlyArchived()->count())->toBe(3);

    Project::onlyArchived()->unarchive();

    expect(Project::withoutArchived()->count())->toBe(3);
    expect(Project::onlyArchived()->count())->toBe(0);
});

it('includes archived models in queries by default', function (): void {
    Project::factory()->create();
    $archivedProject = Project::factory()->create();
    $archivedProject->archive();

    expect(Project::count())->toBe(2);
});

it('can exclude archived models with `withoutArchived` scope', function (): void {
    $activeProject = Project::factory()->create();
    $archivedProject = Project::factory()->create();
    $archivedProject->archive();

    $projects = Project::withoutArchived()->get();

    expect($projects)->toHaveCount(1);
    expect($projects->first()->id)->toBe($activeProject->id);
});

it('can get only archived models with `onlyArchived` scope', function (): void {
    Project::factory()->create();
    $archivedProject = Project::factory()->create();
    $archivedProject->archive();

    $projects = Project::onlyArchived()->get();

    expect($projects)->toHaveCount(1);
    expect($projects->first()->id)->toBe($archivedProject->id);
});

it('excludes archived and unused models with `withoutArchivedAndUnused` scope', function (): void {
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

it('throws `BadMethodCallException` when using `withoutArchivedAndUnused` without a `used` method', function (): void {
    Task::query()->withoutArchivedAndUnused();
})->throws(BadMethodCallException::class);

it('reads an eager-loaded relation existence attribute in `isUsed()` instead of querying', function (): void {
    $article = Article::factory()->create();
    Review::factory()->create(['article_id' => $article->id]);

    $article = Article::query()->withExists('reviews')->find($article->getKey());

    Review::query()->delete();

    expect($article->isUsed())->toBeTrue();
});

it('memoizes the relation existence in `isUsed()` after the first check', function (): void {
    $article = Article::factory()->create();

    expect($article->isUsed())->toBeFalse();

    Review::factory()->create(['article_id' => $article->id]);

    expect($article->isUsed())->toBeFalse();
    expect($article->fresh()->isUsed())->toBeTrue();
});

it('does not mark the model as dirty when memoizing the relation existence in `isUsed()`', function (): void {
    $article = Article::factory()->create();

    expect($article->isUsed())->toBeFalse();
    expect($article->isDirty())->toBeFalse();
});

describe('auditing', function (): void {
    beforeEach(function (): void {
        config()->set('audit.console', true);
    });

    it('records an `archived` audit when archiving', function (bool $isQueued): void {
        config()->set('audit.queue.enable', $isQueued);

        $project = AuditableProject::create(['title' => 'Project']);

        expect($project->audits()->where('event', 'archived')->exists())->toBeFalse();

        $project->archive();

        $audit = $project->audits()->where('event', 'archived')->sole();

        expect($audit->old_values)->toBe(['archived_at' => null])
            ->and($audit->new_values)->toBe(['archived_at' => $project->fresh()->getRawOriginal('archived_at')]);
    })->with(['queued' => true, 'not queued' => false]);

    it('records an `unarchived` audit when unarchiving', function (bool $isQueued): void {
        config()->set('audit.queue.enable', $isQueued);

        $project = AuditableProject::create(['title' => 'Project']);
        $project->archive();

        $archivedAt = $project->fresh()->getRawOriginal('archived_at');

        expect($archivedAt)->not->toBeNull()
            ->and($project->audits()->where('event', 'unarchived')->exists())->toBeFalse();

        $project->unarchive();

        $audit = $project->audits()->where('event', 'unarchived')->sole();

        expect($audit->old_values)->toBe(['archived_at' => $archivedAt])
            ->and($audit->new_values)->toBe(['archived_at' => null]);
    })->with(['queued' => true, 'not queued' => false]);

    it('records the previous archive time when archiving an already-archived model', function (): void {
        $project = AuditableProject::create(['title' => 'Project']);

        expect($project->audits()->where('event', 'archived')->exists())->toBeFalse();

        $project->archive();

        $this->travel(1)->minutes();

        $project->archive();

        $audits = $project->audits()->where('event', 'archived')->oldest('id')->get();

        expect($audits)->toHaveCount(2)
            ->and($audits[1]->old_values['archived_at'])->not->toBeNull()
            ->and($audits[1]->old_values['archived_at'])->toBe($audits[0]->new_values['archived_at'])
            ->and($audits[1]->new_values['archived_at'])->not->toBe($audits[0]->new_values['archived_at']);
    });

    it('does not record an `unarchived` audit when unarchiving a model that is not archived', function (): void {
        $project = AuditableProject::create(['title' => 'Project'])->fresh();

        expect($project->unarchive())->toBeTrue()
            ->and($project->audits()->where('event', 'unarchived')->exists())->toBeFalse();
    });

    it('does not record an `archived` audit when the archive time does not change', function (): void {
        $this->freezeTime();

        $project = AuditableProject::create(['title' => 'Project']);

        $project->archive();
        $project->archive();

        expect($project->audits()->where('event', 'archived')->count())->toBe(1);
    });

    it('does not record an `updated` audit when archiving or unarchiving', function (): void {
        $project = AuditableProject::create(['title' => 'Project']);

        expect($project->audits()->where('event', 'updated')->exists())->toBeFalse();

        $project->archive();
        $project->unarchive();

        expect($project->audits()->where('event', 'updated')->exists())->toBeFalse()
            ->and($project->audits()->where('event', 'archived')->count())->toBe(1)
            ->and($project->audits()->where('event', 'unarchived')->count())->toBe(1);
    });

    it('records the archive alongside an `updated` audit from an `archiving` hook', function (): void {
        AuditableProject::archiving(function (AuditableProject $project): void {
            $project->update(['title' => 'Renamed']);
        });

        $project = AuditableProject::create(['title' => 'Project']);

        expect($project->audits()->whereIn('event', ['updated', 'archived'])->exists())->toBeFalse();

        $project->archive();

        $updatedAudit = $project->audits()->where('event', 'updated')->sole();

        expect($updatedAudit->new_values)->toHaveKey('title')
            ->not->toHaveKey('archived_at')
            ->and($project->audits()->where('event', 'archived')->count())->toBe(1);
    });

    it('does not record an audit when archiving is cancelled', function (): void {
        $isCancelling = true;

        AuditableProject::archiving(function () use (&$isCancelling): ?bool {
            return $isCancelling ? false : null;
        });

        $project = AuditableProject::create(['title' => 'Project']);

        expect($project->audits()->where('event', 'archived')->exists())->toBeFalse();

        expect($project->archive())->toBeFalse()
            ->and($project->audits()->where('event', 'archived')->exists())->toBeFalse();

        $isCancelling = false;

        expect($project->archive())->toBeTrue();

        $audit = $project->audits()->where('event', 'archived')->sole();

        expect($audit->old_values)->toBe(['archived_at' => null]);
    });

    it('leaves the model clean after recording the audit', function (): void {
        $project = AuditableProject::create(['title' => 'Project']);

        expect($project->audits()->where('event', 'updated')->exists())->toBeFalse();

        $project->archive();

        expect($project->isDirty())->toBeFalse();

        $project->update(['title' => 'X']);

        $audit = $project->audits()->where('event', 'updated')->sole();

        expect($audit->new_values)->toHaveKey('title')
            ->not->toHaveKey('archived_at')
            ->and($audit->old_values)->not->toHaveKey('archived_at');
    });

    it('does not record an audit when archiving or unarchiving quietly', function (): void {
        $project = AuditableProject::create(['title' => 'Project']);

        expect($project->audits()->whereIn('event', ['archived', 'unarchived'])->exists())->toBeFalse();

        $project->archiveQuietly();
        $project->unarchiveQuietly();

        expect($project->audits()->whereIn('event', ['archived', 'unarchived'])->exists())->toBeFalse();
    });

    it('does not record an audit when auditing is disabled in the console', function (): void {
        config()->set('audit.console', false);

        $project = AuditableProject::create(['title' => 'Project']);

        expect($project->audits()->whereIn('event', ['archived', 'unarchived'])->exists())->toBeFalse();

        $project->archive();
        $project->unarchive();

        expect($project->audits()->whereIn('event', ['archived', 'unarchived'])->exists())->toBeFalse();
    });

    it('does not record archive audits for a model whose `$auditEvents` omits them', function (): void {
        $project = AuditableProjectWithoutArchiveAudits::create(['title' => 'Project']);

        expect($project->audits()->where('event', 'created')->exists())->toBeTrue()
            ->and($project->audits()->whereIn('event', ['archived', 'unarchived'])->exists())->toBeFalse();

        $project->archive();
        $project->unarchive();

        expect($project->audits()->whereIn('event', ['archived', 'unarchived'])->exists())->toBeFalse();
    });

    it('does not record an audit for a model that is not auditable', function (): void {
        $project = Project::factory()->create();

        $auditCount = Audit::count();

        expect($project->archive())->toBeTrue()
            ->and($project->unarchive())->toBeTrue()
            ->and(Audit::count())->toBe($auditCount);
    });

    it('does not record an audit when bulk archiving or unarchiving with the query builder', function (): void {
        $project = AuditableProject::create(['title' => 'Project']);

        expect($project->audits()->whereIn('event', ['archived', 'unarchived'])->exists())->toBeFalse();

        AuditableProject::query()->archive();

        expect($project->fresh()->isArchived())->toBeTrue();

        AuditableProject::query()->unarchive();

        expect($project->fresh()->isArchived())->toBeFalse()
            ->and($project->audits()->whereIn('event', ['archived', 'unarchived'])->exists())->toBeFalse();
    });
});
