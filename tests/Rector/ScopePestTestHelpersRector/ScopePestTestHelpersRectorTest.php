<?php

namespace CanyonGBS\Common\Tests\Rector\ScopePestTestHelpersRector;

use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

final class ScopePestTestHelpersRectorTest extends AbstractRectorTestCase
{
    #[DataProvider('provideData')]
    public function test(string $filePath): void
    {
        $this->doTestFile($filePath);
    }

    public static function provideData(): Iterator
    {
        foreach (glob(__DIR__ . '/Fixtures/*.php.inc') as $filePath) {
            yield basename($filePath, '.php.inc') => [$filePath];
        }
    }

    public function provideConfigFilePath(): string
    {
        return __DIR__ . '/config/configured_rule.php';
    }
}
