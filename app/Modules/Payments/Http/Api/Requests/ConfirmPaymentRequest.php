<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmPaymentRequest extends FormRequest
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
            'provider_payment_id' => ['nullable', 'string'],
        ];
    }
}
