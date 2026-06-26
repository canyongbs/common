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

it('reports Blueprint json() column definitions in migrations', function () {
    $result = runPhpStanOnJsonColumnInMigrationFixture('tests/PHPStan/Fixtures/JsonColumnInMigrationFixture.php');

    expect($result['exitCode'])->not->toBe(0);
    expect($result['output'])->toContain('Common.jsonColumnInMigration');
});

it('reports Blueprint json() column definitions called on a property', function () {
    $result = runPhpStanOnJsonColumnInMigrationFixture('tests/PHPStan/Fixtures/JsonColumnViaPropertyFixture.php');

    expect($result['exitCode'])->not->toBe(0);
    expect($result['output'])->toContain('Common.jsonColumnInMigration');
});

it('does not report Blueprint jsonb() column definitions', function () {
    $result = runPhpStanOnJsonColumnInMigrationFixture('tests/PHPStan/Fixtures/JsonbColumnInMigrationFixture.php');

    expect($result['exitCode'])->toBe(0, "PHPStan should not report jsonb() column definitions.\nOutput: {$result['output']}");
});

it('does not report json() calls on non-Blueprint receivers', function () {
    $result = runPhpStanOnJsonColumnInMigrationFixture('tests/PHPStan/Fixtures/JsonColumnNonBlueprintFixture.php');

    expect($result['exitCode'])->toBe(0, "PHPStan should not report json() calls on non-Blueprint receivers.\nOutput: {$result['output']}");
});

it('allows json() column definitions silenced with a specific inline ignore', function () {
    $result = runPhpStanOnJsonColumnInMigrationFixture('tests/PHPStan/Fixtures/JsonColumnIgnoredFixture.php');

    expect($result['exitCode'])->toBe(0, "PHPStan should respect the inline ignore for json() column definitions.\nOutput: {$result['output']}");
});

/**
 * @return array{exitCode: int, output: string}
 */
function runPhpStanOnJsonColumnInMigrationFixture(string $filePath): array
{
    $basePath = dirname(__DIR__, 2);
    $phpstanBin = escapeshellarg($basePath . '/vendor/bin/phpstan');
    $configPath = escapeshellarg($basePath . '/tests/PHPStan/phpstan-test.neon');
    $file = escapeshellarg($filePath);

    $command = "{$phpstanBin} analyse {$file} --configuration={$configPath} --error-format=json --no-progress 2>&1";

    exec($command, $outputLines, $exitCode);

    return [
        'exitCode' => $exitCode,
        'output' => implode("\n", $outputLines),
    ];
}
