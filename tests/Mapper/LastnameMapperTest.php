<?php

use CanyonGBS\Common\Parser\Language\English;
use CanyonGBS\Common\Parser\Mapper\LastnameMapper;
use CanyonGBS\Common\Parser\Part\Firstname;
use CanyonGBS\Common\Parser\Part\Lastname;
use CanyonGBS\Common\Parser\Part\LastnamePrefix;
use CanyonGBS\Common\Parser\Part\Salutation;

dataset('provider', function () {
    return [
        [
            'input' => ['Peter', 'Pan'],
            'expectation' => ['Peter', new Lastname('Pan')],
        ],
        [
            'input' => [new Salutation('Mr'), 'Peter', 'Pan'],
            'expectation' => [new Salutation('Mr'), 'Peter', new Lastname('Pan')],
        ],
        [
            'input' => [new Salutation('Mr'), new Firstname('Peter'), 'Pan'],
            'expectation' => [new Salutation('Mr'), new Firstname('Peter'), new Lastname('Pan')],
        ],
        [
            'input' => [new Salutation('Mr'), 'Lars', 'van', 'Trier'],
            'expectation' => [new Salutation('Mr'), 'Lars', new LastnamePrefix('van'), new Lastname('Trier')],
        ],
        [
            'input' => [new Salutation('Mr'), 'Dan', 'Huong'],
            'expectation' => [new Salutation('Mr'), 'Dan', new Lastname('Huong')],
        ],
        [
            'input' => [new Salutation('Mr'), 'Von'],
            'expectation' => [new Salutation('Mr'), new Lastname('Von')],
        ],
        [
            'input' => ['Mr', 'Von'],
            'expectation' => ['Mr', new Lastname('Von')],
        ],
        [
            'input' => ['Kirk'],
            'expectation' => ['Kirk'],
        ],
        [
            'input' => ['Kirk'],
            'expectation' => [new Lastname('Kirk')],
            'arguments' => [true],
        ],
    ];
});

it('maps lastname parts correctly', function (array $input, array $expectation, array $arguments = []) {
    $matchSingle = $arguments[0] ?? false;
    $mapper = new LastnameMapper((new English())->getLastnamePrefixes(), $matchSingle);

    $result = $mapper->map($input);

    expect($result)->toEqualCanonicalizing($expectation);
})->with('provider');
