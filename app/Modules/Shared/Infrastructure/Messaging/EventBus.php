<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Messaging;

use App\Modules\Shared\Domain\Contracts\DomainEvent;

interface EventBus
{
    /**
     * @param DomainEvent|object $event
     */
    public function publish(object $event): void;
}
