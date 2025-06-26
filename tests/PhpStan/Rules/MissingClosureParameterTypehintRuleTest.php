<?php

namespace CanyonGBS\Common\Tests\PhpStan\Rules;

use PHPStan\Rules\Rule;
use CanyonGBS\Common\PhpStan\Rules\MissingClosureParameterTypehintRule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<MissingClosureParameterTypehintRule>
 */
class MissingClosureParameterTypehintRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new MissingClosureParameterTypehintRule();
    }

    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/data/MissingClosureParameterTypehintRule.php'], [
            [
                'Parameter #1 $a of anonymous function has no typehint.',
                3,
            ],
            [
                'Parameter #2 $b of anonymous function has no typehint.',
                3,
            ],
            [
                'Parameter #1 $c of anonymous function has no typehint.',
                12,
            ],
            [
                'Parameter #2 $d of anonymous function has no typehint.',
                12,
            ],
        ]);
    }
}
