<?php

declare(strict_types=1);

namespace App\Landlord\Billing\Domain\Repositories;

use App\Landlord\Billing\Domain\Entities\Subscription;

interface SubscriptionRepository
{
    public function save(Subscription $subscription): void;

    public function findByTenantId(string $tenantId): ?Subscription;

    public function findByStripeSubscriptionId(string $stripeSubscriptionId): ?Subscription;
}
