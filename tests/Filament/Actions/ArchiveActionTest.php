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

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Livewire\Livewire;
use Workbench\App\Filament\Resources\Articles\ArticleResource;
use Workbench\App\Filament\Resources\Articles\Pages\EditArticle;
use Workbench\App\Filament\Resources\Articles\Pages\ViewArticle;
use Workbench\App\Filament\Resources\Attachments\Pages\EditAttachment;
use Workbench\App\Filament\Resources\Projects\Pages\EditProject;
use Workbench\App\Filament\Resources\Projects\Pages\ViewProject;
use Workbench\App\Filament\Resources\Projects\ProjectResource;
use Workbench\App\Filament\Resources\Tags\Pages\EditTag;
use Workbench\App\Filament\Resources\Tasks\Pages\EditTask;
use Workbench\App\Models\Article;
use Workbench\App\Models\Attachment;
use Workbench\App\Models\Project;
use Workbench\App\Models\Review;
use Workbench\App\Models\Tag;
use Workbench\App\Models\Task;
use Workbench\App\Models\User;
use Workbench\App\Policies\ArticlePolicy;
use Workbench\App\Policies\AttachmentPolicy;
use Workbench\App\Policies\ProjectPolicy;
use Workbench\App\Policies\TagPolicy;

beforeEach(function () {
    ProjectPolicy::reset();
    ArticlePolicy::reset();
    AttachmentPolicy::reset();
    TagPolicy::reset();

    Filament::setCurrentPanel('testing');

    $this->actingAs(User::create(['name' => 'Ada', 'email' => 'ada@example.com']));
});

it('looks like an archive action when the model does not define `isUsed()`', function () {
    $project = Project::factory()->create(['name' => 'Apollo']);

    Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
        ->assertActionHasLabel('archive', 'Archive')
        ->assertActionHasColor('archive', 'warning')
        ->assertActionExists('archive', fn (Action $action): bool => ((string) $action->getModalHeading()) === 'Archive Apollo')
        ->assertActionExists('archive', fn (Action $action): bool => $action->getModalSubmitActionLabel() === 'Archive')
        ->assertActionExists('archive', fn (Action $action): bool => $action->getModalIcon() === Heroicon::OutlinedArchiveBox)
        ->assertActionExists('archive', fn (Action $action): bool => $action->getGroupedIcon() === Heroicon::ArchiveBox);
});

it('archives the record and redirects to the index page', function () {
    $project = Project::factory()->create();

    Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
        ->callAction('archive')
        ->assertNotified('Archived')
        ->assertRedirect(ProjectResource::getUrl('index'));

    expect($project->refresh()->isArchived())->toBeTrue();
});

it('archives the record from the view page', function () {
    $project = Project::factory()->create();

    Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
        ->callAction('archive')
        ->assertNotified('Archived')
        ->assertRedirect(ProjectResource::getUrl('index'));

    expect($project->refresh()->isArchived())->toBeTrue();
});

it('deletes an unused record from the view page', function () {
    $article = Article::factory()->create();

    Livewire::test(ViewArticle::class, ['record' => $article->getRouteKey()])
        ->assertActionHasLabel('archive', 'Delete')
        ->callAction('archive')
        ->assertNotified('Deleted')
        ->assertRedirect(ArticleResource::getUrl('index'));

    expect(Article::query()->whereKey($article->getKey())->exists())->toBeFalse();
});

it('is hidden when the record is already archived', function () {
    $project = Project::factory()->create();
    $project->archive();

    Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
        ->assertActionHidden('archive');
});

it('does not archive when the archiving event is cancelled', function () {
    Project::archiving(fn (): bool => false);

    $project = Project::factory()->create();

    Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
        ->callAction('archive')
        ->assertNotNotified();

    expect($project->refresh()->isArchived())->toBeFalse();
});

it('looks like an archive action when the record is used', function () {
    $article = Article::factory()->create(['name' => 'Signals']);
    Review::factory()->create(['article_id' => $article->id]);

    Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
        ->assertActionHasLabel('archive', 'Archive')
        ->assertActionHasColor('archive', 'warning')
        ->assertActionExists('archive', fn (Action $action): bool => ((string) $action->getModalHeading()) === 'Archive Signals');
});

it('looks like a delete action when the record is unused', function () {
    $article = Article::factory()->create(['name' => 'Signals']);

    Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
        ->assertActionHasLabel('archive', 'Delete')
        ->assertActionHasColor('archive', 'danger')
        ->assertActionExists('archive', fn (Action $action): bool => ((string) $action->getModalHeading()) === 'Delete Signals')
        ->assertActionExists('archive', fn (Action $action): bool => $action->getModalSubmitActionLabel() === 'Delete')
        ->assertActionExists('archive', fn (Action $action): bool => $action->getModalIcon() === Heroicon::OutlinedTrash)
        ->assertActionExists('archive', fn (Action $action): bool => $action->getGroupedIcon() === Heroicon::Trash);
});

it('archives a used record instead of deleting it', function () {
    $article = Article::factory()->create();
    Review::factory()->create(['article_id' => $article->id]);

    Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
        ->callAction('archive')
        ->assertNotified('Archived');

    expect($article->refresh()->isArchived())->toBeTrue();
});

it('deletes an unused record and redirects to the index page', function () {
    $article = Article::factory()->create();

    Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
        ->callAction('archive')
        ->assertNotified('Deleted')
        ->assertRedirect(ArticleResource::getUrl('index'));

    expect(Article::query()->whereKey($article->getKey())->exists())->toBeFalse();
});

