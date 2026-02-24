<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\ExchangeRateResource\Pages;

use App\Filament\Tenant\Resources\ExchangeRateResource;
use Filament\Resources\Pages\ListRecords;

class ListExchangeRates extends ListRecords
{
    protected static string $resource = ExchangeRateResource::class;
}
