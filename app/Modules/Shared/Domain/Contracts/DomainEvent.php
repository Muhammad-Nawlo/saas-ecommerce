<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Contracts;

interface DomainEvent
{
    public function occurredAt(): \DateTimeImmutable;
}
