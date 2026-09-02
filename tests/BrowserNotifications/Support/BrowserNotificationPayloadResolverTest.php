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

use CanyonGBS\Common\BrowserNotifications\Support\BrowserNotificationPayloadResolver;
use Illuminate\Notifications\DatabaseNotification;

it('resolves a structured Filament notification payload', function () {
    $notification = new DatabaseNotification([
        'id' => '123e4567-e89b-12d3-a456-426614174000',
        'data' => [
            'title' => '<strong>Request assigned</strong>',
            'body' => 'A request needs your attention.',
            'actions' => [[
                'url' => '/requests/123',
                'shouldOpenUrlInNewTab' => true,
            ]],
        ],
    ]);

    $payload = app(BrowserNotificationPayloadResolver::class)->resolve($notification);

    expect($payload)->not->toBeNull()
        ->and($payload?->id)->toBe('123e4567-e89b-12d3-a456-426614174000')
        ->and($payload?->title)->toBe('Request assigned')
        ->and($payload?->body)->toBe('A request needs your attention.')
        ->and($payload?->actionUrl)->toBe('/requests/123')
        ->and($payload?->openInNewTab)->toBeTrue();
});

it('resolves a destination embedded in an existing HTML title', function () {
    $notification = new DatabaseNotification([
        'id' => '123e4567-e89b-12d3-a456-426614174001',
        'data' => [
            'title' => 'Review <a href="/requests/456">request 456</a>',
            'body' => '',
        ],
    ]);

    $payload = app(BrowserNotificationPayloadResolver::class)->resolve($notification);

    expect($payload?->title)->toBe('Review request 456')
        ->and($payload?->actionUrl)->toBe('/requests/456');
});

it('resolves a destination embedded in an existing body', function () {
    $notification = new DatabaseNotification([
        'id' => '123e4567-e89b-12d3-a456-426614174002',
        'data' => [
            'title' => 'Request assigned',
            'body' => 'Open [request 789](/requests/789) to review it.',
        ],
    ]);

    $payload = app(BrowserNotificationPayloadResolver::class)->resolve($notification);

    expect($payload?->body)->toBe('Open request 789 to review it.')
        ->and($payload?->actionUrl)->toBe('/requests/789');
});

it('does not resolve notifications that opt out of browser delivery', function () {
    $notification = new DatabaseNotification([
        'id' => '123e4567-e89b-12d3-a456-426614174003',
        'data' => [
            'title' => 'Background update',
            'viewData' => ['silent' => true],
        ],
    ]);

    expect(app(BrowserNotificationPayloadResolver::class)->resolve($notification))->toBeNull();
});

it('does not resolve notifications without a destination', function () {
    $notification = new DatabaseNotification([
        'id' => '123e4567-e89b-12d3-a456-426614174004',
        'data' => [
            'title' => 'Background update',
            'body' => 'The background operation has completed.',
        ],
    ]);

    expect(app(BrowserNotificationPayloadResolver::class)->resolve($notification))->toBeNull();
});
