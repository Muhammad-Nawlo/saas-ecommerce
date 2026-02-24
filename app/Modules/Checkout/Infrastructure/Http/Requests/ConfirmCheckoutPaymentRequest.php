<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ConfirmCheckoutPaymentRequest extends FormRequest
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
            'payment_id' => ['required', 'string', 'uuid'],
            'provider_payment_id' => ['nullable', 'string'],
        ];
    }
}
