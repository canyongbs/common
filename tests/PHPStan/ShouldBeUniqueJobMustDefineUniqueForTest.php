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

it('reports jobs implementing ShouldBeUnique that do not define uniqueFor', function () {
    $result = runPhpStanOnShouldBeUniqueJobFixture('tests/PHPStan/Fixtures/JobMissingUniqueForFixture.php');

    expect($result['exitCode'])->not->toBe(0);
    expect($result['output'])->toContain('Common.shouldBeUniqueJobMustDefineUniqueFor');
    expect($result['output'])->toContain('uniqueFor');
});

it('reports jobs implementing ShouldBeUniqueUntilProcessing that do not define uniqueFor', function () {
    $result = runPhpStanOnShouldBeUniqueJobFixture('tests/PHPStan/Fixtures/JobUniqueUntilProcessingMissingUniqueForFixture.php');

    expect($result['exitCode'])->not->toBe(0);
    expect($result['output'])->toContain('Common.shouldBeUniqueJobMustDefineUniqueFor');
});

it('reports jobs that inherit ShouldBeUnique but do not define uniqueFor anywhere', function () {
    $result = runPhpStanOnShouldBeUniqueJobFixture('tests/PHPStan/Fixtures/JobInheritingShouldBeUniqueMissingUniqueForFixture.php');

    expect($result['exitCode'])->not->toBe(0);
    expect($result['output'])->toContain('Common.shouldBeUniqueJobMustDefineUniqueFor');
});

it('does not report jobs that define a uniqueFor property', function () {
    $result = runPhpStanOnShouldBeUniqueJobFixture('tests/PHPStan/Fixtures/JobWithUniqueForPropertyFixture.php');

    expect($result['exitCode'])->toBe(0, "PHPStan should not report jobs that define a uniqueFor property.\nOutput: {$result['output']}");
});

it('does not report jobs that define a uniqueFor method', function () {
    $result = runPhpStanOnShouldBeUniqueJobFixture('tests/PHPStan/Fixtures/JobWithUniqueForMethodFixture.php');

    expect($result['exitCode'])->toBe(0, "PHPStan should not report jobs that define a uniqueFor method.\nOutput: {$result['output']}");
});

it('does not report jobs that inherit a uniqueFor definition from a parent', function () {
    $result = runPhpStanOnShouldBeUniqueJobFixture('tests/PHPStan/Fixtures/JobInheritingUniqueForFixture.php');

    expect($result['exitCode'])->toBe(0, "PHPStan should not report jobs that inherit a uniqueFor definition from a parent.\nOutput: {$result['output']}");
});

it('does not report classes that do not implement ShouldBeUnique', function () {
    $result = runPhpStanOnShouldBeUniqueJobFixture('tests/PHPStan/Fixtures/JobNotUniqueFixture.php');

    expect($result['exitCode'])->toBe(0, "PHPStan should not report classes that do not implement ShouldBeUnique.\nOutput: {$result['output']}");
});

it('does not report abstract jobs that implement ShouldBeUnique', function () {
    $result = runPhpStanOnShouldBeUniqueJobFixture('tests/PHPStan/Fixtures/AbstractUniqueJobFixture.php');

    expect($result['exitCode'])->toBe(0, "PHPStan should not report abstract jobs that implement ShouldBeUnique.\nOutput: {$result['output']}");
});

/**
 * @return array{exitCode: int, output: string}
 */
function runPhpStanOnShouldBeUniqueJobFixture(string $filePath): array
{
    $basePath = dirname(__DIR__, 2);
    $phpstanBin = escapeshellarg($basePath . '/vendor/bin/phpstan');
    $configPath = escapeshellarg($basePath . '/tests/PHPStan/Configs/should-be-unique-job-must-define-unique-for.neon');
    $file = escapeshellarg($filePath);

    $command = "{$phpstanBin} analyse {$file} --configuration={$configPath} --error-format=json --no-progress 2>&1";

    exec($command, $outputLines, $exitCode);

    return [
        'exitCode' => $exitCode,
        'output' => implode("\n", $outputLines),
    ];
}
