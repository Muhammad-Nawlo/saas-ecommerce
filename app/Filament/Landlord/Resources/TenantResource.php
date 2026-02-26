<?php

declare(strict_types=1);

namespace App\Filament\Landlord\Resources;

use App\Landlord\Models\Tenant;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Tenant resource. Landlord DB only. Read-only for tenant data; actions for Suspend/Activate/View subscription. */
class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static bool $isScopedToTenant = false;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Tenant (read-only)')
                    ->schema([
                        Forms\Components\TextInput::make('id')->disabled()->label('ID'),
                        Forms\Components\TextInput::make('name')->disabled(),
                        Forms\Components\TextInput::make('slug')->disabled(),
                        Forms\Components\TextInput::make('email_display')
                            ->label('Email')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(fn ($record) => $record && is_array($record->data ?? null) ? ($record->data['email'] ?? '—') : '—'),
                        Forms\Components\TextInput::make('domain_display')
                            ->label('Domain')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(fn ($record) => $record ? static::getPrimaryDomain($record) : '—'),
                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'suspended' => 'Suspended',
                            ])
                            ->disabled()
                            ->dehydrated(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->limit(8)->copyable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email_display')
                    ->label('Email')
                    ->getStateUsing(fn (Tenant $r) => is_array($r->data ?? null) ? ($r->data['email'] ?? '—') : '—'),
                Tables\Columns\TextColumn::make('domain_display')
                    ->label('Domain')
                    ->getStateUsing(fn (Tenant $r) => static::getPrimaryDomain($r)),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state): string => $state === 'active' ? 'success' : 'danger')->sortable(),
                Tables\Columns\TextColumn::make('plan.name')->label('Plan')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['active' => 'Active', 'suspended' => 'Suspended']),
            ])
            ->recordActions([
                Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Tenant $r) => ($r->status ?? 'active') !== 'active' && auth()->user()?->can('update', $r))
                    ->action(fn (Tenant $r) => $r->update(['status' => 'active']))
                    ->requiresConfirmation(),
                Action::make('suspend')
                    ->label('Suspend')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (Tenant $r) => ($r->status ?? 'active') === 'active' && auth()->user()?->can('update', $r))
                    ->action(fn (Tenant $r) => $r->update(['status' => 'suspended']))
                    ->requiresConfirmation(),
                Action::make('viewSubscription')
                    ->label('Subscription')
                    ->icon('heroicon-o-credit-card')
                    ->url(fn (Tenant $r) => \App\Filament\Landlord\Resources\SubscriptionResource::getUrl('index')),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Landlord\Resources\TenantResource\Pages\ListTenants::route('/'),
            'view' => \App\Filament\Landlord\Resources\TenantResource\Pages\ViewTenant::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes()
            ->with(['domains', 'plan']);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    private static function getPrimaryDomain(Tenant $tenant): string
    {
        $domains = $tenant->domains ?? collect();
        $primary = $domains->where('is_primary', true)->first();
        if ($primary) {
            return $primary->domain ?? '—';
        }
        $first = $domains->first();
        return $first ? ($first->domain ?? '—') : '—';
    }
}
