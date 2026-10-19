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

use CanyonGBS\Common\Filament\Actions\UnarchiveAction;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Livewire\Livewire;
use Workbench\App\Filament\Resources\Articles\Pages\EditArticle;
use Workbench\App\Filament\Resources\Projects\Pages\EditProject;
use Workbench\App\Filament\Resources\Projects\Pages\ViewProject;
use Workbench\App\Models\Article;
use Workbench\App\Models\Project;
use Workbench\App\Models\Review;
use Workbench\App\Models\Task;
use Workbench\App\Models\User;
use Workbench\App\Policies\ArticlePolicy;
use Workbench\App\Policies\ProjectPolicy;

beforeEach(function () {
    ProjectPolicy::reset();
    ArticlePolicy::reset();

    Filament::setCurrentPanel('testing');

    $this->actingAs(User::create(['name' => 'Ada', 'email' => 'ada@example.com']));
});

it('looks like an unarchive action', function () {
    $project = Project::factory()->create(['name' => 'Apollo']);
    $project->archive();

    Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
        ->assertActionHasLabel('unarchive', 'Unarchive')
        ->assertActionHasColor('unarchive', 'gray')
        ->assertActionExists('unarchive', fn (Action $action): bool => ((string) $action->getModalHeading()) === 'Unarchive Apollo')
        ->assertActionExists('unarchive', fn (Action $action): bool => $action->getModalSubmitActionLabel() === 'Unarchive')
        ->assertActionExists('unarchive', fn (Action $action): bool => $action->getModalIcon() === Heroicon::OutlinedArchiveBoxXMark)
        ->assertActionExists('unarchive', fn (Action $action): bool => $action->getGroupedIcon() === Heroicon::ArchiveBoxXMark);
});

it('unarchives the record without redirecting', function () {
    $project = Project::factory()->create();
    $project->archive();

    Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
        ->callAction('unarchive')
        ->assertNotified('Unarchived')
        ->assertNoRedirect();

    expect($project->refresh()->isArchived())->toBeFalse();
});

it('unarchives the record from the view page', function () {
    $project = Project::factory()->create();
    $project->archive();

    Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
        ->callAction('unarchive')
        ->assertNotified('Unarchived')
        ->assertNoRedirect();

    expect($project->refresh()->isArchived())->toBeFalse();
});

it('is hidden when the record is not archived', function () {
    $project = Project::factory()->create();

    Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
        ->assertActionHidden('unarchive');
});

it('does not unarchive when the unarchiving event is cancelled', function () {
    Project::unarchiving(fn (): bool => false);

    $project = Project::factory()->create();
    $project->archive();

    Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
        ->callAction('unarchive')
        ->assertNotNotified();

    expect($project->refresh()->isArchived())->toBeTrue();
});

it('throws when the model does not use the `CanBeArchived` trait', function () {
    $task = Task::factory()->create();

    UnarchiveAction::make()
        ->record($task)
        ->isHidden();
})->throws(Exception::class, 'The [UnarchiveAction] requires the model to use the [CanBeArchived] trait.');

describe('authorization', function () {
    it('is hidden when the user is not authorized to unarchive with the policy\'s `archive` method', function () {
        ArticlePolicy::$abilities['archive'] = false;

        $article = Article::factory()->create();
        $article->archive();

        Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
            ->assertActionHidden('unarchive');
    });

    it('ignores the delete ability for a used record when the policy has an `archive` method', function () {
        ArticlePolicy::$abilities['delete'] = false;

        $article = Article::factory()->create();
        Review::factory()->create(['article_id' => $article->id]);
        $article->archive();

        Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
            ->assertActionVisible('unarchive')
            ->callAction('unarchive')
            ->assertNotified('Unarchived');

        expect($article->refresh()->isArchived())->toBeFalse();
    });

    it('ignores the delete ability for an unused record when the policy has an `archive` method', function () {
        ArticlePolicy::$abilities['delete'] = false;

        $article = Article::factory()->create();
        $article->archive();

        Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
            ->assertActionVisible('unarchive')
            ->callAction('unarchive')
            ->assertNotified('Unarchived');

        expect($article->refresh()->isArchived())->toBeFalse();
    });
});

describe('backwards compatibility', function () {
    it('is hidden when the user is not authorized to delete', function () {
        ProjectPolicy::$abilities['delete'] = false;

        $project = Project::factory()->create();
        $project->archive();

        Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
            ->assertActionHidden('unarchive');
    });

    it('unarchives a record using the delete ability when the policy has no `archive` method', function () {
        $project = Project::factory()->create();
        $project->archive();

        Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
            ->assertActionVisible('unarchive')
            ->callAction('unarchive')
            ->assertNotified('Unarchived');

        expect($project->refresh()->isArchived())->toBeFalse();
    });
});
