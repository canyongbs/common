<?php

use CanyonGBS\Common\Mapper\AbstractMapperTest;
use CanyonGBS\Common\Parser\Language\English;
use CanyonGBS\Common\Parser\Mapper\SuffixMapper;
use CanyonGBS\Common\Parser\Part\Firstname;
use CanyonGBS\Common\Parser\Part\Lastname;
use CanyonGBS\Common\Parser\Part\Suffix;

class SuffixMapperTest extends AbstractMapperTest
{
    /**
     * @return array
     */
    public static function provider()
    {
        return [
            [
                ['Mr.', 'James', 'Blueberg', 'PhD'],
                ['Mr.', 'James', 'Blueberg', new Suffix('PhD')],
                ['matchSinglePart' => false, 'reservedParts' => 2],
            ],
            [
                ['Prince', 'Alfred', 'III'],
                ['Prince', 'Alfred', new Suffix('III')],
                ['matchSinglePart' => false, 'reservedParts' => 2],
            ],
            [
                [new Firstname('Paul'), new Lastname('Smith'), 'Senior'],
                [new Firstname('Paul'), new Lastname('Smith'), new Suffix('Senior')],
                ['matchSinglePart' => false, 'reservedParts' => 2],
            ],
            [
                ['Senior', new Firstname('James'), 'Norrington'],
                ['Senior', new Firstname('James'), 'Norrington'],
                ['matchSinglePart' => false, 'reservedParts' => 2],
            ],
            [
                ['Senior', new Firstname('James'), new Lastname('Norrington')],
                ['Senior', new Firstname('James'), new Lastname('Norrington')],
                ['matchSinglePart' => false, 'reservedParts' => 2],
            ],
            [
                ['James', 'Norrington', 'Senior'],
                ['James', 'Norrington', new Suffix('Senior')],
                ['matchSinglePart' => false, 'reservedParts' => 2],
            ],
            [
                ['Norrington', 'Senior'],
                ['Norrington', 'Senior'],
                ['matchSinglePart' => false, 'reservedParts' => 2],
            ],
            [
                [new Lastname('Norrington'), 'Senior'],
                [new Lastname('Norrington'), new Suffix('Senior')],
                ['matchSinglePart' => false, 'reservedParts' => 1],
            ],
            [
                ['Senior'],
                [new Suffix('Senior')],
                ['matchSinglePart' => true],
            ]
        ];
    }

    protected function getMapper($matchSinglePart = false, $reservedParts = 2)
    {
        $english = new English();

        return new SuffixMapper($english->getSuffixes(), $matchSinglePart, $reservedParts);
    }
}