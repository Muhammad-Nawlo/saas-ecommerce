<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer;

use App\Models\Customer\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CustomerRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = (string) tenant('id');
        return [
            'email' => [
                'required',
                'email',
                'max:255',
                function (string $attribute, string $value, \Closure $fail) use ($tenantId): void {
                    if ($tenantId !== '' && Customer::forTenant($tenantId)->where('email', strtolower($value))->exists()) {
                        $fail(__('validation.unique', ['attribute' => 'email']));
                    }
                },
            ],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ];
    }
}
