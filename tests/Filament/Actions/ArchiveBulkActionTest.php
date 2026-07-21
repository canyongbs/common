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

use Filament\Actions\BulkAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Workbench\App\Filament\Resources\Articles\ArticleResource;
use Workbench\App\Filament\Resources\Articles\Pages\ListArticles;
use Workbench\App\Filament\Resources\Images\ImageResource;
use Workbench\App\Filament\Resources\Images\Pages\ListImages;
use Workbench\App\Filament\Resources\Tasks\Pages\ListTasks;
use Workbench\App\Models\Article;
use Workbench\App\Models\Category;
use Workbench\App\Models\Image;
use Workbench\App\Models\Review;
use Workbench\App\Models\Task;
use Workbench\App\Models\User;
use Workbench\App\Policies\ArticlePolicy;
use Workbench\App\Policies\ImagePolicy;
use Workbench\App\Policies\TaskPolicy;

beforeEach(function () {
    ArticlePolicy::reset();
    ImagePolicy::reset();
    TaskPolicy::reset();

    ArticleResource::$authorizesIndividualRecords = false;
    ArticleResource::$fetchesSelectedRecords = true;
    ArticleResource::$selectedRecordsChunkSize = null;
    ImageResource::$authorizesIndividualRecords = false;

    Filament::setCurrentPanel('testing');

    $this->actingAs(User::create(['name' => 'Ada', 'email' => 'ada@example.com']));
});

it('archives used records and deletes unused records', function () {
    $usedArticles = Article::factory()->count(2)->create();
    $usedArticles->each(fn (Article $article) => Review::factory()->create(['article_id' => $article->id]));

    $unusedArticle = Article::factory()->create();

    Livewire::test(ListArticles::class)
        ->selectTableRecords(Article::query()->pluck('id')->all())
        ->callAction(TestAction::make('archive')->table()->bulk())
        ->assertNotified('2 archived, 1 deleted');

    $usedArticles->each(fn (Article $article) => expect($article->refresh()->isArchived())->toBeTrue());

    expect(Article::query()->whereKey($unusedArticle->getKey())->exists())->toBeFalse();
});

it('archives every record when the model does not define `used()`', function () {
    Image::factory()->count(3)->create();

    Livewire::test(ListImages::class)
        ->selectTableRecords(Image::query()->pluck('id')->all())
        ->callAction(TestAction::make('archive')->table()->bulk())
        ->assertNotified('3 archived');

    expect(Image::query()->onlyArchived()->count())->toBe(3);
});

it('only processes the selected records', function () {
    $selectedArticle = Article::factory()->create();
    $unselectedArticle = Article::factory()->create();

    Livewire::test(ListArticles::class)
        ->selectTableRecords([$selectedArticle->getKey()])
        ->callAction(TestAction::make('archive')->table()->bulk())
        ->assertNotified('1 deleted');

    expect(Article::query()->whereKey($selectedArticle->getKey())->exists())->toBeFalse();
    expect($unselectedArticle->refresh()->isArchived())->toBeFalse();
});

it('fires model events and counts cancelled operations as failures', function () {
    Article::deleting(fn (): bool => false);

    $usedArticle = Article::factory()->create();
    Review::factory()->create(['article_id' => $usedArticle->id]);

    $unusedArticle = Article::factory()->create();

    Livewire::test(ListArticles::class)
        ->selectTableRecords([$usedArticle->getKey(), $unusedArticle->getKey()])
        ->callAction(TestAction::make('archive')->table()->bulk())
        ->assertNotified(
            Notification::make()
                ->warning()
                ->title('1 archived')
                ->body('<p>1 record failed to process.</p>')
                ->persistent(),
        );

    expect($usedArticle->refresh()->isArchived())->toBeTrue();
    expect(Article::query()->whereKey($unusedArticle->getKey())->exists())->toBeTrue();
});

it('counts cancelled archive operations as failures', function () {
    Article::archiving(fn (): bool => false);

    $usedArticle = Article::factory()->create();
    Review::factory()->create(['article_id' => $usedArticle->id]);

    $unusedArticle = Article::factory()->create();

    Livewire::test(ListArticles::class)
        ->selectTableRecords([$usedArticle->getKey(), $unusedArticle->getKey()])
        ->callAction(TestAction::make('archive')->table()->bulk())
        ->assertNotified('1 deleted');

    expect($usedArticle->refresh()->isArchived())->toBeFalse();
    expect(Article::query()->whereKey($unusedArticle->getKey())->exists())->toBeFalse();
});

