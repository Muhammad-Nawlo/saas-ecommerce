<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Widgets;

use App\Enums\TenantRole;
use App\Modules\Shared\Infrastructure\Audit\TenantAuditLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Last 10 tenant actions. Visible only to owner.
 */
class AuditLogWidget extends BaseWidget
{
    protected static ?int $sort = 30;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent activity';

    public static function canView(): bool
    {
        return tenant('id') !== null
            && auth()->user()?->hasRole(TenantRole::Owner->value) === true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(TenantAuditLog::query()->with('user')->recent(10))
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
                TextColumn::make('event_type')->label('Event')->badge(),
                TextColumn::make('description')->limit(40),
                TextColumn::make('user.name')->label('User')->placeholder('—'),
            ])
            ->paginated(false)
            ->striped();
    }
}
