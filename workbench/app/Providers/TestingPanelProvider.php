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

namespace Workbench\App\Providers;

use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Support\Facades\Gate;
use Workbench\App\Filament\Resources\Articles\ArticleResource;
use Workbench\App\Filament\Resources\Attachments\AttachmentResource;
use Workbench\App\Filament\Resources\Images\ImageResource;
use Workbench\App\Filament\Resources\Projects\ProjectResource;
use Workbench\App\Filament\Resources\Tags\TagResource;
use Workbench\App\Filament\Resources\Tasks\TaskResource;
use Workbench\App\Models\Article;
use Workbench\App\Models\Attachment;
use Workbench\App\Models\Image;
use Workbench\App\Models\Project;
use Workbench\App\Models\Tag;
use Workbench\App\Models\Task;
use Workbench\App\Policies\ArticlePolicy;
use Workbench\App\Policies\AttachmentPolicy;
use Workbench\App\Policies\ImagePolicy;
use Workbench\App\Policies\ProjectPolicy;
use Workbench\App\Policies\TagPolicy;
use Workbench\App\Policies\TaskPolicy;

class TestingPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('testing')
            ->path('testing')
            ->default()
            ->resources([
                ArticleResource::class,
                AttachmentResource::class,
                ImageResource::class,
                ProjectResource::class,
                TagResource::class,
                TaskResource::class,
            ]);
    }

    public function boot(): void
    {
        Gate::policy(Article::class, ArticlePolicy::class);
        Gate::policy(Attachment::class, AttachmentPolicy::class);
        Gate::policy(Image::class, ImagePolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Tag::class, TagPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
    }
}
