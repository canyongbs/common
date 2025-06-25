<?php

use CanyonGBS\Common\Parser\Mapper\MiddlenameMapper;
use CanyonGBS\Common\Parser\Part\Firstname;
use CanyonGBS\Common\Parser\Part\Lastname;
use CanyonGBS\Common\Parser\Part\Middlename;

dataset('provider', function () {
    return [
        [
            'input' => [
                new Firstname('Peter'),
                'Fry',
                new Lastname('Pan'),
            ],
            'expectation' => [
                new Firstname('Peter'),
                new Middlename('Fry'),
                new Lastname('Pan'),
            ],
        ],
        [
            'input' => [
                new Firstname('John'),
                'Albert',
                'Tiberius',
                new Lastname('Brown'),
            ],
            'expectation' => [
                new Firstname('John'),
                new Middlename('Albert'),
                new Middlename('Tiberius'),
                new Lastname('Brown'),
            ],
        ],
        [
            'input' => [
                'Mr',
                new Firstname('James'),
                'Tiberius',
                'Kirk',
            ],
            'expectation' => [
                'Mr',
                new Firstname('James'),
                new Middlename('Tiberius'),
                'Kirk',
            ],
        ],
        [
            'input' => [
                'James',
                'Tiberius',
                'Kirk',
            ],
            'expectation' => [
                'James',
                'Tiberius',
                'Kirk',
            ],
        ],
        [
            'input' => [
                'Albert',
                'Einstein',
            ],
            'expectation' => [
                'Albert',
                'Einstein',
            ],
        ],
        [
            'input' => [
                new Firstname('James'),
                'Tiberius',
            ],
            'expectation' => [
                new Firstname('James'),
                new Middlename('Tiberius'),
            ],
            'arguments' => [
                true,
            ],
        ],
    ];
});

it('maps middlename parts correctly', function (array $input, array $expectation, array $arguments = []) {
    $mapWithoutLastname = $arguments[0] ?? false;

    $mapper = new MiddlenameMapper($mapWithoutLastname);
    $result = $mapper->map($input);

    expect($result)->toEqualCanonicalizing($expectation);
})->with('provider');
