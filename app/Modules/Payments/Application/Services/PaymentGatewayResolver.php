<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Services;

use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\ValueObjects\PaymentProvider;
use App\Modules\Shared\Domain\ValueObjects\Money;

interface PaymentGatewayResolver
{
    public function resolve(PaymentProvider $provider): PaymentGateway;
}
