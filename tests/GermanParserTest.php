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
            ],
        ],
        [
            'Frau Maria Lange',
            [
                'salutation' => 'Frau',
                'firstname' => 'Maria',
                'lastname' => 'Lange',
            ],
        ],
        [
            'Hr. Juergen von der Lippe',
            [
                'salutation' => 'Herr',
                'firstname' => 'Juergen',
                'lastname' => 'von der Lippe',
            ],
        ],
        [
            'Fr. Charlotte von Stein',
            [
                'salutation' => 'Frau',
                'firstname' => 'Charlotte',
                'lastname' => 'von Stein',
            ],
        ],
    ];
});

test('parse', function ($input, $expectation) {
    $parser = new Parser([
        new German(),
    ]);
    $name = $parser->parse($input);

    expect($name)->toBeInstanceOf(Name::class);
    expect($name->getAll())->toEqual($expectation);
})->with('provider');
