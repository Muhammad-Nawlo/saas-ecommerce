<?php

declare(strict_types=1);

namespace App\Filament\Landlord\Widgets;

use App\Landlord\Models\LandlordAuditLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Recent platform activity. Visible only to super_admin.
 */
class AuditLogWidget extends BaseWidget
{
    protected static ?int $sort = 30;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent platform activity';

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('super_admin') === true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(LandlordAuditLog::query()->with('user')->recent(10))
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
                TextColumn::make('event_type')->label('Event')->badge(),
                TextColumn::make('description')->limit(40),
                TextColumn::make('tenant_id')->label('Tenant')->limit(8),
                TextColumn::make('user.name')->label('User')->placeholder('—'),
            ])
            ->paginated(false)
            ->striped();
    }
}
