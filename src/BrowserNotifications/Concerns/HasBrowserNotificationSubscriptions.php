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

namespace CanyonGBS\Common\BrowserNotifications\Concerns;

use CanyonGBS\Common\BrowserNotifications\BrowserNotificationsManager;
use CanyonGBS\Common\BrowserNotifications\Notifications\BrowserNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use Minishlink\WebPush\ContentEncoding;
use NotificationChannels\WebPush\PushSubscription;

trait HasBrowserNotificationSubscriptions
{
    public function getBrowserNotificationStorageKey(): string
    {
        return hash('sha256', $this->getMorphClass() . ':' . $this->getKey());
    }

    /** @return MorphMany<PushSubscription, $this> */
    public function pushSubscriptions(): MorphMany
    {
        $model = config('webpush.model');

        assert(is_string($model) && is_a($model, PushSubscription::class, true));

        return $this->morphMany($model, 'subscribable');
    }

    public function updatePushSubscription(
        string $endpoint,
        ?string $key = null,
        ?string $token = null,
        ContentEncoding | string | null $contentEncoding = null,
    ): PushSubscription {
        $model = config('webpush.model');

        assert(is_string($model) && is_a($model, PushSubscription::class, true));

        if (is_string($contentEncoding)) {
            $contentEncoding = ContentEncoding::from($contentEncoding);
        }

        $subscription = $model::findByEndpoint($endpoint);

        if ($subscription && ! $this->ownsPushSubscription($subscription)) {
            if (! $this->subscriptionKeysMatch($subscription, $key, $token)) {
                throw ValidationException::withMessages([
                    'endpoint' => 'This browser subscription belongs to another user.',
                ]);
            }

            $subscription->subscribable()->associate($this);
        }

        if (! $subscription) {
            $subscription = new $model();
            $subscription->endpoint = $endpoint;
            $this->pushSubscriptions()->save($subscription);
        }

        $subscription->public_key = $key;
        $subscription->auth_token = $token;
        $subscription->content_encoding = $contentEncoding;
        $subscription->save();

        return $subscription;
    }

    public function ownsPushSubscription(PushSubscription $subscription): bool
    {
        return (string) $subscription->subscribable_id === (string) $this->getKey()
            && $subscription->subscribable_type === $this->getMorphClass();
    }

    public function deletePushSubscription(string $endpoint): void
    {
        $this->pushSubscriptions()
            ->where('endpoint', $endpoint)
            ->delete();
    }

    /** @return Collection<array-key, PushSubscription> */
    public function routeNotificationForWebPush(?Notification $notification = null): Collection
    {
        $browserNotifications = app(BrowserNotificationsManager::class);
        $subscriptions = $this->pushSubscriptions();

        if ($notification instanceof BrowserNotification) {
            $subscriptions->whereKey($notification->getSubscriptionId());
        }

        return $subscriptions
            ->get()
            ->filter(fn (PushSubscription $subscription): bool => $browserNotifications->isSubscriptionEndpointAllowed(
                $subscription->endpoint,
                $this,
            ))
            ->values();
    }

    protected function subscriptionKeysMatch(PushSubscription $subscription, ?string $key, ?string $token): bool
    {
        return is_string($subscription->public_key)
          && is_string($subscription->auth_token)
          && is_string($key)
          && is_string($token)
          && hash_equals($subscription->public_key, $key)
          && hash_equals($subscription->auth_token, $token);
    }
}
