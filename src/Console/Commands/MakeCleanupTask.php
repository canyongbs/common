<?php

namespace CanyonGBS\Common\Console\Commands;

use CanyonGBS\Common\Console\Concerns\InteractsWithCleanupTasks;
use Illuminate\Console\Command;

use function Laravel\Prompts\text;

class MakeCleanupTask extends Command
{
    use InteractsWithCleanupTasks;

    protected $signature = 'make:cleanup {name? : The name for the cleanup task}';

    protected $description = 'Create a new cleanup task file';

    public function handle(): int
    {
        $name = $this->argument('name') ?? text(
            label: 'What should the cleanup task be named?',
            required: true,
        );

        $path = $this->createCleanupTask($name);

        $this->components->info(sprintf('Cleanup task [%s] created successfully.', $path));

        return self::SUCCESS;
    }
}
