<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Contracts;

use App\Modules\Shared\Domain\ValueObjects\Uuid;

interface AggregateRoot
{
    public function getId(): Uuid;

    /**
     * @return list<object>
     */
    public function pullDomainEvents(): array;
}
