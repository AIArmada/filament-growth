<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Support;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Features\Ingestion\Models\TrackedProperty;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

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

    /**
     * @param  Builder<Experiment>  $query
     * @return Builder<Experiment>
     */
    public static function scopeExperiments(Builder $query, bool $writable = false): Builder
    {
        $query = OwnerUiScope::apply($query, includeGlobal: $writable ? false : null);

        if (! Experiment::ownerScopeConfig()->enabled) {
            if (! TrackedProperty::ownerScopeConfig()->enabled) {
                return $query;
            }

            return $query->whereIn(
                $query->getModel()->qualifyColumn('tracked_property_id'),
                OwnerUiScope::apply(TrackedProperty::query(), includeGlobal: $writable ? false : null)->select('id'),
            );
        }

        $experiment = $query->getModel();
        $property = new TrackedProperty;
        $experimentColumns = OwnerTupleColumns::forModelClass(Experiment::class);
        $propertyColumns = OwnerTupleColumns::forModelClass(TrackedProperty::class);
        $propertyType = $property->qualifyColumn($propertyColumns->ownerTypeColumn);
        $propertyId = $property->qualifyColumn($propertyColumns->ownerIdColumn);
        $experimentType = $experiment->qualifyColumn($experimentColumns->ownerTypeColumn);
        $experimentId = $experiment->qualifyColumn($experimentColumns->ownerIdColumn);

        $matched = TrackedProperty::query()
            ->withoutGlobalScope(OwnerScope::class)
            ->selectRaw('1')
            ->whereColumn($property->getQualifiedKeyName(), $experiment->qualifyColumn('tracked_property_id'))
            ->where(function (Builder $query) use ($propertyType, $propertyId, $experimentType, $experimentId): void {
                $query->where(function (Builder $owned) use ($propertyType, $propertyId, $experimentType, $experimentId): void {
                    $owned->whereColumn($propertyType, $experimentType)
                        ->whereColumn($propertyId, $experimentId);
                })->orWhere(function (Builder $global) use ($propertyType, $propertyId, $experimentType, $experimentId): void {
                    $global->whereNull($propertyType)
                        ->whereNull($propertyId)
                        ->whereNull($experimentType)
                        ->whereNull($experimentId);
                });
            });

        return $query->whereExists($matched);
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
        return Gate::allows('deleteAny', Experiment::class);
    }

    public static function canMutateExperiment(Experiment $experiment): bool
    {
        if (! OwnerUiScope::canMutateRecord($experiment)) {
            return false;
        }

        $experimentScopingEnabled = Experiment::ownerScopeConfig()->enabled;
        $propertyScopingEnabled = TrackedProperty::ownerScopeConfig()->enabled;

        if (! $experimentScopingEnabled && ! $propertyScopingEnabled) {
            return true;
        }

        if ($experiment->relationLoaded('trackedProperty')) {
            $property = $experiment->getRelation('trackedProperty');

            if ($property instanceof TrackedProperty
                && (string) $property->getKey() === (string) $experiment->tracked_property_id) {
                if (! $experimentScopingEnabled) {
                    return OwnerUiScope::canMutateRecord($property);
                }

                return self::sameOwnerTuple($experiment, $property);
            }
        }

        return self::scopeExperiments(Experiment::query(), writable: true)
            ->whereKey($experiment->getKey())
            ->exists();
    }

    public static function canMutateVariant(Variant $variant): bool
    {
        if (! OwnerUiScope::canMutateRecord($variant)) {
            return false;
        }

        if (! Experiment::ownerScopeConfig()->enabled && ! TrackedProperty::ownerScopeConfig()->enabled) {
            return true;
        }

        if ($variant->relationLoaded('experiment')) {
            $experiment = $variant->getRelation('experiment');

            if ($experiment instanceof Experiment
                && (string) $experiment->getKey() === (string) $variant->experiment_id
                && $experiment->relationLoaded('trackedProperty')) {
                return self::canMutateExperiment($experiment);
            }
        }

        return self::scopeExperiments(Experiment::query(), writable: true)
            ->whereKey($variant->experiment_id)
            ->exists();
    }

    private static function sameOwnerTuple(Experiment $experiment, TrackedProperty $property): bool
    {
        $experimentColumns = OwnerTupleColumns::forModelClass(Experiment::class);
        $propertyColumns = OwnerTupleColumns::forModelClass(TrackedProperty::class);

        $experimentType = $experiment->getAttribute($experimentColumns->ownerTypeColumn);
        $experimentId = $experiment->getAttribute($experimentColumns->ownerIdColumn);
        $propertyType = $property->getAttribute($propertyColumns->ownerTypeColumn);
        $propertyId = $property->getAttribute($propertyColumns->ownerIdColumn);

        if ($experimentType === null && $experimentId === null) {
            return $propertyType === null && $propertyId === null;
        }

        if ($experimentType === null || $experimentId === null || $propertyType === null || $propertyId === null) {
            return false;
        }

        return (string) $experimentType === (string) $propertyType
            && (string) $experimentId === (string) $propertyId;
    }
}
