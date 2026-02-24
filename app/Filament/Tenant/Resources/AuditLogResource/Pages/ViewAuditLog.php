<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\AuditLogResource\Pages;

use App\Filament\Tenant\Resources\AuditLogResource;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewAuditLog extends ViewRecord
{
    protected static string $resource = AuditLogResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('created_at')->dateTime()->label('When'),
                TextEntry::make('event_type')->label('Event'),
                TextEntry::make('description')->label('Description')->columnSpanFull(),
                TextEntry::make('model_type')->label('Model type'),
                TextEntry::make('model_id')->label('Model ID'),
                TextEntry::make('user.name')->label('User')->placeholder('—'),
                TextEntry::make('ip_address')->label('IP')->placeholder('—'),
                TextEntry::make('user_agent')->label('User agent')->placeholder('—')->columnSpanFull(),
                KeyValueEntry::make('properties')->label('Properties')->columnSpanFull(),
            ]);
    }
}
