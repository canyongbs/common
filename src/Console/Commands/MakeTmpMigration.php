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

namespace CanyonGBS\Common\Console\Commands;

use Throwable;
use CanyonGBS\Common\Console\Concerns\InteractsWithCleanupTasks;
use Illuminate\Database\Console\Migrations\MigrateMakeCommand;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use InterNACHI\Modular\Console\Commands\Make\Modularize;

class MakeTmpMigration extends MigrateMakeCommand
{
    use InteractsWithCleanupTasks;
    use Modularize;

    protected $signature = 'make:tmp-migration {name : The name of the migration}
        {--create= : The table to be created}
        {--table= : The table to migrate}
        {--path= : The location where the migration file should be created}
        {--realpath : Indicate any provided migration file paths are pre-resolved absolute paths}
        {--fullpath : Output the full path of the migration (Deprecated)}
        {--no-cleanup : Skip cleanup task creation}';

    protected $description = 'Create a new temporary migration file with optional cleanup task';

    public function handle(): int
    {
        $originalName = trim($this->input->getArgument('name'));

        // Strip tmp_ prefix if the user included it — we add it ourselves
        $cleanName = Str::startsWith($originalName, 'tmp_')
            ? Str::substr($originalName, 4)
            : $originalName;

        $this->input->setArgument('name', 'tmp_' . $cleanName);

        // Gather cleanup task input before creating any files
        $cleanupInput = null;

        if (! $this->option('no-cleanup')) {
            $suggestedName = Str::snake($cleanName);
            $cleanupInput = $this->gatherCleanupTaskInput($suggestedName);
        }

        // Create the migration file (parent outputs via components->info)
        try {
            parent::handle();
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return static::FAILURE;
        }

        // Execute cleanup task action and output results
        if ($cleanupInput) {
            $migrationFile = $this->getCreatedMigrationPath();
            $relativePath = str_replace($this->laravel->basePath() . '/', '', $migrationFile);
            $cleanupResult = $this->executeCleanupTaskAction($cleanupInput, 'Temporary Migrations', $relativePath);

            if ($cleanupResult['created']) {
                $this->components->info(sprintf('Cleanup task [%s] created successfully.', $cleanupResult['path']));
            } else {
                $this->components->info(sprintf('Cleanup task [%s] updated successfully.', $cleanupResult['path']));
            }
        }

        return static::SUCCESS;
    }

    protected function getMigrationPath()
    {
        $path = parent::getMigrationPath();

        if ($module = $this->module()) {
            $appDirectory = $this->laravel->databasePath('migrations');
            $moduleDirectory = $module->path('database/migrations');

            $path = str_replace($appDirectory, $moduleDirectory, $path);

            $filesystem = $this->laravel->make(Filesystem::class);

            if (! $filesystem->isDirectory($moduleDirectory)) {
                $filesystem->makeDirectory($moduleDirectory, 0755, true);
            }
        }

        return $path;
    }

    protected function getCreatedMigrationPath(): string
    {
        $name = Str::snake(trim($this->input->getArgument('name')));
        $migrationPath = $this->getMigrationPath();

        // Find the most recently created file in the migration path matching the name
        $filesystem = $this->laravel->make(Filesystem::class);
        $files = $filesystem->glob($migrationPath . '/*_' . $name . '.php');

        if (empty($files)) {
            return $migrationPath . '/' . $name . '.php';
        }

        // Return the last one (most recent by timestamp prefix)
        return end($files);
    }
}
