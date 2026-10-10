<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    // ── helpers ──────────────────────────────────────────────────────────────

    private function makeOrder(array $overrides = []): Order
    {
        $restaurant = Restaurant::factory()->create();
        $customer   = Customer::factory()->create();

        $order = Order::factory()->create(array_merge([
            'restaurant_id' => $restaurant->id,
            'customer_id'   => $customer->id,
        ], $overrides));

        return $order;
    }

    // ── route registration ────────────────────────────────────────────────────

    public function test_routes_are_registered(): void
    {
        $this->makeOrder();

        $this->getJson('/api/orders')->assertStatus(200);
        $this->getJson('/api/orders/current')->assertStatus(200);
        $this->getJson('/api/orders/1')->assertStatus(200);
    }

    // ── GET /api/orders ───────────────────────────────────────────────────────

    public function test_index_returns_paginated_list(): void
    {
        $this->makeOrder();
        $this->makeOrder();

        $response = $this->getJson('/api/orders');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'status', 'delivery_address', 'delivery_fee',
                    'total_amount', 'created_at', 'order_items_count',
                    'customer', 'restaurant', 'driver']],
                'meta' => ['current_page', 'per_page', 'total'],
                'links',
            ]);
    }

    public function test_index_does_not_include_order_items_detail(): void
    {
        $this->makeOrder();

        $response = $this->getJson('/api/orders');

        // order_items_count should be present; order_items array should not
        $response->assertStatus(200)
            ->assertJsonMissing(['order_items' => []]);

        $data = $response->json('data.0');
        $this->assertArrayHasKey('order_items_count', $data);
        $this->assertArrayNotHasKey('order_items', $data);
    }

    public function test_index_filter_by_status(): void
    {
        $this->makeOrder(['status' => OrderStatus::Pending]);
        $this->makeOrder(['status' => OrderStatus::Delivered]);

        $response = $this->getJson('/api/orders?status=pending');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('pending', $data[0]['status']);
    }

    public function test_index_filter_by_restaurant_id(): void
    {
        $r1 = Restaurant::factory()->create();
        $r2 = Restaurant::factory()->create();
        $c  = Customer::factory()->create();

        Order::factory()->create(['restaurant_id' => $r1->id, 'customer_id' => $c->id]);
        Order::factory()->create(['restaurant_id' => $r2->id, 'customer_id' => $c->id]);

        $response = $this->getJson("/api/orders?restaurant_id={$r1->id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_filter_by_driver_id(): void
    {
        $driver = Driver::factory()->create();
        $this->makeOrder(['driver_id' => $driver->id, 'status' => OrderStatus::Accepted]);
        $this->makeOrder(['driver_id' => null,        'status' => OrderStatus::Pending]);

        $response = $this->getJson("/api/orders?driver_id={$driver->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
    }

    public function test_index_filter_by_date(): void
    {
        // Create one order backdated to yesterday
        $restaurant = Restaurant::factory()->create();
        $customer   = Customer::factory()->create();

        $yesterday = now()->subDay()->format('Y-m-d');
        Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'customer_id'   => $customer->id,
            'created_at'    => now()->subDay(),
        ]);

        $today = now()->format('Y-m-d');
        $this->makeOrder(); // today

        $response = $this->getJson("/api/orders?date={$yesterday}");
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_combined_filters(): void
    {
        $driver = Driver::factory()->create();
        $r      = Restaurant::factory()->create();
        $c      = Customer::factory()->create();

        Order::factory()->create([
            'restaurant_id' => $r->id,
            'customer_id'   => $c->id,
            'driver_id'     => $driver->id,
            'status'        => OrderStatus::Accepted,
        ]);

        // Different restaurant — should not appear
        $this->makeOrder(['status' => OrderStatus::Accepted]);

        $response = $this->getJson(
            "/api/orders?restaurant_id={$r->id}&status=accepted"
        );

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_per_page_limit(): void
    {
        foreach (range(1, 5) as $i) {
            $this->makeOrder();
        }

        $response = $this->getJson('/api/orders?per_page=2');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
        $this->assertSame(2, $response->json('meta.per_page'));
        $this->assertSame(5, $response->json('meta.total'));
    }

    // ── validation (422) ─────────────────────────────────────────────────────

    public function test_invalid_status_returns_422(): void
    {
        $this->getJson('/api/orders?status=flying')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_invalid_date_returns_422(): void
    {
        $this->getJson('/api/orders?date=not-a-date')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    public function test_invalid_per_page_returns_422(): void
    {
        $this->getJson('/api/orders?per_page=0')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);

        $this->getJson('/api/orders?per_page=200')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_invalid_restaurant_id_returns_422(): void
    {
        $this->getJson('/api/orders?restaurant_id=abc')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['restaurant_id']);
    }

    // ── GET /api/orders/current ───────────────────────────────────────────────

    public function test_current_returns_only_active_statuses(): void
    {
        $this->makeOrder(['status' => OrderStatus::Pending]);
        $this->makeOrder(['status' => OrderStatus::Accepted]);
        $this->makeOrder(['status' => OrderStatus::PickedUp]);
        $this->makeOrder(['status' => OrderStatus::Delivered]);
        $this->makeOrder(['status' => OrderStatus::Cancelled]);

        $response = $this->getJson('/api/orders/current');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(3, $data);

        $statuses = array_column($data, 'status');
        $this->assertContains('pending',   $statuses);
        $this->assertContains('accepted',  $statuses);
        $this->assertContains('picked_up', $statuses);
        $this->assertNotContains('delivered', $statuses);
        $this->assertNotContains('cancelled', $statuses);
    }

    // ── GET /api/orders/{order} ───────────────────────────────────────────────

    public function test_show_returns_order_with_full_relations(): void
    {
        $order = $this->makeOrder();

        $response = $this->getJson("/api/orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id', 'status', 'delivery_address', 'delivery_fee',
                    'total_amount', 'created_at', 'order_items_count',
                    'customer' => ['id', 'name', 'phone'],
                    'restaurant' => ['id', 'name', 'address'],
                    'driver',
                    'order_items',
                ],
            ]);
    }

    public function test_show_includes_order_items_with_product(): void
    {
        $restaurant = Restaurant::factory()->create();
        $customer   = Customer::factory()->create();
        $product    = Product::factory()->create(['restaurant_id' => $restaurant->id]);

        $order = Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'customer_id'   => $customer->id,
        ]);

        OrderItem::factory()->forOrder($order)->create();

        $response = $this->getJson("/api/orders/{$order->id}");

        $response->assertStatus(200);
        $items = $response->json('data.order_items');
        $this->assertNotEmpty($items);
        $this->assertArrayHasKey('product', $items[0]);
        $this->assertArrayHasKey('unit_price', $items[0]);
        $this->assertArrayHasKey('quantity', $items[0]);
    }

    public function test_show_driver_is_null_when_unassigned(): void
    {
        $order = $this->makeOrder(['driver_id' => null, 'status' => OrderStatus::Pending]);

        $response = $this->getJson("/api/orders/{$order->id}");

        $response->assertStatus(200);
        $this->assertNull($response->json('data.driver'));
    }

    public function test_show_returns_404_for_missing_order(): void
    {
        $this->getJson('/api/orders/99999')->assertStatus(404);
    }

    // ── order_items_count accuracy ────────────────────────────────────────────

    public function test_order_items_count_matches_actual_items(): void
    {
        $restaurant = Restaurant::factory()->create();
        $customer   = Customer::factory()->create();

        $order = Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'customer_id'   => $customer->id,
        ]);

        // Create exactly 3 items manually to have a known count
        OrderItem::factory()->count(3)->forOrder($order)->create();

        $response = $this->getJson("/api/orders/{$order->id}");
        $response->assertStatus(200);

        // The afterCreating hook in OrderFactory already created items,
        // so total count = those + our 3. We just verify the count key
        // matches what's actually in the DB.
        $expected = $order->orderItems()->count();
        $this->assertSame($expected, $response->json('data.order_items_count'));
    }

    // ── N+1 detection ─────────────────────────────────────────────────────────

    public function test_index_does_not_trigger_lazy_loading(): void
    {
        // With preventLazyLoading enabled in AppServiceProvider, any N+1
        // access would throw. Creating multiple orders and listing them
        // without error confirms eager loading is working.
        foreach (range(1, 5) as $i) {
            $this->makeOrder();
        }

        // This will throw LazyLoadingViolationException if any relationship
        // is accessed lazily during serialization.
        $this->getJson('/api/orders')->assertStatus(200);
    }
}
