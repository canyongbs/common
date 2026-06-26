<?php

namespace CanyonGBS\Common\Tests\Rector\UseFakerPropertyInFactoryRector\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;

class KeepsFakeWithLocaleArgumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake('de_DE')->name(),
        ];
    }
}
