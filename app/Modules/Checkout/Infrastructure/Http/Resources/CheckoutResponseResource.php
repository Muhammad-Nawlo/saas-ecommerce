<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Infrastructure\Http\Resources;

use App\Modules\Checkout\Application\DTOs\CheckoutResponseDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CheckoutResponseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var CheckoutResponseDTO $dto */
        $dto = $this->resource;
        return [
            'order_id' => $dto->orderId,
            'payment_id' => $dto->paymentId,
            'client_secret' => $dto->clientSecret,
            'amount' => $dto->amount,
            'currency' => $dto->currency,
        ];
    }
}
