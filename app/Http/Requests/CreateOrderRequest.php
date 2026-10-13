<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id'      => ['required', 'integer', 'min:1', 'exists:customers,id'],
            'restaurant_id'    => ['required', 'integer', 'min:1'],
            'delivery_address' => ['required', 'string', 'max:500'],

            // delivery_fee is accepted from the client at this stage because no
            // server-side pricing rule exists yet. Range 0–100 is a temporary
            // validation limit, not a business pricing rule.
            'delivery_fee'     => ['required', 'numeric', 'min:0', 'max:100'],

            'items'            => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity'   => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
