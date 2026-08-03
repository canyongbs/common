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
 * Compiles common's own `.ai/` guidance — plus the Boost guidelines and skills
 * an app would receive — into the places Copilot reads automatically inside
 * this package.
 *
 * Laravel Boost handles this in consuming apps, but Boost does not run inside
 * the package itself. This command renders the guideline Blade templates into a
 * root `AGENTS.md` and copies the skills into `.github/skills/`. Alongside
 * common's own content it also pulls in the Boost guidelines that packages ship
 * (e.g. Filament's) and the bundled skills enabled in `boost.json` (e.g.
 * TailwindCSS), so developing here benefits from the same guidance we ship to
 * apps. It is wired into composer's post-install/post-update hooks.
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

        $boost = $this->readBoostConfig($root);

        $this->compileGuidelines($root, $boost);
        $this->publishSkills($root, $boost);
        $this->publishGitignore($root);

        return self::SUCCESS;
    }

    /**
     * Read the package's `boost.json` — the same config Boost consumes in apps,
     * and the source of truth for which package guidelines and bundled skills
     * to pull in locally.
     *
     * @return array{packages: list<string>, skills: list<string>}
     */
    protected function readBoostConfig(string $root): array
    {
        $path = $root . '/boost.json';

        if (! $this->files->exists($path)) {
            return ['packages' => [], 'skills' => []];
        }

        try {
            $config = json_decode($this->files->get($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $this->components->warn("Invalid [boost.json] ({$exception->getMessage()}); skipping bundled guidelines/skills.");
            $config = [];
        }

        $list = fn (string $key): array => is_array($config[$key] ?? null)
            ? array_values(array_filter($config[$key], 'is_string'))
            : [];

        return ['packages' => $list('packages'), 'skills' => $list('skills')];
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

    /**
     * @param array{packages: list<string>, skills: list<string>} $boost
     */
    protected function compileGuidelines(string $root, array $boost): void
    {
        $assist = new LocalGuidelineAssist();

        // Common-only guidelines that set the scene (developing the package
        // itself, not a consuming app). They are never published to apps, so
        // they live outside `.ai/guidelines` and are compiled first.
        $local = $this->renderGuidelinesFrom($root . '/.ai/local/guidelines', $assist, applyExclusions: false);

        $shared = $this->renderGuidelinesFrom($root . '/.ai/guidelines', $assist, applyExclusions: true);

        // Guidelines that installed packages ship for Boost (e.g. Filament's),
        // which Boost would inject into an app's AGENTS.md.
        $bundled = $this->bundledPackageGuidelines($root, $boost, $assist);

        $sections = [...array_values($local), ...array_values($shared), ...array_values($bundled)];

        $output = $this->packagePath('AGENTS.md');

        if ($sections === []) {
            $this->files->delete($output);

            return;
        }

        $this->files->put($output, $this->wrapGuidelines($sections));

        $this->components->info('The [AGENTS.md] file was compiled successfully.');
    }

    /**
     * Render the Boost guidelines that packages ship under
     * `resources/boost/guidelines` (as Boost discovers them) for the packages
     * listed in `boost.json`, unless common excludes the key or ships its own
     * override for that package.
     *
     * @param array{packages: list<string>, skills: list<string>} $boost
     *
     * @return array<string, string>
     */
    protected function bundledPackageGuidelines(string $root, array $boost, LocalGuidelineAssist $assist): array
    {
        $excluded = $this->configList('boost.guidelines.exclude');

        $sections = [];

        foreach ($boost['packages'] as $package) {
            if (in_array($package, $excluded, true) || $this->commonShipsGuideline($root, $package)) {
                continue;
            }

            $file = $root . '/vendor/' . $package . '/resources/boost/guidelines/core.blade.php';

            if ($this->files->exists($file)) {
                $sections[$package] = $this->renderGuideline($file, $assist);
            }
        }

        ksort($sections);

        return $sections;
    }

    protected function commonShipsGuideline(string $root, string $key): bool
    {
        foreach (['blade.php', 'md'] as $extension) {
            if ($this->files->exists($root . '/.ai/guidelines/' . $key . '.' . $extension)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A string list read from Boost runtime config (populated by
     * CommonBoostServiceProvider), tolerant of the config being absent.
     *
     * @return list<string>
     */
    protected function configList(string $key): array
    {
        $value = config($key, []);

        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }

    /**
     * Render every guideline Blade template in a directory, keyed by its path
     * relative to that directory (without extension) and sorted by key.
     *
     * @return array<string, string>
     */
    protected function renderGuidelinesFrom(string $source, LocalGuidelineAssist $assist, bool $applyExclusions): array
    {
        if (! $this->files->isDirectory($source)) {
            return [];
        }

        $sections = [];

        foreach ($this->files->allFiles($source) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $key = Str::of($file->getRelativePathname())
                ->replace('\\', '/')
                ->beforeLast('.blade.php')
                ->value();

            if ($applyExclusions && in_array($key, $this->excludedGuidelines, true)) {
                continue;
            }

            $sections[$key] = $this->renderGuideline($file->getPathname(), $assist);
        }

        ksort($sections);

        return $sections;
    }

    protected function renderGuideline(string $path, LocalGuidelineAssist $assist): string
    {
        // Protect code samples before Blade runs: `<?php` tags would otherwise
        // execute, and backticks/component tags would be interpreted. Boost
        // does the same when rendering the guidelines packages ship.
        $placeholders = [
            '`' => '__CG_BACKTICK__',
            '<?php' => '__CG_OPEN_PHP__',
            '<?=' => '__CG_OPEN_PHP_ECHO__',
            '<x-' => '__CG_COMPONENT_OPEN__',
            '</x-' => '__CG_COMPONENT_CLOSE__',
        ];

        $content = str_replace(array_keys($placeholders), array_values($placeholders), $this->files->get($path));

        $rendered = html_entity_decode((string) Blade::render($content, ['assist' => $assist]), ENT_QUOTES | ENT_HTML5);

        $rendered = str_replace(array_values($placeholders), array_keys($placeholders), $rendered);

        // Blade control directives leave behind blank lines; collapse runs of
        // three or more newlines down to a single blank line.
        return trim(preg_replace("/\n{3,}/", "\n\n", $rendered));
    }

    /**
     * @param list<string> $sections
     */
    protected function wrapGuidelines(array $sections): string
    {
        $header = implode("\n", [
            '<!--',
            '    GENERATED FILE — do not edit by hand.',
            '    Produced by `vendor/bin/testbench common:compile-guidance` from the',
            '    Blade templates in `.ai/local/guidelines` and `.ai/guidelines`, plus',
            '    the Boost guidelines shipped by the packages in `boost.json`. Edit',
            '    those sources and re-run the command (composer install/update runs it',
            '    automatically).',
            '-->',
        ]);

        return $header . "\n\n" . implode("\n\n---\n\n", array_values($sections)) . "\n";
    }

    /**
     * @param array{packages: list<string>, skills: list<string>} $boost
     */
    protected function publishSkills(string $root, array $boost): void
    {
        $output = $this->packagePath('.github/skills');

        $this->files->deleteDirectory($output);

        $this->copyCommonSkills($root, $output);
        $this->copyBundledSkills($root, $boost, $output);

        $this->components->info('The [.github/skills] directory was published successfully.');
    }

    protected function copyCommonSkills(string $root, string $output): void
    {
        $source = $root . '/.ai/skills';

        if (! $this->files->isDirectory($source)) {
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
    }

    /**
     * Copy the Boost skills enabled in `boost.json` into `.github/skills`,
     * skipping any common excludes or skills common authors itself.
     *
     * @param array{packages: list<string>, skills: list<string>} $boost
     */
    protected function copyBundledSkills(string $root, array $boost, string $output): void
    {
        $excluded = $this->configList('boost.skills.exclude');

        foreach ($boost['skills'] as $name) {
            // Skip skills excluded for every app, or ones common ships itself
            // (e.g. laravel-best-practices) — common's copy wins.
            if (in_array($name, $excluded, true) || $this->files->isDirectory($root . '/.ai/skills/' . $name)) {
                continue;
            }

            $source = $this->resolveBundledSkillDir($root, $name);

            if ($source === null) {
                $this->components->warn("Could not locate the bundled skill [{$name}]; skipping.");

                continue;
            }

            $this->copySkillDir($source, $output . '/' . $name);
        }
    }

    /**
     * Locate a bundled skill's source directory: first a package that ships it
     * under `resources/boost/skills`, otherwise Boost's own `.ai`, preferring
     * the highest version when the skill is versioned.
     */
    protected function resolveBundledSkillDir(string $root, string $name): ?string
    {
        $firstParty = glob($root . '/vendor/*/*/resources/boost/skills/' . $name, GLOB_ONLYDIR) ?: [];

        if ($firstParty !== []) {
            return $firstParty[0];
        }

        $boostAi = $root . '/vendor/laravel/boost/.ai';

        $unversioned = glob($boostAi . '/*/skill/' . $name, GLOB_ONLYDIR) ?: [];

        if ($unversioned !== []) {
            return $unversioned[0];
        }

        // Versioned layout: `.ai/<package>/<version>/skill/<name>`.
        $versioned = glob($boostAi . '/*/*/skill/' . $name, GLOB_ONLYDIR) ?: [];

        usort($versioned, fn (string $a, string $b): int => version_compare($this->skillVersionSegment($b), $this->skillVersionSegment($a)));

        return $versioned[0] ?? null;
    }

    protected function skillVersionSegment(string $path): string
    {
        $parts = explode('/', str_replace('\\', '/', $path));
        $skillIndex = array_search('skill', $parts, true);

        return $skillIndex > 0 ? $parts[$skillIndex - 1] : '0';
    }

    /**
     * Copy a skill directory into place, rendering a `SKILL.blade.php` to
     * `SKILL.md` and copying every other file (rules/, reference/) verbatim.
     */
    protected function copySkillDir(string $source, string $destination): void
    {
        $assist = new LocalGuidelineAssist();

        foreach ($this->files->allFiles($source, hidden: true) as $file) {
            $relative = $file->getRelativePathname();

            if ($file->getFilename() === 'SKILL.blade.php') {
                $target = $destination . '/' . Str::replaceLast('SKILL.blade.php', 'SKILL.md', $relative);

                $this->files->ensureDirectoryExists(dirname($target));
                $this->files->put($target, $this->renderGuideline($file->getPathname(), $assist) . "\n");

                continue;
            }

            $target = $destination . '/' . $relative;

            $this->files->ensureDirectoryExists(dirname($target));
            $this->files->copy($file->getPathname(), $target);
        }
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
