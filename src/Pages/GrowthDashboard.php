<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Pages;

use AIArmada\FilamentGrowth\Widgets\ExperimentWinnersWidget;
use AIArmada\FilamentGrowth\Widgets\GrowthStatsWidget;
use AIArmada\Growth\Models\Experiment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Illuminate\Support\Facades\Gate;

final class GrowthDashboard extends Dashboard
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $title = 'Growth Dashboard';

    protected static ?string $slug = 'growth';

    public static function getNavigationGroup(): ?string
    {
        return config('filament-growth.navigation_group', 'Growth');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-growth.resources.navigation_sort.dashboard', 10);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-growth.features.dashboard', true)
            && static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user !== null
            && parent::canAccess()
            && Gate::forUser($user)->allows('viewAny', Experiment::class);
    }

    public function getColumns(): int | array
    {
        return [
            'md' => 2,
            'xl' => 4,
        ];
    }

    public function getWidgets(): array
    {
        $widgets = [];

        if (config('filament-growth.features.widgets', true)) {
            $widgets[] = GrowthStatsWidget::class;
            $widgets[] = ExperimentWinnersWidget::class;
        }

        return $widgets;
    }

    protected function getHeaderActions(): array
    {
        if (! config('filament-growth.features.results', true)) {
            return [];
        }

        return [
            Action::make('viewResults')
                ->label('View Results')
                ->icon('heroicon-o-chart-bar')
                ->visible(fn (): bool => ExperimentResultsPage::canAccess())
                ->url(fn (): string => ExperimentResultsPage::getUrl()),
        ];
    }
}
