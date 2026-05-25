<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Widgets;

use AIArmada\CommerceSupport\Traits\FormatsMoney;
use AIArmada\FilamentGrowth\Pages\ExperimentResultsPage;
use AIArmada\FilamentGrowth\Support\AccessibleGrowthRecords;
use AIArmada\Growth\Actions\AggregateExperimentMetrics;
use AIArmada\Growth\Enums\ExperimentModuleType;
use AIArmada\Growth\Models\Experiment;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Gate;
use Throwable;

final class ExperimentWinnersWidget extends Widget
{
    use FormatsMoney {
        formatMoney as private formatMinorMoney;
    }

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    /** @phpstan-ignore-next-line property.defaultValue */
    protected string $view = 'filament-growth::widgets.experiment-winners';

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user !== null
            && parent::canView()
            && Gate::forUser($user)->allows('viewAny', Experiment::class);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getExperimentSnapshots(): array
    {
        return app(AccessibleGrowthRecords::class)
            ->experiments(Experiment::query())
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->map(function (Experiment $experiment): ?array {
                $metrics = $this->safeAggregateExperimentMetrics($experiment);

                if ($metrics === null) {
                    return null;
                }

                $variants = $metrics['variants'] ?? [];

                if (! is_array($variants)) {
                    $variants = [];
                }

                /** @var list<array<string, mixed>> $variants */
                $winnerVariant = collect($variants)->firstWhere('variant_id', $metrics['winner_variant_id'] ?? null);

                return [
                    'name' => (string) $experiment->name,
                    'module_type' => ExperimentModuleType::labelFor($experiment->module_type),
                    'status' => $experiment->status->label(),
                    'winner_name' => is_array($winnerVariant) ? (string) ($winnerVariant['name'] ?? 'Pending') : 'Pending',
                    'winner_metric_label' => $this->metricLabel((string) $metrics['winner_metric']),
                    'winner_metric_value' => is_array($winnerVariant)
                        ? $this->formatMetricValue((string) $metrics['winner_metric'], $winnerVariant, (string) $metrics['currency'])
                        : '—',
                    'revenue' => $this->formatMoney((int) $metrics['totals']['revenue_minor'], (string) $metrics['currency']),
                    'assignments' => number_format((int) $metrics['totals']['assignments']),
                    'results_url' => $this->resultsUrl($experiment),
                ];
            })
            ->filter(static fn (?array $snapshot): bool => is_array($snapshot))
            ->values()
            ->all();
    }

    public function formatMoney(int $minor, string $currency): string
    {
        return $this->formatMinorMoney($minor, $currency);
    }

    /**
     * @param  array<string, mixed>  $variant
     */
    public function formatMetricValue(string $metric, array $variant, string $currency): string
    {
        $value = $variant[$metric] ?? 0;
        $numericValue = is_numeric($value) ? (float) $value : 0.0;

        return match ($metric) {
            'conversion_rate' => number_format($numericValue * 100, 2) . '%',
            'revenue_minor', 'revenue_per_visitor' => $this->formatMoney((int) round($numericValue), $currency),
            default => number_format((int) round($numericValue)),
        };
    }

    public function metricLabel(string $metric): string
    {
        return match ($metric) {
            'revenue_per_visitor' => 'Revenue Per Visitor',
            'revenue_minor' => 'Revenue',
            'conversion_rate' => 'Conversion Rate',
            'checkout_starts' => 'Checkout Starts',
            'purchases' => 'Purchases',
            default => ucfirst(str_replace('_', ' ', $metric)),
        };
    }

    private function resultsUrl(Experiment $experiment): string
    {
        if (! config('filament-growth.features.results', true)) {
            return '#';
        }

        try {
            return ExperimentResultsPage::getUrl(['experiment' => $experiment->getKey()]);
        } catch (Throwable) {
            return '#';
        }
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
}
