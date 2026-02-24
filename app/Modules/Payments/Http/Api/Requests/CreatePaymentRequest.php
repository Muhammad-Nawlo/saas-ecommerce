<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePaymentRequest extends FormRequest
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
            'order_id' => ['required', 'uuid'],
            'amount_minor_units' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'provider' => ['required', 'string', 'in:stripe,manual,paypal'],
        ];
    }
}
