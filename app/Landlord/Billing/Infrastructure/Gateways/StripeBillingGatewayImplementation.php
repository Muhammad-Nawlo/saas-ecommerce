<?php

declare(strict_types=1);

namespace App\Landlord\Billing\Infrastructure\Gateways;

use App\Landlord\Billing\Domain\Contracts\StripeBillingGateway;
use Stripe\StripeClient;
use Stripe\Subscription;

final readonly class StripeBillingGatewayImplementation implements StripeBillingGateway
{
    public function __construct(
        private StripeClient $stripe
    ) {
    }

    public static function fromConfig(): self
    {
        $secret = config('services.stripe.secret');
        if ($secret === null || $secret === '') {
            throw new \InvalidArgumentException('Stripe secret key is not configured');
        }
        return new self(new StripeClient($secret));
    }

    public function createCustomer(string $email, array $metadata = []): string
    {
        $params = [
            'email' => $email,
            'metadata' => $metadata,
        ];
        $customer = $this->stripe->customers->create($params);
        return (string) $customer->id;
    }

    public function createSubscription(string $customerId, string $priceId): array
    {
        $subscription = $this->stripe->subscriptions->create([
            'customer' => $customerId,
            'items' => [['price' => $priceId]],
            'payment_behavior' => 'default_incomplete',
            'expand' => ['latest_invoice.payment_intent'],
        ]);
        return $this->subscriptionToArray($subscription);
    }

    public function cancelSubscription(string $stripeSubscriptionId): void
    {
        $this->stripe->subscriptions->update($stripeSubscriptionId, [
            'cancel_at_period_end' => true,
        ]);
    }

    public function retrieveSubscription(string $stripeSubscriptionId): array
    {
        $subscription = $this->stripe->subscriptions->retrieve($stripeSubscriptionId);
        return $this->subscriptionToArray($subscription);
    }

    /**
     * @return array{id: string, status: string, current_period_start: int, current_period_end: int, cancel_at_period_end: bool}
     */
    private function subscriptionToArray(Subscription $subscription): array
    {
        return [
            'id' => (string) $subscription->id,
            'status' => (string) $subscription->status,
            'current_period_start' => (int) $subscription->current_period_start,
            'current_period_end' => (int) $subscription->current_period_end,
            'cancel_at_period_end' => (bool) $subscription->cancel_at_period_end,
        ];
    }
}
