<?php

use CanyonGBS\Common\Mapper\AbstractMapperTest;
use CanyonGBS\Common\Parser\Mapper\FirstnameMapper;
use CanyonGBS\Common\Parser\Part\Firstname;
use CanyonGBS\Common\Parser\Part\Lastname;
use CanyonGBS\Common\Parser\Part\Salutation;

class FirstnameMapperTest extends AbstractMapperTest
{
    /**
     * @return array
     */
    public static function provider()
    {
        return [
            [
                'input' => [
                    'Peter',
                    'Pan',
                ],
                'expectation' => [
                    new Firstname('Peter'),
                    'Pan',
                ],
            ],
            [
                'input' => [
                    new Salutation('Mr'),
                    'Peter',
                    'Pan',
                ],
                'expectation' => [
                    new Salutation('Mr'),
                    new Firstname('Peter'),
                    'Pan',
                ],
            ],
            [
                'input' => [
                    new Salutation('Mr'),
                    'Peter',
                    new Lastname('Pan'),
                ],
                'expectation' => [
                    new Salutation('Mr'),
                    new Firstname('Peter'),
                    new Lastname('Pan'),
                ],
            ],
            [
                'input' => [
                    'Alfonso',
                    new Salutation('Mr'),
                ],
                'expectation' => [
                    new Firstname('Alfonso'),
                    new Salutation('Mr'),
                ]
            ]
        ];
    }

    protected function getMapper()
    {
        return new FirstnameMapper();
    }
}
