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

use Workbench\App\Models\Category;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Deployment;
use Workbench\App\Models\Project;
use Workbench\App\Models\Tag;

// HasMany: Project->tasks() where Task LACKS CanBeArchived
$project = new Project();
$project->tasks()->withoutArchived();
$project->tasks()->onlyArchived();
$project->tasks()->withoutArchivedAndUnused();

// HasOne: Project->latestTask() where Task LACKS CanBeArchived
$project->latestTask()->withoutArchived();
$project->latestTask()->onlyArchived();
$project->latestTask()->withoutArchivedAndUnused();

// MorphMany: Project->comments() where Comment LACKS CanBeArchived
$project->comments()->withoutArchived();
$project->comments()->onlyArchived();
$project->comments()->withoutArchivedAndUnused();

// BelongsTo: Deployment->task() where Task LACKS CanBeArchived
$deployment = new Deployment();
$deployment->task()->withoutArchived();
$deployment->task()->onlyArchived();
$deployment->task()->withoutArchivedAndUnused();

// HasOneThrough: Category->latestReview() where Review LACKS CanBeArchived
$category = new Category();
$category->latestReview()->withoutArchived();
$category->latestReview()->onlyArchived();
$category->latestReview()->withoutArchivedAndUnused();

// HasManyThrough: Category->reviews() where Review LACKS CanBeArchived
$category->reviews()->withoutArchived();
$category->reviews()->onlyArchived();
$category->reviews()->withoutArchivedAndUnused();

// BelongsToMany: Tag->tasks() where Task LACKS CanBeArchived
$tag = new Tag();
$tag->tasks()->withoutArchived();
$tag->tasks()->onlyArchived();
$tag->tasks()->withoutArchivedAndUnused();

// MorphOne: Tag->comment() where Comment LACKS CanBeArchived
$tag->comment()->withoutArchived();
$tag->comment()->onlyArchived();
$tag->comment()->withoutArchivedAndUnused();

// MorphMany (negative using Tag->images is positive, so use separate):
// MorphToMany: Tag->reviews() where Review LACKS CanBeArchived
$tag->reviews()->withoutArchived();
$tag->reviews()->onlyArchived();
$tag->reviews()->withoutArchivedAndUnused();

// MorphTo (mixed union): Comment->commentable() where MorphTo<Project|Task> — Task LACKS CanBeArchived
$comment = new Comment();
$comment->commentable()->withoutArchived();
$comment->commentable()->onlyArchived();
$comment->commentable()->withoutArchivedAndUnused();
