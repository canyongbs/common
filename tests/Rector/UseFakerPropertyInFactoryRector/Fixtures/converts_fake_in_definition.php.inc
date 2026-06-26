<?php

namespace CanyonGBS\Common\Tests\Rector\UseFakerPropertyInFactoryRector\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;

class ConvertsFakeInDefinitionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
        ];
    }
}

?>
-----
<?php

namespace CanyonGBS\Common\Tests\Rector\UseFakerPropertyInFactoryRector\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;

class ConvertsFakeInDefinitionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
        ];
    }
}

?>
