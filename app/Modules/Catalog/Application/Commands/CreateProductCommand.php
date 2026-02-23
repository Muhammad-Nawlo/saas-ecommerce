<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Commands;

use App\Modules\Shared\Domain\Contracts\Command;
use App\Modules\Shared\Domain\ValueObjects\TenantId;

final readonly class CreateProductCommand implements Command
{
    public function __construct(
        public string $tenantId,
        public string $name,
        public string $slug,
        public string $description,
        public int $priceMinorUnits,
        public string $currency
    ) {
    }

    public function tenantIdVo(): TenantId
    {
        return TenantId::fromString($this->tenantId);
    }
}