it('counts records that throw during processing as failures', function () {
    Article::deleting(function (): void {
        throw new RuntimeException('Delete failed.');
    });

    $usedArticle = Article::factory()->create();
    Review::factory()->create(['article_id' => $usedArticle->id]);

    $unusedArticle = Article::factory()->create();

    Livewire::test(ListArticles::class)
        ->selectTableRecords([$usedArticle->getKey(), $unusedArticle->getKey()])
        ->callAction(TestAction::make('archive')->table()->bulk())
        ->assertNotified('1 archived');

    expect($usedArticle->refresh()->isArchived())->toBeTrue();
    expect(Article::query()->whereKey($unusedArticle->getKey())->exists())->toBeTrue();
});

it('processes records in bulk queries without model events when not fetching records', function () {
    ArticleResource::$fetchesSelectedRecords = false;

    $deletingEventFired = false;

    Article::deleting(function () use (&$deletingEventFired): void {
        $deletingEventFired = true;
    });

    $usedArticles = Article::factory()->count(2)->create();
    $usedArticles->each(fn (Article $article) => Review::factory()->create(['article_id' => $article->id]));

    $unusedArticle = Article::factory()->create();

    Livewire::test(ListArticles::class)
        ->selectTableRecords(Article::query()->pluck('id')->all())
        ->callAction(TestAction::make('archive')->table()->bulk())
        ->assertNotified('2 archived, 1 deleted');

    expect($deletingEventFired)->toBeFalse();

    $usedArticles->each(fn (Article $article) => expect($article->refresh()->isArchived())->toBeTrue());

    expect(Article::query()->whereKey($unusedArticle->getKey())->exists())->toBeFalse();
});

it('skips and reports records that fail individual `delete` authorization when it is enabled', function () {
    ArticleResource::$authorizesIndividualRecords = true;

    $usedArticle = Article::factory()->create();
    Review::factory()->create(['article_id' => $usedArticle->id]);

    $unusedArticle = Article::factory()->create();

    $deniedArticle = Article::factory()->create();
    ArticlePolicy::$deniedDeleteKeys = [$deniedArticle->getKey()];

    Livewire::test(ListArticles::class)
        ->selectTableRecords(Article::query()->pluck('id')->all())
        ->callAction(TestAction::make('archive')->table()->bulk())
        ->assertNotified('1 archived, 1 deleted');

    expect($usedArticle->refresh()->isArchived())->toBeTrue();
    expect(Article::query()->whereKey($unusedArticle->getKey())->exists())->toBeFalse();
    expect($deniedArticle->refresh()->isArchived())->toBeFalse();
});

it('does not check the `delete` ability for each record by default', function () {
    ArticlePolicy::$abilities['delete'] = false;

    $article = Article::factory()->create();

    Livewire::test(ListArticles::class)
        ->selectTableRecords([$article->getKey()])
        ->callAction(TestAction::make('archive')->table()->bulk())
        ->assertNotified('1 deleted');

    expect(Article::query()->whereKey($article->getKey())->exists())->toBeFalse();
});

it('is hidden when the user is not authorized to delete any records', function () {
    ArticlePolicy::$abilities['deleteAny'] = false;

    Article::factory()->create();

    Livewire::test(ListArticles::class)
        ->assertActionHidden(TestAction::make('archive')->table()->bulk());
});

it('throws when the model does not use the `CanBeArchived` trait', function () {
    $task = Task::factory()->create();

    Livewire::test(ListTasks::class)
        ->selectTableRecords([$task->getKey()])
        ->callAction(TestAction::make('archive')->table()->bulk());
})->throws(Exception::class, 'The [ArchiveBulkAction] requires the model to use the [CanBeArchived] trait.');

