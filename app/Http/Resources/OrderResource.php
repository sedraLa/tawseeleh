<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'status'           => $this->status->value,
            'delivery_address' => $this->delivery_address,
            'delivery_fee'     => $this->delivery_fee,
            'total_amount'     => $this->total_amount,
            'created_at'       => $this->created_at,
            'order_items_count'=> $this->order_items_count,
            'customer'         => new CustomerResource($this->customer),
            'restaurant'       => new RestaurantResource($this->restaurant),
            'driver'           => $this->driver ? new DriverResource($this->driver) : null,
            'order_items'      => $this->whenLoaded('orderItems', fn () =>
                OrderItemResource::collection($this->orderItems)
            ),
        ];
    }
}
