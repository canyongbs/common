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

it('unarchives every record when the model does not define `used()`', function () {
    Image::factory()->count(3)->create()->each(fn (Image $image) => $image->archive());

    Livewire::test(ListImages::class)
        ->selectTableRecords(Image::query()->pluck('id')->all())
        ->callAction(TestAction::make('unarchive')->table()->bulk())
        ->assertNotified('3 unarchived');

    expect(Image::query()->onlyArchived()->count())->toBe(0);
});

it('unarchives used and unused records', function () {
    $usedArticle = Article::factory()->create();
    Review::factory()->create(['article_id' => $usedArticle->id]);
    $usedArticle->archive();

    $unusedArticle = Article::factory()->create();
    $unusedArticle->archive();

    Livewire::test(ListArticles::class)
        ->selectTableRecords(Article::query()->pluck('id')->all())
        ->callAction(TestAction::make('unarchive')->table()->bulk())
        ->assertNotified('2 unarchived');

    expect($usedArticle->refresh()->isArchived())->toBeFalse();
    expect($unusedArticle->refresh()->isArchived())->toBeFalse();
});

it('only processes the selected records', function () {
    $selectedArticle = Article::factory()->create();
    $selectedArticle->archive();

    $unselectedArticle = Article::factory()->create();
    $unselectedArticle->archive();

    Livewire::test(ListArticles::class)
        ->selectTableRecords([$selectedArticle->getKey()])
        ->callAction(TestAction::make('unarchive')->table()->bulk())
        ->assertNotified('1 unarchived');

    expect($selectedArticle->refresh()->isArchived())->toBeFalse();
    expect($unselectedArticle->refresh()->isArchived())->toBeTrue();
});

it('skips records that are not archived', function (bool $fetchesSelectedRecords) {
    ArticleResource::$fetchesSelectedRecords = $fetchesSelectedRecords;

    $archivedArticle = Article::factory()->create();
    $archivedArticle->archive();

    $article = Article::factory()->create();
    $updatedAt = $article->refresh()->updated_at;

    $this->travel(1)->minutes();

    Livewire::test(ListArticles::class)
        ->selectTableRecords([$archivedArticle->getKey(), $article->getKey()])
        ->callAction(TestAction::make('unarchive')->table()->bulk())
        ->assertNotified(Notification::make()->success()->title('1 unarchived'));

    expect($archivedArticle->refresh()->isArchived())->toBeFalse();
    expect($article->refresh()->updated_at->equalTo($updatedAt))->toBeTrue();
})->with([
    'fetching records' => true,
    'not fetching records' => false,
]);

it('does nothing when no records are selected', function () {
    $article = Article::factory()->create();
    $article->archive();

    Livewire::test(ListArticles::class)
        ->callAction(TestAction::make('unarchive')->table()->bulk())
        ->assertNotified('No records were unarchived');

    expect($article->refresh()->isArchived())->toBeTrue();
});

it('processes chunked selections', function () {
    ArticleResource::$selectedRecordsChunkSize = 50;

    $category = Category::factory()->create();

    Article::factory()->count(120)->create(['category_id' => $category->id]);
    Article::query()->archive();

    Livewire::test(ListArticles::class)
        ->selectTableRecords(Article::query()->pluck('id')->all())
        ->callAction(TestAction::make('unarchive')->table()->bulk())
        ->assertNotified('120 unarchived');

    expect(Article::query()->onlyArchived()->count())->toBe(0);
});

it('has unarchive styling', function () {
    Article::factory()->create();

    Livewire::test(ListArticles::class)
        ->assertActionHasLabel(TestAction::make('unarchive')->table()->bulk(), 'Unarchive')
        ->assertActionHasColor(TestAction::make('unarchive')->table()->bulk(), 'gray')
        ->assertActionHasIcon(TestAction::make('unarchive')->table()->bulk(), Heroicon::ArchiveBoxXMark)
        ->assertActionExists(TestAction::make('unarchive')->table()->bulk(), fn (BulkAction $action): bool => ((string) $action->getModalHeading()) === 'Unarchive Articles')
        ->assertActionExists(TestAction::make('unarchive')->table()->bulk(), fn (BulkAction $action): bool => $action->getModalSubmitActionLabel() === 'Unarchive')
        ->assertActionExists(TestAction::make('unarchive')->table()->bulk(), fn (BulkAction $action): bool => $action->getModalIcon() === Heroicon::OutlinedArchiveBoxXMark);
});

