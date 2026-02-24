<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Financial\TaxRateResource\Pages;

use App\Filament\Tenant\Resources\Financial\TaxRateResource;
use Filament\Resources\Pages\ListRecords;

class ListTaxRates extends ListRecords
{
    protected static string $resource = TaxRateResource::class;
}
