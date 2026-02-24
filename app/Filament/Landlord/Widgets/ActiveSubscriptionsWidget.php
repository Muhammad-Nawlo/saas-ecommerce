<?php

declare(strict_types=1);

namespace App\Filament\Landlord\Widgets;

use App\Landlord\Models\Subscription;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ActiveSubscriptionsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected function getStats(): array
    {
        $connection = config('tenancy.database.central_connection', config('database.default'));
        $active = Subscription::on($connection)->where('status', 'active')->count();

        return [
            Stat::make('Active subscriptions', $active)
                ->description('Currently active')
                ->icon('heroicon-o-document-check'),
        ];
    }
}
