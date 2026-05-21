<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Widgets;

use AIArmada\CommerceSupport\Traits\FormatsMoney;
use AIArmada\FilamentGrowth\Support\AccessibleGrowthRecords;
use AIArmada\Growth\Actions\AggregateExperimentMetrics;
use AIArmada\Growth\Enums\ExperimentStatus;
use AIArmada\Growth\Models\Assignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Gate;
use Throwable;

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
        $statsExperimentLimit = (int) config('filament-growth.tables.stats_experiment_limit', 10);
        $experimentCounts = app(AccessibleGrowthRecords::class)
            ->experiments(Experiment::query())
            ->selectRaw('COUNT(*) as total_experiments')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active_experiments', [ExperimentStatus::Active->value])
            ->first();
        $totalExperiments = (int) ($experimentCounts?->getAttribute('total_experiments') ?? 0);
        $activeExperiments = (int) ($experimentCounts?->getAttribute('active_experiments') ?? 0);

        $experiments = app(AccessibleGrowthRecords::class)
            ->experiments(Experiment::query())
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->limit(max(1, $statsExperimentLimit))
            ->get(['id', 'tracked_property_id', 'owner_type', 'owner_id', 'name', 'module_type', 'status', 'goal_event_name', 'winner_metric', 'created_at', 'updated_at']);

        $summary = $experiments->reduce(function (array $carry, Experiment $experiment): array {
            $metrics = $this->safeAggregateExperimentMetrics($experiment);

            if ($metrics === null) {
                return $carry;
            }

            $currency = (string) ($metrics['currency'] ?? config('signals.defaults.currency', 'MYR'));

            $carry['revenue_minor_by_currency'][$currency] = ($carry['revenue_minor_by_currency'][$currency] ?? 0) + (int) $metrics['totals']['revenue_minor'];
            $carry['winner_ready'] += (int) (is_string($metrics['winner_variant_id'] ?? null) && ($metrics['winner_variant_id'] ?? '') !== '');

            return $carry;
        }, [
            'revenue_minor_by_currency' => [],
            'winner_ready' => 0,
        ]);

        $revenueSummary = $this->formatRevenueSummary($summary['revenue_minor_by_currency']);
        $winnersDescriptionParts = [number_format((int) $summary['winner_ready']) . ' experiment(s) with winner-ready metrics'];

        if ($experiments->count() < $totalExperiments) {
            $winnersDescriptionParts[] = sprintf(
                'Based on latest %s of %s experiments',
                number_format($experiments->count()),
                number_format($totalExperiments),
            );
        }

        if ($revenueSummary['details'] !== null) {
            $winnersDescriptionParts[] = $revenueSummary['details'];
        }

        $winnersDescription = implode(' • ', $winnersDescriptionParts);
        $variantCount = app(AccessibleGrowthRecords::class)->variants(Variant::query())->count();
        $assignmentCount = app(AccessibleGrowthRecords::class)->assignments(Assignment::query())->count();

        return [
            Stat::make('Active Experiments', number_format($activeExperiments))
                ->description('Currently splitting traffic')
                ->color('success'),
            Stat::make('Variants', number_format($variantCount))
                ->description('Configured across all experiments')
                ->color('primary'),
            Stat::make('Assignments', number_format($assignmentCount))
                ->description('Sticky subject allocations recorded')
                ->color('info'),
            Stat::make('Tracked Revenue', $revenueSummary['value'])
                ->description($winnersDescription)
                ->color('warning'),
        ];
    }

    private function formatDisplayMoney(int $minor, string $currency): string
    {
        return $this->formatMinorMoney($minor, $currency);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function safeAggregateExperimentMetrics(Experiment $experiment): ?array
    {
        try {
            return app(AggregateExperimentMetrics::class)->handle($experiment);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, int>  $revenueByCurrency
     * @return array{value: string, details: string|null}
     */
    private function formatRevenueSummary(array $revenueByCurrency): array
    {
        if ($revenueByCurrency === []) {
            $currency = (string) config('signals.defaults.currency', 'MYR');

            return [
                'value' => $this->formatDisplayMoney(0, $currency),
                'details' => null,
            ];
        }

        if (count($revenueByCurrency) === 1) {
            $currency = array_key_first($revenueByCurrency);

            return [
                'value' => $this->formatDisplayMoney((int) $revenueByCurrency[$currency], (string) $currency),
                'details' => null,
            ];
        }

        $details = collect($revenueByCurrency)
            ->map(fn (int $minor, string $currency): string => $this->formatDisplayMoney($minor, $currency))
            ->implode(' / ');

        return [
            'value' => 'Mixed',
            'details' => $details,
        ];
    }
}
