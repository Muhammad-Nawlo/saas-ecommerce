<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Exceptions;

final class PlanLimitExceededException extends DomainException
{
    public static function forFeature(string $featureCode, int $limit): self
    {
        return new self("Plan limit exceeded for {$featureCode}: maximum {$limit} allowed.");
    }

    public static function unlimitedNotAllowed(): self
    {
        return new self('Cannot determine limit for unlimited feature.');
    }
}
