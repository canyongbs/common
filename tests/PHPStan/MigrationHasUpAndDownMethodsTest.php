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

it('does not report migrations that define both up() and down() methods', function () {
    $result = runPhpStanOnMigrationHasUpAndDownMethodsFixture('tests/PHPStan/Fixtures/MigrationWithUpAndDownFixture.php');

    expect($result['exitCode'])->toBe(0, "PHPStan should not report migrations that define both up() and down().\nOutput: {$result['output']}");
});

it('reports migrations that are missing a down() method', function () {
    $result = runPhpStanOnMigrationHasUpAndDownMethodsFixture('tests/PHPStan/Fixtures/MigrationMissingDownFixture.php');

    expect($result['exitCode'])->not->toBe(0);
    expect($result['output'])->toContain('Common.migrationMissingDownMethod');
    expect($result['output'])->not->toContain('Common.migrationMissingUpMethod');
});

it('reports migrations that are missing an up() method', function () {
    $result = runPhpStanOnMigrationHasUpAndDownMethodsFixture('tests/PHPStan/Fixtures/MigrationMissingUpFixture.php');

    expect($result['exitCode'])->not->toBe(0);
    expect($result['output'])->toContain('Common.migrationMissingUpMethod');
    expect($result['output'])->not->toContain('Common.migrationMissingDownMethod');
});

it('reports migrations that are missing both up() and down() methods', function () {
    $result = runPhpStanOnMigrationHasUpAndDownMethodsFixture('tests/PHPStan/Fixtures/MigrationMissingBothFixture.php');

    expect($result['exitCode'])->not->toBe(0);
    expect($result['output'])->toContain('Common.migrationMissingUpMethod');
    expect($result['output'])->toContain('Common.migrationMissingDownMethod');
});

it('does not report anonymous migrations that define both up() and down() methods', function () {
    $result = runPhpStanOnMigrationHasUpAndDownMethodsFixture('tests/PHPStan/Fixtures/AnonymousMigrationWithUpAndDownFixture.php');

    expect($result['exitCode'])->toBe(0, "PHPStan should not report anonymous migrations that define both up() and down().\nOutput: {$result['output']}");
});

it('reports anonymous migrations that are missing a down() method', function () {
    $result = runPhpStanOnMigrationHasUpAndDownMethodsFixture('tests/PHPStan/Fixtures/AnonymousMigrationMissingDownFixture.php');

    expect($result['exitCode'])->not->toBe(0);
    expect($result['output'])->toContain('Common.migrationMissingDownMethod');
});

it('does not report classes that are not migrations', function () {
    $result = runPhpStanOnMigrationHasUpAndDownMethodsFixture('tests/PHPStan/Fixtures/NonMigrationClassFixture.php');

    expect($result['exitCode'])->toBe(0, "PHPStan should not report classes that are not migrations.\nOutput: {$result['output']}");
});

/**
 * @return array{exitCode: int, output: string}
 */
function runPhpStanOnMigrationHasUpAndDownMethodsFixture(string $filePath): array
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
