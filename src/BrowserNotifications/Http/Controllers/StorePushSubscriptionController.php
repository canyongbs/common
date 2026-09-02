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

namespace CanyonGBS\Common\BrowserNotifications\Http\Controllers;

use CanyonGBS\Common\BrowserNotifications\Actions\StorePushSubscription;
use CanyonGBS\Common\BrowserNotifications\BrowserNotificationsManager;
use CanyonGBS\Common\BrowserNotifications\Contracts\ReceivesBrowserNotifications;
use CanyonGBS\Common\BrowserNotifications\Support\BrowserNotificationSubscriptionKeyValidator;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Minishlink\WebPush\ContentEncoding;

class StorePushSubscriptionController
{
    public function __invoke(
        Request $request,
        BrowserNotificationsManager $browserNotifications,
        BrowserNotificationSubscriptionKeyValidator $subscriptionKeyValidator,
        StorePushSubscription $storePushSubscription,
    ): JsonResponse {
        $notifiable = $request->user();

        abort_unless($notifiable instanceof ReceivesBrowserNotifications, 404);
        abort_unless($browserNotifications->isAvailable($notifiable), 404);

        $validated = $request->validate([
            'endpoint' => [
                'required',
                'string',
                'url:https',
                'max:500',
                function (string $attribute, mixed $value, Closure $fail) use ($browserNotifications, $notifiable): void {
                    if (! is_string($value) || ! $browserNotifications->isSubscriptionEndpointAllowed($value, $notifiable)) {
                        $fail('The browser subscription endpoint is invalid.');
                    }
                },
            ],
            'keys' => ['required', 'array:p256dh,auth'],
            'keys.p256dh' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail) use ($subscriptionKeyValidator): void {
                    if (! is_string($value) || ! $subscriptionKeyValidator->isValidPublicKey($value)) {
                        $fail('The browser subscription public key is invalid.');
                    }
                },
            ],
            'keys.auth' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail) use ($subscriptionKeyValidator): void {
                    if (! is_string($value) || ! $subscriptionKeyValidator->isValidAuthToken($value)) {
                        $fail('The browser subscription auth token is invalid.');
                    }
                },
            ],
            'contentEncoding' => ['nullable', Rule::enum(ContentEncoding::class)],
        ]);

        $contentEncoding = ContentEncoding::tryFrom($validated['contentEncoding'] ?? '')
            ?? ContentEncoding::aes128gcm;

        $subscription = $storePushSubscription(
            notifiable: $notifiable,
            endpoint: $validated['endpoint'],
            publicKey: $validated['keys']['p256dh'],
            authToken: $validated['keys']['auth'],
            contentEncoding: $contentEncoding,
        );

        return response()->json([
            'id' => (string) $subscription->getKey(),
        ]);
    }
}
