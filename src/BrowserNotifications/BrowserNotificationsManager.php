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

namespace CanyonGBS\Common\BrowserNotifications;

use CanyonGBS\Common\BrowserNotifications\DataTransferObjects\BrowserNotificationPayload;
use CanyonGBS\Common\BrowserNotifications\Support\BrowserNotificationEndpointValidator;
use CanyonGBS\Common\BrowserNotifications\Support\BrowserNotificationPayloadResolver;
use Closure;
use Illuminate\Notifications\DatabaseNotification;
use Throwable;

class BrowserNotificationsManager
{
    protected ?Closure $availabilityCallback = null;

    protected ?Closure $payloadResolverCallback = null;

    protected ?Closure $subscriptionEndpointCallback = null;

    protected Closure $iconCallback;

    /** @var array<int, string> */
    protected array $routeMiddleware = ['web'];

    /** @var array<int, string> */
    protected array $authMiddleware = ['auth'];

    public function __construct()
    {
        $this->iconCallback = fn (): string => '/favicon.ico';
    }

    public function availableUsing(Closure $callback): static
    {
        $this->availabilityCallback = $callback;

        return $this;
    }

    public function resolvePayloadUsing(Closure $callback): static
    {
        $this->payloadResolverCallback = $callback;

        return $this;
    }

    public function allowSubscriptionEndpointsUsing(Closure $callback): static
    {
        $this->subscriptionEndpointCallback = $callback;

        return $this;
    }

    public function iconUsing(Closure $callback): static
    {
        $this->iconCallback = $callback;

        return $this;
    }

    /** @param array<int, string> $middleware */
    public function routeMiddleware(array $middleware): static
    {
        $this->routeMiddleware = $middleware;

        return $this;
    }

    /** @param array<int, string> $middleware */
    public function authMiddleware(array $middleware): static
    {
        $this->authMiddleware = $middleware;

        return $this;
    }

    public function isAvailable(?object $notifiable = null): bool
    {
        if (! $this->isConfigured() || $this->availabilityCallback === null) {
            return false;
        }

        try {
            return (bool) app()->call($this->availabilityCallback, ['notifiable' => $notifiable]);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    public function isEnabled(): bool
    {
        return $this->availabilityCallback !== null;
    }

    public function isConfigured(): bool
    {
        return filled(config('webpush.vapid.subject'))
            && filled(config('webpush.vapid.public_key'))
            && filled(config('webpush.vapid.private_key'));
    }

    public function isSubscriptionEndpointAllowed(string $endpoint, ?object $notifiable = null): bool
    {
        try {
            $endpointValidator = app(BrowserNotificationEndpointValidator::class);

            if (! $endpointValidator->hasValidStructure($endpoint)) {
                return false;
            }

            if ($this->subscriptionEndpointCallback !== null) {
                return (bool) app()->call($this->subscriptionEndpointCallback, [
                    'endpoint' => $endpoint,
                    'notifiable' => $notifiable,
                ]);
            }

            return $endpointValidator->isAllowed($endpoint);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    public function resolvePayload(DatabaseNotification $notification): ?BrowserNotificationPayload
    {
        if ($this->payloadResolverCallback !== null) {
            $payload = app()->call($this->payloadResolverCallback, ['notification' => $notification]);

            assert($payload instanceof BrowserNotificationPayload || $payload === null);

            return $payload;
        }

        return app(BrowserNotificationPayloadResolver::class)->resolve($notification);
    }

    public function resolveIcon(?object $notifiable = null): string
    {
        try {
            return (string) app()->call($this->iconCallback, ['notifiable' => $notifiable]);
        } catch (Throwable $exception) {
            report($exception);

            return '/favicon.ico';
        }
    }

    /** @return array<int, string> */
    public function getRouteMiddleware(): array
    {
        return $this->routeMiddleware;
    }

    /** @return array<int, string> */
    public function getAuthMiddleware(): array
    {
        return $this->authMiddleware;
    }
}
