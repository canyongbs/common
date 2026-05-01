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

namespace CanyonGBS\Common\Console\Concerns;

use CanyonGBS\Common\Console\Enums\CleanupTaskAction;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

trait InteractsWithCleanupTasks
{
    protected function cleanupTasksDirectory(): string
    {
        return $this->laravel->basePath('.cleanup-tasks');
    }

    /**
     * Check if a cleanup task with the given name would conflict with an existing file.
     *
     * @param array{action: CleanupTaskAction, name?: string, file?: string}|null $input
     */
    protected function cleanupTaskWouldConflict(?array $input): bool
    {
        if ($input === null || $input['action'] !== CleanupTaskAction::Create) {
            return false;
        }

        $filesystem = $this->laravel->make(Filesystem::class);
        $directory = $this->cleanupTasksDirectory();
        $date = now()->format('Y_m_d');
        $snakeName = Str::snake($input['name']);
        $filename = "{$date}_{$snakeName}.md";

        return $filesystem->exists($directory . '/' . $filename);
    }

    /**
     * @return array<int, string>
     */
    protected function getExistingCleanupTasks(): array
    {
        $filesystem = $this->laravel->make(Filesystem::class);
        $directory = $this->cleanupTasksDirectory();

        if (! $filesystem->isDirectory($directory)) {
            return [];
        }

        return collect($filesystem->files($directory))
            ->filter(fn ($file) => Str::endsWith($file->getFilename(), '.md'))
            ->map(fn ($file) => $file->getFilename())
            ->values()
            ->all();
    }

    protected function createCleanupTask(string $name): string
    {
        $filesystem = $this->laravel->make(Filesystem::class);
        $directory = $this->cleanupTasksDirectory();

        if (! $filesystem->isDirectory($directory)) {
            $filesystem->makeDirectory($directory, 0755, true);
        }

        $date = now()->format('Y_m_d');
        $snakeName = Str::snake($name);
        $filename = "{$date}_{$snakeName}.md";
        $path = $directory . '/' . $filename;

        $stub = $this->resolveCleanupTaskStub();
        $title = Str::headline($name);

        $content = str_replace(
            ['{{ title }}', '{{ date }}'],
            [$title, now()->format('Y-m-d')],
            $stub,
        );

        $filesystem->put($path, $content);

        return $path;
    }

    /**
     * Gather cleanup task input from the user without creating or modifying any files.
     *
     * @return array{action: CleanupTaskAction, name?: string, file?: string}|null
     */
    protected function gatherCleanupTaskInput(string $suggestedName): ?array
    {
        $existing = $this->getExistingCleanupTasks();

        $options = ['Create new cleanup task'];

        if (count($existing) > 0) {
            $options[] = 'Add to existing cleanup task';
        }

        $options[] = 'Skip';

        $choice = select(
            label: 'Cleanup task',
            options: $options,
            default: 'Create new cleanup task',
        );

        if ($choice === 'Skip') {
            return null;
        }

        if ($choice === 'Create new cleanup task') {
            $name = text(
                label: 'Cleanup task name',
                default: $suggestedName,
                required: true,
            );

            return ['action' => CleanupTaskAction::Create, 'name' => $name];
        }

        $selected = select(
            label: 'Select a cleanup task',
            options: $existing,
        );

        return ['action' => CleanupTaskAction::AddToExisting, 'file' => $selected];
    }

    /**
     * Execute the cleanup task action (create or select existing) and append an entry.
     *
     * @param array{action: CleanupTaskAction, name?: string, file?: string} $input
     *
     * @return array{path: string, created: bool}
     */
    protected function executeCleanupTaskAction(array $input, string $section, string $entry): array
    {
        if ($input['action'] === CleanupTaskAction::Create) {
            $path = $this->createCleanupTask($input['name']);
            $this->appendToCleanupSection($path, $section, $entry);

            return ['path' => $path, 'created' => true];
        }

        $path = $this->cleanupTasksDirectory() . '/' . $input['file'];
        $this->appendToCleanupSection($path, $section, $entry);

        return ['path' => $path, 'created' => false];
    }

    protected function appendToCleanupSection(string $filePath, string $section, string $entry): void
    {
        $filesystem = $this->laravel->make(Filesystem::class);
        $content = $filesystem->get($filePath);

        $heading = "## {$section}";
        $position = strpos($content, $heading);

        if ($position === false) {
            return;
        }

        // Find the end of the heading line
        $afterHeading = strpos($content, "\n", $position);

        if ($afterHeading === false) {
            // Heading is at end of file, append after it
            $content .= "\n\n- {$entry}";
        } else {
            // Insert the entry after the heading line
            $before = substr($content, 0, $afterHeading + 1);
            $after = substr($content, $afterHeading + 1);

            // Check if there's already content in this section (look for next ## or end)
            $nextSection = strpos($after, "\n## ");

            if ($nextSection !== false) {
                $sectionContent = substr($after, 0, $nextSection);
                $remainder = substr($after, $nextSection);
            } else {
                $sectionContent = $after;
                $remainder = '';
            }

            // Append entry at end of section content (before blank line to next section)
            $trimmedSection = rtrim($sectionContent);

            if ($trimmedSection === '') {
                $content = $before . "\n- {$entry}\n" . $remainder;
            } else {
                $content = $before . $trimmedSection . "\n- {$entry}\n" . $remainder;
            }
        }

        $filesystem->put($filePath, $content);
    }

    protected function resolveCleanupTaskStub(): string
    {
        $customPath = $this->laravel->basePath('stubs/cleanup-task.stub');

        if (file_exists($customPath)) {
            return file_get_contents($customPath);
        }

        return file_get_contents(__DIR__ . '/../../../stubs/cleanup-task.stub');
    }
}
