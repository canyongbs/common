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

use CanyonGBS\Common\Console\Commands\MakeCleanupTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->travelTo(Carbon::create(2026, 5, 1, 12, 0, 0));
    $this->testDir = sys_get_temp_dir() . '/' . uniqid('common-trait-test-');
    mkdir($this->testDir, 0755, true);
    $this->app->setBasePath($this->testDir);
    $this->cleanupDir = $this->app->basePath('.cleanup-tasks');
});

afterEach(function () {
    File::deleteDirectory($this->testDir);
});

describe('getExistingCleanupTasks', function () {
    it('returns empty array when directory does not exist', function () {
        $command = $this->app->make(MakeCleanupTask::class);
        $command->setLaravel($this->app);

        $method = new ReflectionMethod($command, 'getExistingCleanupTasks');

        expect($method->invoke($command))->toBe([]);
    });

    it('returns empty array when directory has no md files', function () {
        File::makeDirectory($this->cleanupDir, 0755, true);
        file_put_contents($this->cleanupDir . '/.gitkeep', '');

        $command = $this->app->make(MakeCleanupTask::class);
        $command->setLaravel($this->app);

        $method = new ReflectionMethod($command, 'getExistingCleanupTasks');

        expect($method->invoke($command))->toBe([]);
    });

    it('returns only md filenames', function () {
        File::makeDirectory($this->cleanupDir, 0755, true);
        file_put_contents($this->cleanupDir . '/.gitkeep', '');
        file_put_contents($this->cleanupDir . '/2026_05_01_task_one.md', '# Task');
        file_put_contents($this->cleanupDir . '/notes.txt', 'notes');
        file_put_contents($this->cleanupDir . '/2026_05_01_task_two.md', '# Task');

        $command = $this->app->make(MakeCleanupTask::class);
        $command->setLaravel($this->app);

        $method = new ReflectionMethod($command, 'getExistingCleanupTasks');
        $result = $method->invoke($command);

        expect($result)->toHaveCount(2)
            ->and($result)->each->toEndWith('.md');
    });
});

describe('createCleanupTask', function () {
    it('creates the cleanup-tasks directory if it does not exist', function () {
        expect(is_dir($this->cleanupDir))->toBeFalse();

        $command = $this->app->make(MakeCleanupTask::class);
        $command->setLaravel($this->app);

        $method = new ReflectionMethod($command, 'createCleanupTask');
        $method->invoke($command, 'my_new_task');

        expect(is_dir($this->cleanupDir))->toBeTrue();
    });

    it('creates file with correct date_snake_name naming', function () {
        $command = $this->app->make(MakeCleanupTask::class);
        $command->setLaravel($this->app);

        $method = new ReflectionMethod($command, 'createCleanupTask');
        $path = $method->invoke($command, 'MyNewTask');

        expect($path)->toEndWith('2026_05_01_my_new_task.md')
            ->and(file_exists($path))->toBeTrue();
    });

    it('fills stub placeholders with headline title and date', function () {
        $command = $this->app->make(MakeCleanupTask::class);
        $command->setLaravel($this->app);

        $method = new ReflectionMethod($command, 'createCleanupTask');
        $path = $method->invoke($command, 'refactor_auth_system');

        $content = file_get_contents($path);

        expect($content)->toContain('title: Refactor Auth System')
            ->and($content)->toContain('created: 2026-05-01')
            ->and($content)->toContain('## Feature Flags')
            ->and($content)->toContain('## Temporary Migrations')
            ->and($content)->toContain('## Additional Cleanup');
    });

    it('returns the full path to the created file', function () {
        $command = $this->app->make(MakeCleanupTask::class);
        $command->setLaravel($this->app);

        $method = new ReflectionMethod($command, 'createCleanupTask');
        $path = $method->invoke($command, 'test_task');

        expect($path)->toBe($this->cleanupDir . '/2026_05_01_test_task.md');
    });
});

