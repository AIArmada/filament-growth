<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Widgets;

use AIArmada\CommerceSupport\Traits\FormatsMoney;
use AIArmada\FilamentGrowth\Support\GrowthStatsAggregator;
use AIArmada\Growth\Models\Experiment;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Gate;

final class GrowthStatsWidget extends StatsOverviewWidget
{
    use FormatsMoney {
        formatMoney as private formatMinorMoney;
    }

    protected ?string $pollingInterval = null;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user !== null
            && parent::canView()
            && Gate::forUser($user)->allows('viewAny', Experiment::class);
    }

    protected function getStats(): array
    {
        $aggregated = GrowthStatsAggregator::aggregate();

        return [
            Stat::make('Active Experiments', number_format($aggregated['activeExperiments']))
                ->description('Currently splitting traffic')
                ->color('success'),
            Stat::make('Variants', number_format($aggregated['variantCount']))
                ->description('Configured across all experiments')
                ->color('primary'),
            Stat::make('Assignments', number_format($aggregated['assignmentCount']))
                ->description('Sticky subject allocations recorded')
                ->color('info'),
            Stat::make('Tracked Revenue', $aggregated['revenueSummary']['value'])
                ->description($aggregated['winnersDescription'])
                ->color('warning'),
        ];
    }
}
