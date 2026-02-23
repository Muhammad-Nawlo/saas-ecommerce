<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Messaging;

use App\Modules\Shared\Domain\Contracts\Command;

interface CommandBus
{
    public function dispatch(Command $command): void;
}
