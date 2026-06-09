<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Support;

use AIArmada\CommerceSupport\Traits\FormatsMoney;
use AIArmada\Growth\Actions\AggregateExperimentMetrics;
use AIArmada\Growth\Enums\ExperimentStatus;
use AIArmada\Growth\Models\Assignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use Throwable;

final class GrowthStatsAggregator
{
    protected ?string $currency = 'MYR';

    use FormatsMoney {
        formatMoney as private formatMinorMoney;
    }

    /**
     * @return array{activeExperiments: int, variantCount: int, assignmentCount: int, winnersDescription: string, revenueSummary: array{value: string, details: string|null}}
     */
    public static function aggregate(): array
    {
        $statsExperimentLimit = (int) config('filament-growth.tables.stats_experiment_limit', 10);
        $experimentCounts = Experiment::query()
            ->selectRaw('COUNT(*) as total_experiments')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active_experiments', [ExperimentStatus::Active->value])
            ->first();
        $totalExperiments = (int) ($experimentCounts?->getAttribute('total_experiments') ?? 0);
        $activeExperiments = (int) ($experimentCounts?->getAttribute('active_experiments') ?? 0);

        $experiments = Experiment::query()
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->limit(max(1, $statsExperimentLimit))
            ->get(['id', 'tracked_property_id', 'owner_type', 'owner_id', 'name', 'module_type', 'status', 'goal_event_name', 'winner_metric', 'created_at', 'updated_at']);

        $summary = $experiments->reduce(function (array $carry, Experiment $experiment): array {
            $metrics = self::safeAggregateExperimentMetrics($experiment);

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

        $instance = new self;
        $revenueSummary = $instance->formatRevenueSummary($summary['revenue_minor_by_currency']);
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
        $variantCount = Variant::query()->count();
        $assignmentCount = Assignment::query()->count();

        return [
            'activeExperiments' => $activeExperiments,
            'variantCount' => $variantCount,
            'assignmentCount' => $assignmentCount,
            'winnersDescription' => $winnersDescription,
            'revenueSummary' => $revenueSummary,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function safeAggregateExperimentMetrics(Experiment $experiment): ?array
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

    private function formatDisplayMoney(int $minor, string $currency): string
    {
        return $this->formatMinorMoney($minor, $currency);
    }
}
