<?php

namespace App\Http\Controllers;

use App\Contracts\DeliveryFeeCalculator;
use App\Enums\OrderStatus;
use App\Http\Requests\AssignDriverRequest;
use App\Http\Requests\CreateOrderRequest;
use App\Http\Requests\ListOrdersRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Driver;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct(private readonly DeliveryFeeCalculator $feeCalculator) {}

    private const EAGER = [
        'customer:id,name,phone',
        'driver:id,name,phone',
    ];

    // GET /api/orders
    public function index(ListOrdersRequest $request): AnonymousResourceCollection
    {
        $query = Order::query()
            ->with([
                ...self::EAGER,
                'restaurant' => fn ($q) => $q->withoutGlobalScopes()
                                             ->select('id', 'name', 'address'),
            ])
            ->withCount('orderItems');

        if ($request->filled('status')) {
            $query->withStatus($request->status);
        }

        if ($request->filled('restaurant_id')) {
            $query->where('restaurant_id', $request->integer('restaurant_id'));
        }

        if ($request->filled('driver_id')) {
            $query->forDriver($request->integer('driver_id'));
        }

        if ($request->filled('date')) {
            $date = $request->date('date', 'Y-m-d');
            $query->where('created_at', '>=', $date->startOfDay()->copy())
                  ->where('created_at', '<',  $date->copy()->addDay()->startOfDay());
        }

        $perPage = $request->integer('per_page', 15);

        return OrderResource::collection(
            $query->orderByDesc('created_at')->paginate($perPage)
        );
    }

    // GET /api/orders/current
    public function current(): AnonymousResourceCollection
    {
        $orders = Order::active()
            ->with([
                ...self::EAGER,
                'restaurant' => fn ($q) => $q->withoutGlobalScopes()
                                             ->select('id', 'name', 'address'),
            ])
            ->withCount('orderItems')
            ->orderByDesc('created_at')
            ->paginate(15);

        return OrderResource::collection($orders);
    }

    // GET /api/orders/{order}
    public function show(Order $order): JsonResponse
    {
        $order->load([
            'customer:id,name,phone',
            'driver:id,name,phone',
            'restaurant' => fn ($q) => $q->withoutGlobalScopes()
                                         ->select('id', 'name', 'address'),
            'orderItems.product:id,name,price',
        ]);

        $order->loadCount('orderItems');

        return response()->json(['data' => new OrderResource($order)]);
    }

    // POST /api/orders
    public function store(CreateOrderRequest $request): JsonResponse
    {
 
        $restaurant = Restaurant::find($request->integer('restaurant_id'));

        if (! $restaurant) {
            return response()->json([
                'message' => 'The selected restaurant does not exist or is not active.',
                'errors'  => ['restaurant_id' => ['The selected restaurant does not exist or is not active.']],
            ], 422);
        }

        // Deduplicate items by product_id, summing quantities for duplicates.
        $itemsInput = collect($request->input('items'))
            ->groupBy('product_id')
            ->map(fn ($group, $productId) => [
                'product_id' => (int) $productId,
                'quantity'   => $group->sum('quantity'),
            ])
            ->values();

   
        $quantityErrors = [];
        foreach ($itemsInput as $item) {
            if ($item['quantity'] > 100) {
                $quantityErrors["items.{$item['product_id']}"][] =
                    "The total quantity for product {$item['product_id']} cannot exceed 100.";
            }
        }
        if (! empty($quantityErrors)) {
            return response()->json(['message' => 'The given data was invalid.', 'errors' => $quantityErrors], 422);
        }

        // Load all requested products in one query and verify each one.
        $productIds = $itemsInput->pluck('product_id')->all();
        $products   = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $errors = [];

        foreach ($itemsInput as $item) {
            $product = $products->get($item['product_id']);

            if (! $product) {
                $errors["items.{$item['product_id']}"][] = "Product {$item['product_id']} does not exist.";
                continue;
            }

            if (! $product->is_available) {
                $errors["items.{$item['product_id']}"][] = "Product \"{$product->name}\" is not available.";
            }

            if ($product->restaurant_id !== $restaurant->id) {
                $errors["items.{$item['product_id']}"][] = "Product \"{$product->name}\" does not belong to the selected restaurant.";
            }
        }

        if (! empty($errors)) {
            return response()->json(['message' => 'The given data was invalid.', 'errors' => $errors], 422);
        }

        $deliveryFee = $this->feeCalculator->calculate();

        $order = DB::transaction(function () use ($request, $restaurant, $itemsInput, $products, $deliveryFee) {
            $subtotal = 0.0;

            $order = Order::create([
                'customer_id'      => $request->integer('customer_id'),
                'restaurant_id'    => $restaurant->id,
                'driver_id'        => null,
                'status'           => OrderStatus::Pending,
                'delivery_address' => $request->input('delivery_address'),
                'delivery_fee'     => $deliveryFee,
                'total_amount'     => 0,  // updated below after items are inserted
            ]);

            foreach ($itemsInput as $item) {
                $product   = $products->get($item['product_id']);
                $unitPrice = round((float) $product->price, 2);
                $qty       = $item['quantity'];

                $order->orderItems()->create([
                    'product_id' => $product->id,
                    'quantity'   => $qty,
                    'unit_price' => $unitPrice,
                ]);

                $subtotal += $unitPrice * $qty;
            }

            $order->total_amount = round($subtotal + $deliveryFee, 2);
            $order->save();

            return $order;
        });

        $order->load([
            'customer:id,name,phone',
            'restaurant:id,name,address',
            'driver:id,name,phone',
            'orderItems.product:id,name,price',
        ]);
        $order->loadCount('orderItems');

        return response()->json(['data' => new OrderResource($order)], 201);
    }

    // PATCH /api/orders/{order}/status
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $allowed = [
            OrderStatus::Pending->value   => [OrderStatus::Accepted->value,  OrderStatus::Cancelled->value],
            OrderStatus::Accepted->value  => [OrderStatus::PickedUp->value,  OrderStatus::Cancelled->value],
            OrderStatus::PickedUp->value  => [OrderStatus::Delivered->value],
            OrderStatus::Delivered->value => [],
            OrderStatus::Cancelled->value => [],
        ];

        $current = $order->status->value;
        $next    = $request->input('status');

        if (! in_array($next, $allowed[$current], true)) {
            return response()->json([
                'message' => 'Invalid status transition.',
                'errors'  => [
                    'status' => ["Cannot transition from \"{$current}\" to \"{$next}\"."],
                ],
            ], 422);
        }

        $order->status = OrderStatus::from($next);
        $order->save();

        $order->load([
            'customer:id,name,phone',
            'driver:id,name,phone',
            'restaurant' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'name', 'address'),
            'orderItems.product:id,name,price',
        ]);
        $order->loadCount('orderItems');

        return response()->json(['data' => new OrderResource($order)]);
    }


    public function assignDriver(AssignDriverRequest $request, Order $order): JsonResponse
    {
        $assignable = [OrderStatus::Pending->value, OrderStatus::Accepted->value];

        if (! in_array($order->status->value, $assignable, true)) {
            return response()->json([
                'message' => 'Driver cannot be assigned to this order.',
                'errors'  => [
                    'order' => ["A driver can only be assigned to a pending or accepted order."],
                ],
            ], 422);
        }

        $driver = Driver::available()->find($request->integer('driver_id'));

        if (! $driver) {
            return response()->json([
                'message' => 'The selected driver is not available.',
                'errors'  => [
                    'driver_id' => ['The selected driver does not exist or is not active and available.'],
                ],
            ], 422);
        }

        $order->driver_id = $driver->id;
        $order->save();

        $order->load([
            'customer:id,name,phone',
            'driver:id,name,phone',
            'restaurant' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'name', 'address'),
            'orderItems.product:id,name,price',
        ]);
        $order->loadCount('orderItems');

        return response()->json(['data' => new OrderResource($order)]);
    }
}
