<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
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
            'customer_email' => ['required', 'email'],
        ];
    }
}
