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

use CanyonGBS\Common\Health\Checks\OpcacheHitRateCheck;
use CanyonGBS\Common\Health\Services\OpcacheStatusService;
use Mockery\MockInterface;
use Spatie\Health\Enums\Status;

it('returns ok status when hit rate is above 99%', function () {
    $this->mock(OpcacheStatusService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getStatus')->andReturn([
            'opcache_statistics' => [
                'hits' => 9950,
                'misses' => 10,
            ],
        ]);
    });

    $result = (new OpcacheHitRateCheck())->run();

    expect($result->status)->toBe(Status::ok());
});

it('returns warning status when hit rate is below 99% but above 95%', function () {
    $this->mock(OpcacheStatusService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getStatus')->andReturn([
            'opcache_statistics' => [
                'hits' => 970,
                'misses' => 30,
            ],
        ]);
    });

    $result = (new OpcacheHitRateCheck())->run();

    expect($result->status)->toBe(Status::warning());
});

it('returns failed status when hit rate is at or below 95%', function () {
    $this->mock(OpcacheStatusService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getStatus')->andReturn([
            'opcache_statistics' => [
                'hits' => 950,
                'misses' => 50,
            ],
        ]);
    });

    $result = (new OpcacheHitRateCheck())->run();

    expect($result->status)->toBe(Status::failed());
});

it('returns failed status when hit rate is below 95%', function () {
    $this->mock(OpcacheStatusService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getStatus')->andReturn([
            'opcache_statistics' => [
                'hits' => 800,
                'misses' => 200,
            ],
        ]);
    });

    $result = (new OpcacheHitRateCheck())->run();

    expect($result->status)->toBe(Status::failed());
});

it('returns ok status when there are no requests yet', function () {
    $this->mock(OpcacheStatusService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getStatus')->andReturn([
            'opcache_statistics' => [
                'hits' => 0,
                'misses' => 0,
            ],
        ]);
    });

    $result = (new OpcacheHitRateCheck())->run();

    expect($result->status)->toBe(Status::ok());
});

it('returns failed status when opcache is not available', function () {
    $this->mock(OpcacheStatusService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getStatus')->andReturn(false);
    });

    $result = (new OpcacheHitRateCheck())->run();

    expect($result->status)->toBe(Status::failed());
});
