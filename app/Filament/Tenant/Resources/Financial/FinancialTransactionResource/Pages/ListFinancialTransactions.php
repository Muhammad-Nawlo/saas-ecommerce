<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Financial\FinancialTransactionResource\Pages;

use App\Filament\Tenant\Resources\Financial\FinancialTransactionResource;
use Filament\Resources\Pages\ListRecords;

class ListFinancialTransactions extends ListRecords
{
    protected static string $resource = FinancialTransactionResource::class;
}
