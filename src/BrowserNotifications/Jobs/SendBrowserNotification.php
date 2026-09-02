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

namespace CanyonGBS\Common\BrowserNotifications\Jobs;

use CanyonGBS\Common\BrowserNotifications\BrowserNotificationsManager;
use CanyonGBS\Common\BrowserNotifications\Contracts\ReceivesBrowserNotifications;
use CanyonGBS\Common\BrowserNotifications\DataTransferObjects\BrowserNotificationPayload;
use CanyonGBS\Common\BrowserNotifications\Notifications\BrowserNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use Throwable;

class SendBrowserNotification implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 86400;

    public function __construct(
        public Model $notifiable,
        public string $subscriptionId,
        public BrowserNotificationPayload $payload,
    ) {}

    public function uniqueId(): string
    {
        return $this->payload->id . ':' . $this->subscriptionId;
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [1, 5, 10];
    }

    public function handle(BrowserNotificationsManager $browserNotifications): void
    {
        if (! $this->notifiable instanceof ReceivesBrowserNotifications) {
            return;
        }

        if (! $browserNotifications->isAvailable($this->notifiable)) {
            return;
        }

        Notification::send(
            $this->notifiable,
            new BrowserNotification(
                payload: $this->payload,
                subscriptionId: $this->subscriptionId,
                icon: $browserNotifications->resolveIcon($this->notifiable),
            ),
        );
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception !== null) {
            report($exception);
        }
    }
}
