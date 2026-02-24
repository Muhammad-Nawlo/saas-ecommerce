<?php

declare(strict_types=1);

namespace App\Filament\Landlord\Widgets;

use App\Landlord\Models\Subscription;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FailedPaymentsWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    protected function getStats(): array
    {
        $connection = config('tenancy.database.central_connection', config('database.default'));
        $pastDue = Subscription::on($connection)->where('status', 'past_due')->count();

        return [
            Stat::make('Failed payments (past due)', $pastDue)
                ->description('Subscriptions past due')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($pastDue > 0 ? 'danger' : 'success'),
        ];
    }
}
