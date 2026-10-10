<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory;
    protected $fillable = [
        'customer_id',
        'restaurant_id',
        'driver_id',
        'status',
        'delivery_address',
        'delivery_fee',
        'total_amount',
    ];

    protected $casts = [
        // Cast the string column to the OrderStatus backed enum.
        'status'       => OrderStatus::class,
        'delivery_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    // -------------------------------------------------------------------------
    // Local Scopes
    // -------------------------------------------------------------------------

    /**
     * Scope: orders that are in-flight (not yet completed or cancelled).
     *
     * Includes: pending, accepted, picked_up.
     * Excludes: delivered, cancelled.
     *
     * Usage: Order::active()->get();
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', [
            OrderStatus::Pending->value,
            OrderStatus::Accepted->value,
            OrderStatus::PickedUp->value,
        ]);
    }

    /**
     * Scope: orders assigned to a specific driver.
     *
     * Usage: Order::forDriver($driverId)->get();
     *        Order::active()->forDriver($driverId)->get();
     */
    public function scopeForDriver(Builder $query, int $driverId): void
    {
        $query->where('driver_id', $driverId);
    }


    public function scopeToday(Builder $query): void
    {
        $query->where('created_at', '>=', today()->startOfDay())
              ->where('created_at', '<',  today()->addDay()->startOfDay());
    }


    public function scopeOlderThanMinutes(Builder $query, int $minutes): void
    {
        $query->where('created_at', '<', now()->subMinutes($minutes));
    }


    public function scopeWithStatus(Builder $query, OrderStatus|string $status): void
    {
        $value = $status instanceof OrderStatus ? $status->value : $status;

        $query->where('status', $value);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }


    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }


    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
