<?php

namespace CanyonGBS\Common\Tests\Rector\UseFakerPropertyInFactoryRector\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;

class ConvertsMultipleFakeCallsFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->safeEmail(),
        ];
    }
}

?>
-----
<?php

namespace CanyonGBS\Common\Tests\Rector\UseFakerPropertyInFactoryRector\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;

class ConvertsMultipleFakeCallsFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->safeEmail(),
        ];
    }
}

?>
