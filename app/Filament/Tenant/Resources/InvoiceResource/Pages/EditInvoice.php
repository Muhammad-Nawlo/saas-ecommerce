<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\InvoiceResource\Pages;

use App\Filament\Tenant\Resources\InvoiceResource;
use Filament\Resources\Pages\EditRecord;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return !$record->isLocked();
    }
}
