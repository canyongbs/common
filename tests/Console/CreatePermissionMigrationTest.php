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

use CanyonGBS\Common\Console\Commands\CreatePermissionMigration;
use Illuminate\Database\Console\Migrations\MigrateMakeCommand;
use Illuminate\Database\Migrations\MigrationCreator;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->testDir = sys_get_temp_dir() . '/' . uniqid('common-permission-migration-test-');
    mkdir($this->testDir, 0755, true);
    $this->app->setBasePath($this->testDir);
    $this->migrationsDir = $this->app->databasePath('migrations');
});

afterEach(function () {
    File::deleteDirectory($this->testDir);
});

it('creates a permission migration file in database/migrations', function () {
    $this->artisan('make:permission-migration', ['name' => 'seed_permissions_for_example'])
        ->assertSuccessful();

    $files = glob($this->migrationsDir . '/*_seed_permissions_for_example.php');

    expect($files)->toHaveCount(1);
});

it('falls back to the bundled placeholder stub when the app provides none', function () {
    $this->artisan('make:permission-migration', ['name' => 'seed_permissions_for_fallback'])
        ->assertSuccessful();

    $files = glob($this->migrationsDir . '/*_seed_permissions_for_fallback.php');

    expect($files)->toHaveCount(1);

    $content = file_get_contents($files[0]);

    // The bundled fallback uses the CanModifyPermissions trait and a placeholder guard,
    // never a real guard, so a missing-stub app can't silently generate wrong guards.
    expect($content)->toContain('use CanModifyPermissions;')
        ->and($content)->toContain('createPermissions')
        ->and($content)->toContain('guard-name');
});

it('honors an app-level stubs/permission-migration.stub override', function () {
    // Discover where the creator actually looks for an app-level stub (its
    // customStubPath, captured when the command was constructed) and write the
    // override there. This keeps the test independent of base-path resolution
    // timing — in a real app the base path is fixed at bootstrap, long before any
    // command resolves, so the override is always read from <base>/stubs.
    $command = $this->app->make(CreatePermissionMigration::class);
    $creator = Closure::bind(fn () => $this->creator, $command, MigrateMakeCommand::class)();
    $stubsDir = Closure::bind(fn () => $this->customStubPath, $creator, MigrationCreator::class)();

    File::ensureDirectoryExists($stubsDir);
    $stubFile = $stubsDir . '/permission-migration.stub';
    file_put_contents($stubFile, "<?php\n\n// APP OVERRIDE staff_web\n");

    try {
        $this->artisan('make:permission-migration', ['name' => 'seed_permissions_for_override'])
            ->assertSuccessful();

        $files = glob($this->migrationsDir . '/*_seed_permissions_for_override.php');

        expect($files)->toHaveCount(1);

        $content = file_get_contents($files[0]);

        expect($content)->toContain('APP OVERRIDE staff_web');
    } finally {
        @unlink($stubFile);
    }
});
