<?php

/*
<COPYRIGHT>

    Copyright © 2016-2025, Canyon GBS LLC. All rights reserved.

    Canyon GBS Common is licensed under the Elastic License 2.0. For more details,
    see https://github.com/canyongbs/common/blob/main/LICENSE.

    Notice:

    - You may not provide the software to third parties as a hosted or managed
      service, where the service provides users with access to any substantial set of
      the features or functionality of the software.
    - You may not move, change, disable, or circumvent the license key functionality
      in the software, and you may not remove or obscure any functionality in the
      software that is protected by the license key.
    - You may not alter, remove, or obscure any licensing, copyright, or other notices
      of the licensor in the software. Any use of the licensor’s trademarks is subject
      to applicable law.
    - Canyon GBS LLC respects the intellectual property rights of others and expects the
      same in return. Canyon GBS™ and Canyon GBS Common are registered trademarks of
      Canyon GBS LLC, and we are committed to enforcing and protecting our trademarks
      vigorously.
    - The software solution, including services, infrastructure, and code, is offered as a
      Software as a Service (SaaS) by Canyon GBS LLC.
    - Use of this software implies agreement to the license terms and conditions as stated
      in the Elastic License 2.0.

    For more information or inquiries please visit our website at
    https://www.canyongbs.com or contact us via email at legal@canyongbs.com.

</COPYRIGHT>
*/

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
