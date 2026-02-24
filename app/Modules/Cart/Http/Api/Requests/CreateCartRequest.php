<?php

declare(strict_types=1);

namespace App\Modules\Cart\Http\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCartRequest extends FormRequest
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
            'customer_email' => ['nullable', 'email', 'required_without:session_id'],
            'session_id' => ['nullable', 'string', 'required_without:customer_email'],
        ];
    }
}
