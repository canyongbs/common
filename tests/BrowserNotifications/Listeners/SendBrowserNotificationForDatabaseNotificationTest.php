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
use CanyonGBS\Common\BrowserNotifications\DataTransferObjects\BrowserNotificationPayload;
use CanyonGBS\Common\BrowserNotifications\Jobs\SendBrowserNotification;
use CanyonGBS\Common\BrowserNotifications\Listeners\SendBrowserNotificationForDatabaseNotification;
use CanyonGBS\Common\BrowserNotifications\Notifications\BrowserNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Minishlink\WebPush\ContentEncoding;
use Workbench\App\Models\BrowserNotificationUser;

it('dispatches one browser notification with the database notification payload', function () {
    $user = BrowserNotificationUser::create(['name' => 'Ada', 'email' => Str::uuid() . '@example.com']);
    $subscription = $user->updatePushSubscription(
        'https://push.example.com/subscriptions/' . Str::uuid(),
        'public-key',
        'auth-token',
        ContentEncoding::aes128gcm,
    );

    expect(app(BrowserNotificationsManager::class)->isAvailable($user))->toBeTrue()
        ->and($user->routeNotificationForWebPush())->toHaveCount(1);

    Queue::fake();

    $notification = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'test',
        'data' => [
            'title' => 'Request assigned',
            'body' => 'A request needs your attention.',
            'actions' => [['url' => '/requests/123']],
        ],
    ]);

    app(SendBrowserNotificationForDatabaseNotification::class)->handle($notification);

    Queue::assertPushed(SendBrowserNotification::class, function (SendBrowserNotification $job) use ($notification, $subscription): bool {
        return $job->payload->id === $notification->getKey()
            && $job->payload->title === 'Request assigned'
            && $job->payload->body === 'A request needs your attention.'
            && $job->payload->actionUrl === '/requests/123'
            && $job->subscriptionId === $subscription->getKey()
            && $job->afterCommit === true;
    });

    Queue::assertPushed(SendBrowserNotification::class, 1);
});

it('dispatches a separate browser notification job for each subscription', function () {
    $user = BrowserNotificationUser::create(['name' => 'Ada', 'email' => Str::uuid() . '@example.com']);
    $subscriptions = collect([
        $user->updatePushSubscription(
            'https://push.example.com/subscriptions/' . Str::uuid(),
            'public-key',
            'auth-token',
            ContentEncoding::aes128gcm,
        ),
        $user->updatePushSubscription(
            'https://push.example.com/subscriptions/' . Str::uuid(),
            'public-key',
            'auth-token',
            ContentEncoding::aes128gcm,
        ),
    ]);

    Queue::fake();

    $notification = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'test',
        'data' => [
            'title' => 'Request assigned',
            'actions' => [['url' => '/requests/123']],
        ],
    ]);

    Queue::assertPushed(SendBrowserNotification::class, 2);

    $subscriptions->each(fn ($subscription) => Queue::assertPushed(
        SendBrowserNotification::class,
        fn (SendBrowserNotification $job): bool => $job->subscriptionId === $subscription->getKey()
            && $job->uniqueId() === $notification->getKey() . ':' . $subscription->getKey(),
    ));
});

it('does not send a browser notification when database notification creation rolls back', function () {
    config()->set('queue.default', 'sync');

    Notification::fake();

    $user = BrowserNotificationUser::create(['name' => 'Ada', 'email' => Str::uuid() . '@example.com']);
    $user->updatePushSubscription(
        'https://push.example.com/subscriptions/' . Str::uuid(),
        'public-key',
        'auth-token',
        ContentEncoding::aes128gcm,
    );

    try {
        DB::transaction(function () use ($user): void {
            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'test',
                'data' => [
                    'title' => 'Request assigned',
                    'actions' => [['url' => '/requests/123']],
                ],
            ]);

            throw new RuntimeException('Roll back the database notification.');
        });
    } catch (RuntimeException) {
    }

    Notification::assertNothingSent();
});

it('sends a browser notification after database notification creation commits', function () {
    config()->set('queue.default', 'sync');

    Notification::fake();

    $user = BrowserNotificationUser::create(['name' => 'Ada', 'email' => Str::uuid() . '@example.com']);
    $user->updatePushSubscription(
        'https://push.example.com/subscriptions/' . Str::uuid(),
        'public-key',
        'auth-token',
        ContentEncoding::aes128gcm,
    );

    DB::transaction(function () use ($user): void {
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'test',
            'data' => [
                'title' => 'Request assigned',
                'actions' => [['url' => '/requests/123']],
            ],
        ]);
    });

    Notification::assertSentTo($user, BrowserNotification::class);
});

it('does not query subscriptions or dispatch when the consuming app disables the feature', function () {
    app(BrowserNotificationsManager::class)->availableUsing(fn (): bool => false);

    Queue::fake();

    $user = BrowserNotificationUser::create(['name' => 'Ada', 'email' => Str::uuid() . '@example.com']);

    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'test',
        'data' => ['title' => 'Request assigned'],
    ]);

    Queue::assertNothingPushed();
});

it('does not dispatch a custom browser notification payload without a destination', function () {
    $user = BrowserNotificationUser::create(['name' => 'Ada', 'email' => Str::uuid() . '@example.com']);
    $user->updatePushSubscription(
        'https://push.example.com/subscriptions/' . Str::uuid(),
        'public-key',
        'auth-token',
        ContentEncoding::aes128gcm,
    );

    $endpointWasChecked = false;

    app(BrowserNotificationsManager::class)
        ->resolvePayloadUsing(fn (): BrowserNotificationPayload => new BrowserNotificationPayload(
            id: (string) Str::uuid(),
            title: 'Request assigned',
            body: 'A request needs your attention.',
            actionUrl: '',
        ))
        ->allowSubscriptionEndpointsUsing(function () use (&$endpointWasChecked): bool {
            $endpointWasChecked = true;

            return true;
        });

    Queue::fake();

    $notification = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'test',
        'data' => ['title' => 'Request assigned'],
    ]);

    app(SendBrowserNotificationForDatabaseNotification::class)->handle($notification);

    Queue::assertNothingPushed();

    expect($endpointWasChecked)->toBeFalse();
});

it('isolates browser notification failures from database notification creation', function () {
    $manager = app(BrowserNotificationsManager::class);
    $manager->availableUsing(fn (): bool => false);

    $user = BrowserNotificationUser::create(['name' => 'Ada', 'email' => Str::uuid() . '@example.com']);
    $user->updatePushSubscription(
        'https://push.example.com/subscriptions/' . Str::uuid(),
        'public-key',
        'auth-token',
        ContentEncoding::aes128gcm,
    );

    $notification = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'test',
        'data' => ['title' => 'Request assigned'],
    ]);

    $resolverWasCalled = false;

    $manager
        ->availableUsing(fn (): bool => true)
        ->resolvePayloadUsing(function () use (&$resolverWasCalled): never {
            $resolverWasCalled = true;

            throw new RuntimeException('Payload resolution failed.');
        });

    app(SendBrowserNotificationForDatabaseNotification::class)->handle($notification);

    expect($resolverWasCalled)->toBeTrue()
        ->and($notification->exists)->toBeTrue();
});