it('counts cancelled unarchive operations as failures', function () {
    $cancelledArticle = Article::factory()->create();
    $cancelledArticle->archive();

    $article = Article::factory()->create();
    $article->archive();

    Article::unarchiving(fn (Article $record): bool => ! $record->is($cancelledArticle));

    Livewire::test(ListArticles::class)
        ->selectTableRecords([$cancelledArticle->getKey(), $article->getKey()])
        ->callAction(TestAction::make('unarchive')->table()->bulk())
        ->assertNotified(
            Notification::make()
                ->warning()
                ->title('1 unarchived')
                ->body('<p>1 record failed to process.</p>')
                ->persistent(),
        );

    expect($cancelledArticle->refresh()->isArchived())->toBeTrue();
    expect($article->refresh()->isArchived())->toBeFalse();
});

it('counts records that throw during processing as failures', function () {
    $failingArticle = Article::factory()->create();
    $failingArticle->archive();

    $article = Article::factory()->create();
    $article->archive();

    Article::unarchiving(function (Article $record) use ($failingArticle): void {
        if ($record->is($failingArticle)) {
            throw new RuntimeException('Unarchive failed.');
        }
    });

    Livewire::test(ListArticles::class)
        ->selectTableRecords([$failingArticle->getKey(), $article->getKey()])
        ->callAction(TestAction::make('unarchive')->table()->bulk())
        ->assertNotified('1 unarchived');

    expect($failingArticle->refresh()->isArchived())->toBeTrue();
    expect($article->refresh()->isArchived())->toBeFalse();
});

it('processes records in a bulk query without model events when not fetching records', function () {
    ArticleResource::$fetchesSelectedRecords = false;

    $unarchivingEventFired = false;

    Article::unarchiving(function () use (&$unarchivingEventFired): void {
        $unarchivingEventFired = true;
    });

    Article::factory()->count(3)->create()->each(fn (Article $article) => $article->archive());

    Livewire::test(ListArticles::class)
        ->selectTableRecords(Article::query()->pluck('id')->all())
        ->callAction(TestAction::make('unarchive')->table()->bulk())
        ->assertNotified('3 unarchived');

    expect($unarchivingEventFired)->toBeFalse();
    expect(Article::query()->onlyArchived()->count())->toBe(0);
});

it('reports a complete failure when a bulk statement fails', function () {
    ArticleResource::$fetchesSelectedRecords = false;

    DB::statement(<<<'SQL'
        CREATE TRIGGER fail_article_unarchives
        BEFORE UPDATE ON articles
        WHEN OLD.archived_at IS NOT NULL AND NEW.archived_at IS NULL
        BEGIN
            SELECT RAISE(ABORT, 'Unarchiving is not allowed.');
        END
        SQL);

    $article = Article::factory()->create();
    $article->archive();

    Livewire::test(ListArticles::class)
        ->selectTableRecords([$article->getKey()])
        ->callAction(TestAction::make('unarchive')->table()->bulk())
        ->assertNotified('No records were unarchived');

    expect($article->refresh()->isArchived())->toBeTrue();
});

it('throws when the model does not use the `CanBeArchived` trait', function () {
    $task = Task::factory()->create();

    Livewire::test(ListTasks::class)
        ->selectTableRecords([$task->getKey()])
        ->callAction(TestAction::make('unarchive')->table()->bulk());
})->throws(Exception::class, 'The [UnarchiveBulkAction] requires the model to use the [CanBeArchived] trait.');