describe('resolveCleanupTaskStub', function () {
    it('uses custom stub when present in base path', function () {
        $stubDir = $this->app->basePath('stubs');
        File::makeDirectory($stubDir, 0755, true);
        file_put_contents($stubDir . '/cleanup-task.stub', 'CUSTOM: {{ title }}');

        $command = $this->app->make(MakeCleanupTask::class);
        $command->setLaravel($this->app);

        $method = new ReflectionMethod($command, 'resolveCleanupTaskStub');
        $result = $method->invoke($command);

        expect($result)->toBe('CUSTOM: {{ title }}');
    });

    it('falls back to package stub when no custom stub exists', function () {
        $command = $this->app->make(MakeCleanupTask::class);
        $command->setLaravel($this->app);

        $method = new ReflectionMethod($command, 'resolveCleanupTaskStub');
        $result = $method->invoke($command);

        expect($result)->toContain('{{ title }}')
            ->and($result)->toContain('## Feature Flags');
    });
});

describe('appendToCleanupSection', function () {
    it('appends entry under the correct section heading', function () {
        $command = $this->app->make(MakeCleanupTask::class);
        $command->setLaravel($this->app);

        // Create a cleanup task file first
        $createMethod = new ReflectionMethod($command, 'createCleanupTask');
        $path = $createMethod->invoke($command, 'test_task');

        $appendMethod = new ReflectionMethod($command, 'appendToCleanupSection');
        $appendMethod->invoke($command, $path, 'Feature Flags', 'App\\Features\\TestFeature');

        $content = file_get_contents($path);

        expect($content)->toContain("## Feature Flags\n\n- App\\Features\\TestFeature\n");
    });

    it('appends additional entries below existing ones', function () {
        $command = $this->app->make(MakeCleanupTask::class);
        $command->setLaravel($this->app);

        $createMethod = new ReflectionMethod($command, 'createCleanupTask');
        $path = $createMethod->invoke($command, 'test_task');

        $appendMethod = new ReflectionMethod($command, 'appendToCleanupSection');
        $appendMethod->invoke($command, $path, 'Feature Flags', 'App\\Features\\FirstFeature');
        $appendMethod->invoke($command, $path, 'Feature Flags', 'App\\Features\\SecondFeature');

        $content = file_get_contents($path);

        expect($content)->toContain("- App\\Features\\FirstFeature\n- App\\Features\\SecondFeature\n");
    });

    it('does not modify other sections', function () {
        $command = $this->app->make(MakeCleanupTask::class);
        $command->setLaravel($this->app);

        $createMethod = new ReflectionMethod($command, 'createCleanupTask');
        $path = $createMethod->invoke($command, 'test_task');

        $appendMethod = new ReflectionMethod($command, 'appendToCleanupSection');
        $appendMethod->invoke($command, $path, 'Feature Flags', 'App\\Features\\TestFeature');

        $content = file_get_contents($path);

        // Temporary Migrations section should remain empty
        $tmpSection = substr($content, strpos($content, '## Temporary Migrations'));
        $nextSection = strpos($tmpSection, '## Additional Cleanup');

        $sectionContent = substr($tmpSection, strlen("## Temporary Migrations\n"), $nextSection - strlen("## Temporary Migrations\n"));

        expect(trim($sectionContent))->toBe('');
    });

    it('does nothing when section heading is not found', function () {
        $command = $this->app->make(MakeCleanupTask::class);
        $command->setLaravel($this->app);

        $createMethod = new ReflectionMethod($command, 'createCleanupTask');
        $path = $createMethod->invoke($command, 'test_task');

        $originalContent = file_get_contents($path);

        $appendMethod = new ReflectionMethod($command, 'appendToCleanupSection');
        $appendMethod->invoke($command, $path, 'Nonexistent Section', 'some entry');

        expect(file_get_contents($path))->toBe($originalContent);
    });
});
