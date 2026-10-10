<?php

namespace App\Models;

use App\Models\Scopes\ActiveRestaurantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Restaurant extends Model
{
 
    use HasFactory;
    protected $fillable = [
        'name',
        'address',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];


    protected static function booted(): void
    {
        static::addGlobalScope(new ActiveRestaurantScope());
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }


    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
