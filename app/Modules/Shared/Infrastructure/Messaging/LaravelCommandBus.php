<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Messaging;

use App\Modules\Shared\Domain\Contracts\Command;
use Illuminate\Contracts\Container\Container;

final class LaravelCommandBus implements CommandBus
{
    public function __construct(
        private Container $container
    ) {
    }

    public function dispatch(Command $command): void
    {
        $handlerClass = $this->resolveHandlerClass($command);
        $handler = $this->container->make($handlerClass);
        $handler($command);
    }

    private function resolveHandlerClass(Command $command): string
    {
        $commandClass = $command::class;
        return str_replace('\\Command\\', '\\CommandHandler\\', $commandClass) . 'Handler';
    }
}
