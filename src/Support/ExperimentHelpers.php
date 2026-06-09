<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Support;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
use AIArmada\Growth\Models\Assignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class ExperimentHelpers
{
    /**
     * @param  Builder<Experiment>  $query
     * @return Builder<Experiment>
     */
    public static function applyOwnerSafeRelationCounts(Builder $query): Builder
    {
        $experimentTable = $query->getModel()->getTable();

        if ($query->getQuery()->columns === null) {
            $query->select($experimentTable . '.*');
        }

        return $query
            ->selectSub(static::ownerMatchedChildCount(Variant::class, $experimentTable), 'variants_count')
            ->selectSub(static::ownerMatchedChildCount(Assignment::class, $experimentTable), 'assignments_count');
    }

    /**
     * Build a correlated subquery counting children whose owner tuple matches the experiment's.
     *
     * This runs in SQL context where Eloquent's global OwnerScope doesn't apply, so
     * `withoutGlobalScope` is required to prevent double-scoping when the child model has
     * `HasOwner`. The manual `whereColumn` match ensures the child's owner_type/owner_id
     * match the experiment's (same tenant) OR both are null (global rows), which is the
     * correct semantics for cross-table owner-safe subquery counting.
     *
     * @template TChildModel of Model
     *
     * @param  class-string<TChildModel>  $childModelClass
     * @return Builder<TChildModel>
     */
    private static function ownerMatchedChildCount(string $childModelClass, string $experimentTable): Builder
    {
        $childModel = new $childModelClass;
        $childTable = $childModel->getTable();
        $experimentOwnerColumns = OwnerTupleColumns::forModelClass(Experiment::class);
        $childOwnerColumns = OwnerTupleColumns::forModelClass($childModelClass);

        /** @var Builder<TChildModel> $childQuery */
        $childQuery = $childModelClass::query();

        if (method_exists($childModelClass, 'scopeWithoutOwnerScope')) {
            $childQuery = $childQuery->withoutGlobalScope(OwnerScope::class);
        }

        $childQuery = $childQuery
            ->selectRaw('count(*)')
            ->whereColumn($childTable . '.experiment_id', $experimentTable . '.id')
            ->where(function (Builder $query) use ($childOwnerColumns, $childTable, $experimentOwnerColumns, $experimentTable): void {
                $query
                    ->where(function (Builder $ownerMatchedQuery) use ($childOwnerColumns, $childTable, $experimentOwnerColumns, $experimentTable): void {
                        $ownerMatchedQuery
                            ->whereColumn(
                                $childTable . '.' . $childOwnerColumns->ownerTypeColumn,
                                $experimentTable . '.' . $experimentOwnerColumns->ownerTypeColumn,
                            )
                            ->whereColumn(
                                $childTable . '.' . $childOwnerColumns->ownerIdColumn,
                                $experimentTable . '.' . $experimentOwnerColumns->ownerIdColumn,
                            );
                    })
                    ->orWhere(function (Builder $globalQuery) use ($childOwnerColumns, $childTable, $experimentOwnerColumns, $experimentTable): void {
                        $globalQuery
                            ->whereNull($childTable . '.' . $childOwnerColumns->ownerTypeColumn)
                            ->whereNull($childTable . '.' . $childOwnerColumns->ownerIdColumn)
                            ->whereNull($experimentTable . '.' . $experimentOwnerColumns->ownerTypeColumn)
                            ->whereNull($experimentTable . '.' . $experimentOwnerColumns->ownerIdColumn);
                    });
            });

        /** @var Builder<TChildModel> $childQuery */
        return $childQuery;
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
