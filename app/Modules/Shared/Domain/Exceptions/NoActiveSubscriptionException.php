<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Exceptions;

final class NoActiveSubscriptionException extends DomainException
{
    public static function forTenant(string $tenantId): self
    {
        return new self("Tenant {$tenantId} has no active subscription.");
    }
}
