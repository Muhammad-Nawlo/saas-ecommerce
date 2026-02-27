<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Gateways;

use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Shared\Domain\ValueObjects\Money;
use Illuminate\Support\Facades\Config;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Stripe;

/**
 * StripePaymentGateway
 *
 * PaymentGateway implementation for Stripe: create PaymentIntent, confirm (retrieve and validate status),
 * refund. Uses Money value object (minor units); no float. External API calls only; no DB writes in this class.
 * Used by PaymentService via PaymentGatewayResolver. Api key from config('services.stripe.secret').
 *
 * Side effects: Stripe API (create, retrieve, refund). Tenant context not required for Stripe; payment record is tenant-scoped in caller.
 */
final class StripePaymentGateway implements PaymentGateway
{
    public function __construct()
    {
        $secret = Config::get('services.stripe.secret');
        if ($secret !== null && $secret !== '') {
            Stripe::setApiKey($secret);
        }
    }

    /**
     * @param array<string, string> $metadata
     * @return array{client_secret: string, provider_payment_id: string}
     */
    public function createPaymentIntent(Money $amount, array $metadata): array
    {
        $params = [
            'amount' => $amount->amountInMinorUnits(),
            'currency' => strtolower($amount->currency()),
            'metadata' => $metadata,
            'automatic_payment_methods' => ['enabled' => true],
        ];
        $intent = PaymentIntent::create($params);
        return [
            'client_secret' => (string) $intent->client_secret,
            'provider_payment_id' => (string) $intent->id,
        ];
    }

    public function confirmPayment(string $providerPaymentId): void
    {
        $intent = PaymentIntent::retrieve($providerPaymentId);
        if ($intent->status !== 'succeeded' && $intent->status !== 'requires_capture') {
            throw new \RuntimeException(
                sprintf('Payment intent %s is not in a confirmable state: %s', $providerPaymentId, $intent->status)
            );
        }
    }

    public function refund(string $providerPaymentId): void
    {
        Refund::create([
            'payment_intent' => $providerPaymentId,
        ]);
    }
}
