<?php

declare(strict_types=1);

namespace App\Landlord\Billing\Domain\Contracts;

interface StripeBillingGateway
{
    /**
     * @param array<string, string> $metadata
     */
    public function createCustomer(string $email, array $metadata = []): string;

    /**
     * @return array{id: string, status: string, current_period_start: int, current_period_end: int, cancel_at_period_end: bool}
     */
    public function createSubscription(string $customerId, string $priceId): array;

    public function cancelSubscription(string $stripeSubscriptionId): void;

    /**
     * @return array{id: string, status: string, current_period_start: int, current_period_end: int, cancel_at_period_end: bool}
     */
    public function retrieveSubscription(string $stripeSubscriptionId): array;
}
