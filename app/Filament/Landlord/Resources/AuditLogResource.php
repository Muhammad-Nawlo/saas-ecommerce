<?php

declare(strict_types=1);

namespace App\Filament\Landlord\Resources;

use App\Landlord\Models\LandlordAuditLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Landlord audit logs. Read-only. Only super_admin can access.
 */
class AuditLogResource extends Resource
{
    protected static ?string $model = LandlordAuditLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return 'Audit log';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') === true;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('event_type')->label('Event')->badge()->sortable(),
                Tables\Columns\TextColumn::make('description')->limit(50)->searchable(),
                Tables\Columns\TextColumn::make('model_type')->label('Model')->toggleable(),
                Tables\Columns\TextColumn::make('model_id')->label('ID')->toggleable(),
                Tables\Columns\TextColumn::make('tenant_id')->label('Tenant')->toggleable(),
                Tables\Columns\TextColumn::make('user.name')->label('User')->placeholder('—')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_type')
                    ->options(fn () => LandlordAuditLog::query()->distinct()->pluck('event_type', 'event_type')->all()),
                Tables\Filters\SelectFilter::make('model_type')
                    ->options(fn () => LandlordAuditLog::query()->distinct()->pluck('model_type', 'model_type')->all()),
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->options(fn () => \App\Landlord\Models\Tenant::on(config('tenancy.database.central_connection'))->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name', fn (Builder $q) => $q->useConnection(config('tenancy.database.central_connection'))->orderBy('name'))
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('From'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['from'])) {
                            $query->whereDate('created_at', '>=', $data['from']);
                        }
                        if (!empty($data['until'])) {
                            $query->whereDate('created_at', '<=', $data['until']);
                        }
                        return $query;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->simplePaginate();
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Landlord\Resources\AuditLogResource\Pages\ListAuditLogs::route('/'),
            'view' => \App\Filament\Landlord\Resources\AuditLogResource\Pages\ViewAuditLog::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getModelLabel(): string
    {
        return 'Audit log';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Audit log';
    }
}
