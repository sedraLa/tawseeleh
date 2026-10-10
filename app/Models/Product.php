<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{

    use HasFactory;
    protected $fillable = [
        'restaurant_id',
        'name',
        'price',
        'is_available',
    ];

    protected $casts = [
        'price'        => 'decimal:2',
        'is_available' => 'boolean',
    ];


    public function scopeAvailable(Builder $query): void
    {
        $query->where('is_available', true);
    }


    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }


    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
