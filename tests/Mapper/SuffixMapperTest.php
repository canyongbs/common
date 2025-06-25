<?php

use CanyonGBS\Common\Parser\Language\English;
use CanyonGBS\Common\Parser\Mapper\SuffixMapper;
use CanyonGBS\Common\Parser\Part\Firstname;
use CanyonGBS\Common\Parser\Part\Lastname;
use CanyonGBS\Common\Parser\Part\Suffix;


/**
 * @return array
 */
dataset('provider', function () {
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
        ],
    ];
});

it('maps suffix parts correctly', function (array $input, array $expected, array $config) {
    $matchSinglePart = $config['matchSinglePart'] ?? false;
    $reservedParts = $config['reservedParts'] ?? 2;

    $english = new English();
    $mapper = new SuffixMapper($english->getSuffixes(), $matchSinglePart, $reservedParts);

    $result = $mapper->map($input);

    expect($result)->toEqualCanonicalizing($expected);
})->with('provider');
