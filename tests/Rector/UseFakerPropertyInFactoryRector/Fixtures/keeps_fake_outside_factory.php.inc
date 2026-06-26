<?php

namespace CanyonGBS\Common\Tests\Rector\UseFakerPropertyInFactoryRector\Fixtures;

class KeepsFakeOutsideFactory
{
    public function values(): array
    {
        return [
            'name' => fake()->name(),
        ];
    }
}
