<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources;

use App\Models\Customer\Customer;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section as SchemaSection;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

/**
 * Tenant-scoped customer identity (storefront customers). Separate from admin users.
 */
class CustomerIdentityResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    /** @var string|\UnitEnum|null */
    protected static string|\UnitEnum|null $navigationGroup = 'Store';

    protected static ?string $navigationLabel = 'Customers';

    protected static ?string $modelLabel = 'Customer';

    protected static ?string $pluralModelLabel = 'Customers';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                SchemaSection::make('Profile')
                    ->schema([
                        Forms\Components\TextInput::make('email')->email()->required()->maxLength(255),
                        Forms\Components\TextInput::make('first_name')->required()->maxLength(255),
                        Forms\Components\TextInput::make('last_name')->required()->maxLength(255),
                        Forms\Components\TextInput::make('phone')->tel()->maxLength(50),
                        Forms\Components\Toggle::make('is_active')->default(true),
                        Forms\Components\DateTimePicker::make('email_verified_at')->nullable(),
                        Forms\Components\DateTimePicker::make('last_login_at')->disabled()->nullable(),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->nullable()
                            ->maxLength(255),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('first_name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('last_name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('phone')->placeholder('—'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->sortable(),
                Tables\Columns\TextColumn::make('email_verified_at')->dateTime()->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('last_login_at')->dateTime()->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('deactivate')
                    ->label('Deactivate')
                    ->icon('heroicon-o-no-symbol')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (Customer $record) => $record->update(['is_active' => false]))
                    ->visible(fn (Customer $record) => $record->is_active),
                Action::make('resetPassword')
                    ->label('Reset password')
                    ->icon('heroicon-o-key')
                    ->form([
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->required()
                            ->confirmed()
                            ->maxLength(255),
                    ])
                    ->action(fn (Customer $record, array $data) => $record->update(['password' => Hash::make($data['password'])])),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Tenant\Resources\CustomerIdentityResource\RelationManagers\AddressesRelationManager::class,
            \App\Filament\Tenant\Resources\CustomerIdentityResource\RelationManagers\OrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Tenant\Resources\CustomerIdentityResource\Pages\ListCustomerIdentities::route('/'),
            'edit' => \App\Filament\Tenant\Resources\CustomerIdentityResource\Pages\EditCustomerIdentity::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = tenant('id');
        if ($tenantId === null) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }
        return parent::getEloquentQuery()->forTenant((string) $tenantId);
    }
}
