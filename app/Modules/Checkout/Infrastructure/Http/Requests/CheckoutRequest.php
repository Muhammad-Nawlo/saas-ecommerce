<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CheckoutRequest extends FormRequest
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
            'cart_id' => ['required', 'string', 'uuid'],
            'payment_provider' => ['required', 'string', Rule::in(['stripe', 'manual', 'paypal'])],
            'customer_email' => ['required', 'email'],
        ];
    }
}
