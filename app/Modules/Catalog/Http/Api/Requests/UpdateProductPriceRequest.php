<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductPriceRequest extends FormRequest
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
            'price_minor_units' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
        ];
    }
}
