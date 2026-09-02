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

namespace CanyonGBS\Common\Tests;

use CanyonGBS\Common\CommonServiceProvider;
use Orchestra\Testbench\Foundation\Actions\CreateVendorSymlink;
use Orchestra\Testbench\TestCase as Orchestra;
use Workbench\App\Models\PushSubscription;
use Workbench\App\Models\User;
use Workbench\App\Providers\TestingPanelProvider;

abstract class TestCase extends Orchestra
{
    protected $enablesPackageDiscoveries = true;

    protected function resolveApplication()
    {
        $app = parent::resolveApplication();

        // Package discovery reads the skeleton's `vendor` directory, which
        // must be symlinked to this package's `vendor` directory. The path is
        // resolved explicitly instead of with `package_path()`, which reports
        // Rector's bundled `vendor` directory once a Rector test class has
        // been autoloaded.
        (new CreateVendorSymlink(dirname(__DIR__) . '/vendor'))->handle(clone $app);

        return $app;
    }

    protected function getPackageProviders($app): array
    {
        return [
            CommonServiceProvider::class,
            TestingPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('webpush.database_connection', 'testing');
        $app['config']->set('webpush.model', PushSubscription::class);
        $app['config']->set('webpush.table_name', 'push_subscriptions');
        $app['config']->set('webpush.vapid.subject', 'mailto:test@example.com');
        $app['config']->set('webpush.vapid.public_key', 'test-public-key');
        $app['config']->set('webpush.vapid.private_key', 'test-private-key');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../workbench/database/migrations');
    }
}
