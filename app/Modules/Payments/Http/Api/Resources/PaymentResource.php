<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Api\Resources;

use App\Modules\Payments\Application\DTOs\PaymentDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentDTO
 */
final class PaymentResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PaymentDTO $dto */
        $dto = $this->resource;
        $data = [
            'id' => $dto->id,
            'tenant_id' => $dto->tenantId,
            'order_id' => $dto->orderId,
            'amount' => [
                'minor_units' => $dto->amountMinorUnits,
                'currency' => $dto->currency,
            ],
            'status' => $dto->status,
            'provider' => $dto->provider,
            'provider_payment_id' => $dto->providerPaymentId,
            'created_at' => $dto->createdAt,
            'updated_at' => $dto->updatedAt,
        ];
        if (isset($this->additional['client_secret'])) {
            $data['client_secret'] = $this->additional['client_secret'];
        }
        return $data;
    }
}
