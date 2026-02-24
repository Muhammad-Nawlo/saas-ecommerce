<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IncreaseStockRequest extends FormRequest
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
            'amount' => ['required', 'integer', 'min:1'],
        ];
    }
}
