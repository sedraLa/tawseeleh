<?php

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'        => ['sometimes', Rule::enum(OrderStatus::class)],
            'restaurant_id' => ['sometimes', 'integer', 'min:1'],
            'driver_id'     => ['sometimes', 'integer', 'min:1'],
            'date'          => ['sometimes', 'date_format:Y-m-d'],
            'per_page'      => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
