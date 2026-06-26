<?php

namespace CanyonGBS\Common\Tests\Rector\UseFakerPropertyInFactoryRector\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;

class ConvertsFakeInStateClosureFactory extends Factory
{
    public function definition(): array
    {
        return [];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => fake()->sentence(),
        ]);
    }
}

?>
-----
<?php

namespace CanyonGBS\Common\Tests\Rector\UseFakerPropertyInFactoryRector\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;

class ConvertsFakeInStateClosureFactory extends Factory
{
    public function definition(): array
    {
        return [];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => $this->faker->sentence(),
        ]);
    }
}

?>
