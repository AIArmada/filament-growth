<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Authorization;

use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Growth\Queries\ExperimentOwnership;
use AIArmada\Signals\Features\Ingestion\Models\TrackedProperty;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class ExperimentAccess
{
    /**
     * @param  Builder<Experiment>  $query
     * @return Builder<Experiment>
     */
    public static function applyOwnerSafeRelationCounts(Builder $query): Builder
    {
        return $query->withOwnerMatchedCounts();
    }

    public static function can(string $ability, mixed $arguments = []): bool
    {
        $actor = self::actor();

        return $actor !== null && Gate::forUser($actor)->allows($ability, $arguments);
    }

    public static function authorize(string $ability, mixed $arguments = []): void
    {
        $actor = self::actor();

        if (! $actor instanceof Authenticatable) {
            throw new AuthorizationException('An authenticated Filament user is required for this growth action.');
        }

        Gate::forUser($actor)->authorize($ability, $arguments);
    }

    public static function canViewExperiment(Experiment $experiment): bool
    {
        try {
            return ExperimentOwnership::scopeExperiments(Experiment::query())
                ->whereKey($experiment->getKey())
                ->exists();
        } catch (NoCurrentOwnerException | InvalidArgumentException | AuthorizationException) {
            return false;
        }
    }

    public static function canViewVariant(Variant $variant): bool
    {
        try {
            return ExperimentOwnership::scopeVariants(Variant::query())
                ->whereKey($variant->getKey())
                ->exists();
        } catch (NoCurrentOwnerException | InvalidArgumentException | AuthorizationException) {
            return false;
        }
    }

    public static function canCreateVariant(): bool
    {
        return OwnerUiScope::canCreate(Variant::class)
            && self::hasWritableExperiment();
    }

    public static function canCreateExperiment(): bool
    {
        if (Experiment::ownerScopeConfig()->enabled && ! OwnerUiScope::canCreate(Experiment::class)) {
            return false;
        }

        return self::hasWritableTrackedProperty();
    }

    public static function canDeleteAnyExperiment(): bool
    {
        return self::can('deleteAny', Experiment::class);
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

        try {
            return ExperimentOwnership::scopeExperiments(Experiment::query(), writable: true)
                ->whereKey($experiment->getKey())
                ->exists();
        } catch (NoCurrentOwnerException | InvalidArgumentException | AuthorizationException) {
            return false;
        }
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
                return (! Variant::ownerScopeConfig()->enabled || self::sameOwnerTuple($variant, $experiment))
                    && self::canMutateExperiment($experiment);
            }
        }

        try {
            return ExperimentOwnership::scopeVariants(Variant::query(), writable: true)
                ->whereKey($variant->getKey())
                ->exists();
        } catch (NoCurrentOwnerException | InvalidArgumentException | AuthorizationException) {
            return false;
        }
    }

    public static function hasWritableTrackedProperty(): bool
    {
        try {
            if (! TrackedProperty::ownerScopeConfig()->enabled) {
                return true;
            }

            return ExperimentOwnership::scopeOwner(TrackedProperty::query(), writable: true)->exists();
        } catch (NoCurrentOwnerException | InvalidArgumentException | AuthorizationException) {
            return false;
        }
    }

    public static function hasReadableExperiment(): bool
    {
        try {
            return ExperimentOwnership::scopeExperiments(Experiment::query())->exists();
        } catch (NoCurrentOwnerException | InvalidArgumentException | AuthorizationException) {
            return false;
        }
    }

    public static function hasWritableExperiment(): bool
    {
        try {
            return ExperimentOwnership::scopeExperiments(Experiment::query(), writable: true)->exists();
        } catch (NoCurrentOwnerException | InvalidArgumentException | AuthorizationException) {
            return false;
        }
    }

    public static function hasReadableVariant(): bool
    {
        try {
            return ExperimentOwnership::scopeVariants(Variant::query())->exists();
        } catch (NoCurrentOwnerException | InvalidArgumentException | AuthorizationException) {
            return false;
        }
    }

    public static function hasWritableVariant(): bool
    {
        try {
            return ExperimentOwnership::scopeVariants(Variant::query(), writable: true)->exists();
        } catch (NoCurrentOwnerException | InvalidArgumentException | AuthorizationException) {
            return false;
        }
    }

    private static function actor(): ?Authenticatable
    {
        $actor = Filament::auth()->user();

        return $actor instanceof Authenticatable ? $actor : null;
    }

    private static function sameOwnerTuple(Experiment | Variant $experiment, TrackedProperty | Experiment $property): bool
    {
        $experimentColumns = OwnerTupleColumns::forModelClass($experiment::class);
        $propertyColumns = OwnerTupleColumns::forModelClass($property::class);

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
