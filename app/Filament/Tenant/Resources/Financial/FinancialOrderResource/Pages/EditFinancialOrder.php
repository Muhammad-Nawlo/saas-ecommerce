<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Financial\FinancialOrderResource\Pages;

use App\Filament\Tenant\Resources\Financial\FinancialOrderResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFinancialOrder extends EditRecord
{
    protected static string $resource = FinancialOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn () => !$this->record->isLocked()),
        ];
    }
}
