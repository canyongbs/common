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

it('does not report action classes that only expose __invoke publicly', function () {
    $result = runPhpStanOnActionClassFixture('tests/PHPStan/Fixtures/ActionInvokableFixture.php');

    expect($result['exitCode'])->toBe(0, "PHPStan should not report valid action classes.\nOutput: {$result['output']}");
});

it('reports action classes missing __invoke and exposing execute publicly', function () {
    $result = runPhpStanOnActionClassFixture('tests/PHPStan/Fixtures/ActionMissingInvokeFixture.php');

    expect($result['exitCode'])->not->toBe(0);
    expect($result['output'])->toContain('Common.actionClassMustBeInvokable');
    expect($result['output'])->toContain('Common.actionClassHasDisallowedPublicMethod');
});

it('reports action classes that expose extra public methods', function () {
    $result = runPhpStanOnActionClassFixture('tests/PHPStan/Fixtures/ActionWithExtraPublicMethodFixture.php');

    expect($result['exitCode'])->not->toBe(0);
    expect($result['output'])->toContain('Common.actionClassHasDisallowedPublicMethod');
    expect($result['output'])->not->toContain('Common.actionClassMustBeInvokable');
});

it('does not report classes outside action namespaces', function () {
    $result = runPhpStanOnActionClassFixture('tests/PHPStan/Fixtures/NonActionOutsideNamespaceFixture.php');

    expect($result['exitCode'])->toBe(0, "PHPStan should not report classes outside action namespaces.\nOutput: {$result['output']}");
});

it('does not report abstract action classes', function () {
    $result = runPhpStanOnActionClassFixture('tests/PHPStan/Fixtures/AbstractActionFixture.php');

    expect($result['exitCode'])->toBe(0, "PHPStan should not report abstract action classes.\nOutput: {$result['output']}");
});

it('reports modular action namespaces matched by wildcard include patterns', function () {
    $result = runPhpStanOnActionClassFixture('tests/PHPStan/Fixtures/ModularActionMissingInvokeFixture.php');

    expect($result['exitCode'])->not->toBe(0);
    expect($result['output'])->toContain('Common.actionClassMustBeInvokable');
    expect($result['output'])->toContain('Common.actionClassHasDisallowedPublicMethod');
});

it('does not report classes in Filament actions namespaces excluded by default', function () {
    $result = runPhpStanOnActionClassFixture('tests/PHPStan/Fixtures/FilamentActionExcludedFixture.php');

    expect($result['exitCode'])->toBe(0, "PHPStan should not report classes in excluded Filament action namespaces.\nOutput: {$result['output']}");
});

it('does not report classes in nested Filament actions namespaces excluded by default', function () {
    $result = runPhpStanOnActionClassFixture('tests/PHPStan/Fixtures/NestedFilamentActionExcludedFixture.php');

    expect($result['exitCode'])->toBe(0, "PHPStan should not report classes in nested excluded Filament action namespaces.\nOutput: {$result['output']}");
});

/**
 * @return array{exitCode: int, output: string}
 */
function runPhpStanOnActionClassFixture(string $filePath): array
{
    $basePath = dirname(__DIR__, 2);
    $phpstanBin = escapeshellarg($basePath . '/vendor/bin/phpstan');
    $configPath = escapeshellarg($basePath . '/tests/PHPStan/Configs/action-classes-must-be-invokable.neon');
    $file = escapeshellarg($filePath);

    $command = "{$phpstanBin} analyse {$file} --configuration={$configPath} --error-format=json --no-progress 2>&1";

    exec($command, $outputLines, $exitCode);

    return [
        'exitCode' => $exitCode,
        'output' => implode("\n", $outputLines),
    ];
}
