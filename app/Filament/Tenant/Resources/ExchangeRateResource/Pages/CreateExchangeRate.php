<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\ExchangeRateResource\Pages;

use App\Filament\Tenant\Resources\ExchangeRateResource;
use App\Models\Currency\Currency;
use App\Services\Currency\ExchangeRateService;
use Filament\Resources\Pages\CreateRecord;

class CreateExchangeRate extends CreateRecord
{
    protected static string $resource = ExchangeRateResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $base = Currency::findOrFail($data['base_currency_id']);
        $target = Currency::findOrFail($data['target_currency_id']);
        return app(ExchangeRateService::class)->setManualRate(
            $base,
            $target,
            (float) $data['rate'],
            isset($data['effective_at']) ? \Carbon\Carbon::parse($data['effective_at']) : null,
        );
    }
}
