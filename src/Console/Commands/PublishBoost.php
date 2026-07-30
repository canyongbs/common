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

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

use function array_is_list;

class PublishBoost extends Command
{
    protected $signature = 'common:publish-boost';

    protected $description = 'Publish boost.json and .vscode/mcp.json by deep merging the app\'s overrides over the common base configuration';

    public function __construct(
        protected Filesystem $files,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $files = [
            [
                'base' => __DIR__ . '/../../../boost.json',
                'override' => base_path('boost.override.json'),
                'output' => base_path('boost.json'),
            ],
            [
                'base' => __DIR__ . '/../../../.vscode/mcp.json',
                'override' => base_path('.vscode/mcp.override.json'),
                'output' => base_path('.vscode/mcp.json'),
            ],
        ];

        foreach ($files as $file) {
            if ($this->publish($file['base'], $file['override'], $file['output']) === self::FAILURE) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    protected function publish(string $basePath, string $overridePath, string $outputPath): int
    {
        $baseName = basename($basePath);
        $overrideName = basename($overridePath);
        $outputName = basename($outputPath);

        if (! $this->files->exists($basePath)) {
            $this->components->error("The common base [{$baseName}] could not be found.");

            return self::FAILURE;
        }

        $base = $this->readJson($basePath);

        if ($base === null) {
            $this->components->error("The common base [{$baseName}] contains invalid JSON.");

            return self::FAILURE;
        }

        $override = [];

        if ($this->files->exists($overridePath)) {
            $override = $this->readJson($overridePath);

            if ($override === null) {
                $this->components->error("The app's [{$overrideName}] contains invalid JSON.");

                return self::FAILURE;
            }
        }

        $merged = $this->deepMerge($base, $override);

        $this->files->ensureDirectoryExists(dirname($outputPath));

        $this->files->put(
            $outputPath,
            json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL,
        );

        $this->components->info("The [{$outputName}] file was published successfully.");

        return self::SUCCESS;
    }

    /**
     * @return array<mixed>|null
     */
    protected function readJson(string $path): ?array
    {
        $decoded = json_decode($this->files->get($path), associative: true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<mixed> $base
     * @param array<mixed> $override
     *
     * @return array<mixed>
     */
    protected function deepMerge(array $base, array $override): array
    {
        if (array_is_list($base) && array_is_list($override)) {
            $merged = [...$base, ...$override];

            $unique = [];

            foreach ($merged as $value) {
                if (! in_array($value, $unique, strict: true)) {
                    $unique[] = $value;
                }
            }

            return $unique;
        }

        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->deepMerge($base[$key], $value);

                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }
}
