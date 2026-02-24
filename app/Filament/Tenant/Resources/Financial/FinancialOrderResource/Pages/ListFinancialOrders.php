<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Financial\FinancialOrderResource\Pages;

use App\Filament\Tenant\Resources\Financial\FinancialOrderResource;
use Filament\Resources\Pages\ListRecords;

class ListFinancialOrders extends ListRecords
{
    protected static string $resource = FinancialOrderResource::class;
}
