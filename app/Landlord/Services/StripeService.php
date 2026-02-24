<?php

declare(strict_types=1);

namespace App\Landlord\Services;

use App\Landlord\Models\Plan;
use App\Landlord\Models\Tenant;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\StripeClient;
use Stripe\Subscription as StripeSubscription;

/**
 * Handles Stripe subscription lifecycle: Checkout Session, Customer Portal,
 * and subscription cancel/retrieve. Uses central (landlord) config.
 */
final class StripeService
{
    private const string SUCCESS_ROUTE = 'landlord.billing.success';
    private const string CANCEL_ROUTE = 'landlord.billing.cancel';
    private const string PORTAL_RETURN_ROUTE = 'landlord.billing.portal.return';

    public function __construct(
        private StripeClient $stripe
    ) {
    }

    public static function fromConfig(): self
    {
        $secret = config('services.stripe.secret');
        if ($secret === null || $secret === '') {
            throw new \InvalidArgumentException('Stripe secret is not configured');
        }
        return new self(new StripeClient($secret));
    }

    /**
     * Create a Stripe Checkout Session in subscription mode and return the session URL.
     * Caller should redirect the user to the returned URL.
     */
    public function createCheckoutSession(Tenant $tenant, Plan $plan): string
    {
        $customerId = $this->createCustomerIfNotExists($tenant);
        $priceId = $this->resolveStripePriceId($plan);

        $params = [
            'mode' => 'subscription',
            'customer' => $customerId,
            'line_items' => [
                [
                    'price' => $priceId,
                    'quantity' => 1,
                ],
            ],
            'success_url' => $this->url(self::SUCCESS_ROUTE) . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $this->url(self::CANCEL_ROUTE),
            'metadata' => [
                'tenant_id' => $tenant->id,
            ],
            'subscription_data' => [
                'metadata' => [
                    'tenant_id' => $tenant->id,
                ],
            ],
        ];

        $session = $this->stripe->checkout->sessions->create($params);

        return (string) $session->url;
    }

    /**
     * Ensure the tenant has a Stripe customer ID; create customer if missing and persist.
     */
    public function createCustomerIfNotExists(Tenant $tenant): string
    {
        $existing = $this->getStripeCustomerId($tenant);
        if ($existing !== null && $existing !== '') {
            return $existing;
        }

        $email = $this->tenantEmail($tenant);
        $customer = $this->stripe->customers->create([
            'email' => $email,
            'name' => $tenant->name,
            'metadata' => [
                'tenant_id' => $tenant->id,
            ],
        ]);

        $tenant->stripe_customer_id = (string) $customer->id;
        $tenant->save();

        return (string) $customer->id;
    }

    /**
     * Create a Stripe Billing Portal session URL for the tenant to manage subscription.
     */
    public function createPortalSession(Tenant $tenant): string
    {
        $customerId = $this->createCustomerIfNotExists($tenant);

        $session = $this->stripe->billingPortal->sessions->create([
            'customer' => $customerId,
            'return_url' => $this->url(self::PORTAL_RETURN_ROUTE),
        ]);

        return (string) $session->url;
    }

    /**
     * Cancel the subscription at period end (Stripe-side).
     */
    public function cancelSubscription(string $stripeSubscriptionId): void
    {
        $this->stripe->subscriptions->update($stripeSubscriptionId, [
            'cancel_at_period_end' => true,
        ]);
    }

    /**
     * Retrieve subscription from Stripe.
     *
     * @return array{id: string, status: string, current_period_start: int, current_period_end: int, cancel_at_period_end: bool, price_id: string|null}
     */
    public function retrieveSubscription(string $stripeSubscriptionId): array
    {
        $subscription = $this->stripe->subscriptions->retrieve($stripeSubscriptionId);

        return $this->subscriptionToArray($subscription);
    }

    /**
     * Retrieve a Checkout Session (e.g. after redirect from Stripe).
     */
    public function retrieveCheckoutSession(string $sessionId): StripeCheckoutSession
    {
        return $this->stripe->checkout->sessions->retrieve($sessionId, [
            'expand' => ['subscription'],
        ]);
    }

    private function getStripeCustomerId(Tenant $tenant): ?string
    {
        $id = $tenant->stripe_customer_id ?? null;

        return $id !== null && $id !== '' ? $id : null;
    }

    private function tenantEmail(Tenant $tenant): ?string
    {
        $data = $tenant->data;
        if (is_array($data) && isset($data['email']) && is_string($data['email'])) {
            return $data['email'];
        }

        return null;
    }

    private function resolveStripePriceId(Plan $plan): string
    {
        $id = $plan->stripe_price_id ?? null;
        if ($id !== null && $id !== '') {
            return $id;
        }
        throw new \InvalidArgumentException('Plan has no stripe_price_id');
    }

    private function url(string $route): string
    {
        return url()->route($route, [], true);
    }

    /**
     * @return array{id: string, status: string, current_period_start: int, current_period_end: int, cancel_at_period_end: bool, price_id: string|null}
     */
    private function subscriptionToArray(StripeSubscription $subscription): array
    {
        $priceId = null;
        if (isset($subscription->items->data[0]->price->id)) {
            $priceId = (string) $subscription->items->data[0]->price->id;
        }

        return [
            'id' => (string) $subscription->id,
            'status' => (string) $subscription->status,
            'current_period_start' => (int) $subscription->current_period_start,
            'current_period_end' => (int) $subscription->current_period_end,
            'cancel_at_period_end' => (bool) $subscription->cancel_at_period_end,
            'price_id' => $priceId,
        ];
    }
}
