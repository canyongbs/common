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

use Carbon\Carbon;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->travelTo(Carbon::create(2026, 5, 1, 12, 0, 0));
    $this->testDir = sys_get_temp_dir() . '/' . uniqid('common-ff-test-');
    mkdir($this->testDir, 0755, true);

    // Feature flag command needs composer.json for namespace resolution
    file_put_contents($this->testDir . '/composer.json', json_encode([
        'autoload' => ['psr-4' => ['App\\' => 'app/']],
    ]));

    $this->app->setBasePath($this->testDir);
    $this->cleanupDir = $this->app->basePath('.cleanup-tasks');
    $this->featuresDir = $this->app->basePath('app/Features');
});

afterEach(function () {
    File::deleteDirectory($this->testDir);
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
    it('does not create cleanup task when feature flag already exists', function () {
        // Create the file first so the second attempt fails
        $this->artisan('make:ff', ['name' => 'Existing', '--no-cleanup' => true])
            ->assertSuccessful();

        // Attempt to create again — user is prompted for cleanup but file creation fails
        $this->artisan('make:ff', ['name' => 'Existing'])
            ->expectsChoice('Cleanup task', 'Create new cleanup task', ['Create new cleanup task', 'Skip'])
            ->expectsQuestion('Cleanup task name', 'ExistingFeature')
            ->assertFailed();

        $files = is_dir($this->cleanupDir) ? glob($this->cleanupDir . '/*.md') : [];

        expect($files)->toBeEmpty();
    });

    it('does not update existing cleanup task when feature flag creation fails', function () {
        File::makeDirectory($this->cleanupDir, 0755, true);
        $existingFile = $this->cleanupDir . '/2026_04_30_existing_task.md';
        $originalContent = "---\ntitle: Existing Task\ncreated: 2026-04-30\n---\n\n## Feature Flags\n\n## Temporary Migrations\n\n## Additional Cleanup\n";
        file_put_contents($existingFile, $originalContent);

        // Create the file first so the second attempt fails
        $this->artisan('make:ff', ['name' => 'Duplicate', '--no-cleanup' => true])
            ->assertSuccessful();

        // Attempt to create again — should fail and leave cleanup untouched
        $this->artisan('make:ff', ['name' => 'Duplicate'])
            ->expectsChoice('Cleanup task', 'Add to existing cleanup task', ['Create new cleanup task', 'Add to existing cleanup task', 'Skip'])
            ->expectsChoice('Select a cleanup task', '2026_04_30_existing_task.md', ['2026_04_30_existing_task.md'])
            ->assertFailed();

        $content = file_get_contents($existingFile);

        expect($content)->toBe($originalContent);
    });

    it('returns failure with --no-cleanup when feature flag already exists', function () {
        $this->artisan('make:ff', ['name' => 'AlreadyHere', '--no-cleanup' => true])
            ->assertSuccessful();

        $this->artisan('make:ff', ['name' => 'AlreadyHere', '--no-cleanup' => true])
            ->assertFailed();

        $files = is_dir($this->cleanupDir) ? glob($this->cleanupDir . '/*.md') : [];

        expect($files)->toBeEmpty();
    });
});
