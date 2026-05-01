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

use CanyonGBS\Common\Console\Commands\MakeTmpMigration;
use Carbon\Carbon;
use Illuminate\Database\Migrations\MigrationCreator;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->travelTo(Carbon::create(2026, 5, 1, 12, 0, 0));
    $this->testDir = sys_get_temp_dir() . '/' . uniqid('common-migration-test-');
    mkdir($this->testDir, 0755, true);
    $this->app->setBasePath($this->testDir);
    $this->cleanupDir = $this->app->basePath('.cleanup-tasks');
    $this->migrationsDir = $this->app->databasePath('migrations');
});

afterEach(function () {
    File::deleteDirectory($this->testDir);
});

describe('name normalization', function () {
    it('prepends tmp_ to the migration name', function () {
        $this->artisan('make:tmp-migration', ['name' => 'add_column_to_users', '--no-cleanup' => true])
            ->assertSuccessful();

        $files = glob($this->migrationsDir . '/*_tmp_add_column_to_users.php');

        expect($files)->toHaveCount(1);
    });

    it('strips redundant tmp_ prefix provided by user', function () {
        $this->artisan('make:tmp-migration', ['name' => 'tmp_add_column_to_users', '--no-cleanup' => true])
            ->assertSuccessful();

        // Should be tmp_add_column_to_users, NOT tmp_tmp_add_column_to_users
        $correctFiles = glob($this->migrationsDir . '/*_tmp_add_column_to_users.php');
        $doubleFiles = glob($this->migrationsDir . '/*_tmp_tmp_add_column_to_users.php');

        expect($correctFiles)->toHaveCount(1)
            ->and($doubleFiles)->toBeEmpty();
    });
});

describe('file creation', function () {
    it('creates a migration file in database/migrations', function () {
        $this->artisan('make:tmp-migration', ['name' => 'seed_settings', '--no-cleanup' => true])
            ->assertSuccessful();

        $files = glob($this->migrationsDir . '/*_tmp_seed_settings.php');

        expect($files)->toHaveCount(1);

        $content = file_get_contents($files[0]);

        expect($content)->toContain('return new class extends Migration');
    });

    it('supports --create option for table creation stub', function () {
        $this->artisan('make:tmp-migration', [
            'name' => 'create_temp_cache',
            '--create' => 'temp_cache',
            '--no-cleanup' => true,
        ])->assertSuccessful();

        $files = glob($this->migrationsDir . '/*_tmp_create_temp_cache.php');

        expect($files)->toHaveCount(1);

        $content = file_get_contents($files[0]);

        expect($content)->toContain('Schema::create');
    });
});

