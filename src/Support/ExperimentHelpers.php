<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Support;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Database\Eloquent\Builder;

final class ExperimentHelpers
{
    /**
     * @param  Builder<Experiment>  $query
     * @return Builder<Experiment>
     */
    public static function applyOwnerSafeRelationCounts(Builder $query): Builder
    {
        return $query->withOwnerMatchedCounts();
    }

    public static function canCreateExperiment(): bool
    {
        if (Experiment::ownerScopeConfig()->enabled && ! OwnerUiScope::canCreate(Experiment::class)) {
            return false;
        }

        if (! TrackedProperty::ownerScopeConfig()->enabled) {
            return true;
        }

        return OwnerUiScope::apply(TrackedProperty::query(), includeGlobal: false)->exists();
    }

    public static function canDeleteAnyExperiment(): bool
    {
        return true;
    }

    public static function canMutateViaTrackedProperty(Experiment $experiment): bool
    {
        if (! TrackedProperty::ownerScopeConfig()->enabled) {
            return false;
        }

        return OwnerUiScope::apply(TrackedProperty::query(), includeGlobal: false)
            ->whereKey($experiment->tracked_property_id)
            ->exists();
    }
}
