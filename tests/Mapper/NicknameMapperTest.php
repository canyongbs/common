<?php

use CanyonGBS\Common\Parser\Mapper\NicknameMapper;
use CanyonGBS\Common\Parser\Part\Nickname;
use CanyonGBS\Common\Parser\Part\Salutation;

dataset('provider', function () {
    return [
        [
            'input' => ['James', '(Jim)', 'T.', 'Kirk'],
            'expectation' => ['James', new Nickname('Jim'), 'T.', 'Kirk'],
        ],
        [
            'input' => ['James', "('Jim')", 'T.', 'Kirk'],
            'expectation' => ['James', new Nickname('Jim'), 'T.', 'Kirk'],
        ],
        [
            'input' => ['William', '"Will"', 'Shatner'],
            'expectation' => ['William', new Nickname('Will'), 'Shatner'],
        ],
        [
            'input' => [new Salutation('Mr'), 'Andre', '(The', 'Giant)', 'Rene', 'Roussimoff'],
            'expectation' => [
                new Salutation('Mr'),
                'Andre',
                new Nickname('The'),
                new Nickname('Giant'),
                'Rene',
                'Roussimoff',
            ],
        ],
        [
            'input' => [new Salutation('Mr'), 'Andre', '["The', 'Giant"]', 'Rene', 'Roussimoff'],
            'expectation' => [
                new Salutation('Mr'),
                'Andre',
                new Nickname('The'),
                new Nickname('Giant'),
                'Rene',
                'Roussimoff',
            ],
        ],
        [
            'input' => [new Salutation('Mr'), 'Andre', '"The', 'Giant"', 'Rene', 'Roussimoff'],
            'expectation' => [
                new Salutation('Mr'),
                'Andre',
                new Nickname('The'),
                new Nickname('Giant'),
                'Rene',
                'Roussimoff',
            ],
        ],
    ];
});

it('maps nickname parts correctly', function (array $input, array $expectation) {
    $mapper = new NicknameMapper([
        '[' => ']',
        '{' => '}',
        '(' => ')',
        '<' => '>',
        '"' => '"',
        '\'' => '\'',
    ]);

    $result = $mapper->map($input);

    expect($result)->toEqualCanonicalizing($expectation);
})->with('provider');
