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

namespace Workbench\App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Workbench\App\Support\LocalGuidelineAssist;

/**
 * Compiles common's own `.ai/` guidance into the places Copilot reads
 * automatically inside this package.
 *
 * Laravel Boost handles this in consuming apps, but Boost does not run inside
 * the package itself. This command renders the guideline Blade templates into a
 * root `AGENTS.md` and copies the skills into `.github/skills/`, so that when we
 * write code in this package Copilot benefits from the same shared guidance we
 * ship to apps. It is wired into composer's post-install/post-update hooks.
 */
class CompileAgentGuidance extends Command
{
    protected $signature = 'common:compile-guidance';

    protected $description = 'Compile common\'s .ai guidelines and skills into AGENTS.md and .github/skills for local Copilot use';

    /**
     * Guideline keys (path relative to `.ai/guidelines` without extension) that
     * are app-only and must not be compiled into the package's own guidance.
     * `pls` describes the Docker/`pls exec app` workflow, which does not exist
     * inside this package — here everything runs directly on the host.
     *
     * @var list<string>
     */
    protected array $excludedGuidelines = [
        'pls',
    ];

    /**
     * The generated artifacts this command manages, ignored by git.
     *
     * @var list<string>
     */
    protected array $gitignoreEntries = [
        '/AGENTS.md',
        '/.github/skills/',
    ];

    public function __construct(
        protected Filesystem $files,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $root = $this->packagePath();

        $this->compileGuidelines($root);
        $this->publishSkills($root);
        $this->publishGitignore($root);

        return self::SUCCESS;
    }

    /**
     * The common package root. `base_path()` points at the Testbench skeleton,
     * so the root is resolved from this file's location instead.
     */
    protected function packagePath(string $path = ''): string
    {
        $root = dirname(__DIR__, 4);

        return $path === '' ? $root : $root . '/' . ltrim($path, '/');
    }

    protected function compileGuidelines(string $root): void
    {
        $source = $root . '/.ai/guidelines';

        if (! $this->files->isDirectory($source)) {
            $this->components->warn('No [.ai/guidelines] directory found; skipping AGENTS.md.');

            return;
        }

        $assist = new LocalGuidelineAssist();

        $sections = [];

        foreach ($this->files->allFiles($source) as $file) {
            if ($file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $key = Str::of($file->getRelativePathname())
                ->replace('\\', '/')
                ->beforeLast('.blade.php')
                ->value();

            if (in_array($key, $this->excludedGuidelines, true)) {
                continue;
            }

            $sections[$key] = $this->renderGuideline($file->getPathname(), $assist);
        }

        ksort($sections);

        $output = $this->packagePath('AGENTS.md');

        if ($sections === []) {
            $this->files->delete($output);

            return;
        }

        $this->files->put($output, $this->wrapGuidelines($sections));

        $this->components->info('The [AGENTS.md] file was compiled successfully.');
    }

    protected function renderGuideline(string $path, LocalGuidelineAssist $assist): string
    {
        $rendered = Blade::render($this->files->get($path), ['assist' => $assist]);

        // Blade control directives leave behind blank lines; collapse runs of
        // three or more newlines down to a single blank line.
        return trim(preg_replace("/\n{3,}/", "\n\n", $rendered));
    }

    /**
     * @param array<string, string> $sections
     */
    protected function wrapGuidelines(array $sections): string
    {
        $header = implode("\n", [
            '<!--',
            '    GENERATED FILE — do not edit by hand.',
            '    Produced by `vendor/bin/testbench common:compile-guidance` from the',
            '    Blade templates in `.ai/guidelines`. Edit those sources and re-run the',
            '    command (composer install/update runs it automatically).',
            '-->',
        ]);

        return $header . "\n\n" . implode("\n\n---\n\n", array_values($sections)) . "\n";
    }

    protected function publishSkills(string $root): void
    {
        $source = $root . '/.ai/skills';
        $output = $this->packagePath('.github/skills');

        $this->files->deleteDirectory($output);

        if (! $this->files->isDirectory($source)) {
            $this->components->warn('No [.ai/skills] directory found; skipping .github/skills.');

            return;
        }

        foreach ($this->files->allFiles($source, hidden: true) as $file) {
            if ($file->getFilename() === '.gitkeep') {
                continue;
            }

            $destination = $output . '/' . $file->getRelativePathname();

            $this->files->ensureDirectoryExists(dirname($destination));

            $this->files->copy($file->getPathname(), $destination);
        }

        $this->components->info('The [.github/skills] directory was published successfully.');
    }

    protected function publishGitignore(string $root): void
    {
        $start = '# BEGIN canyongbs/common (common:compile-guidance) — do not edit this block manually.';
        $end = '# END canyongbs/common (common:compile-guidance)';

        $block = implode(PHP_EOL, [$start, ...$this->gitignoreEntries, $end]);

        $path = $root . '/.gitignore';

        $contents = $this->files->exists($path) ? $this->files->get($path) : '';

        $pattern = '/' . preg_quote($start, '/') . '.*?' . preg_quote($end, '/') . '/s';

        if (preg_match($pattern, $contents)) {
            $contents = preg_replace($pattern, $block, $contents);
        } else {
            $contents = rtrim($contents);
            $contents = ($contents === '' ? '' : $contents . PHP_EOL . PHP_EOL) . $block . PHP_EOL;
        }

        $this->files->put($path, $contents);

        $this->components->info('The [.gitignore] file was updated successfully.');
    }
}
