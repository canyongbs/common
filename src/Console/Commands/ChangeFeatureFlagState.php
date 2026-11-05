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
use Illuminate\Console\ConfirmableTrait;

abstract class ChangeFeatureFlagState extends Command
{
    use ConfirmableTrait;
    use InteractsWithFeatureFlags;

    public function handle(): int
    {
        $names = $this->argument('flags');

        assert(is_array($names));

        $flags = [];

        foreach ($names as $name) {
            $flag = $this->resolveFeatureFlag($name);

            if ($flag === null) {
                $this->components->error("[{$name}] is not a feature flag. Pass a class in `{$this->featureFlagsNamespace}` that extends `{$this->tenantFeatureFlagBaseClass}` or `{$this->landlordFeatureFlagBaseClass}`.");

                return self::FAILURE;
            }

            $flags[] = $flag;
        }

        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $tenantFlags = array_values(array_filter($flags, fn (string $flag): bool => ! $this->isLandlordFeatureFlag($flag)));
        $landlordFlags = array_values(array_filter($flags, fn (string $flag): bool => $this->isLandlordFeatureFlag($flag)));

        if ($landlordFlags !== [] && $this->option('tenant') !== []) {
            $this->components->error('The --tenant option cannot be used with landlord feature flags.');

            return self::FAILURE;
        }

        $tenants = $tenantFlags === [] ? [] : $this->targetTenants();

        if ($tenants === null) {
            return self::FAILURE;
        }

        foreach ($landlordFlags as $flag) {
            $this->components->task(
                "{$this->verb()} {$this->featureFlagLabel($flag)} (landlord)",
                fn (): mixed => $this->withinFeatureFlagContext(null, fn (): mixed => call_user_func([$flag, $this->method()])),
            );
        }

        foreach ($tenantFlags as $flag) {
            if ($tenants === []) {
                $this->components->task(
                    "{$this->verb()} {$this->featureFlagLabel($flag)}",
                    fn (): mixed => $this->withinFeatureFlagContext(null, fn (): mixed => call_user_func([$flag, $this->method()])),
                );

                continue;
            }

            foreach ($tenants as $tenant) {
                $this->components->task(
                    "{$this->verb()} {$this->featureFlagLabel($flag)} for tenant {$this->tenantRunner()->describe($tenant)}",
                    fn (): mixed => $this->withinFeatureFlagContext($tenant, fn (): mixed => call_user_func([$flag, $this->method()])),
                );
            }
        }

        return self::SUCCESS;
    }

    abstract protected function method(): string;

    abstract protected function verb(): string;
}
