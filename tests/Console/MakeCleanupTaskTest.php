<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->travelTo(Carbon::create(2026, 5, 1, 12, 0, 0));
    $this->cleanupDir = $this->app->basePath('.cleanup-tasks');
});

afterEach(function () {
    File::deleteDirectory($this->cleanupDir);
});

it('creates a cleanup task file with the given name argument', function () {
    $this->artisan('make:cleanup', ['name' => 'refactor_auth'])
        ->assertSuccessful();

    $expectedFile = $this->cleanupDir . '/2026_05_01_refactor_auth.md';

    expect(file_exists($expectedFile))->toBeTrue();

    $content = file_get_contents($expectedFile);

    expect($content)->toContain('title: Refactor Auth')
        ->and($content)->toContain('created: 2026-05-01');
});

it('prompts for name when no argument is provided', function () {
    $this->artisan('make:cleanup')
        ->expectsQuestion('What should the cleanup task be named?', 'my_task')
        ->assertSuccessful();

    $expectedFile = $this->cleanupDir . '/2026_05_01_my_task.md';

    expect(file_exists($expectedFile))->toBeTrue();
});

it('creates the cleanup-tasks directory when it does not exist', function () {
    expect(is_dir($this->cleanupDir))->toBeFalse();

    $this->artisan('make:cleanup', ['name' => 'test'])
        ->assertSuccessful();

    expect(is_dir($this->cleanupDir))->toBeTrue();
});

it('outputs success message with file path', function () {
    $this->artisan('make:cleanup', ['name' => 'test_task'])
        ->assertSuccessful()
        ->expectsOutputToContain('created successfully');
});

it('returns exit code 0', function () {
    $this->artisan('make:cleanup', ['name' => 'test'])
        ->assertExitCode(0);
});
