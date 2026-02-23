<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Messaging;

use Illuminate\Contracts\Events\Dispatcher;

final class LaravelEventBus implements EventBus
{
    public function __construct(
        private Dispatcher $dispatcher
    ) {
    }

    public function publish(object $event): void
    {
        $this->dispatcher->dispatch($event);
    }
}
