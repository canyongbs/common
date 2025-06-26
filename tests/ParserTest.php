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
            'James Norrington',
            [
                'firstname' => 'James',
                'lastname' => 'Norrington',
            ],
        ],
        [
            'Hans Christian Anderssen',
            [
                'firstname' => 'Hans',
                'lastname' => 'Anderssen',
                'middlename' => 'Christian',
            ],
        ],
        [
            'Mr Anthony R Von Fange III',
            [
                'salutation' => 'Mr.',
                'firstname' => 'Anthony',
                'initials' => 'R',
                'lastname' => 'von Fange',
                'suffix' => 'III',
            ],
        ],
        [
            'J. B. Hunt',
            [
                'firstname' => 'J.',
                'initials' => 'B.',
                'lastname' => 'Hunt',
            ],
        ],
        [
            'J.B. Hunt',
            [
                'firstname' => 'J',
                'initials' => 'B',
                'lastname' => 'Hunt',
            ],
        ],
        [
            'Edward Senior III',
            [
                'firstname' => 'Edward',
                'lastname' => 'Senior',
                'suffix' => 'III',
            ],
        ],
        [
            'Edward Dale Senior II',
            [
                'firstname' => 'Edward',
                'lastname' => 'Dale',
                'suffix' => 'Senior II',
            ],
        ],
        [
            'Dale Edward Jones Senior',
            [
                'firstname' => 'Dale',
                'middlename' => 'Edward',
                'lastname' => 'Jones',
                'suffix' => 'Senior',
            ],
        ],
        [
            'Jason Rodriguez Sr.',
            [
                'firstname' => 'Jason',
                'lastname' => 'Rodriguez',
                'suffix' => 'Sr',
            ],
        ],
        [
            'Jason Senior',
            [
                'firstname' => 'Jason',
                'lastname' => 'Senior',
            ],
        ],
        [
            'Bill Junior',
            [
                'firstname' => 'Bill',
                'lastname' => 'Junior',
            ],
        ],
        [
            'Sara Ann Fraser',
            [
                'firstname' => 'Sara',
                'middlename' => 'Ann',
                'lastname' => 'Fraser',
            ],
        ],
        [
            'Adam',
            [
                'firstname' => 'Adam',
            ],
        ],
        [
            'OLD MACDONALD',
            [
                'firstname' => 'Old',
                'lastname' => 'Macdonald',
            ],
        ],
        [
            'Old MacDonald',
            [
                'firstname' => 'Old',
                'lastname' => 'MacDonald',
            ],
        ],
        [
            'Old McDonald',
            [
                'firstname' => 'Old',
                'lastname' => 'McDonald',
            ],
        ],
        [
            'Old Mc Donald',
            [
                'firstname' => 'Old',
                'middlename' => 'Mc',
                'lastname' => 'Donald',
            ],
        ],
        [
            'Old Mac Donald',
            [
                'firstname' => 'Old',
                'middlename' => 'Mac',
                'lastname' => 'Donald',
            ],
        ],
        [
            'James van Allen',
            [
                'firstname' => 'James',
                'lastname' => 'van Allen',
            ],
        ],
        [
            'Jimmy (Bubba) Smith',
            [
                'firstname' => 'Jimmy',
                'lastname' => 'Smith',
                'nickname' => 'Bubba',
            ],
        ],
        [
            'Miss Jennifer Shrader Lawrence',
            [
                'salutation' => 'Miss',
                'firstname' => 'Jennifer',
                'middlename' => 'Shrader',
                'lastname' => 'Lawrence',
            ],
        ],
        [
            'Dr. Jonathan Smith',
            [
                'salutation' => 'Dr.',
                'firstname' => 'Jonathan',
                'lastname' => 'Smith',
            ],
        ],
        [
            'Ms. Jamie P. Harrowitz',
            [
                'salutation' => 'Ms.',
                'firstname' => 'Jamie',
                'initials' => 'P.',
                'lastname' => 'Harrowitz',
            ],
        ],
        [
            'Mr John Doe',
            [
                'salutation' => 'Mr.',
                'firstname' => 'John',
                'lastname' => 'Doe',
            ],
        ],
        [
            'Rev. Dr John Doe',
            [
                'salutation' => 'Rev. Dr.',
                'firstname' => 'John',
                'lastname' => 'Doe',
            ],
        ],
        [
            'Prof. Tyson J. Hirthe',
            [
                'salutation' => 'Prof.',
                'lastname' => 'Hirthe',
                'firstname' => 'Tyson',
                'initials' => 'J.',
            ],
        ],
        [
            'prof Eveline Aufderhar',
            [
                'salutation' => 'Prof.',
                'lastname' => 'Aufderhar',
                'firstname' => 'Eveline',
            ],
        ],
        [
            'Anthony Von Fange III',
            [
                'firstname' => 'Anthony',
                'lastname' => 'von Fange',
                'suffix' => 'III',
            ],
        ],
        [
            'Smarty Pants Phd',
            [
                'firstname' => 'Smarty',
                'lastname' => 'Pants',
                'suffix' => 'PhD',
            ],
        ],
        [
            'Mark Peter Williams',
            [
                'firstname' => 'Mark',
                'middlename' => 'Peter',
                'lastname' => 'Williams',
            ],
        ],
        [
            'Mark P Williams',
            [
                'firstname' => 'Mark',
                'lastname' => 'Williams',
                'initials' => 'P',
            ],
        ],
        [
            'Mark P. Williams',
            [
                'firstname' => 'Mark',
                'initials' => 'P.',
                'lastname' => 'Williams',
            ],
        ],
        [
            'M Peter Williams',
            [
                'firstname' => 'Peter',
                'initials' => 'M',
                'lastname' => 'Williams',
            ],
        ],
        [
            'M. Peter Williams',
            [
                'firstname' => 'Peter',
                'initials' => 'M.',
                'lastname' => 'Williams',
            ],
        ],
        [
            'M. P. Williams',
            [
                'firstname' => 'M.',
                'initials' => 'P.',
                'lastname' => 'Williams',
            ],
        ],
        [
            'The Rev. Mark Williams',
            [
                'salutation' => 'Rev.',
                'firstname' => 'Mark',
                'lastname' => 'Williams',
            ],
        ],
        [
            'Mister Mark Williams',
            [
                'salutation' => 'Mr.',
                'firstname' => 'Mark',
                'lastname' => 'Williams',
            ],
        ],
        [
            'Fraser, Joshua',
            [
                'firstname' => 'Joshua',
                'lastname' => 'Fraser',
            ],
        ],
        [
            'Mrs. Brown, Amanda',
            [
                'salutation' => 'Mrs.',
                'firstname' => 'Amanda',
                'lastname' => 'Brown',
            ],
        ],
        [
            "Mr.\r\nPaul\rJoseph\nMaria\tWinters",
            [
                'salutation' => 'Mr.',
                'firstname' => 'Paul',
                'middlename' => 'Joseph Maria',
                'lastname' => 'Winters',
            ],
        ],
        [
            'Van Truong',
            [
                'firstname' => 'Van',
                'lastname' => 'Truong',
            ],
        ],
        [
            'John Van',
            [
                'firstname' => 'John',
                'lastname' => 'Van',
            ],
        ],
        [
            'Mr. Van Truong',
            [
                'salutation' => 'Mr.',
                'firstname' => 'Van',
                'lastname' => 'Truong',
            ],
        ],
        [
            'Anthony Von Fange III, PHD',
            [
                'firstname' => 'Anthony',
                'lastname' => 'von Fange',
                'suffix' => 'III PhD',
            ],
        ],
        [
            'Jimmy (Bubba Junior) Smith',
            [
                'nickname' => 'Bubba Junior',
                'firstname' => 'Jimmy',
                'lastname' => 'Smith',
            ],
        ],
        [
            'Jonathan Smith, MD',
            [
                'firstname' => 'Jonathan',
                'lastname' => 'Smith',
                'suffix' => 'MD',
            ],
        ],
        [
            'Kirk, James T.',
            [
                'firstname' => 'James',
                'initials' => 'T.',
                'lastname' => 'Kirk',
            ],
        ],
        [
            'James B',
            [
                'firstname' => 'James',
                'lastname' => 'B',
            ],
        ],
        [
            'Williams, Hank, Jr.',
            [
                'firstname' => 'Hank',
                'lastname' => 'Williams',
                'suffix' => 'Jr',
            ],
        ],
        [
            'Sir James Reynolds, Junior',
            [
                'salutation' => 'Sir',
                'firstname' => 'James',
                'lastname' => 'Reynolds',
                'suffix' => 'Junior',
            ],
        ],
        [
            'Sir John Paul Getty Sr.',
            [
                'salutation' => 'Sir',
                'firstname' => 'John',
                'middlename' => 'Paul',
                'lastname' => 'Getty',
                'suffix' => 'Sr',
            ],
        ],
        [
            'etna übel',
            [
                'firstname' => 'Etna',
                'lastname' => 'Übel',
            ],
        ],
        [
            'Markus Müller',
            [
                'firstname' => 'Markus',
                'lastname' => 'Müller',
            ],
        ],
        [
            'Charles Dixon (20th century)',
            [
                'firstname' => 'Charles',
                'lastname' => 'Dixon',
                'nickname' => '20Th Century',
            ],
        ],
        [
            'Smith, John Eric',
            [
                'lastname' => 'Smith',
                'firstname' => 'John',
                'middlename' => 'Eric',
            ],
        ],
        [
            'PAUL M LEWIS MR',
            [
                'firstname' => 'Paul',
                'initials' => 'M',
                'lastname' => 'Lewis Mr',
            ],
        ],
        [
            'SUJAN MASTER',
            [
                'firstname' => 'Sujan',
                'lastname' => 'Master',
            ],
        ],
        [
            'JAMES J MA',
            [
                'firstname' => 'James',
                'initials' => 'J',
                'lastname' => 'Ma',
            ],
        ],
        [
            'Tiptree, James, Jr.',
            [
                'lastname' => 'Tiptree',
                'firstname' => 'James',
                'suffix' => 'Jr',
            ],
        ],
        [
            'Miller, Walter M., Jr.',
            [
                'lastname' => 'Miller',
                'firstname' => 'Walter',
                'initials' => 'M.',
                'suffix' => 'Jr',
            ],
        ],
        [
            'Tiptree, James Jr.',
            [
                'lastname' => 'Tiptree',
                'firstname' => 'James',
                'suffix' => 'Jr',
            ],
        ],
        [
            'Miller, Walter M. Jr.',
            [
                'lastname' => 'Miller',
                'firstname' => 'Walter',
                'initials' => 'M.',
                'suffix' => 'Jr',
            ],
        ],
        [
            'Thái Quốc Nguyễn',
            [
                'lastname' => 'Nguyễn',
                'middlename' => 'Quốc',
                'firstname' => 'Thái',
            ],
        ],
        [
            'Yumeng Du',
            [
                'lastname' => 'Du',
                'firstname' => 'Yumeng',
            ],
        ],
        [
            'Her Honour Mrs Judy',
            [
                'lastname' => 'Judy',
                'salutation' => 'Her Honour Mrs.',
            ],
        ],
        [
            'Etje Heijdanus-De Boer',
            [
                'firstname' => 'Etje',
                'lastname' => 'Heijdanus-De Boer',
            ],
        ],
        [
            'JB Hunt',
            [
                'firstname' => 'J',
                'initials' => 'B',
                'lastname' => 'Hunt',
            ],
        ],
        [
            'Charles Philip Arthur George Mountbatten-Windsor',
            [
                'firstname' => 'Charles',
                'middlename' => 'Philip Arthur George',
                'lastname' => 'Mountbatten-Windsor',
            ],
        ],
        [
            'Ella Marija Lani Yelich-O\'Connor',
            [
                'firstname' => 'Ella',
                'middlename' => 'Marija Lani',
                'lastname' => 'Yelich-O\'Connor',
            ],
        ],
    ];
});
test('parse', function ($input, $expectation) {
    $parser = new Parser();
    $name = $parser->parse($input);

    expect($name)->toBeInstanceOf(Name::class);
    expect($name->getAll())->toEqual($expectation);
})->with('provider');
test('set get whitespace', function () {
    $parser = new Parser();
    $parser->setWhitespace('abc');
    expect($parser->getWhitespace())->toBe('abc');
    $parser->setWhitespace(' ');
    expect($parser->getWhitespace())->toBe(' ');
    $parser->setWhitespace('   _');
    expect($parser->getWhitespace())->toBe('   _');
});
test('set get nickname delimiters', function () {
    $parser = new Parser();
    $parser->setNicknameDelimiters(['[' => ']']);
    expect($parser->getNicknameDelimiters())->toBe(['[' => ']']);
    expect($parser->parse('[Jim]')->getNickname())->toBe('Jim');
    $this->assertNotSame('Jim', $parser->parse('(Jim)')->getNickname());
});
test('set max salutation index', function () {
    $parser = new Parser();
    expect($parser->getMaxSalutationIndex())->toBe(0);
    $parser->setMaxSalutationIndex(1);
    expect($parser->getMaxSalutationIndex())->toBe(1);
    expect($parser->parse('Francis Mr')->getSalutation())->toBe('');

    $parser = new Parser();
    expect($parser->getMaxSalutationIndex())->toBe(0);
    $parser->setMaxSalutationIndex(2);
    expect($parser->getMaxSalutationIndex())->toBe(2);
    expect($parser->parse('Francis Mr')->getSalutation())->toBe('Mr.');
});
test('set max combined initials', function () {
    $parser = new Parser();
    expect($parser->getMaxCombinedInitials())->toBe(2);
    $parser->setMaxCombinedInitials(1);
    expect($parser->getMaxCombinedInitials())->toBe(1);
    expect($parser->parse('DJ Westbam')->getInitials())->toBe('');

    $parser = new Parser();
    expect($parser->getMaxCombinedInitials())->toBe(2);
    $parser->setMaxCombinedInitials(3);
    expect($parser->getMaxCombinedInitials())->toBe(3);
    expect($parser->parse('Charles PAG Mountbatten-Windsor')->getInitials())->toBe('P A G');
});
test('parser and subparsers properly handle languages', function () {
    $parser = new Parser([
        new German(),
    ]);

    expect($parser->parse('Herr Schmidt')->getSalutation())->toBe('Herr');
    expect($parser->parse('Herr Schmidt, Bernd')->getSalutation())->toBe('Herr');
});
