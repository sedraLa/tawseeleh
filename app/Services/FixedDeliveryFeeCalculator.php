<?php

namespace App\Services;

use App\Contracts\DeliveryFeeCalculator;

class FixedDeliveryFeeCalculator implements DeliveryFeeCalculator
{
    public function __construct(private readonly float $fee) {}

    public function calculate(): float
    {
        return $this->fee;
    }
}