describe('authorization', function () {
    it('is hidden when the user is not authorized to delete any records', function () {
        ArticlePolicy::$abilities['deleteAny'] = false;

        Article::factory()->create();

        Livewire::test(ListArticles::class)
            ->assertActionHidden(TestAction::make('unarchive')->table()->bulk());
    });

    it('does not check the `archive` ability for each record by default', function () {
        ArticlePolicy::$abilities['archive'] = false;

        $article = Article::factory()->create();
        $article->archive();

        Livewire::test(ListArticles::class)
            ->selectTableRecords([$article->getKey()])
            ->callAction(TestAction::make('unarchive')->table()->bulk())
            ->assertNotified('1 unarchived');

        expect($article->refresh()->isArchived())->toBeFalse();
    });

    it('skips records that fail individual `archive` authorization when it is enabled', function () {
        ArticleResource::$authorizesIndividualRecords = true;

        $article = Article::factory()->create();
        $article->archive();

        $deniedArticle = Article::factory()->create();
        $deniedArticle->archive();
        ArticlePolicy::$deniedArchiveKeys = [$deniedArticle->getKey()];

        Livewire::test(ListArticles::class)
            ->selectTableRecords(Article::query()->pluck('id')->all())
            ->callAction(TestAction::make('unarchive')->table()->bulk())
            ->assertNotified('1 unarchived');

        expect($article->refresh()->isArchived())->toBeFalse();
        expect($deniedArticle->refresh()->isArchived())->toBeTrue();
    });

    it('reports skipped records alongside the successful count when individual authorization fails', function () {
        ArticleResource::$authorizesIndividualRecords = true;

        Article::factory()->create()->archive();

        $deniedArticle = Article::factory()->create();
        $deniedArticle->archive();
        ArticlePolicy::$deniedArchiveKeys = [$deniedArticle->getKey()];

        Livewire::test(ListArticles::class)
            ->selectTableRecords(Article::query()->pluck('id')->all())
            ->callAction(TestAction::make('unarchive')->table()->bulk())
            ->assertNotified(
                Notification::make()
                    ->warning()
                    ->title('1 unarchived')
                    ->body('<p>1 record could not be processed because you are not authorized to unarchive it.</p>')
                    ->persistent(),
            );
    });

    it('reports when no records could be processed because of authorization', function () {
        ArticleResource::$authorizesIndividualRecords = true;
        ArticlePolicy::$abilities['archive'] = false;

        Article::factory()->count(2)->create()->each(fn (Article $article) => $article->archive());

        Livewire::test(ListArticles::class)
            ->selectTableRecords(Article::query()->pluck('id')->all())
            ->callAction(TestAction::make('unarchive')->table()->bulk())
            ->assertNotified(
                Notification::make()
                    ->danger()
                    ->title('No records were unarchived')
                    ->body('<p>2 records could not be processed because you are not authorized to unarchive them.</p>')
                    ->persistent(),
            );

        expect(Article::query()->onlyArchived()->count())->toBe(2);
    });

    it('does not authorize individual records when not fetching records', function () {
        ArticleResource::$authorizesIndividualRecords = true;
        ArticleResource::$fetchesSelectedRecords = false;
        ArticlePolicy::$abilities['archive'] = false;

        $article = Article::factory()->create();
        $article->archive();

        Livewire::test(ListArticles::class)
            ->selectTableRecords([$article->getKey()])
            ->callAction(TestAction::make('unarchive')->table()->bulk())
            ->assertNotified('1 unarchived');

        expect($article->refresh()->isArchived())->toBeFalse();
    });

    it('authorizes records with the `archive` ability when the policy has an `archive` method', function () {
        ArticleResource::$authorizesIndividualRecords = true;
        ArticlePolicy::$abilities['archive'] = false;

        $article = Article::factory()->create();
        $article->archive();

        Livewire::test(ListArticles::class)
            ->selectTableRecords([$article->getKey()])
            ->callAction(TestAction::make('unarchive')->table()->bulk())
            ->assertNotified('No records were unarchived');

        expect($article->refresh()->isArchived())->toBeTrue();
    });

    it('ignores the delete ability when the policy has an `archive` method', function () {
        ArticleResource::$authorizesIndividualRecords = true;
        ArticlePolicy::$abilities['delete'] = false;

        $article = Article::factory()->create();
        $article->archive();

        Livewire::test(ListArticles::class)
            ->selectTableRecords([$article->getKey()])
            ->callAction(TestAction::make('unarchive')->table()->bulk())
            ->assertNotified('1 unarchived');

        expect($article->refresh()->isArchived())->toBeFalse();
    });
});

describe('backwards compatibility', function () {
    it('falls back to the delete ability when the policy has no `archive` method', function () {
        ImageResource::$authorizesIndividualRecords = true;
        ImagePolicy::$abilities['delete'] = false;

        Image::factory()->count(2)->create()->each(fn (Image $image) => $image->archive());

        Livewire::test(ListImages::class)
            ->selectTableRecords(Image::query()->pluck('id')->all())
            ->callAction(TestAction::make('unarchive')->table()->bulk())
            ->assertNotified('No records were unarchived');

        expect(Image::query()->onlyArchived()->count())->toBe(2);
    });

    it('unarchives records using the delete ability when the policy has no `archive` method', function () {
        ImageResource::$authorizesIndividualRecords = true;

        Image::factory()->count(2)->create()->each(fn (Image $image) => $image->archive());

        Livewire::test(ListImages::class)
            ->selectTableRecords(Image::query()->pluck('id')->all())
            ->callAction(TestAction::make('unarchive')->table()->bulk())
            ->assertNotified('2 unarchived');

        expect(Image::query()->onlyArchived()->count())->toBe(0);
    });
});
