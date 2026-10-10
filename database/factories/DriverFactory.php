<?php

namespace Database\Factories;

use App\Models\Driver;
use Illuminate\Database\Eloquent\Factories\Factory;


class DriverFactory extends Factory
{
    protected $model = Driver::class;

    public function definition(): array
    {
        return [
            'name'         => fake()->name(),
            'phone'        => fake()->numerify('05########'),
            'is_active'    => true,
            'is_available' => true,
        ];
    }


    public function inactive(): static
    {
        return $this->state([
            'is_active'    => false,
            'is_available' => false,
        ]);
    }

    public function offDuty(): static
    {
        return $this->state([
            'is_active'    => true,
            'is_available' => false,
        ]);
    }
}
