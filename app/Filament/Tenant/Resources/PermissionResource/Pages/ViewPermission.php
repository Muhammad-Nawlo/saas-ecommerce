<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PermissionResource\Pages;

use App\Filament\Tenant\Resources\PermissionResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewPermission extends ViewRecord
{
    protected static string $resource = PermissionResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('name'),
                TextEntry::make('guard_name'),
            ]);
    }
}
