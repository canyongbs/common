<?php

it('does not trigger for policies that have both delete, force delete, restore, and their equivalent bulk actions defined', function () {
    $result = runPhpStanOnFixture('tests/PHPStan/Fixtures/PolicyWithBulkDeleteForceDeleteRestoreFixture.php');

    expect($result['exitCode'])->toBe(0, "PHPStan should not report errors for policies with the appropriate bulk actions defined.\nOutput: {$result['output']}");
});

it('does trigger for policies that do not have both delete, force delete, restore, and their equivalent bulk actions defined', function () {
    $result = runPhpStanOnFixture('tests/PHPStan/Fixtures/PolicyWithoutBulkDeleteForceDeleteRestoreFixture.php');

    expect($result['exitCode'])->not->toBe(0);
    expect($result['output'])->toContain('Common.deleteForceDeleteRestoreBulkEquivalents');
});

/**
 * @return array{exitCode: int, output: string}
 */
function runPhpStanOnFixture(string $filePath): array
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