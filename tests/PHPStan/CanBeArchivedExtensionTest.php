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

it('recognizes archive methods on a builder for a model with CanBeArchived', function () {
    $result = runPhpStan('tests/PHPStan/Fixtures/CanBeArchivedModelFixture.php');

    expect($result['exitCode'])->toBe(0, "PHPStan should not report errors for archive methods on a model with CanBeArchived.\nOutput: {$result['output']}");
});

it('reports errors for archive methods on a builder for a model without CanBeArchived', function () {
    $result = runPhpStan('tests/PHPStan/Fixtures/NonArchivedModelFixture.php');

    expect($result['exitCode'])->not->toBe(0);
    expect($result['output'])->toContain('withoutArchived');
    expect($result['output'])->toContain('onlyArchived');
    expect($result['output'])->toContain('withoutArchivedAndUnused');
});

it('recognizes archive methods on a relation to a model with CanBeArchived', function () {
    $result = runPhpStan('tests/PHPStan/Fixtures/CanBeArchivedRelationFixture.php');

    expect($result['exitCode'])->toBe(0, "PHPStan should not report errors for archive methods on a relation.\nOutput: {$result['output']}");
});

/**
 * @return array{exitCode: int, output: string}
 */
function runPhpStan(string $filePath): array
{
    $basePath = dirname(__DIR__, 2);
    $phpstanBin = escapeshellarg($basePath . '/vendor/bin/phpstan');
    $configPath = escapeshellarg($basePath . '/tests/PHPStan/phpstan-test.neon');
    $file = escapeshellarg($filePath);

    $command = "{$phpstanBin} analyse {$file} --configuration={$configPath} --error-format=table --no-progress 2>&1";

    exec($command, $outputLines, $exitCode);

    return [
        'exitCode' => $exitCode,
        'output' => implode("\n", $outputLines),
    ];
}
