<?php

declare(strict_types=1);

namespace App\Filament\Widgets\RefererSources;

use App\Models\RefererHit;
use Filament\Widgets\LineChartWidget;

class RefererTrend extends LineChartWidget
{
    protected static ?int $sort = 10;

    protected ?string $pollingInterval = null;

    public function getHeading(): ?string
    {
        return __('referer-sources.chart_heading');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $rows = RefererHit::query()
            ->selectRaw('date, SUM(hits) AS total')
            ->where('date', '>=', now()->subDays(29)->toDateString())
            ->groupBy('date')
            ->pluck('total', 'date');

        $labels = [];
        $data = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $key = $day->toDateString();
            $labels[] = $day->format('m-d');
            $data[] = (int) ($rows[$key] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => __('referer-sources.chart_series'),
                    'data' => $data,
                    'fill' => 'start',
                ],
            ],
            'labels' => $labels,
        ];
    }
}
