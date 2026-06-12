<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Pages;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Traits\FormatsMoney;
use AIArmada\FilamentGrowth\Resources\ExperimentResource;
use AIArmada\Growth\Actions\AggregateExperimentMetrics;
use AIArmada\Growth\Enums\ExperimentModuleType;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Signals\Models\TrackedProperty;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Throwable;
use UnitEnum;

/**
 * @property-read Schema $form
 */
final class ExperimentResultsPage extends Page implements HasForms
{
    use FormatsMoney {
        formatMoney as private formatMinorMoney;
    }
    use InteractsWithForms;

    public mixed $experimentId = null;

    public mixed $chartMetric = 'revenue_per_visitor';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Results';

    protected static ?string $title = 'Experiment Results';

    protected static ?string $slug = 'growth/results';

    protected string $view = 'filament-growth::pages.experiment-results';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-growth.navigation.group', 'Growth');
    }

    public static function getNavigationSort(): int
    {
        return (int) config('filament-growth.resources.navigation_sort.results', 11);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-growth.features.results', true)
            && static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user !== null
            && parent::canAccess()
            && Gate::forUser($user)->allows('viewAny', Experiment::class);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $requestedExperimentId = $this->normalizeRequestedExperimentId(request()->query('experiment'), null);

        $this->experimentId = $requestedExperimentId ?? $this->defaultExperimentId();
        $this->chartMetric = $this->normalizeChartMetric($this->chartMetric);

        $this->form->fill([
            'experimentId' => $this->experimentId,
            'chartMetric' => $this->chartMetric,
        ]);

        $this->loadResults();
    }

    public function getTitle(): string
    {
        return 'Experiment Results';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Grid::make(2)
                ->schema([
                    Forms\Components\Select::make('experimentId')
                        ->label('Experiment')
                        ->options($this->experimentOptions())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(fn (): null => $this->loadResults()),

                    Forms\Components\Select::make('chartMetric')
                        ->label('Chart Metric')
                        ->options($this->metricOptions())
                        ->default('revenue_per_visitor')
                        ->live()
                        ->afterStateUpdated(fn (): null => $this->loadResults()),
                ]),
        ]);
    }

    public function loadResults(): void
    {
        $requestedExperimentId = $this->normalizeRequestedExperimentId($this->experimentId, null);
        $this->chartMetric = $this->normalizeChartMetric($this->chartMetric);

        if ($requestedExperimentId === null) {
            $this->experimentId = $this->defaultExperimentId();

            return;
        }

        $this->experimentId = $requestedExperimentId;

        if (! Experiment::query()->whereKey($this->experimentId)->exists()) {
            $this->experimentId = null;
        }
    }

    #[Computed]
    public function selectedExperiment(): ?Experiment
    {
        $experimentId = $this->normalizeRequestedExperimentId($this->experimentId, null);

        if ($experimentId === null) {
            return null;
        }

        $experiment = Experiment::query()->whereKey($experimentId)->first();

        if (! $experiment instanceof Experiment) {
            return null;
        }

        $experiment->setRelation(
            'trackedProperty',
            $this->findTrackedPropertyForExperiment($experiment),
        );

        return $experiment;
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function getResults(): array
    {
        $experiment = $this->selectedExperiment();

        if (! $experiment instanceof Experiment) {
            return [];
        }

        return $this->safeAggregateExperimentMetrics($experiment);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function getVariantComparison(): array
    {
        /** @var array<int, array<string, float|int|string|null>> $variants */
        $variants = $this->getResults()['variants'] ?? [];

        return $this->buildVariantComparison($variants, $this->normalizeChartMetric($this->chartMetric));
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function getWinnerSummary(): ?array
    {
        return $this->buildWinnerSummary($this->getResults());
    }

    public function formatMoney(int $minor): string
    {
        return $this->formatMinorMoney($minor, $this->currentCurrency());
    }

    public function formatMetricValue(string $metric, array $variant): string
    {
        $value = $this->numericMetricValue($metric, $variant);

        return match ($metric) {
            'conversion_rate' => number_format($value * 100, 2) . '%',
            'revenue_minor', 'revenue_per_visitor' => $this->formatMoney((int) round($value)),
            default => number_format((int) round($value)),
        };
    }

    public function metricLabel(string $metric): string
    {
        return $this->metricOptions()[$metric] ?? ucfirst(str_replace('_', ' ', $metric));
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        if (config('filament-growth.features.experiments', true)) {
            $actions[] = Action::make('manageExperiments')
                ->label('Manage Experiments')
                ->icon('heroicon-o-beaker')
                ->visible(fn (): bool => ExperimentResource::canViewAny())
                ->url(fn (): string => ExperimentResource::getUrl('index'));
        }

        $actions[] = Action::make('refreshResults')
            ->label('Refresh')
            ->icon('heroicon-o-arrow-path')
            ->action(fn (): null => $this->loadResults());

        return $actions;
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $results = $this->getResults();
        $chartMetric = $this->normalizeChartMetric($this->chartMetric);

        return [
            'experiment' => $this->selectedExperiment(),
            'results' => $results,
            'winnerSummary' => $this->getWinnerSummary(),
            'variantComparison' => $this->getVariantComparison(),
            'winnerMetricLabel' => $this->metricLabel((string) ($results['winner_metric'] ?? 'revenue_per_visitor')),
            'chartMetricLabel' => $this->metricLabel($chartMetric),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function experimentOptions(): array
    {
        return Experiment::query()
            ->orderByDesc('created_at')
            ->get(['id', 'name'])
            ->mapWithKeys(fn (Experiment $experiment): array => [(string) $experiment->getKey() => (string) $experiment->name])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function metricOptions(): array
    {
        return [
            'revenue_per_visitor' => 'Revenue Per Visitor',
            'revenue_minor' => 'Revenue',
            'conversion_rate' => 'Conversion Rate',
            'checkout_starts' => 'Checkout Starts',
            'purchases' => 'Purchases',
        ];
    }

    private function defaultExperimentId(): ?string
    {
        $experimentId = Experiment::query()
            ->orderByDesc('created_at')
            ->value('id');

        return is_scalar($experimentId) ? (string) $experimentId : null;
    }

    private function normalizeRequestedExperimentId(mixed $requestedExperimentId, ?string $fallback): ?string
    {
        if (is_scalar($requestedExperimentId)) {
            $normalizedExperimentId = mb_trim((string) $requestedExperimentId);

            if ($normalizedExperimentId !== '') {
                return $normalizedExperimentId;
            }
        }

        return $fallback;
    }

    private function normalizeChartMetric(mixed $chartMetric): string
    {
        if (is_scalar($chartMetric)) {
            $normalizedChartMetric = mb_trim((string) $chartMetric);

            if (array_key_exists($normalizedChartMetric, $this->metricOptions())) {
                return $normalizedChartMetric;
            }
        }

        return 'revenue_per_visitor';
    }

    /**
     * @param  array<int, array<string, float|int|string|null>>  $variants
     * @return array<int, array<string, mixed>>
     */
    private function buildVariantComparison(array $variants, string $metric): array
    {
        $maxValue = collect($variants)
            ->map(fn (array $variant): float => $this->numericMetricValue($metric, $variant))
            ->max() ?? 0;

        $colors = [
            'bg-primary-500',
            'bg-success-500',
            'bg-warning-500',
            'bg-info-500',
            'bg-danger-500',
        ];

        return collect($variants)
            ->values()
            ->map(function (array $variant, int $index) use ($colors, $maxValue, $metric): array {
                $value = $this->numericMetricValue($metric, $variant);

                return [
                    'variant_id' => (string) ($variant['variant_id'] ?? ''),
                    'code' => (string) ($variant['code'] ?? ''),
                    'name' => (string) ($variant['name'] ?? 'Variant'),
                    'value_label' => $this->formatMetricValue($metric, $variant),
                    'percent' => ($maxValue > 0 && $value > 0) ? max(4, round(($value / $maxValue) * 100, 2)) : 0,
                    'color_class' => $colors[$index % count($colors)],
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $results
     * @return array<string, mixed>|null
     */
    private function buildWinnerSummary(array $results): ?array
    {
        $winnerVariantId = $results['winner_variant_id'] ?? null;

        if (! is_string($winnerVariantId) || $winnerVariantId === '') {
            return null;
        }

        $variants = $results['variants'] ?? [];

        if (! is_array($variants)) {
            $variants = [];
        }

        /** @var list<array<string, mixed>> $variants */
        $winner = collect($variants)->firstWhere('variant_id', $winnerVariantId);

        if (! is_array($winner)) {
            return null;
        }

        return [
            'variant_id' => $winnerVariantId,
            'code' => (string) ($winner['code'] ?? ''),
            'name' => (string) ($winner['name'] ?? 'Winner'),
            'winner_metric_label' => $this->metricLabel((string) ($results['winner_metric'] ?? 'revenue_per_visitor')),
            'winner_metric_value' => $this->formatMetricValue((string) ($results['winner_metric'] ?? 'revenue_per_visitor'), $winner),
            'revenue' => $this->formatMoney((int) ($winner['revenue_minor'] ?? 0)),
            'conversion_rate' => $this->formatMetricValue('conversion_rate', $winner),
            'purchases' => number_format((int) ($winner['purchases'] ?? 0)),
        ];
    }

    /**
     * @param  array<string, mixed>  $variant
     */
    private function numericMetricValue(string $metric, array $variant): float
    {
        $value = $variant[$metric] ?? 0;

        return is_numeric($value) ? (float) $value : 0.0;
    }

    public function moduleLabel(): ?string
    {
        $experiment = $this->selectedExperiment();

        if (! $experiment instanceof Experiment) {
            return null;
        }

        return ExperimentModuleType::labelFor($experiment->module_type);
    }

    private function currentCurrency(): string
    {
        return (string) ($this->getResults()['currency'] ?? $this->selectedExperiment()?->trackedProperty?->currency ?? config('signals.defaults.currency', 'MYR'));
    }

    private function findTrackedPropertyForExperiment(Experiment $experiment): ?TrackedProperty
    {
        $query = OwnerUiScope::applyForRecordOwner(
            TrackedProperty::query(),
            $experiment,
            recordConfigKey: 'growth.features.owner',
            queryConfigKey: 'signals.owner',
        );

        $trackedProperty = $query
            ->whereKey($experiment->tracked_property_id)
            ->first();

        return $trackedProperty instanceof TrackedProperty ? $trackedProperty : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function safeAggregateExperimentMetrics(Experiment $experiment): array
    {
        try {
            return app(AggregateExperimentMetrics::class)->handle($experiment);
        } catch (Throwable) {
            return [];
        }
    }
}
