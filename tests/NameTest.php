<?php

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