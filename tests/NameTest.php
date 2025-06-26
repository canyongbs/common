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

use CanyonGBS\Common\Parser\Name;
use CanyonGBS\Common\Parser\Parser;
use CanyonGBS\Common\Parser\Part\Firstname;
use CanyonGBS\Common\Parser\Part\Initial;
use CanyonGBS\Common\Parser\Part\Lastname;
use CanyonGBS\Common\Parser\Part\LastnamePrefix;
use CanyonGBS\Common\Parser\Part\Middlename;
use CanyonGBS\Common\Parser\Part\Nickname;
use CanyonGBS\Common\Parser\Part\Salutation;
use CanyonGBS\Common\Parser\Part\Suffix;

test('to string', function () {
    $parts = [
        new Salutation('Mr', 'Mr.'),
        new Firstname('James'),
        new Middlename('Morgan'),
        new Nickname('Jim'),
        new Initial('T.'),
        new Lastname('Smith'),
        new Suffix('I', 'I'),
    ];

    $name = new Name($parts);

    expect($name->getParts())->toBe($parts);
    expect((string) $name)->toBe('Mr. James (Jim) Morgan T. Smith I');
});
test('get nickname', function () {
    $name = new Name([
        new Nickname('Jim'),
    ]);

    expect($name->getNickname())->toBe('Jim');
    expect($name->getNickname(true))->toBe('(Jim)');
});
test('getting lastname and lastname prefix separately', function () {
    $name = new Name([
        new Firstname('Frank'),
        new LastnamePrefix('van'),
        new Lastname('Delft'),
    ]);

    expect($name->getFirstname())->toBe('Frank');
    expect($name->getLastnamePrefix())->toBe('van');
    expect($name->getLastname(true))->toBe('Delft');
    expect($name->getLastname())->toBe('van Delft');
});
test('get given name should return given name in given order', function () {
    $parser = new Parser();
    $name = $parser->parse('Schuler, J. Peter M.');
    expect($name->getGivenName())->toBe('J. Peter M.');
});
test('get full name should return the full name in given order', function () {
    $parser = new Parser();
    $name = $parser->parse('Schuler, J. Peter M.');
    expect($name->getFullName())->toBe('J. Peter M. Schuler');
});
