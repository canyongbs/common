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

use CanyonGBS\Common\Support\Auditing\RegisterArchiveAuditEvents;
use Illuminate\Support\Arr;

it('adds the archive audit events when the app boots', function (): void {
    expect(config('audit.events'))->toContain('archived', 'unarchived');
});

it('appends the archive audit events to the configured events', function (): void {
    config()->set('audit.events', ['created', 'updated']);

    app(RegisterArchiveAuditEvents::class)();

    expect(config('audit.events'))->toBe(['created', 'updated', 'unarchived', 'archived']);
});

it('starts from the auditing package defaults when no events are configured', function (): void {
    config()->set('audit', Arr::except(config('audit', []), 'events'));

    app(RegisterArchiveAuditEvents::class)();

    expect(config('audit.events'))->toBe(['created', 'updated', 'deleted', 'restored', 'unarchived', 'archived']);
});

it('does not duplicate events that are already configured', function (): void {
    config()->set('audit.events', ['created', 'archived']);

    app(RegisterArchiveAuditEvents::class)();
    app(RegisterArchiveAuditEvents::class)();

    expect(config('audit.events'))->toBe(['created', 'unarchived', 'archived']);
});

it('treats a keyed event as already configured', function (): void {
    config()->set('audit.events', ['created', 'archived' => 'getCustomArchivedAttributes']);

    app(RegisterArchiveAuditEvents::class)();

    expect(config('audit.events'))->toBe(['created', 'unarchived', 'archived' => 'getCustomArchivedAttributes']);
});

it('leaves a non-array events config untouched', function (): void {
    config()->set('audit.events', null);

    app(RegisterArchiveAuditEvents::class)();

    expect(config('audit.events'))->toBeNull();
});
