<?php

namespace App\Modules\Shared\Domain\ValueObjects;

use Ramsey\Uuid\Uuid as RamseyUuid;

class Uuid
{
    private string $value;

    public function __construct(?string $value = null)
    {
        $this->value = $value ?? RamseyUuid::uuid4()->toString();
    }

    public function value(): string
    {
        return $this->value;
    }
}