it('remains visible as a delete action when an unused record is archived', function () {
    $article = Article::factory()->create();
    $article->archive();

    Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
        ->assertActionVisible('archive')
        ->assertActionHasLabel('archive', 'Delete');
});

it('is hidden when a used record is archived', function () {
    $article = Article::factory()->create();
    Review::factory()->create(['article_id' => $article->id]);
    $article->archive();

    Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
        ->assertActionHidden('archive');
});

it('is hidden when the user is not authorized to delete an unused record', function () {
    ArticlePolicy::$abilities['delete'] = false;

    $article = Article::factory()->create();

    Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
        ->assertActionHidden('archive');
});

it('is hidden when the user is not authorized to archive a used record with the policy\'s `archive` method', function () {
    ArticlePolicy::$abilities['archive'] = false;

    $article = Article::factory()->create();
    Review::factory()->create(['article_id' => $article->id]);

    Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
        ->assertActionHidden('archive');
});

it('ignores the delete ability for a used record when the policy has an `archive` method', function () {
    ArticlePolicy::$abilities['delete'] = false;

    $article = Article::factory()->create();
    Review::factory()->create(['article_id' => $article->id]);

    Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
        ->assertActionVisible('archive')
        ->callAction('archive')
        ->assertNotified('Archived');

    expect($article->refresh()->isArchived())->toBeTrue();
});

it('ignores the archive ability for an unused record', function () {
    ArticlePolicy::$abilities['archive'] = false;

    $article = Article::factory()->create();

    Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
        ->assertActionVisible('archive')
        ->callAction('archive')
        ->assertNotified('Deleted');

    expect(Article::query()->whereKey($article->getKey())->exists())->toBeFalse();
});

describe('backwards compatibility', function () {
    it('is hidden when the user is not authorized to delete', function () {
        ProjectPolicy::$abilities['delete'] = false;

        $project = Project::factory()->create();

        Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
            ->assertActionHidden('archive');
    });

    it('falls back to the delete ability for a used record when the policy has no `archive` method', function () {
        AttachmentPolicy::$abilities['delete'] = false;

        $attachment = Attachment::factory()->used()->create();

        Livewire::test(EditAttachment::class, ['record' => $attachment->getRouteKey()])
            ->assertActionHidden('archive');
    });

    it('archives a used record using the delete ability when the policy has no `archive` method', function () {
        $attachment = Attachment::factory()->used()->create();

        Livewire::test(EditAttachment::class, ['record' => $attachment->getRouteKey()])
            ->assertActionVisible('archive')
            ->callAction('archive')
            ->assertNotified('Archived');

        expect($attachment->refresh()->isArchived())->toBeTrue();
    });

    it('authorizes archiving with the policy\'s `archive` method for a model without `isUsed()`', function () {
        TagPolicy::$abilities['archive'] = false;

        $tag = Tag::factory()->create();

        Livewire::test(EditTag::class, ['record' => $tag->getRouteKey()])
            ->assertActionHidden('archive');
    });

    it('ignores the delete ability for a model without `isUsed()` when the policy has an `archive` method', function () {
        TagPolicy::$abilities['delete'] = false;

        $tag = Tag::factory()->create();

        Livewire::test(EditTag::class, ['record' => $tag->getRouteKey()])
            ->assertActionVisible('archive')
            ->callAction('archive')
            ->assertNotified('Archived');

        expect($tag->refresh()->isArchived())->toBeTrue();
    });
});

it('does not delete when the deleting event is cancelled', function () {
    Article::deleting(fn (): bool => false);

    $article = Article::factory()->create();

    Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
        ->callAction('archive')
        ->assertNotNotified();

    expect(Article::query()->whereKey($article->getKey())->exists())->toBeTrue();
});

it('throws when the model does not use the `CanBeArchived` trait', function () {
    $task = Task::factory()->create();

    Livewire::test(EditTask::class, ['record' => $task->getRouteKey()]);
})->throws(Exception::class, 'The [ArchiveAction] requires the model to use the [CanBeArchived] trait.');

it('deletes an archived record that is unused', function () {
    $article = Article::factory()->create();
    $article->archive();

    Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
        ->callAction('archive')
        ->assertNotified('Deleted');

    expect(Article::query()->whereKey($article->getKey())->exists())->toBeFalse();
});

it('is hidden in delete mode when the record is soft-deleted', function () {
    $attachment = Attachment::factory()->create();
    $attachment->delete();

    Livewire::test(EditAttachment::class, ['record' => $attachment->getRouteKey()])
        ->assertActionHidden('archive');
});

it('remains visible in delete mode when a soft-deletable record is not trashed', function () {
    $attachment = Attachment::factory()->create();

    Livewire::test(EditAttachment::class, ['record' => $attachment->getRouteKey()])
        ->assertActionVisible('archive')
        ->assertActionHasLabel('archive', 'Delete');
});

it('soft-deletes an unused record when the model uses `SoftDeletes`', function () {
    $attachment = Attachment::factory()->create();

    Livewire::test(EditAttachment::class, ['record' => $attachment->getRouteKey()])
        ->callAction('archive')
        ->assertNotified('Deleted');

    expect($attachment->refresh()->trashed())->toBeTrue();
});

it('is visible in archive mode even when the record is soft-deleted', function () {
    $attachment = Attachment::factory()->used()->create();
    $attachment->delete();

    Livewire::test(EditAttachment::class, ['record' => $attachment->getRouteKey()])
        ->assertActionVisible('archive')
        ->assertActionHasLabel('archive', 'Archive');
});
