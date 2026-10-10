<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    private static array $foodItems = [
        'Cheese Burger', 'Crispy Chicken Sandwich', 'Beef Shawarma', 'Chicken Shawarma',
        'Margherita Pizza', 'BBQ Pizza', 'Pepperoni Pizza', 'Falafel Wrap',
        'Grilled Salmon', 'Lamb Chops', 'Caesar Salad', 'Greek Salad',
        'Hummus Plate', 'Mixed Grill', 'Chicken Wings', 'Onion Rings',
        'French Fries', 'Mozzarella Sticks', 'Garlic Bread', 'Coleslaw',
        'Vanilla Milkshake', 'Chocolate Lava Cake', 'Kunafa', 'Tiramisu',
        'Fresh Orange Juice', 'Lemonade', 'Iced Coffee', 'Mineral Water',
        'Pasta Bolognese', 'Grilled Chicken Breast', 'Veggie Burger', 'Fish Tacos',
        'Beef Tacos', 'Club Sandwich', 'Tuna Melt', 'BLT Sandwich',
        'Chicken Biryani', 'Lamb Biryani', 'Vegetable Soup', 'Lentil Soup',
    ];

    public function definition(): array
    {
        return [
            // restaurant_id must be supplied by the caller or the seeder
            'restaurant_id' => Restaurant::factory(),
            'name'          => fake()->unique()->randomElement(self::$foodItems),
            'price'         => fake()->randomFloat(2, 5, 80),
            'is_available'  => true,
        ];
    }


    public function unavailable(): static
    {
        return $this->state(['is_available' => false]);
    }
}
