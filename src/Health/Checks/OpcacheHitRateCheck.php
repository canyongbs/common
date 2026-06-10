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

namespace CanyonGBS\Common\Health\Checks;

use CanyonGBS\Common\Health\Services\OpcacheStatusService;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

class OpcacheHitRateCheck extends Check
{
    public function run(): Result
    {
        $status = app(OpcacheStatusService::class)->getStatus();

        if ($status === false) {
            return Result::make()
                ->failed('OPcache is not available.');
        }

        $hits = $status['opcache_statistics']['hits'] ?? 0;
        $misses = $status['opcache_statistics']['misses'] ?? 0;
        $total = $hits + $misses;

        if ($total === 0) {
            return Result::make()
                ->shortSummary('No requests yet')
                ->meta(['hits' => 0, 'misses' => 0, 'hit_rate' => null])
                ->ok();
        }

        $hitRate = round($hits / $total * 100, 2);

        $result = Result::make()
            ->shortSummary("{$hitRate}%")
            ->meta(['hits' => $hits, 'misses' => $misses, 'hit_rate' => $hitRate]);

        if ($hitRate <= 95) {
            return $result->failed("OPcache hit rate is critically low ({$hitRate}%)");
        }

        if ($hitRate < 99) {
            return $result->warning("OPcache hit rate is below optimal ({$hitRate}%)");
        }

        return $result->ok();
    }
}
