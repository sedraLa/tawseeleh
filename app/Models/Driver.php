<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Driver extends Model
{
    /** @use HasFactory<\Database\Factories\DriverFactory> */
    use HasFactory;
    protected $fillable = [
        'name',
        'phone',
        'is_active',
        'is_available',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'is_available' => 'boolean',
    ];


    public function scopeAvailable(Builder $query): void
    {
        $query->where('is_active', true)
              ->where('is_available', true);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
