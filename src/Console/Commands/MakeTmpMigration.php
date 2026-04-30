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
        // Prefix the migration name with tmp_
        $originalName = trim($this->input->getArgument('name'));

        if (! Str::startsWith($originalName, 'tmp_')) {
            $this->input->setArgument('name', 'tmp_' . $originalName);
        }

        parent::handle();

        if (! $this->option('no-cleanup')) {
            $suggestedName = Str::replace('tmp_', '', Str::snake($originalName));

            $path = $this->promptForCleanupTask($suggestedName);

            if ($path) {
                $migrationFile = $this->getCreatedMigrationPath();
                $relativePath = str_replace($this->laravel->basePath() . '/', '', $migrationFile);
                $this->appendToCleanupSection($path, 'Temporary Migrations', $relativePath);
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
