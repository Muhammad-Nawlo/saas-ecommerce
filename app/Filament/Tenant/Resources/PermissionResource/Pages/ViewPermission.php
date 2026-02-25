<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PermissionResource\Pages;

use App\Filament\Tenant\Resources\PermissionResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewPermission extends ViewRecord
{
    protected static string $resource = PermissionResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextEntry::make('name'),
                TextEntry::make('guard_name'),
            ]);
    }
}
