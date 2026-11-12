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

namespace CanyonGBS\Common\Console\Concerns;

use CanyonGBS\Common\Support\Tenancy\TenantRunner;
use Closure;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

trait InteractsWithFeatureFlags
{
    protected string $featureFlagsNamespace = 'App\\Features';

    protected string $tenantFeatureFlagBaseClass = 'App\\Support\\AbstractFeatureFlag';

    protected string $landlordFeatureFlagBaseClass = 'App\\Support\\LandlordAbstractFeatureFlag';

    /**
     * Accepts a fully-qualified class name, or a class name in the features namespace with or without its `Feature` suffix.
     *
     * @return class-string|null
     */
    protected function resolveFeatureFlag(string $name): ?string
    {
        $name = ltrim($name, '\\');

        $candidates = [
            $name,
            "{$this->featureFlagsNamespace}\\{$name}",
            Str::finish("{$this->featureFlagsNamespace}\\{$name}", 'Feature'),
        ];

        foreach ($candidates as $candidate) {
            if (class_exists($candidate) && $this->isFeatureFlag($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param class-string $class
     */
    protected function isFeatureFlag(string $class): bool
    {
        // Abstract helpers may live in the features namespace, but Pennant cannot instantiate them.
        if ((new ReflectionClass($class))->isAbstract()) {
            return false;
        }

        return is_subclass_of($class, $this->tenantFeatureFlagBaseClass)
            || is_subclass_of($class, $this->landlordFeatureFlagBaseClass);
    }

    /**
     * @param class-string $class
     */
    protected function isLandlordFeatureFlag(string $class): bool
    {
        return is_subclass_of($class, $this->landlordFeatureFlagBaseClass);
    }

    /**
     * Find feature flags the same way `Feature::discover()` does: every class directly inside `app/Features`.
     *
     * @return array<int, class-string>
     */
    protected function discoverFeatureFlags(): array
    {
        $path = $this->laravel->basePath('app/Features');

        if (! is_dir($path)) {
            return [];
        }

        $flags = [];

        foreach ((new Finder())->files()->name('*.php')->depth(0)->in($path)->sortByName() as $file) {
            $class = "{$this->featureFlagsNamespace}\\{$file->getBasename('.php')}";

            if (class_exists($class) && $this->isFeatureFlag($class)) {
                $flags[] = $class;
            }
        }

        return $flags;
    }

    protected function tenantRunner(): TenantRunner
    {
        return $this->laravel->make(TenantRunner::class);
    }

    /**
     * Reject `--tenant` in applications without tenants, whatever flags are involved.
     */
    protected function tenantOptionIsSupported(): bool
    {
        $identifiers = $this->option('tenant');

        assert(is_array($identifiers));

        if ($identifiers !== [] && ! $this->tenantRunner()->supportsTenants()) {
            $this->components->error('The --tenant option is only available in multi-tenant applications.');

            return false;
        }

        return true;
    }

    /**
     * Resolve the tenants a tenant-scoped flag should be changed or read for, following
     * Spatie's tenant-aware commands: the current tenant when one is set, otherwise
     * every tenant, narrowed by `--tenant`.
     *
     * @return array<int, object>|null Null when the command should fail.
     */
    protected function targetTenants(): ?array
    {
        $identifiers = $this->option('tenant');

        assert(is_array($identifiers));

        $runner = $this->tenantRunner();

        if (! $runner->supportsTenants()) {
            return $this->tenantOptionIsSupported() ? [] : null;
        }

        if ($current = $runner->current()) {
            if ($identifiers !== []) {
                $this->components->error('A tenant is already current; do not combine --tenant with a tenant-aware runner such as `tenants:artisan`.');

                return null;
            }

            return [$current];
        }

        $tenants = $runner->tenants($identifiers);

        if ($tenants === []) {
            $this->components->error('No tenant(s) found.');

            return null;
        }

        return $tenants;
    }

    /**
     * Run a callback against a flag's store, inside the tenant when one is given.
     *
     * Pennant caches resolved values per store, and every tenant shares the same store
     * name while its database connection is switched underneath it, so the cache is
     * flushed on either side of the callback.
     */
    protected function withinFeatureFlagContext(?object $tenant, Closure $callback): mixed
    {
        $run = function () use ($callback): mixed {
            Feature::flushCache();

            try {
                return $callback();
            } finally {
                Feature::flushCache();
            }
        };

        return $tenant === null ? $run() : $this->tenantRunner()->execute($tenant, $run);
    }

    /**
     * @param class-string $class
     */
    protected function featureFlagLabel(string $class): string
    {
        return Str::after($class, "{$this->featureFlagsNamespace}\\");
    }
}
