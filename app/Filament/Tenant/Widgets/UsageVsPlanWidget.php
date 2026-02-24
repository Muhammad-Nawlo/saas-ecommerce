<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Widgets;

use App\Landlord\Services\FeatureUsageService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UsageVsPlanWidget extends BaseWidget
{
    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 1;

    protected function getStats(): array
    {
        try {
            $featureUsage = app(FeatureUsageService::class);
            $summary = $featureUsage->usageSummary();
        } catch (\Throwable) {
            return [];
        }
        $products = $summary['products'];
        $loc = $summary['inventory_locations'];
        $pLimit = $products['limit'] === null ? '∞' : $products['limit'];
        $atLimit = $products['at_limit'] || $loc['at_limit'];
        $desc = 'Products: ' . $products['used'] . '/' . $pLimit . ($loc['at_limit'] ? ' · Locations at limit' : '');

        return [
            Stat::make('Plan usage', $atLimit ? 'At limit' : 'OK')
                ->description($desc)
                ->color($atLimit ? 'warning' : 'success')
                ->icon($atLimit ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle'),
        ];
    }
}
