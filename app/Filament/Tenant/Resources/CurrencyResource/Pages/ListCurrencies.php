<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\CurrencyResource\Pages;

use App\Filament\Tenant\Resources\CurrencyResource;
use Filament\Resources\Pages\ListRecords;

class ListCurrencies extends ListRecords
{
    protected static string $resource = CurrencyResource::class;
}