it('has archive styling and a delete warning in the modal when the model defines `used()`', function () {
    Article::factory()->create();

    Livewire::test(ListArticles::class)
        ->assertActionHasLabel(TestAction::make('archive')->table()->bulk(), 'Archive / Delete')
        ->assertActionHasColor(TestAction::make('archive')->table()->bulk(), 'warning')
        ->assertActionHasIcon(TestAction::make('archive')->table()->bulk(), Heroicon::ArchiveBox)
        ->assertActionExists(TestAction::make('archive')->table()->bulk(), fn (BulkAction $action): bool => ((string) $action->getModalHeading()) === 'Archive Articles')
        ->assertActionExists(TestAction::make('archive')->table()->bulk(), fn (BulkAction $action): bool => $action->getModalSubmitActionLabel() === 'Archive')
        ->assertActionExists(TestAction::make('archive')->table()->bulk(), fn (BulkAction $action): bool => $action->getModalIcon() === Heroicon::OutlinedArchiveBox)
        ->assertActionExists(TestAction::make('archive')->table()->bulk(), fn (BulkAction $action): bool => ((string) $action->getModalDescription()) === 'Records that are no longer in use will be permanently deleted instead of archived.');
});

it('has no delete warning in the modal when the model does not define `used()`', function () {
    Image::factory()->create();

    Livewire::test(ListImages::class)
        ->assertActionHasLabel(TestAction::make('archive')->table()->bulk(), 'Archive')
        ->assertActionExists(TestAction::make('archive')->table()->bulk(), fn (BulkAction $action): bool => ! str_contains((string) $action->getModalDescription(), 'permanently deleted'));
});

it('reports when no records could be processed because of authorization', function () {
    ArticleResource::$authorizesIndividualRecords = true;
    ArticlePolicy::$abilities['delete'] = false;

    Article::factory()->count(2)->create();

    Livewire::test(ListArticles::class)
        ->selectTableRecords(Article::query()->pluck('id')->all())
        ->callAction(TestAction::make('archive')->table()->bulk())
        ->assertNotified(
            Notification::make()
                ->danger()
                ->title('No records were archived or deleted')
                ->body('<p>2 records could not be processed because you are not authorized to delete them.</p>')
                ->persistent(),
        );

    expect(Article::query()->count())->toBe(2);
    expect(Article::query()->onlyArchived()->count())->toBe(0);
});

it('reports skipped records alongside the successful counts when individual authorization fails', function () {
    ArticleResource::$authorizesIndividualRecords = true;

    $usedArticle = Article::factory()->create();
    Review::factory()->create(['article_id' => $usedArticle->id]);

    $unusedArticle = Article::factory()->create();

    $deniedArticle = Article::factory()->create();
    ArticlePolicy::$deniedDeleteKeys = [$deniedArticle->getKey()];

    Livewire::test(ListArticles::class)
        ->selectTableRecords(Article::query()->pluck('id')->all())
        ->callAction(TestAction::make('archive')->table()->bulk())
        ->assertNotified('1 archived, 1 deleted');

    expect($deniedArticle->refresh()->isArchived())->toBeFalse();
});

it('does not authorize individual records when not fetching records', function () {
    ArticleResource::$authorizesIndividualRecords = true;
    ArticleResource::$fetchesSelectedRecords = false;
    ArticlePolicy::$abilities['delete'] = false;

    $article = Article::factory()->create();

    Livewire::test(ListArticles::class)
        ->selectTableRecords([$article->getKey()])
        ->callAction(TestAction::make('archive')->table()->bulk())
        ->assertNotified('1 deleted');

    expect(Article::query()->whereKey($article->getKey())->exists())->toBeFalse();
});

it('reports a complete failure when a bulk statement fails', function () {
    ArticleResource::$fetchesSelectedRecords = false;

    DB::statement(<<<'SQL'
        CREATE TRIGGER fail_article_archives
        BEFORE UPDATE ON articles
        WHEN NEW.archived_at IS NOT NULL
        BEGIN
            SELECT RAISE(ABORT, 'Archiving is not allowed.');
        END
        SQL);

    $usedArticle = Article::factory()->create();
    Review::factory()->create(['article_id' => $usedArticle->id]);

    $unusedArticle = Article::factory()->create();

    Livewire::test(ListArticles::class)
        ->selectTableRecords([$usedArticle->getKey(), $unusedArticle->getKey()])
        ->callAction(TestAction::make('archive')->table()->bulk())
        ->assertNotified('No records were archived or deleted');

    expect($usedArticle->refresh()->isArchived())->toBeFalse();
    expect(Article::query()->whereKey($unusedArticle->getKey())->exists())->toBeTrue();
});