describe('cleanup integration', function () {
    it('skips cleanup task prompt with --no-cleanup', function () {
        $this->artisan('make:tmp-migration', ['name' => 'skip_test', '--no-cleanup' => true])
            ->assertSuccessful();

        expect(is_dir($this->cleanupDir))->toBeFalse();
    });

    it('creates new cleanup task with migration path in Temporary Migrations section', function () {
        $this->artisan('make:tmp-migration', ['name' => 'backfill_users'])
            ->expectsChoice('Cleanup task', 'Create new cleanup task', ['Create new cleanup task', 'Skip'])
            ->expectsQuestion('Cleanup task name', 'backfill_users')
            ->assertSuccessful();

        $migrationFiles = glob($this->migrationsDir . '/*_tmp_backfill_users.php');

        expect($migrationFiles)->toHaveCount(1);

        $cleanupFiles = glob($this->cleanupDir . '/*.md');

        expect($cleanupFiles)->toHaveCount(1);

        $content = file_get_contents($cleanupFiles[0]);

        expect($content)->toContain('## Temporary Migrations')
            ->and($content)->toContain('tmp_backfill_users.php');
    });

    it('appends to existing cleanup task', function () {
        File::makeDirectory($this->cleanupDir, 0755, true);
        $existingFile = $this->cleanupDir . '/2026_04_30_existing_task.md';
        file_put_contents($existingFile, "---\ntitle: Existing Task\ncreated: 2026-04-30\n---\n\n## Feature Flags\n\n## Temporary Migrations\n\n## Additional Cleanup\n");

        $this->artisan('make:tmp-migration', ['name' => 'fix_data'])
            ->expectsChoice('Cleanup task', 'Add to existing cleanup task', ['Create new cleanup task', 'Add to existing cleanup task', 'Skip'])
            ->expectsChoice('Select a cleanup task', '2026_04_30_existing_task.md', ['2026_04_30_existing_task.md'])
            ->assertSuccessful();

        $content = file_get_contents($existingFile);

        expect($content)->toContain('tmp_fix_data.php');
    });

    it('skips cleanup task when user selects Skip', function () {
        $this->artisan('make:tmp-migration', ['name' => 'some_migration'])
            ->expectsChoice('Cleanup task', 'Skip', ['Create new cleanup task', 'Skip'])
            ->assertSuccessful();

        $migrationFiles = glob($this->migrationsDir . '/*_tmp_some_migration.php');

        expect($migrationFiles)->toHaveCount(1);

        $cleanupFiles = is_dir($this->cleanupDir) ? glob($this->cleanupDir . '/*.md') : [];

        expect($cleanupFiles)->toBeEmpty();
    });

    it('uses clean name without tmp_ as suggested cleanup task name', function () {
        $this->artisan('make:tmp-migration', ['name' => 'backfill_roles'])
            ->expectsChoice('Cleanup task', 'Create new cleanup task', ['Create new cleanup task', 'Skip'])
            ->expectsQuestion('Cleanup task name', 'backfill_roles')
            ->assertSuccessful();

        $cleanupFiles = glob($this->cleanupDir . '/*backfill_roles*.md');

        expect($cleanupFiles)->toHaveCount(1);
    });

    it('outputs created message for new cleanup task', function () {
        $this->artisan('make:tmp-migration', ['name' => 'output_test'])
            ->expectsChoice('Cleanup task', 'Create new cleanup task', ['Create new cleanup task', 'Skip'])
            ->expectsQuestion('Cleanup task name', 'output_test')
            ->expectsOutputToContain('created successfully')
            ->assertSuccessful();
    });

    it('outputs updated message for existing cleanup task', function () {
        File::makeDirectory($this->cleanupDir, 0755, true);
        file_put_contents(
            $this->cleanupDir . '/2026_04_30_existing.md',
            "---\ntitle: Existing\ncreated: 2026-04-30\n---\n\n## Feature Flags\n\n## Temporary Migrations\n\n## Additional Cleanup\n",
        );

        $this->artisan('make:tmp-migration', ['name' => 'update_test'])
            ->expectsChoice('Cleanup task', 'Add to existing cleanup task', ['Create new cleanup task', 'Add to existing cleanup task', 'Skip'])
            ->expectsChoice('Select a cleanup task', '2026_04_30_existing.md', ['2026_04_30_existing.md'])
            ->expectsOutputToContain('updated successfully')
            ->assertSuccessful();
    });
});

describe('migration creation failure', function () {
    beforeEach(function () {
        $mock = Mockery::mock(MigrationCreator::class);
        $mock->shouldReceive('create')->andThrow(new InvalidArgumentException('A migration with that name already exists.'));

        $this->app->singleton(MakeTmpMigration::class, function () use ($mock) {
            return new MakeTmpMigration($mock, $this->app->make(Composer::class));
        });
    });

    it('does not create cleanup task when migration creation fails', function () {
        $this->artisan('make:tmp-migration', ['name' => 'duplicate_test'])
            ->expectsChoice('Cleanup task', 'Create new cleanup task', ['Create new cleanup task', 'Skip'])
            ->expectsQuestion('Cleanup task name', 'duplicate_test')
            ->assertFailed();

        // No cleanup task should have been created
        $cleanupFiles = is_dir($this->cleanupDir) ? glob($this->cleanupDir . '/*.md') : [];

        expect($cleanupFiles)->toBeEmpty();
    });

    it('does not update existing cleanup task when migration creation fails', function () {
        File::makeDirectory($this->cleanupDir, 0755, true);
        $existingFile = $this->cleanupDir . '/2026_04_30_existing_task.md';
        $originalContent = "---\ntitle: Existing Task\ncreated: 2026-04-30\n---\n\n## Feature Flags\n\n## Temporary Migrations\n\n## Additional Cleanup\n";
        file_put_contents($existingFile, $originalContent);

        $this->artisan('make:tmp-migration', ['name' => 'fail_update_test'])
            ->expectsChoice('Cleanup task', 'Add to existing cleanup task', ['Create new cleanup task', 'Add to existing cleanup task', 'Skip'])
            ->expectsChoice('Select a cleanup task', '2026_04_30_existing_task.md', ['2026_04_30_existing_task.md'])
            ->assertFailed();

        // Existing cleanup task should be unchanged
        $content = file_get_contents($existingFile);

        expect($content)->toBe($originalContent);
    });

    it('does not prompt for cleanup task with --no-cleanup when migration fails', function () {
        $this->artisan('make:tmp-migration', ['name' => 'no_cleanup_fail', '--no-cleanup' => true])
            ->assertFailed();

        $cleanupFiles = is_dir($this->cleanupDir) ? glob($this->cleanupDir . '/*.md') : [];

        expect($cleanupFiles)->toBeEmpty();
    });
});
