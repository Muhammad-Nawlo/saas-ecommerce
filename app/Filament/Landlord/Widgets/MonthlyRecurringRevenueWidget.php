<?php

declare(strict_types=1);

namespace App\Filament\Landlord\Widgets;

use App\Landlord\Models\Subscription;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** MRR = sum(plan.price) for subscriptions where status = active. */
class MonthlyRecurringRevenueWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected function getStats(): array
    {
        $connection = config('tenancy.database.central_connection', config('database.default'));
        $mrr = Subscription::on($connection)
            ->where('status', 'active')
            ->with('plan')
            ->get()
            ->sum(fn ($s) => (float) ($s->plan->price ?? 0));

        return [
            Stat::make('Monthly recurring revenue', '$' . number_format($mrr, 2))
                ->description('Sum of active plan prices')
                ->icon('heroicon-o-currency-dollar'),
        ];
    }
}
