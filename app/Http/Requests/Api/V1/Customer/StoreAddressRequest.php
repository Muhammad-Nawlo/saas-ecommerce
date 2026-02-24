<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer;

use App\Models\Customer\CustomerAddress;
use Illuminate\Foundation\Http\FormRequest;

class StoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:' . CustomerAddress::TYPE_BILLING . ',' . CustomerAddress::TYPE_SHIPPING],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country_code' => ['required', 'string', 'size:2'],
            'is_default' => ['boolean'],
        ];
    }
}
