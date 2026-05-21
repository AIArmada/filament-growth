<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth;

use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentGrowthServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-growth')
            ->hasViews('filament-growth')
            ->hasConfigFile('filament-growth');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(FilamentGrowthPlugin::class);
    }

    public function packageBooted(): void
    {
        Gate::policy(Experiment::class, Policies\ExperimentPolicy::class);
        Gate::policy(Variant::class, Policies\VariantPolicy::class);
    }
}
