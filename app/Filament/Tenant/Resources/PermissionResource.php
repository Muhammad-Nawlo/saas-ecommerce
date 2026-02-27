<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources;

use App\Constants\TenantPermissions;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Permission;

/**
 * Tenant panel: view permissions. Only users with manage roles can access.
 */
class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';

    /** @var string|\UnitEnum|null */
    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 11;

    public static function getNavigationLabel(): string
    {
        return 'Permissions';
    }

    public static function canAccess(): bool
    {
        return tenant('id') !== null && auth()->user()?->can(TenantPermissions::MANAGE_ROLES) === true;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('guard_name')->toggleable(),
            ])
            ->filters([])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('guard_name', 'web')->orderBy('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Tenant\Resources\PermissionResource\Pages\ListPermissions::route('/'),
            'view' => \App\Filament\Tenant\Resources\PermissionResource\Pages\ViewPermission::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getModelLabel(): string
    {
        return 'Permission';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Permissions';
    }
}
