<?php

namespace CanyonGBS\Common\Console\Commands;

use CanyonGBS\Common\Console\Concerns\InteractsWithCleanupTasks;
use Laravel\Pennant\Commands\FeatureMakeCommand;
use Symfony\Component\Console\Input\InputOption;

class MakeFeatureFlag extends FeatureMakeCommand
{
    use InteractsWithCleanupTasks;

    protected $name = 'make:ff';

    protected $description = 'Create a new feature flag class with optional cleanup task';

    public function handle()
    {
        $result = parent::handle();

        if ($result === false) {
            return false;
        }

        if (! $this->option('no-cleanup')) {
            $suggestedName = $this->getNameInput();

            $path = $this->promptForCleanupTask($suggestedName);

            if ($path) {
                $qualifiedClass = $this->qualifyClass($this->getNameInput());
                $this->appendToCleanupSection($path, 'Feature Flags', $qualifiedClass);
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
