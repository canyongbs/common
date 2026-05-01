<?php

namespace CanyonGBS\Common\Console\Commands;

use CanyonGBS\Common\Console\Concerns\InteractsWithCleanupTasks;
use Illuminate\Support\Str;
use Laravel\Pennant\Commands\FeatureMakeCommand;
use Symfony\Component\Console\Input\InputOption;

class MakeFeatureFlag extends FeatureMakeCommand
{
    use InteractsWithCleanupTasks;

    protected $name = 'make:ff';

    protected $description = 'Create a new feature flag class with optional cleanup task';

    public function handle()
    {
        // Ensure the name ends with "Feature"
        $name = $this->getNameInput();

        if (! Str::endsWith($name, 'Feature')) {
            $this->input->setArgument('name', $name . 'Feature');
        }

        // Gather cleanup task input before creating any files
        $cleanupInput = null;

        if (! $this->option('no-cleanup')) {
            $cleanupInput = $this->gatherCleanupTaskInput($this->getNameInput());
        }

        // Now create the feature flag file (parent handles output suppression isn't needed — it outputs via components->info)
        $result = parent::handle();

        if ($result === false) {
            return false;
        }

        // Execute cleanup task action and output results
        if ($cleanupInput) {
            $qualifiedClass = $this->qualifyClass($this->getNameInput());
            $cleanupResult = $this->executeCleanupTaskAction($cleanupInput, 'Feature Flags', $qualifiedClass);

            if ($cleanupResult['created']) {
                $this->components->info(sprintf('Cleanup task [%s] created successfully.', $cleanupResult['path']));
            } else {
                $this->components->info(sprintf('Cleanup task [%s] updated successfully.', $cleanupResult['path']));
            }
        }

        return null;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function getOptions()
    {
        return array_merge(parent::getOptions(), [
            ['no-cleanup', null, InputOption::VALUE_NONE, 'Skip cleanup task creation'],
        ]);
    }
}
