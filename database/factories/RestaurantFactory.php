<?php

namespace Database\Factories;

use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;


class RestaurantFactory extends Factory
{
    protected $model = Restaurant::class;

  
    private static array $types = [
        'Kitchen', 'Grill', 'Bistro', 'House', 'Corner',
        'Palace', 'Garden', 'Express', 'Cafe', 'Spot',
    ];

    public function definition(): array
    {
        $name = fake()->lastName() . ' ' . fake()->randomElement(self::$types);

        return [
            'name'      => $name,
            'address'   => fake()->streetAddress() . ', ' . fake()->city(),
            'is_active' => true,
        ];
    }


    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
