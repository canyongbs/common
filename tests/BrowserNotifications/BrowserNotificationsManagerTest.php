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

use CanyonGBS\Common\BrowserNotifications\BrowserNotificationsManager;

it('is disabled until a consuming app explicitly configures availability', function () {
    $manager = new BrowserNotificationsManager();

    expect($manager->isEnabled())->toBeFalse()
        ->and($manager->isAvailable())->toBeFalse();

    $manager->availableUsing(fn (): bool => true);

    expect($manager->isEnabled())->toBeTrue()
        ->and($manager->isAvailable())->toBeTrue();
});

it('allows only supported browser push service endpoints by default', function (string $endpoint, bool $isAllowed) {
    expect((new BrowserNotificationsManager())->isSubscriptionEndpointAllowed($endpoint))->toBe($isAllowed);
})->with([
    'Google Chrome and Edge' => ['https://fcm.googleapis.com/fcm/send/subscription', true],
    'Mozilla Firefox' => ['https://updates.push.services.mozilla.com/wpush/v2/subscription', true],
    'Apple Safari' => ['https://web.push.apple.com/subscription', true],
    'Microsoft push service' => ['https://db5.notify.windows.com/w/subscription', true],
    'unrecognized public host' => ['https://push.example.com/subscription', false],
    'deceptive Google host' => ['https://fcm.googleapis.com.attacker.example/subscription', false],
    'deceptive Mozilla host' => ['https://updates.push.services.mozilla.com.attacker.example/subscription', false],
    'deceptive Microsoft host' => ['https://db5.notify.windows.com.attacker.example/subscription', false],
    'HTTP endpoint' => ['http://fcm.googleapis.com/fcm/send/subscription', false],
    'loopback endpoint' => ['https://127.0.0.1/push/subscription', false],
    'IPv6 endpoint' => ['https://[2001:4860:4860::8888]/push/subscription', false],
    'embedded credentials' => ['https://user:password@fcm.googleapis.com/fcm/send/subscription', false],
    'non-standard port' => ['https://fcm.googleapis.com:8443/fcm/send/subscription', false],
]);

it('allows the consuming app to customize subscription endpoint validation', function () {
    $manager = (new BrowserNotificationsManager())
        ->allowSubscriptionEndpointsUsing(fn (string $endpoint): bool => $endpoint === 'https://push.example.com/subscription');

    expect($manager->isSubscriptionEndpointAllowed('https://push.example.com/subscription'))->toBeTrue()
        ->and($manager->isSubscriptionEndpointAllowed('https://other.example.com/subscription'))->toBeFalse()
        ->and($manager->isSubscriptionEndpointAllowed('http://push.example.com/subscription'))->toBeFalse();
});

it('disables redirects for Web Push delivery', function () {
    expect(config('webpush.client_options.allow_redirects'))->toBeFalse();
});
