<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\CustomerIdentityResource\Pages;

use App\Filament\Tenant\Resources\CustomerIdentityResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCustomerIdentity extends EditRecord
{
    protected static string $resource = CustomerIdentityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
