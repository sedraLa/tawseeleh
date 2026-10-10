<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListOrdersRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    // Relationships eager-loaded on every order query.
    // Restaurant uses withoutGlobalScopes so orders from inactive restaurants
    // still return their restaurant data instead of null.
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
}
