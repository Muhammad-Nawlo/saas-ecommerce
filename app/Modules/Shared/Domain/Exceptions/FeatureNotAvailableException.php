<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Exceptions;

final class FeatureNotAvailableException extends DomainException
{
    public static function forFeature(string $featureCode): self
    {
        return new self("Feature {$featureCode} is not available on your plan.");
    }
}
