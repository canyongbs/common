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

declare(strict_types = 1);

use Workbench\App\Models\Image;
use Workbench\App\Models\Project;
use Workbench\App\Models\Task;

// BelongsTo: Task->project() where Project HAS CanBeArchived
$task = new Task();
$task->project()->withoutArchived();
$task->project()->onlyArchived();
$task->project()->withoutArchivedAndUnused();

// HasOne: Task->deployment() where Deployment HAS CanBeArchived
$task->deployment()->withoutArchived();
$task->deployment()->onlyArchived();
$task->deployment()->withoutArchivedAndUnused();

// BelongsToMany: Task->tags() where Tag HAS CanBeArchived
$task->tags()->withoutArchived();
$task->tags()->onlyArchived();
$task->tags()->withoutArchivedAndUnused();

// MorphOne: Project->image() where Image HAS CanBeArchived
$project = new Project();
$project->image()->withoutArchived();
$project->image()->onlyArchived();
$project->image()->withoutArchivedAndUnused();

// MorphToMany: Project->tags() where Tag HAS CanBeArchived
$project->tags()->withoutArchived();
$project->tags()->onlyArchived();
$project->tags()->withoutArchivedAndUnused();

// HasOneThrough: Project->latestDeployment() where Deployment HAS CanBeArchived
$project->latestDeployment()->withoutArchived();
$project->latestDeployment()->onlyArchived();
$project->latestDeployment()->withoutArchivedAndUnused();

// HasManyThrough: Project->deployments() where Deployment HAS CanBeArchived
$project->deployments()->withoutArchived();
$project->deployments()->onlyArchived();
$project->deployments()->withoutArchivedAndUnused();

// MorphTo (all models in union have trait): Image->imageable() where MorphTo<Project|Image>
$image = new Image();
$image->imageable()->withoutArchived();
$image->imageable()->onlyArchived();
$image->imageable()->withoutArchivedAndUnused();
