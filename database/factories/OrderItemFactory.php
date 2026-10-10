<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;


class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        return [
            'order_id'   => Order::factory(),
            'product_id' => Product::factory(),
            'quantity'   => fake()->numberBetween(1, 5),
            // unit_price defaults to a plausible range.
            // forOrder() overrides this with the product's real price.
            'unit_price' => fake()->randomFloat(2, 5, 80),
        ];
    }


    public function forOrder(Order $order): static
    {
        return $this->state(function () use ($order) {
           
            $restaurant = $order->restaurant()->withoutGlobalScopes()->first();

         
            $product = Product::where('restaurant_id', $restaurant->id)
                ->inRandomOrder()
                ->first()
                ?? Product::factory()->create(['restaurant_id' => $restaurant->id]);

            return [
                'order_id'   => $order->id,
                'product_id' => $product->id,
                'unit_price' => round((float) $product->price, 2),
            ];
        });
    }
}
