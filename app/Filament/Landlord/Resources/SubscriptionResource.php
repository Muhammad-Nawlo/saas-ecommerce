<?php

declare(strict_types=1);

namespace App\Filament\Landlord\Resources;

use App\Landlord\Models\Plan;
use App\Landlord\Models\Subscription;
use App\Landlord\Models\Tenant;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Subscription resource. Landlord DB only. Read-only except manual cancel and plan override (admin). */
class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static bool $isScopedToTenant = false;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 0;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Subscription')
                    ->schema([
                        Forms\Components\Select::make('tenant_id')
                            ->label('Tenant')
                            ->relationship('tenant', 'name', fn (Builder $q) => $q->orderBy('name'))
                            ->searchable()
                            ->required()
                            ->disabled(fn (?string $operation) => $operation !== 'edit'),
                        Forms\Components\Select::make('plan_id')
                            ->label('Plan')
                            ->options(Plan::on(config('tenancy.database.central_connection', config('database.default')))->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->helperText('Admin can override plan (manual change).'),
                        Forms\Components\TextInput::make('status')->disabled()->dehydrated(true),
                        Forms\Components\DateTimePicker::make('starts_at')->disabled(),
                        Forms\Components\DateTimePicker::make('ends_at')->disabled(),
                        Forms\Components\DateTimePicker::make('current_period_start')->disabled(),
                        Forms\Components\DateTimePicker::make('current_period_end')->disabled(),
                        Forms\Components\TextInput::make('stripe_subscription_id')->disabled()->label('Stripe Subscription ID'),
                        Forms\Components\Toggle::make('cancel_at_period_end')->disabled(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tenant.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('plan.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'active' => 'success',
                    'past_due' => 'warning',
                    'canceled', 'cancelled' => 'gray',
                    default => 'danger',
                })->sortable(),
                Tables\Columns\TextColumn::make('current_period_start')->dateTime()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('current_period_end')->dateTime()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('stripe_subscription_id')->limit(20)->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'past_due' => 'Past due',
                        'canceled' => 'Canceled',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('cancelAtPeriodEnd')
                    ->label('Cancel at period end')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->visible(fn (Subscription $r) => $r->status === 'active' && !$r->cancel_at_period_end)
                    ->action(function (Subscription $r) {
                        $r->update(['cancel_at_period_end' => true]);
                    })
                    ->requiresConfirmation(),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Landlord\Resources\SubscriptionResource\Pages\ListSubscriptions::route('/'),
            'edit' => \App\Filament\Landlord\Resources\SubscriptionResource\Pages\EditSubscription::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes()
            ->with(['tenant', 'plan']);
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
