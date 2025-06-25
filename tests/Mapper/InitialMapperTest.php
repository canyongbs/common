<?php

use CanyonGBS\Common\Parser\Mapper\InitialMapper;
use CanyonGBS\Common\Parser\Part\Initial;
use CanyonGBS\Common\Parser\Part\Lastname;
use CanyonGBS\Common\Parser\Part\Salutation;

dataset('provider', function () {
    return [
        [
            'input' => ['A', 'B'],
            'expectation' => [new Initial('A'), 'B'],
        ],
        [
            'input' => [new Salutation('Mr'), 'P.', 'Pan'],
            'expectation' => [new Salutation('Mr'), new Initial('P.'), 'Pan'],
        ],
        [
            'input' => [new Salutation('Mr'), 'Peter', 'D.', new Lastname('Pan')],
            'expectation' => [new Salutation('Mr'), 'Peter', new Initial('D.'), new Lastname('Pan')],
        ],
        [
            'input' => ['James', 'B'],
            'expectation' => ['James', 'B'],
        ],
        [
            'input' => ['James', 'B'],
            'expectation' => ['James', new Initial('B')],
            'arguments' => [2, true],
        ],
        [
            'input' => ['JM', 'Walker'],
            'expectation' => [new Initial('J'), new Initial('M'), 'Walker'],
        ],
        [
            'input' => ['JM', 'Walker'],
            'expectation' => ['JM', 'Walker'],
            'arguments' => [1],
        ],
    ];
});

it('maps initial parts correctly', function (array $input, array $expectation, array $arguments = []) {
    $maxCombined = $arguments[0] ?? 2;
    $matchLastPart = $arguments[1] ?? false;

    $mapper = new InitialMapper($maxCombined, $matchLastPart);
    $result = $mapper->map($input);

    expect($result)->toEqualCanonicalizing($expectation);
})->with('provider');
