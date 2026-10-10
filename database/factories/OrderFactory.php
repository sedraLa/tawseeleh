<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;


class OrderFactory extends Factory
{
    protected $model = Order::class;


    public function configure(): static
    {
        return $this->afterCreating(function (Order $order): void {
            $itemCount = fake()->numberBetween(1, 3);

         
            OrderItem::factory()
                ->count($itemCount)
                ->forOrder($order)
                ->create();

           
            $subtotal = $order->orderItems()
                ->sum(DB::raw('unit_price * quantity'));

            $order->total_amount = round(
                (float) $subtotal + (float) $order->delivery_fee,
                2
            );
            $order->save();
        });
    }

    public function definition(): array
    {
        $status = fake()->randomElement(OrderStatus::cases());

     
        $needsDriver = ! in_array($status, [OrderStatus::Pending, OrderStatus::Cancelled]);
        $driverRate  = $needsDriver ? 1.0 : 0.3;
        $hasDriver   = fake()->boolean((int) ($driverRate * 100));

        return [
            'customer_id'      => Customer::factory(),
            'restaurant_id'    => Restaurant::factory(),
            'driver_id'        => $hasDriver ? Driver::factory() : null,
            'status'           => $status,
            'delivery_address' => fake()->streetAddress() . ', ' . fake()->city(),
            'delivery_fee'     => round(fake()->randomFloat(2, 2, 15), 2),
            // Initial placeholder — overwritten by the afterCreating callback
            // before the caller ever sees the model.
            'total_amount'     => 0.00,
        ];
    }


    public function pending(): static
    {
        return $this->state(['status' => OrderStatus::Pending, 'driver_id' => null]);
    }

    public function accepted(): static
    {
        return $this->state(['status' => OrderStatus::Accepted]);
    }

    public function pickedUp(): static
    {
        return $this->state(['status' => OrderStatus::PickedUp]);
    }

    public function delivered(): static
    {
        return $this->state(['status' => OrderStatus::Delivered]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => OrderStatus::Cancelled, 'driver_id' => null]);
    }
}
