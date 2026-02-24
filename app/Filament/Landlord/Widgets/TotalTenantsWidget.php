<?php

declare(strict_types=1);

namespace App\Filament\Landlord\Widgets;

use App\Landlord\Models\Tenant;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TotalTenantsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 1;

    protected function getStats(): array
    {
        $connection = config('tenancy.database.central_connection', config('database.default'));
        $total = Tenant::on($connection)->count();

        return [
            Stat::make('Total tenants', $total)
                ->description('Registered on platform')
                ->icon('heroicon-o-building-office-2'),
        ];
    }
}
