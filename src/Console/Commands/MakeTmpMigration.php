<?php

namespace CanyonGBS\Common\Console\Commands;

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

    public function handle()
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
        parent::handle();

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
