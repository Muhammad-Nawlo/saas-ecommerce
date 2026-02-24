<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Exceptions;

/**
 * Thrown when an action requires a tenant feature or limit that is not enabled.
 */
final class FeatureNotEnabledException extends DomainException
{
    public static function forFeature(string $feature): self
    {
        return new self(sprintf('Feature "%s" is not enabled for this tenant', $feature));
    }

    public static function forLimit(string $limit): self
    {
        return new self(sprintf('Tenant limit "%s" has been reached or is not allowed', $limit));
    }
}