it('processes chunked selections', function () {
    ArticleResource::$selectedRecordsChunkSize = 50;

    $category = Category::factory()->create();

    Article::factory()->count(120)->create(['category_id' => $category->id]);

    $usedArticle = Article::factory()->create(['category_id' => $category->id]);
    Review::factory()->create(['article_id' => $usedArticle->id]);

    Livewire::test(ListArticles::class)
        ->selectTableRecords(Article::query()->pluck('id')->all())
        ->callAction(TestAction::make('archive')->table()->bulk())
        ->assertNotified('1 archived, 120 deleted');

    expect(Article::query()->count())->toBe(1);
    expect($usedArticle->refresh()->isArchived())->toBeTrue();
});

it('does nothing when no records are selected', function () {
    Article::factory()->create();

    Livewire::test(ListArticles::class)
        ->callAction(TestAction::make('archive')->table()->bulk())
        ->assertNotified('No records were archived or deleted');

    expect(Article::query()->count())->toBe(1);
    expect(Article::query()->onlyArchived()->count())->toBe(0);
});

it('authorizes records that will be archived with the `archive` ability when the policy has an `archive` method', function () {
    ArticleResource::$authorizesIndividualRecords = true;
    ArticlePolicy::$abilities['archive'] = false;

    $usedArticle = Article::factory()->create();
    Review::factory()->create(['article_id' => $usedArticle->id]);

    $unusedArticle = Article::factory()->create();

    Livewire::test(ListArticles::class)
        ->selectTableRecords([$usedArticle->getKey(), $unusedArticle->getKey()])
        ->callAction(TestAction::make('archive')->table()->bulk())
        ->assertNotified('1 deleted');

    expect($usedArticle->refresh()->isArchived())->toBeFalse();
    expect(Article::query()->whereKey($unusedArticle->getKey())->exists())->toBeFalse();
});

it('ignores the delete ability for records that will be archived when the policy has an `archive` method', function () {
    ArticleResource::$authorizesIndividualRecords = true;
    ArticlePolicy::$abilities['delete'] = false;

    $usedArticle = Article::factory()->create();
    Review::factory()->create(['article_id' => $usedArticle->id]);

    $unusedArticle = Article::factory()->create();

    Livewire::test(ListArticles::class)
        ->selectTableRecords([$usedArticle->getKey(), $unusedArticle->getKey()])
        ->callAction(TestAction::make('archive')->table()->bulk())
        ->assertNotified('1 archived');

    expect($usedArticle->refresh()->isArchived())->toBeTrue();
    expect(Article::query()->whereKey($unusedArticle->getKey())->exists())->toBeTrue();
});

describe('backwards compatibility', function () {
    it('falls back to the delete ability for records that will be archived when the policy has no `archive` method', function () {
        ImageResource::$authorizesIndividualRecords = true;
        ImagePolicy::$abilities['delete'] = false;

        Image::factory()->count(2)->create();

        Livewire::test(ListImages::class)
            ->selectTableRecords(Image::query()->pluck('id')->all())
            ->callAction(TestAction::make('archive')->table()->bulk())
            ->assertNotified('No records were archived or deleted');

        expect(Image::query()->onlyArchived()->count())->toBe(0);
    });

    it('archives records using the delete ability when the policy has no `archive` method', function () {
        ImageResource::$authorizesIndividualRecords = true;

        Image::factory()->count(2)->create();

        Livewire::test(ListImages::class)
            ->selectTableRecords(Image::query()->pluck('id')->all())
            ->callAction(TestAction::make('archive')->table()->bulk())
            ->assertNotified('2 archived');

        expect(Image::query()->onlyArchived()->count())->toBe(2);
    });
});

it('includes already-archived used records in the archived count', function () {
    $usedArticles = Article::factory()->count(2)->create();
    $usedArticles->each(fn (Article $article) => Review::factory()->create(['article_id' => $article->id]));

    $usedArticles->first()->archive();

    Livewire::test(ListArticles::class)
        ->selectTableRecords($usedArticles->pluck('id')->all())
        ->callAction(TestAction::make('archive')->table()->bulk())
        ->assertNotified('2 archived');

    expect(Article::query()->onlyArchived()->count())->toBe(2);
});
