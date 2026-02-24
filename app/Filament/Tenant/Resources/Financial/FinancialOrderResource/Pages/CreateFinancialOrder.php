<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Financial\FinancialOrderResource\Pages;

use App\Filament\Tenant\Resources\Financial\FinancialOrderResource;
use App\Models\Financial\FinancialOrder;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateFinancialOrder extends CreateRecord
{
    protected static string $resource = FinancialOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = tenant('id');
        $data['order_number'] ??= 'FO-' . strtoupper(Str::random(8));
        $data['subtotal_cents'] = 0;
        $data['tax_total_cents'] = 0;
        $data['total_cents'] = 0;
        $data['status'] = FinancialOrder::STATUS_DRAFT;
        return $data;
    }
}
