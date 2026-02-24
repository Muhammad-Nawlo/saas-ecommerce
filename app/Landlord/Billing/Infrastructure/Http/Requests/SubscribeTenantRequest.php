<?php

declare(strict_types=1);

namespace App\Landlord\Billing\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SubscribeTenantRequest extends FormRequest
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
            'tenant_id' => ['required', 'string', 'uuid'],
            'plan_id' => ['required', 'string', 'uuid'],
            'customer_email' => ['required', 'email'],
        ];
    }
}
