<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Messaging;

use App\Modules\Shared\Domain\Contracts\Query;
use Illuminate\Contracts\Container\Container;

final class LaravelQueryBus implements QueryBus
{
    public function __construct(
        private Container $container
    ) {
    }

    public function ask(Query $query): mixed
    {
        $handlerClass = $this->resolveHandlerClass($query);
        $handler = $this->container->make($handlerClass);
        return $handler($query);
    }

    private function resolveHandlerClass(Query $query): string
    {
        $queryClass = $query::class;
        return str_replace('\\Query\\', '\\QueryHandler\\', $queryClass) . 'Handler';
    }
}
