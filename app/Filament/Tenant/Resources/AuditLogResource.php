<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources;

use App\Enums\TenantRole;
use App\Modules\Shared\Infrastructure\Audit\TenantAuditLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tenant audit logs. Read-only. Only owner can access.
 */
class AuditLogResource extends Resource
{
    protected static ?string $model = TenantAuditLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    /** @var string|\UnitEnum|null */
    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return 'Audit log';
    }

    public static function canAccess(): bool
    {
        return tenant('id') !== null
            && auth()->user()?->hasRole(TenantRole::Owner->value) === true;
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
                Tables\Columns\TextColumn::make('user.name')->label('User')->placeholder('—')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_type')
                    ->options(fn () => TenantAuditLog::query()->distinct()->pluck('event_type', 'event_type')->all()),
                Tables\Filters\SelectFilter::make('model_type')
                    ->options(fn () => TenantAuditLog::query()->distinct()->pluck('model_type', 'model_type')->all()),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name', fn (Builder $q) => $q->orderBy('name'))
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
            'index' => \App\Filament\Tenant\Resources\AuditLogResource\Pages\ListAuditLogs::route('/'),
            'view' => \App\Filament\Tenant\Resources\AuditLogResource\Pages\ViewAuditLog::route('/{record}'),
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
