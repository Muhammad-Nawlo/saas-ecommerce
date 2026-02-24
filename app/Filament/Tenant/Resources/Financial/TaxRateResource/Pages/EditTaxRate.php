<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Financial\TaxRateResource\Pages;

use App\Filament\Tenant\Resources\Financial\TaxRateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTaxRate extends EditRecord
{
    protected static string $resource = TaxRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
