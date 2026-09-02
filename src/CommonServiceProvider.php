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

namespace CanyonGBS\Common;

use CanyonGBS\Common\BrowserNotifications\BrowserNotificationsManager;
use CanyonGBS\Common\BrowserNotifications\Http\Controllers\DeletePushSubscriptionController;
use CanyonGBS\Common\BrowserNotifications\Http\Controllers\ServiceWorkerController;
use CanyonGBS\Common\BrowserNotifications\Http\Controllers\StorePushSubscriptionController;
use CanyonGBS\Common\BrowserNotifications\Listeners\SendBrowserNotificationForDatabaseNotification;
use CanyonGBS\Common\BrowserNotifications\Support\BrowserNotificationReportHandler;
use CanyonGBS\Common\Console\Commands\CreatePermissionMigration;
use CanyonGBS\Common\Console\Commands\MakeCleanupTask;
use CanyonGBS\Common\Console\Commands\MakeFeatureFlag;
use CanyonGBS\Common\Console\Commands\MakeTmpMigration;
use CanyonGBS\Common\Console\Commands\Publish;
use CanyonGBS\Common\Database\Migrations\PermissionMigrationCreator;
use CanyonGBS\Common\Support\PermissionResolver;
use Filament\Support\Assets\Js;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use NotificationChannels\WebPush\ReportHandlerInterface;
use NotificationChannels\WebPush\WebPushChannel;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Tapp\FilamentTimezoneField\Forms\Components\TimezoneSelect;

class CommonServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('common')
            ->hasViews()
            ->hasMigrations([
                'create_roles_table',
                'create_role_assignments_table',
                'create_role_permissions_table',
            ])
            ->hasCommands([
                MakeCleanupTask::class,
                MakeFeatureFlag::class,
                Publish::class,
            ]);
    }

    public function packageRegistered(): void
    {
        config()->set('webpush.client_options.allow_redirects', false);

        $this->app->singleton(BrowserNotificationsManager::class);

        $this->app->singleton(PermissionIndex::class);

        $this->app->scoped(PermissionResolver::class);

        $this->app->singleton(MakeTmpMigration::class, function (Application $app) {
            return new MakeTmpMigration($app['migration.creator'], $app[Composer::class]);
        });

        $this->app->singleton(PermissionMigrationCreator::class, function (Application $app) {
            return new PermissionMigrationCreator($app['files'], $app->basePath('stubs'));
        });

        $this->app->singleton(CreatePermissionMigration::class, function (Application $app) {
            return new CreatePermissionMigration($app[PermissionMigrationCreator::class], $app[Composer::class]);
        });

        $this->commands([
            MakeTmpMigration::class,
            CreatePermissionMigration::class,
        ]);
    }

    public function packageBooted(): void
    {
        $browserNotifications = app(BrowserNotificationsManager::class);

        if ($browserNotifications->isEnabled()) {
            Event::listen(
                'eloquent.created: ' . DatabaseNotification::class,
                SendBrowserNotificationForDatabaseNotification::class,
            );

            $this->registerBrowserNotificationRoutes($browserNotifications);
        }

        $this->app->booted(function (): void {
            $this->app->when(WebPushChannel::class)
                ->needs(ReportHandlerInterface::class)
                ->give(BrowserNotificationReportHandler::class);
        });

        FilamentAsset::register([
            Js::make('browser-notifications', __DIR__ . '/../resources/js/dist/filament/browser-notifications.js')
                ->loadedOnRequest(),
            Js::make('rich-content-plugins/video-embed', __DIR__ . '/../resources/js/dist/filament/rich-content-plugins/video-embed.js')
                ->loadedOnRequest(),
        ], 'common');

        FilamentColor::register([
            'red' => Color::Red,
            'orange' => Color::Orange,
            'amber' => Color::Amber,
            'yellow' => Color::Yellow,
            'lime' => Color::Lime,
            'green' => Color::Green,
            'emerald' => Color::Emerald,
            'teal' => Color::Teal,
            'cyan' => Color::Cyan,
            'sky' => Color::Sky,
            'blue' => Color::Blue,
            'indigo' => Color::Indigo,
            'violet' => Color::Violet,
            'purple' => Color::Purple,
            'fuchsia' => Color::Fuchsia,
            'pink' => Color::Pink,
            'rose' => Color::Rose,
        ]);

        TimezoneSelect::configureUsing(function (TimezoneSelect $component) {
            $component->searchable();
        });
    }

    protected function registerBrowserNotificationRoutes(BrowserNotificationsManager $browserNotifications): void
    {
        Route::middleware($browserNotifications->getRouteMiddleware())
            ->group(function () use ($browserNotifications): void {
                Route::get('/browser-notifications/service-worker.js', ServiceWorkerController::class)
                    ->name('common.browser-notifications.service-worker');

                Route::middleware($browserNotifications->getAuthMiddleware())
                    ->group(function (): void {
                        Route::post('/browser-notifications/subscriptions', StorePushSubscriptionController::class)
                            ->name('common.browser-notifications.subscriptions.store');

                        Route::delete('/browser-notifications/subscriptions', DeletePushSubscriptionController::class)
                            ->name('common.browser-notifications.subscriptions.destroy');
                    });
            });
    }
}
