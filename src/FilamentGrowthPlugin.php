<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth;

use AIArmada\FilamentGrowth\Pages\ExperimentResultsPage;
use AIArmada\FilamentGrowth\Pages\GrowthDashboard;
use AIArmada\FilamentGrowth\Pages\ManageGrowthSettings;
use AIArmada\FilamentGrowth\Resources\ExperimentResource;
use AIArmada\FilamentGrowth\Resources\VariantResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentGrowthPlugin implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(self::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-growth';
    }

    public function register(Panel $panel): void
    {
        $pages = [];
        $resources = [];

        if (config('filament-growth.features.dashboard', true)) {
            $pages[] = GrowthDashboard::class;
        }

        if (config('filament-growth.features.results', true)) {
            $pages[] = ExperimentResultsPage::class;
        }

        if (config('filament-growth.features.settings_page', true)) {
            $pages[] = ManageGrowthSettings::class;
        }

        if (config('filament-growth.features.experiments', true)) {
            $resources[] = ExperimentResource::class;
        }

        if (config('filament-growth.features.variants', true)) {
            $resources[] = VariantResource::class;
        }

        $panel
            ->pages($pages)
            ->resources($resources);
    }

    public function boot(Panel $panel): void {}
}
