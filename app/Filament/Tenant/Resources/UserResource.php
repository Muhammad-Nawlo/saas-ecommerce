<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources;

use App\Constants\TenantPermissions;
use App\Filament\Tenant\Resources\RoleResource;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

/**
 * Tenant panel: manage users and assign roles. Only owner (or manage users permission) can access.
 * Prevents privilege escalation: can only assign roles from assignableRoleNames().
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    /** @var string|\UnitEnum|null */
    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 9;

    public static function getNavigationLabel(): string
    {
        return 'Team';
    }

    public static function canAccess(): bool
    {
        return tenant('id') !== null && auth()->user()?->can(TenantPermissions::MANAGE_USERS) === true;
    }

    public static function form(Schema $schema): Schema
    {
        $assignableRoles = RoleResource::assignableRoleNames();

        return $schema
            ->schema([
                Forms\Components\Section::make('User')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $operation) => $operation === 'create')
                            ->maxLength(255),
                        Forms\Components\Select::make('roles')
                            ->relationship(
                                name: 'roles',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $q) => $q->where('guard_name', 'web')
                            )
                            ->multiple()
                            ->preload()
                            ->options(
                                \Spatie\Permission\Models\Role::where('guard_name', 'web')
                                    ->when(count($assignableRoles) > 0, fn (Builder $q) => $q->whereIn('name', $assignableRoles))
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                            )
                            ->helperText('You can only assign roles at or below your own level.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->separator(','),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Tenant\Resources\UserResource\Pages\ListUsers::route('/'),
            'create' => \App\Filament\Tenant\Resources\UserResource\Pages\CreateUser::route('/create'),
            'edit' => \App\Filament\Tenant\Resources\UserResource\Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function getModelLabel(): string
    {
        return 'User';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Team';
    }
}
