<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->travelTo(Carbon::create(2026, 5, 1, 12, 0, 0));
    $this->cleanupDir = $this->app->basePath('.cleanup-tasks');
    $this->featuresDir = $this->app->basePath('app/Features');
});

afterEach(function () {
    File::deleteDirectory($this->cleanupDir);
    File::deleteDirectory($this->featuresDir);
});

describe('name normalization', function () {
    it('appends Feature suffix when not present', function () {
        $this->artisan('make:ff', ['name' => 'MyFlag', '--no-cleanup' => true])
            ->assertSuccessful();

        expect(file_exists($this->featuresDir . '/MyFlagFeature.php'))->toBeTrue();
    });

    it('does not double the Feature suffix', function () {
        $this->artisan('make:ff', ['name' => 'MyFlagFeature', '--no-cleanup' => true])
            ->assertSuccessful();

        expect(file_exists($this->featuresDir . '/MyFlagFeature.php'))->toBeTrue()
            ->and(file_exists($this->featuresDir . '/MyFlagFeatureFeature.php'))->toBeFalse();
    });
});

describe('file creation', function () {
    it('creates the feature flag class file', function () {
        $this->artisan('make:ff', ['name' => 'NewDashboard', '--no-cleanup' => true])
            ->assertSuccessful();

        $file = $this->featuresDir . '/NewDashboardFeature.php';

        expect(file_exists($file))->toBeTrue();

        $content = file_get_contents($file);

        expect($content)->toContain('class NewDashboardFeature');
    });
});

describe('cleanup integration', function () {
    it('skips cleanup task prompt with --no-cleanup', function () {
        $this->artisan('make:ff', ['name' => 'SkipTest', '--no-cleanup' => true])
            ->assertSuccessful();

        expect(file_exists($this->featuresDir . '/SkipTestFeature.php'))->toBeTrue()
            ->and(is_dir($this->cleanupDir))->toBeFalse();
    });

    it('creates new cleanup task with feature flag FQCN in Feature Flags section', function () {
        $this->artisan('make:ff', ['name' => 'NewDashboard'])
            ->expectsChoice('Cleanup task', 'Create new cleanup task', ['Create new cleanup task', 'Skip'])
            ->expectsQuestion('Cleanup task name', 'NewDashboardFeature')
            ->assertSuccessful();

        expect(file_exists($this->featuresDir . '/NewDashboardFeature.php'))->toBeTrue();

        $files = glob($this->cleanupDir . '/*.md');

        expect($files)->toHaveCount(1);

        $content = file_get_contents($files[0]);

        expect($content)->toContain('## Feature Flags')
            ->and($content)->toContain('App\Features\NewDashboardFeature');
    });

    it('appends to existing cleanup task', function () {
        File::makeDirectory($this->cleanupDir, 0755, true);
        $existingFile = $this->cleanupDir . '/2026_04_30_existing_task.md';
        file_put_contents($existingFile, "---\ntitle: Existing Task\ncreated: 2026-04-30\n---\n\n## Feature Flags\n\n## Temporary Migrations\n\n## Additional Cleanup\n");

        $this->artisan('make:ff', ['name' => 'AnotherFlag'])
            ->expectsChoice('Cleanup task', 'Add to existing cleanup task', ['Create new cleanup task', 'Add to existing cleanup task', 'Skip'])
            ->expectsChoice('Select a cleanup task', '2026_04_30_existing_task.md', ['2026_04_30_existing_task.md'])
            ->assertSuccessful();

        expect(file_exists($this->featuresDir . '/AnotherFlagFeature.php'))->toBeTrue();

        $content = file_get_contents($existingFile);

        expect($content)->toContain('App\Features\AnotherFlagFeature');
    });

    it('skips cleanup task when user selects Skip', function () {
        $this->artisan('make:ff', ['name' => 'SkipMe'])
            ->expectsChoice('Cleanup task', 'Skip', ['Create new cleanup task', 'Skip'])
            ->assertSuccessful();

        expect(file_exists($this->featuresDir . '/SkipMeFeature.php'))->toBeTrue();

        $files = is_dir($this->cleanupDir) ? glob($this->cleanupDir . '/*.md') : [];

        expect($files)->toBeEmpty();
    });

    it('outputs created message for new cleanup task', function () {
        $this->artisan('make:ff', ['name' => 'OutputTest'])
            ->expectsChoice('Cleanup task', 'Create new cleanup task', ['Create new cleanup task', 'Skip'])
            ->expectsQuestion('Cleanup task name', 'OutputTestFeature')
            ->expectsOutputToContain('created successfully')
            ->assertSuccessful();
    });

    it('outputs updated message for existing cleanup task', function () {
        File::makeDirectory($this->cleanupDir, 0755, true);
        file_put_contents(
            $this->cleanupDir . '/2026_04_30_existing.md',
            "---\ntitle: Existing\ncreated: 2026-04-30\n---\n\n## Feature Flags\n\n## Temporary Migrations\n\n## Additional Cleanup\n",
        );

        $this->artisan('make:ff', ['name' => 'UpdateTest'])
            ->expectsChoice('Cleanup task', 'Add to existing cleanup task', ['Create new cleanup task', 'Add to existing cleanup task', 'Skip'])
            ->expectsChoice('Select a cleanup task', '2026_04_30_existing.md', ['2026_04_30_existing.md'])
            ->expectsOutputToContain('updated successfully')
            ->assertSuccessful();
    });
});

describe('failure handling', function () {
    it('does not create cleanup task when feature flag creation fails', function () {
        // Create the file first so the second attempt fails (file already exists)
        File::makeDirectory($this->featuresDir, 0755, true);
        file_put_contents($this->featuresDir . '/ExistingFeature.php', '<?php // existing');

        $this->artisan('make:ff', ['name' => 'Existing', '--no-cleanup' => true]);

        $files = is_dir($this->cleanupDir) ? glob($this->cleanupDir . '/*.md') : [];

        expect($files)->toBeEmpty();
    });
});
