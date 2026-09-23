<?php

declare(strict_types=1);

namespace App\Filament\Widgets\RefererSources;

use App\Models\RefererHit;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class TopReferers extends BaseWidget
{
    protected static ?int $sort = 20;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected function getTableHeading(): string|Htmlable|null
    {
        return __('referer-sources.top_domains');
    }

    protected function getDefaultTableSortColumn(): ?string
    {
        return 'hits_total';
    }

    protected function getDefaultTableSortDirection(): ?string
    {
        return 'desc';
    }

    /**
     * @return Builder<RefererHit>
     */
    protected function getTableQuery(): Builder
    {
        $day7 = now()->subDays(6)->toDateString();
        $day30 = now()->subDays(29)->toDateString();

        $inner = RefererHit::query()
            ->getQuery()
            ->from('referer_hits')
            ->select('host')
            ->selectRaw('MIN(id) AS id')
            ->selectRaw('SUM(hits) AS hits_total')
            ->selectRaw('SUM(CASE WHEN date >= ? THEN hits ELSE 0 END) AS hits_7d', [$day7])
            ->selectRaw('SUM(CASE WHEN date >= ? THEN hits ELSE 0 END) AS hits_30d', [$day30])
            ->selectRaw('MAX(last_seen_at) AS last_seen')
            ->selectRaw('MIN(first_seen_at) AS first_seen')
            ->selectRaw('(SELECT last_path FROM referer_hits rh2 WHERE rh2.host = referer_hits.host ORDER BY last_seen_at DESC LIMIT 1) AS last_path')
            ->groupBy('host');

        return RefererHit::query()
            ->fromSub($inner, 'referer_hits')
            ->select('referer_hits.*')
            ->orderByDesc('hits_total');
    }

    /**
     * @return array<int, TextColumn>
     */
    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('host')
                ->label(__('referer-sources.host'))
                ->sortable()
                ->searchable(),
            TextColumn::make('hits_7d')
                ->label(__('referer-sources.hits_7d'))
                ->numeric()
                ->sortable(),
            TextColumn::make('hits_30d')
                ->label(__('referer-sources.hits_30d'))
                ->numeric()
                ->sortable(),
            TextColumn::make('hits_total')
                ->label(__('referer-sources.hits_total'))
                ->numeric()
                ->sortable(),
            TextColumn::make('last_path')
                ->label(__('referer-sources.last_path'))
                ->toggleable(),
            TextColumn::make('last_seen')
                ->label(__('referer-sources.last_seen'))
                ->dateTime('Y-m-d H:i')
                ->sortable(),
            TextColumn::make('first_seen')
                ->label(__('referer-sources.first_seen'))
                ->dateTime('Y-m-d H:i')
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [10, 25, 50];
    }
}
