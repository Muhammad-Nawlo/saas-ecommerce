<?php

declare(strict_types=1);

namespace App\Modules\Cart\Http\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'uuid'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_price_minor_units' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
        ];
    }
}
