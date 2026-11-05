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

namespace CanyonGBS\Common\Console\Commands;

use CanyonGBS\Common\Console\Concerns\InteractsWithFeatureFlags;
use Illuminate\Console\Command;

class ListFeatureFlags extends Command
{
    use InteractsWithFeatureFlags;

    protected $signature = 'ff:list
        {--tenant=* : Only these tenants (multi-tenant apps; matched like Spatie tenant commands). Defaults to every tenant}';

    protected $description = 'List the feature flags in app/Features and whether each is active';

    public function handle(): int
    {
        $flags = $this->discoverFeatureFlags();

        if ($flags === []) {
            $this->components->info('No feature flags found in app/Features.');

            return self::SUCCESS;
        }

        $hasTenantFlags = array_filter($flags, fn (string $flag): bool => ! $this->isLandlordFeatureFlag($flag)) !== [];

        $tenants = $hasTenantFlags ? $this->targetTenants() : [];

        if ($tenants === null) {
            return self::FAILURE;
        }

        $rows = [];

        foreach ($flags as $flag) {
            if ($this->isLandlordFeatureFlag($flag) || $tenants === []) {
                $rows[] = [
                    $this->featureFlagLabel($flag),
                    $this->isLandlordFeatureFlag($flag) ? 'Landlord' : 'Global',
                    $this->isActive($flag, null) ? 'Yes' : 'No',
                ];

                continue;
            }

            $inactive = array_values(array_filter($tenants, fn (object $tenant): bool => ! $this->isActive($flag, $tenant)));

            $rows[] = [
                $this->featureFlagLabel($flag),
                'Tenant',
                $this->summarizeTenantState(count($tenants), $inactive),
            ];
        }

        $this->table(['Flag', 'Scope', 'Active'], $rows);

        return self::SUCCESS;
    }

    /**
     * @param class-string $flag
     */
    protected function isActive(string $flag, ?object $tenant): bool
    {
        return (bool) $this->withinFeatureFlagContext($tenant, fn (): mixed => call_user_func([$flag, 'active']));
    }

    /**
     * @param array<int, object> $inactive
     */
    protected function summarizeTenantState(int $total, array $inactive): string
    {
        $activeCount = $total - count($inactive);

        if ($inactive === []) {
            return $total === 1 ? 'Yes' : "Yes (all {$total} tenants)";
        }

        if ($activeCount === 0) {
            return $total === 1 ? 'No' : "No (0 of {$total} tenants)";
        }

        $names = implode(', ', array_map(fn (object $tenant): string => $this->tenantRunner()->describe($tenant), $inactive));

        return "Partly ({$activeCount} of {$total} tenants; inactive for {$names})";
    }
}
