<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Widgets;

use App\Modules\Orders\Infrastructure\Persistence\OrderModel;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class RevenueChartWidget extends ChartWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '300px';

    public ?string $filter = '30';

    protected function getData(): array
    {
        $tenantId = tenant('id');
        if ($tenantId === null) {
            return ['datasets' => [], 'labels' => []];
        }
        $days = (int) $this->filter;
        $from = now()->subDays($days);
        $driver = \DB::connection()->getDriverName();
        $dateExpr = $driver === 'sqlite' ? "date(created_at)" : "DATE(created_at)";
        $query = OrderModel::forTenant((string) $tenantId)
            ->whereIn('status', ['paid', 'shipped'])
            ->where('created_at', '>=', $from)
            ->selectRaw("{$dateExpr} as date, SUM(total_amount) as total")
            ->groupBy('date')
            ->orderBy('date');
        $results = $query->get();
        $labels = [];
        $values = [];
        $byDate = $results->keyBy('date');
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = now()->subDays($i)->format('Y-m-d');
            $labels[] = $d;
            $values[] = ($byDate->get($d)->total ?? 0) / 100;
        }
        return [
            'datasets' => [
                [
                    'label' => 'Revenue (USD)',
                    'data' => $values,
                    'fill' => true,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }
}
