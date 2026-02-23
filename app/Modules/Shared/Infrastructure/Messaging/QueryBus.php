<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Messaging;

use App\Modules\Shared\Domain\Contracts\Query;

interface QueryBus
{
    public function ask(Query $query): mixed;
}
