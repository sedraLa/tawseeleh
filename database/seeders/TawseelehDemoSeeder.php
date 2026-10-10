<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TawseelehDemoSeeder extends Seeder
{
    
    private const CUSTOMERS    = 30;
    private const RESTAURANTS  = 10;   // 8 active, 2 inactive
    private const DRIVERS      = 15;   // 10 active+available, 3 offDuty, 2 inactive
    private const PRODUCTS     = 100;
    private const ORDERS       = 3000;
    private const ITEMS_MIN    = 1;
    private const ITEMS_MAX    = 5;
    private const CHUNK        = 500;

    
    public function run(): void
    {
      
        $conn   = config('database.default');
        $dbName = DB::selectOne('SELECT DATABASE() AS d')->d;

        if ($conn !== 'mysql' || $dbName !== 'tawseeleh') {
            $this->command->error(
                "Aborted: expected mysql/tawseeleh, got {$conn}/{$dbName}."
            );
            return;
        }

        
        $businessTables = [
            'customers', 'restaurants', 'drivers',
            'products', 'orders', 'order_items',
        ];
        $nonEmpty = [];
        foreach ($businessTables as $table) {
            $count = DB::table($table)->count();
            if ($count > 0) {
                $nonEmpty[$table] = $count;
            }
        }

        if (! empty($nonEmpty)) {
            $lines = implode(', ', array_map(
                fn($t, $c) => "{$t}: {$c}",
                array_keys($nonEmpty),
                array_values($nonEmpty)
            ));
            $this->command->warn(
                "Seeder aborted: the following business tables are not empty — {$lines}. " .
                'No data was inserted. Remove the existing data manually if you intend to re-seed.'
            );
            return;
        }


        DB::transaction(function () {
            $this->command->info('[1/5] Restaurants, customers, drivers…');
            $this->seedRestaurants();
            $this->seedCustomers();
            $this->seedDrivers();

            $this->command->info('[2/5] Products…');
            $this->seedProducts();

            $this->command->info('[3/5] Orders…');
            $orderTotals = $this->seedOrders();

            $this->command->info('[4/5] Back-filling order totals…');
            $this->backfillTotals($orderTotals);
        });

        $this->command->info('[5/5] Done.');
    }


    private function seedRestaurants(): void
    {
        $now   = now();
        $names = [
            'Al-Safa Kitchen','Burger Town','Noodle House','Pizza Corner',
            'The Grill Spot','Green Garden','Sunrise Cafe','Desert Bistro',
            'Sea Palace','Urban Express',
        ];
        $addresses = [
            'King Fahad Rd, Riyadh','Olaya St, Riyadh','Tahlia St, Jeddah',
            'Prince Sultan Rd, Jeddah','Al Corniche, Dammam','Al Khobar Main St',
            'Al Nakheel Blvd, Riyadh','Andalus St, Makkah','Al Balad, Jeddah',
            'Al Hamra District, Riyadh',
        ];

        $rows = [];
        for ($i = 0; $i < self::RESTAURANTS; $i++) {
            $rows[] = [
                'name'       => $names[$i],
                'address'    => $addresses[$i],
                'is_active'  => $i < 8 ? 1 : 0,   // last 2 are inactive
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('restaurants')->insert($rows);
    }


    private function seedCustomers(): void
    {
        $now  = now();
        $rows = [];
        for ($i = 0; $i < self::CUSTOMERS; $i++) {
            $rows[] = [
                'name'       => fake()->name(),
                'phone'      => '05' . fake()->numerify('########'),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('customers')->insert($rows);
    }


    private function seedDrivers(): void
    {
        $now  = now();
        $rows = [];
        for ($i = 0; $i < self::DRIVERS; $i++) {
            if ($i < 10) {
                $isActive = 1; $isAvailable = 1;
            } elseif ($i < 13) {
                $isActive = 1; $isAvailable = 0;
            } else {
                $isActive = 0; $isAvailable = 0;
            }
            $rows[] = [
                'name'         => fake()->name(),
                'phone'        => '05' . fake()->numerify('########'),
                'is_active'    => $isActive,
                'is_available' => $isAvailable,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        }
        DB::table('drivers')->insert($rows);
    }

  
    private function seedProducts(): void
    {
        $now           = now();
        $restaurantIds = DB::table('restaurants')->pluck('id')->toArray();
        $rCount        = count($restaurantIds);
        $foodItems     = $this->foodItems();
        $fCount        = count($foodItems);

        $rows = [];
        for ($i = 0; $i < self::PRODUCTS; $i++) {
            $rows[] = [
                'restaurant_id' => $restaurantIds[$i % $rCount],
                'name'          => $foodItems[$i % $fCount],
                'price'         => round(fake()->randomFloat(2, 5, 80), 2),
                'is_available'  => ($i % 7 !== 0) ? 1 : 0,
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table('products')->insert($chunk);
        }
    }


    private function seedOrders(): array
    {
        $now = now();

 
        $customerIds   = DB::table('customers')->pluck('id')->toArray();
        $restaurantIds = DB::table('restaurants')->pluck('id')->toArray();
        $driverIds     = DB::table('drivers')->pluck('id')->toArray();

    
        $productsByRestaurant = [];
        foreach (DB::table('products')->select('id', 'restaurant_id', 'price')->get() as $p) {
            $productsByRestaurant[$p->restaurant_id][] = ['id' => $p->id, 'price' => (float) $p->price];
        }
        $allProducts = DB::table('products')->select('id', 'price')->get()
            ->map(fn($p) => ['id' => $p->id, 'price' => (float) $p->price])
            ->toArray();

        $statusPool = array_merge(
            array_fill(0, 15, OrderStatus::Pending->value),
            array_fill(0, 15, OrderStatus::Accepted->value),
            array_fill(0, 15, OrderStatus::PickedUp->value),
            array_fill(0, 45, OrderStatus::Delivered->value),
            array_fill(0, 10, OrderStatus::Cancelled->value)
        );
        $poolSize = count($statusPool);

        $activeStatuses = [
            OrderStatus::Accepted->value,
            OrderStatus::PickedUp->value,
            OrderStatus::Delivered->value,
        ];

        $cities = ['Riyadh','Jeddah','Dammam','Makkah','Madinah','Khobar','Taif','Tabuk'];

        $orderRows = [];

        for ($i = 0; $i < self::ORDERS; $i++) {
            $status       = $statusPool[$i % $poolSize];
            $restaurantId = $restaurantIds[$i % count($restaurantIds)];
            $customerId   = $customerIds[$i % count($customerIds)];
            $deliveryFee  = round(fake()->randomFloat(2, 2, 15), 2);

            $driverId = null;
            if (in_array($status, $activeStatuses)) {
                $driverId = $driverIds[$i % count($driverIds)];
            }

            
            $createdAt = ($i % 10 === 0)
                ? $now->copy()->startOfDay()->addHours(fake()->numberBetween(0, 23))->addMinutes(fake()->numberBetween(0, 59))
                : $now->copy()->subDays(fake()->numberBetween(1, 60))->subHours(fake()->numberBetween(0, 23));

            $orderRows[] = [
                'customer_id'      => $customerId,
                'restaurant_id'    => $restaurantId,
                'driver_id'        => $driverId,
                'status'           => $status,
                'delivery_address' => fake()->streetAddress() . ', ' . fake()->randomElement($cities),
                'delivery_fee'     => $deliveryFee,
                'total_amount'     => 0.00,
                'created_at'       => $createdAt,
                'updated_at'       => $createdAt,
            ];
        }

     
        foreach (array_chunk($orderRows, self::CHUNK) as $chunk) {
            DB::table('orders')->insert($chunk);
        }

        
        $inserted = DB::table('orders')
            ->select('id', 'restaurant_id', 'delivery_fee')
            ->orderBy('id')
            ->get()
            ->toArray();

        $itemRows   = [];
        $orderTotals = [];

        foreach ($inserted as $idx => $order) {
            $pool = $productsByRestaurant[$order->restaurant_id] ?? $allProducts;
            $itemCount = rand(self::ITEMS_MIN, self::ITEMS_MAX);
            $itemCount = min($itemCount, count($pool));

          
            $pickedKeys = (array) array_rand($pool, $itemCount);
            $subtotal   = 0.0;

            foreach ($pickedKeys as $key) {
                $product   = $pool[$key];
                $qty       = rand(1, 5);
                $unitPrice = round($product['price'], 2);
                $subtotal += $unitPrice * $qty;

                $itemRows[] = [
                    'order_id'   => $order->id,
                    'product_id' => $product['id'],
                    'quantity'   => $qty,
                    'unit_price' => $unitPrice,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $orderTotals[$order->id] = round($subtotal + (float) $order->delivery_fee, 2);
        }

        foreach (array_chunk($itemRows, self::CHUNK) as $chunk) {
            DB::table('order_items')->insert($chunk);
        }

        return $orderTotals;
    }

    
    private function backfillTotals(array $totals): void
    {
        foreach (array_chunk($totals, self::CHUNK, true) as $chunk) {
           
            $cases  = '';
            $ids    = [];
            $bindings = [];

            foreach ($chunk as $id => $amount) {
                $cases      .= "WHEN ? THEN ? ";
                $bindings[]  = $id;
                $bindings[]  = $amount;
                $ids[]       = $id;
            }

            $inPlaceholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "UPDATE orders SET total_amount = CASE id {$cases}END WHERE id IN ({$inPlaceholders})";

            DB::statement($sql, array_merge($bindings, $ids));
        }
    }

    private function foodItems(): array
    {
        return [
            'Cheese Burger','Double Smash Burger','Crispy Chicken Sandwich',
            'Spicy Chicken Burger','Beef Shawarma','Chicken Shawarma',
            'Margherita Pizza','BBQ Chicken Pizza','Pepperoni Pizza',
            'Four Cheese Pizza','Falafel Wrap','Veggie Wrap',
            'Grilled Salmon','Grilled Sea Bass','Lamb Chops',
            'Mixed Grill Platter','Caesar Salad','Greek Salad',
            'Fattoush Salad','Hummus Plate','Baba Ghanoush',
            'Chicken Wings','Onion Rings','French Fries',
            'Sweet Potato Fries','Mozzarella Sticks','Garlic Bread',
            'Coleslaw','Corn on the Cob','Pasta Bolognese',
            'Pasta Carbonara','Penne Arrabiata','Grilled Chicken Breast',
            'Chicken Biryani','Lamb Biryani','Beef Kabab',
            'Chicken Tikka','Fish Tacos','Beef Tacos',
            'Club Sandwich','Tuna Melt','BLT Sandwich',
            'Philly Cheesesteak','Vegetable Soup','Lentil Soup',
            'Tomato Basil Soup','Chocolate Lava Cake','Kunafa',
            'Tiramisu','Crème Brûlée','Vanilla Milkshake',
            'Chocolate Milkshake','Strawberry Milkshake','Fresh Orange Juice',
            'Watermelon Juice','Lemonade','Iced Coffee',
            'Americano','Cappuccino','Mineral Water',
            'Veggie Burger','Mushroom Burger','Breakfast Platter',
            'Pancake Stack','Eggs Benedict','Avocado Toast',
            'Beef Steak','Lamb Rack','Shrimp Tempura',
            'Calamari Rings','Sushi Platter','Chicken Quesadilla',
            'Beef Quesadilla','Loaded Nachos','Buffalo Wings',
            'Chicken Caesar Wrap','Tuna Salad Wrap','Steak Sandwich',
            'Pulled Chicken Sandwich','BBQ Ribs','Lamb Ouzi',
            'Chicken Mandy','Beef Mandy','Macaroni Béchamel',
            'Stuffed Grape Leaves','Kibbeh','Samosa Plate',
            'Spring Rolls','Dim Sum Basket','Pad Thai',
            'Fried Rice','Noodle Soup','Ramen Bowl',
            'Pho Bowl','Burrito Bowl','Açaí Bowl',
            'Overnight Oats','Granola Parfait','Chocolate Brownie',
            'Cheesecake Slice','Baklava','Umm Ali','Date Pudding',
        ];
    }
}
