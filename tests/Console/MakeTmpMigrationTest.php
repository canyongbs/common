<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->travelTo(Carbon::create(2026, 5, 1, 12, 0, 0));
    $this->cleanupDir = $this->app->basePath('.cleanup-tasks');
    $this->migrationsDir = $this->app->databasePath('migrations');
});

afterEach(function () {
    File::deleteDirectory($this->cleanupDir);

    // Clean up any tmp_ migration files we created
    if (is_dir($this->migrationsDir)) {
        foreach (glob($this->migrationsDir . '/*tmp_*.php') as $file) {
            unlink($file);
        }
    }
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
