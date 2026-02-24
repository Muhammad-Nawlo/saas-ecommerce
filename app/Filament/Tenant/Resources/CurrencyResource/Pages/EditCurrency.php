<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\CurrencyResource\Pages;

use App\Filament\Tenant\Resources\CurrencyResource;
use Filament\Resources\Pages\EditRecord;

class EditCurrency extends EditRecord
{
    protected static string $resource = CurrencyResource::class;
}
