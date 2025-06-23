<?php

use CanyonGBS\Common\Mapper\AbstractMapperTest;
use CanyonGBS\Common\Parser\Language\English;
use CanyonGBS\Common\Parser\Mapper\SalutationMapper;
use CanyonGBS\Common\Parser\Part\Firstname;
use CanyonGBS\Common\Parser\Part\Salutation;

class SalutationMapperTest extends AbstractMapperTest
{
    /**
     * @return array
     */
    public static function provider()
    {
        return [
            [
                'input' => [
                    'Mr.',
                    'Pan',
                ],
                'expectation' => [
                    new Salutation('Mr.', 'Mr.'),
                    'Pan',
                ],
            ],
            [
                'input' => [
                    'Mr',
                    'Peter',
                    'Pan',
                ],
                'expectation' => [
                    new Salutation('Mr', 'Mr.'),
                    'Peter',
                    'Pan',
                ],
            ],
            [
                'input' => [
                    'Mr',
                    new Firstname('James'),
                    'Miss',
                ],
                'expectation' => [
                    new Salutation('Mr', 'Mr.'),
                    new Firstname('James'),
                    'Miss',
                ],
            ],
        ];
    }

    protected function getMapper()
    {
        $english = new English();

        return new SalutationMapper($english->getSalutations());
    }
}
