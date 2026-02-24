<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Financial\TaxRateResource\Pages;

use App\Filament\Tenant\Resources\Financial\TaxRateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTaxRate extends CreateRecord
{
    protected static string $resource = TaxRateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = tenant('id');
        return $data;
    }
}
