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

use OwenIt\Auditing\Models\Audit;
use Workbench\App\Models\AuditableCategory;
use Workbench\App\Models\AuditableLabel;
use Workbench\App\Models\AuditableMember;
use Workbench\App\Models\AuditableMembership;
use Workbench\App\Models\AuditablePost;

beforeEach(function () {
    config()->set('audit.console', true);
});

it('records only the attached row when attaching a many-to-many relation', function () {
    $post = AuditablePost::create(['title' => 'Post']);
    $category = AuditableCategory::create(['name' => 'A']);

    $post->categories()->attach($category->id);

    $audit = $post->audits()->where('event', 'attach')->sole();

    expect($audit->old_values['categories'])->toBe([]);
    expect($audit->new_values['categories'])->toHaveCount(1);
    expect((int) $audit->new_values['categories'][0]['id'])->toBe($category->id);
});

it('records only the removed row when detaching a many-to-many relation', function () {
    $post = AuditablePost::create(['title' => 'Post']);
    $a = AuditableCategory::create(['name' => 'A']);
    $b = AuditableCategory::create(['name' => 'B']);

    $post->categories()->attach([$a->id, $b->id]);

    $post->categories()->detach($a->id);

    $audit = $post->audits()->where('event', 'detach')->sole();

    expect($audit->old_values['categories'])->toHaveCount(1);
    expect((int) $audit->old_values['categories'][0]['id'])->toBe($a->id);
    expect($audit->new_values['categories'])->toBe([]);
});

it('records both the added and removed rows when syncing a many-to-many relation', function () {
    $post = AuditablePost::create(['title' => 'Post']);
    $a = AuditableCategory::create(['name' => 'A']);
    $b = AuditableCategory::create(['name' => 'B']);

    $post->categories()->attach($a->id);

    $post->categories()->sync([$b->id]);

    $audit = $post->audits()->where('event', 'sync')->sole();

    expect($audit->old_values['categories'])->toHaveCount(1);
    expect((int) $audit->old_values['categories'][0]['id'])->toBe($a->id);
    expect($audit->new_values['categories'])->toHaveCount(1);
    expect((int) $audit->new_values['categories'][0]['id'])->toBe($b->id);
});

it('records a pivot attribute change even when the related keys are unchanged', function () {
    $post = AuditablePost::create(['title' => 'Post']);
    $category = AuditableCategory::create(['name' => 'A']);

    $post->categories()->attach($category->id, ['sort' => 1]);

    $post->categories()->syncWithPivotValues([$category->id], ['sort' => 2], detaching: false);

    $audit = $post->audits()->where('event', 'sync')->sole();

    expect($audit->old_values['categories'])->toHaveCount(1);
    expect((int) $audit->old_values['categories'][0]['pivot']['sort'])->toBe(1);
    expect($audit->new_values['categories'])->toHaveCount(1);
    expect((int) $audit->new_values['categories'][0]['pivot']['sort'])->toBe(2);
});

it('records nothing changed for a no-op sync', function () {
    $post = AuditablePost::create(['title' => 'Post']);
    $category = AuditableCategory::create(['name' => 'A']);

    $post->categories()->attach($category->id);

    $post->categories()->sync([$category->id]);

    $audit = $post->audits()->where('event', 'sync')->sole();

    expect($audit->old_values)->toBe([]);
    expect($audit->new_values)->toBe([]);
});

it('records the diff for a morph to many relation', function () {
    $post = AuditablePost::create(['title' => 'Post']);
    $label = AuditableLabel::create(['name' => 'L']);

    $post->labels()->attach($label->id);

    $audit = $post->audits()->where('event', 'attach')->sole();

    expect($audit->new_values['labels'])->toHaveCount(1);
    expect((int) $audit->new_values['labels'][0]['id'])->toBe($label->id);
});

it('suppresses the pivot model auditing while still recording the parent event', function () {
    $post = AuditablePost::create(['title' => 'Post']);
    $member = AuditableMember::create(['name' => 'M']);

    $post->members()->attach($member->id);

    $audit = $post->audits()->where('event', 'attach')->sole();

    expect($audit->new_values['members'])->toHaveCount(1);
    expect((int) $audit->new_values['members'][0]['id'])->toBe($member->id);

    expect(Audit::query()->where('auditable_type', AuditableMembership::class)->count())->toBe(0);
});
