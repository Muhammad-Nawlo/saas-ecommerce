<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Contracts;

use App\Modules\Shared\Domain\ValueObjects\Uuid;

interface Repository
{
    public function find(Uuid $id): ?object;

    public function save(object $aggregate): void;

    public function remove(object $aggregate): void;
}
