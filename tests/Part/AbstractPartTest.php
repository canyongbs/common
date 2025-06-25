<?php

use CanyonGBS\Common\Parser\Part\AbstractPart;
test('normalize', function () {
     $part = $this->getMockBuilder(AbstractPart::class)
            ->setConstructorArgs(['abc'])
            ->onlyMethods([]) 
            ->getMock();
    expect($part->normalize())->toEqual('abc');
});

test('set value unwraps', function () {
     $part = $this->getMockBuilder(AbstractPart::class)
            ->setConstructorArgs(['abc'])
            ->onlyMethods([]) 
            ->getMock();
    expect($part->getValue())->toEqual('abc');

    $wrappedPart = $this->getMockBuilder(AbstractPart::class)
            ->setConstructorArgs([$part])
            ->onlyMethods([]) 
            ->getMock();
    expect($wrappedPart->getValue())->toEqual('abc');
});
