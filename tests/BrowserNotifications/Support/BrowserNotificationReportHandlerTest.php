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

use CanyonGBS\Common\BrowserNotifications\Exceptions\RetryableBrowserNotificationDeliveryException;
use CanyonGBS\Common\BrowserNotifications\Support\BrowserNotificationReportHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Event;
use Minishlink\WebPush\MessageSentReport;
use NotificationChannels\WebPush\Events\NotificationFailed;
use NotificationChannels\WebPush\PushSubscription;
use NotificationChannels\WebPush\ReportHandler;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

it('configures Web Push to use the browser notification report handler', function () {
    $channel = app(WebPushChannel::class);
    $reportHandler = (new ReflectionProperty(WebPushChannel::class, 'reportHandler'))->getValue($channel);

    expect($reportHandler)->toBeInstanceOf(BrowserNotificationReportHandler::class);
});

it('reports and retries transient browser notification failures', function (?int $status) {
    Event::fake();

    expect(fn () => browserNotificationReportHandler()->handleReport(
        browserNotificationDeliveryReport($status),
        new PushSubscription(['endpoint' => 'https://push.example.com/subscription']),
        commonBrowserNotificationMessage(),
    ))->toThrow(RetryableBrowserNotificationDeliveryException::class);

    Event::assertDispatched(NotificationFailed::class);
})->with([
    'network failure' => null,
    'request timeout' => 408,
    'too early' => 425,
    'rate limited' => 429,
    'internal server error' => 500,
    'service unavailable' => 503,
]);

it('reports without retrying permanent browser notification failures', function (int $status) {
    Event::fake();

    expect(fn () => browserNotificationReportHandler()->handleReport(
        browserNotificationDeliveryReport($status),
        new PushSubscription(['endpoint' => 'https://push.example.com/subscription']),
        commonBrowserNotificationMessage(),
    ))->not->toThrow(RetryableBrowserNotificationDeliveryException::class);

    Event::assertDispatched(NotificationFailed::class);
})->with([
    'bad request' => 400,
    'unauthorized' => 401,
    'forbidden' => 403,
    'expired' => 404,
    'gone' => 410,
    'unprocessable' => 422,
]);

it('does not retry unrelated Web Push failures', function () {
    $message = (new WebPushMessage())->data([]);

    expect(fn () => browserNotificationReportHandler()->handleReport(
        browserNotificationDeliveryReport(503),
        new PushSubscription(['endpoint' => 'https://push.example.com/subscription']),
        $message,
    ))->not->toThrow(RetryableBrowserNotificationDeliveryException::class);
});

function browserNotificationReportHandler(): BrowserNotificationReportHandler
{
    return new BrowserNotificationReportHandler(app(ReportHandler::class));
}

function browserNotificationDeliveryReport(?int $status): MessageSentReport
{
    return new MessageSentReport(
        request: new Request('POST', 'https://push.example.com/subscription'),
        response: $status === null ? null : new Response($status),
        success: false,
        reason: 'Delivery failed.',
    );
}

function commonBrowserNotificationMessage(): WebPushMessage
{
    return (new WebPushMessage())->data(['common_browser_notification' => true]);
}
