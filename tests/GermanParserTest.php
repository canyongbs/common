<?php

use CanyonGBS\Common\Parser\Language\German;
use CanyonGBS\Common\Parser\Name;
use CanyonGBS\Common\Parser\Parser;

/**
 * @return array
 */
dataset('provider', function () {
    return [
        [
            'Herr Schmidt',
            [
                'salutation' => 'Herr',
                'lastname' => 'Schmidt',
            ]
        ],
        [
            'Frau Maria Lange',
            [
                'salutation' => 'Frau',
                'firstname' => 'Maria',
                'lastname' => 'Lange',
            ]
        ],
        [
            'Hr. Juergen von der Lippe',
            [
                'salutation' => 'Herr',
                'firstname' => 'Juergen',
                'lastname' => 'von der Lippe',
            ]
        ],
        [
            'Fr. Charlotte von Stein',
            [
                'salutation' => 'Frau',
                'firstname' => 'Charlotte',
                'lastname' => 'von Stein',
            ]
        ],
    ];
});

test('parse', function ($input, $expectation) {
    $parser = new Parser([
        new German()
    ]);
    $name = $parser->parse($input);

    expect($name)->toBeInstanceOf(Name::class);
    expect($name->getAll())->toEqual($expectation);
})->with('provider');
