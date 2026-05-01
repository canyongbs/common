<?php

use Carbon\Carbon;
use CanyonGBS\Common\Console\Commands\MakeCleanupTask;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->travelTo(Carbon::create(2026, 5, 1, 12, 0, 0));
    $this->cleanupDir = $this->app->basePath('.cleanup-tasks');
});

afterEach(function () {
    File::deleteDirectory($this->cleanupDir);

    $customStub = $this->app->basePath('stubs/cleanup-task.stub');

    if (file_exists($customStub)) {
        unlink($customStub);
    }

    $stubDir = $this->app->basePath('stubs');

    if (is_dir($stubDir) && count(scandir($stubDir)) === 2) {
        rmdir($stubDir);
    }
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
