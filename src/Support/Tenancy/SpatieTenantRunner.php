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

namespace CanyonGBS\Common\Support\Tenancy;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Mirrors how `spatie/laravel-multitenancy` tenant-aware commands find and switch tenants
 * (`Spatie\Multitenancy\Commands\Concerns\TenantAware`), without a hard dependency on the package.
 */
class SpatieTenantRunner implements TenantRunner
{
    public const TENANT_CONTRACT = 'Spatie\Multitenancy\Contracts\IsTenant';

    public function __construct(
        protected Container $container,
        protected Config $config,
    ) {}

    public static function isAvailable(Container $container): bool
    {
        return $container->bound(self::TENANT_CONTRACT);
    }

    public function supportsTenants(): bool
    {
        return true;
    }

    public function current(): ?object
    {
        $current = call_user_func([$this->tenantModel()::class, 'current']);

        return is_object($current) ? $current : null;
    }

    public function tenants(array $identifiers = []): array
    {
        $query = $this->tenantModel()->newQuery();

        if ($identifiers !== []) {
            $query->where(function (Builder $query) use ($identifiers): void {
                foreach ($this->searchFields() as $field) {
                    $query->orWhereIn($field, $identifiers);
                }
            });
        }

        return $query->get()->all();
    }

    public function execute(object $tenant, Closure $callback): mixed
    {
        return call_user_func([$tenant, 'execute'], $callback);
    }

    public function describe(object $tenant): string
    {
        assert($tenant instanceof Model);

        $name = $tenant->getAttribute('name');
        $key = $tenant->getKey();

        return filled($name) ? "{$name} ({$key})" : (string) $key;
    }

    protected function tenantModel(): Model
    {
        $model = $this->container->make(self::TENANT_CONTRACT);

        assert($model instanceof Model);

        return $model;
    }

    /**
     * @return array<int, string>
     */
    protected function searchFields(): array
    {
        return Arr::wrap($this->config->get('multitenancy.tenant_artisan_search_fields', ['id']));
    }
}
