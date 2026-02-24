<?php

declare(strict_types=1);

namespace App\Filament\Landlord\Resources\AuditLogResource\Pages;

use App\Filament\Landlord\Resources\AuditLogResource;
use Filament\Resources\Pages\ListRecords;

class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;
}
