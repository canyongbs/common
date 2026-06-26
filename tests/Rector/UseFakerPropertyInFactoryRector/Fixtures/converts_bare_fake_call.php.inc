<?php

namespace CanyonGBS\Common\Tests\Rector\UseFakerPropertyInFactoryRector\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;

class ConvertsBareFakeCallFactory extends Factory
{
    public function definition(): array
    {
        return [
            'faker' => fake(),
        ];
    }
}

?>
-----
<?php

namespace CanyonGBS\Common\Tests\Rector\UseFakerPropertyInFactoryRector\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;

class ConvertsBareFakeCallFactory extends Factory
{
    public function definition(): array
    {
        return [
            'faker' => $this->faker,
        ];
    }
}

?>
