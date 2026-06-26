<?php

namespace CanyonGBS\Common\Tests\Rector\UseFakerPropertyInFactoryRector\Fixtures;

function keeps_fake_in_plain_function(): string
{
    return fake()->name();
}
