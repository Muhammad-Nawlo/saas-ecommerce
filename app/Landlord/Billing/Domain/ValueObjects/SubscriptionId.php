<?php

declare(strict_types=1);

namespace App\Landlord\Billing\Domain\ValueObjects;

use App\Modules\Shared\Domain\ValueObjects\Uuid;

final readonly class SubscriptionId
{
    private function __construct(
        private Uuid $uuid
    ) {
    }

    public static function fromString(string $value): self
    {
        return new self(Uuid::fromString($value));
    }

    public static function generate(): self
    {
        return new self(Uuid::v4());
    }

    public function value(): string
    {
        return $this->uuid->value();
    }
}
