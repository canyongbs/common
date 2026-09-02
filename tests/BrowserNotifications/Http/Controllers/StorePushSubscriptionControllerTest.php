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
use Illuminate\Support\Str;
use Minishlink\WebPush\ContentEncoding;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;

use Workbench\App\Models\BrowserNotificationUser;
use Workbench\App\Models\PushSubscription;

it('requires authentication', function () {
    postJson(route('common.browser-notifications.subscriptions.store'), [
        'endpoint' => 'https://push.example.com/subscriptions/' . Str::uuid(),
        'keys' => browserNotificationSubscriptionKeys(),
    ])->assertUnauthorized();
});

it('stores a validated subscription using the modern content encoding', function () {
    $user = BrowserNotificationUser::create(['name' => 'Ada', 'email' => Str::uuid() . '@example.com']);

    actingAs($user);

    postJson(route('common.browser-notifications.subscriptions.store'), [
        'endpoint' => 'https://push.example.com/subscriptions/' . Str::uuid(),
        'keys' => browserNotificationSubscriptionKeys(),
    ])->assertSuccessful();

    $subscription = PushSubscription::query()->sole();

    expect($subscription->content_encoding)->toBe(ContentEncoding::aes128gcm);

    assertDatabaseHas('push_subscriptions', [
        'subscribable_id' => $user->getKey(),
        'endpoint' => $subscription->endpoint,
    ]);
});

it('rejects malformed subscription payloads', function () {
    actingAs(BrowserNotificationUser::create(['name' => 'Ada', 'email' => Str::uuid() . '@example.com']));

    postJson(route('common.browser-notifications.subscriptions.store'), [
        'endpoint' => 'not-a-url',
        'keys' => [],
        'contentEncoding' => 'unsupported',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['endpoint', 'keys.p256dh', 'keys.auth', 'contentEncoding']);
});

it('rejects subscription endpoints that the consuming app does not allow', function () {
    actingAs(BrowserNotificationUser::create(['name' => 'Ada', 'email' => Str::uuid() . '@example.com']));

    postJson(route('common.browser-notifications.subscriptions.store'), [
        'endpoint' => 'https://127.0.0.1/subscriptions/' . Str::uuid(),
        'keys' => browserNotificationSubscriptionKeys(),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['endpoint']);
});

it('rejects malformed subscription keys', function () {
    actingAs(BrowserNotificationUser::create(['name' => 'Ada', 'email' => Str::uuid() . '@example.com']));

    postJson(route('common.browser-notifications.subscriptions.store'), [
        'endpoint' => 'https://push.example.com/subscriptions/' . Str::uuid(),
        'keys' => [
            'p256dh' => str_repeat('A', 87),
            'auth' => browserNotificationBase64UrlEncode(random_bytes(15)),
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['keys.p256dh', 'keys.auth']);
});

it('does not transfer an endpoint between users', function () {
    $endpoint = 'https://push.example.com/subscriptions/' . Str::uuid();
    $owner = BrowserNotificationUser::create(['name' => 'Owner', 'email' => Str::uuid() . '@example.com']);
    $otherUser = BrowserNotificationUser::create(['name' => 'Other', 'email' => Str::uuid() . '@example.com']);
    $ownerKeys = browserNotificationSubscriptionKeys();

    $owner->updatePushSubscription($endpoint, $ownerKeys['p256dh'], $ownerKeys['auth'], ContentEncoding::aes128gcm);

    actingAs($otherUser);

    postJson(route('common.browser-notifications.subscriptions.store'), [
        'endpoint' => $endpoint,
        'keys' => browserNotificationSubscriptionKeys(),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['endpoint']);

    expect(PushSubscription::query()->sole()->subscribable_id)->toBe($owner->getKey());
});

it('transfers an endpoint when the current browser proves possession of its keys', function () {
    $endpoint = 'https://push.example.com/subscriptions/' . Str::uuid();
    $keys = browserNotificationSubscriptionKeys();
    $owner = BrowserNotificationUser::create(['name' => 'Owner', 'email' => Str::uuid() . '@example.com']);
    $otherUser = BrowserNotificationUser::create(['name' => 'Other', 'email' => Str::uuid() . '@example.com']);

    $owner->updatePushSubscription($endpoint, $keys['p256dh'], $keys['auth'], ContentEncoding::aes128gcm);

    actingAs($otherUser);

    postJson(route('common.browser-notifications.subscriptions.store'), [
        'endpoint' => $endpoint,
        'keys' => $keys,
    ])->assertSuccessful();

    expect(PushSubscription::query()->sole()->subscribable_id)->toBe($otherUser->getKey());
});

it('does not accept subscriptions when the consuming app disables the feature', function () {
    app(BrowserNotificationsManager::class)->availableUsing(fn (): bool => false);

    actingAs(BrowserNotificationUser::create(['name' => 'Ada', 'email' => Str::uuid() . '@example.com']));

    postJson(route('common.browser-notifications.subscriptions.store'), [
        'endpoint' => 'https://push.example.com/subscriptions/' . Str::uuid(),
        'keys' => browserNotificationSubscriptionKeys(),
    ])->assertNotFound();
});

/** @return array{p256dh: string, auth: string} */
function browserNotificationSubscriptionKeys(): array
{
    $opensslKey = openssl_pkey_new([
        'curve_name' => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'private_key_bits' => 2048,
    ]);
    assert($opensslKey !== false);

    $details = openssl_pkey_get_details($opensslKey);
    assert(is_array($details));

    return [
        'p256dh' => browserNotificationBase64UrlEncode("\x04" . $details['ec']['x'] . $details['ec']['y']),
        'auth' => browserNotificationBase64UrlEncode(random_bytes(16)),
    ];
}

function browserNotificationBase64UrlEncode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}
