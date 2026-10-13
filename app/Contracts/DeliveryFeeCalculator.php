<?php

namespace App\Contracts;

interface DeliveryFeeCalculator
{
    public function calculate(): float;
}
