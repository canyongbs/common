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
      of the licensor in the software. Any use of the licensor's trademarks is subject
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

use CanyonGBS\Common\Health\Checks\OpcacheCachedFilesCheck;
use CanyonGBS\Common\Health\Services\OpcacheStatusService;
use Mockery\MockInterface;
use Spatie\Health\Enums\Status;

it('returns ok status when cached scripts are well below max', function () {
    $this->mock(OpcacheStatusService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getStatus')->andReturn([
            'opcache_statistics' => [
                'num_cached_scripts' => 3000,
                'max_cached_keys' => 10000,
            ],
        ]);
    });

    $result = (new OpcacheCachedFilesCheck())->run();

    expect($result->status)->toBe(Status::ok());
});

it('returns warning status when cached scripts reach 90% of max', function () {
    $this->mock(OpcacheStatusService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getStatus')->andReturn([
            'opcache_statistics' => [
                'num_cached_scripts' => 9000,
                'max_cached_keys' => 10000,
            ],
        ]);
    });

    $result = (new OpcacheCachedFilesCheck())->run();

    expect($result->status)->toBe(Status::warning());
});

it('returns warning status when cached scripts are between 90% and 100% of max', function () {
    $this->mock(OpcacheStatusService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getStatus')->andReturn([
            'opcache_statistics' => [
                'num_cached_scripts' => 9500,
                'max_cached_keys' => 10000,
            ],
        ]);
    });

    $result = (new OpcacheCachedFilesCheck())->run();

    expect($result->status)->toBe(Status::warning());
});

it('returns failed status when cached scripts reach max', function () {
    $this->mock(OpcacheStatusService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getStatus')->andReturn([
            'opcache_statistics' => [
                'num_cached_scripts' => 10000,
                'max_cached_keys' => 10000,
            ],
        ]);
    });

    $result = (new OpcacheCachedFilesCheck())->run();

    expect($result->status)->toBe(Status::failed());
});

it('returns failed status when cached scripts exceed max', function () {
    $this->mock(OpcacheStatusService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getStatus')->andReturn([
            'opcache_statistics' => [
                'num_cached_scripts' => 10500,
                'max_cached_keys' => 10000,
            ],
        ]);
    });

    $result = (new OpcacheCachedFilesCheck())->run();

    expect($result->status)->toBe(Status::failed());
});

it('returns failed status when opcache is not available', function () {
    $this->mock(OpcacheStatusService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getStatus')->andReturn(false);
    });

    $result = (new OpcacheCachedFilesCheck())->run();

    expect($result->status)->toBe(Status::failed());
});

it('returns failed status when max_cached_keys is zero', function () {
    $this->mock(OpcacheStatusService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getStatus')->andReturn([
            'opcache_statistics' => [
                'num_cached_scripts' => 0,
                'max_cached_keys' => 0,
            ],
        ]);
    });

    $result = (new OpcacheCachedFilesCheck())->run();

    expect($result->status)->toBe(Status::failed());
});
