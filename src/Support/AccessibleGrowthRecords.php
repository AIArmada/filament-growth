<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Support;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
use AIArmada\Growth\Actions\ScopeSignalQueryToOwner;
use AIArmada\Growth\Models\Assignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

final class AccessibleGrowthRecords
{
    public function __construct(
        private readonly ScopeSignalQueryToOwner $scopeSignalQueryToOwner,
    ) {}

    /**
     * @param  Builder<Experiment>  $query
     * @return Builder<Experiment>
     */
    public function experiments(Builder $query): Builder
    {
        if (Experiment::ownerScopeConfig()->enabled) {
            return $this->constrainExperimentsToConsistentTrackedProperties(
                OwnerUiScope::apply($query),
            );
        }

        if (! TrackedProperty::ownerScopeConfig()->enabled) {
            return $query;
        }

        return $query->whereIn(
            $query->getModel()->qualifyColumn('tracked_property_id'),
            $this->accessibleTrackedProperties(TrackedProperty::query())->select('id'),
        );
    }

    /**
     * @param  Builder<Experiment>  $query
     * @return Builder<Experiment>
     */
    public function writableExperiments(Builder $query): Builder
    {
        if (Experiment::ownerScopeConfig()->enabled) {
            if (! OwnerUiScope::canCreate(Experiment::class)) {
                return $query->whereRaw('1 = 0');
            }

            return $this->constrainExperimentsToConsistentTrackedProperties(
                OwnerUiScope::apply($query, includeGlobal: false),
            );
        }

        if (! TrackedProperty::ownerScopeConfig()->enabled) {
            return $query;
        }

        return $query->whereIn(
            $query->getModel()->qualifyColumn('tracked_property_id'),
            $this->writableTrackedProperties(TrackedProperty::query())->select('id'),
        );
    }

    /**
     * @param  Builder<Variant>  $query
     * @return Builder<Variant>
     */
    public function variants(Builder $query): Builder
    {
        if (Variant::ownerScopeConfig()->enabled) {
            return OwnerUiScope::apply($query)
                ->whereIn(
                    $query->getModel()->qualifyColumn('experiment_id'),
                    $this->experiments(Experiment::query())->select('id'),
                );
        }

        if (! TrackedProperty::ownerScopeConfig()->enabled) {
            return $query;
        }

        return $query->whereIn(
            $query->getModel()->qualifyColumn('experiment_id'),
            $this->experiments(Experiment::query())->select('id'),
        );
    }

    /**
     * @param  Builder<Assignment>  $query
     * @return Builder<Assignment>
     */
    public function assignments(Builder $query): Builder
    {
        if (Assignment::ownerScopeConfig()->enabled) {
            return OwnerUiScope::apply($query)
                ->whereIn(
                    $query->getModel()->qualifyColumn('experiment_id'),
                    $this->experiments(Experiment::query())->select('id'),
                );
        }

        if (! TrackedProperty::ownerScopeConfig()->enabled) {
            return $query;
        }

        return $query->whereIn(
            $query->getModel()->qualifyColumn('experiment_id'),
            $this->experiments(Experiment::query())->select('id'),
        );
    }

    /**
     * @param  Builder<TrackedProperty>  $query
     * @return Builder<TrackedProperty>
     */
    public function accessibleTrackedProperties(Builder $query): Builder
    {
        if (! TrackedProperty::ownerScopeConfig()->enabled) {
            return $query;
        }

        $owner = OwnerContext::resolve();

        if ($owner === null && ! OwnerContext::isExplicitGlobal()) {
            return $query->whereRaw('1 = 0');
        }

        return $this->scopeSignalQueryToOwner->handle(
            $query,
            $owner,
            TrackedProperty::ownerScopeConfig()->includeGlobal,
        );
    }

    /**
     * @param  Builder<TrackedProperty>  $query
     * @return Builder<TrackedProperty>
     */
    public function writableTrackedProperties(Builder $query): Builder
    {
        if (! TrackedProperty::ownerScopeConfig()->enabled) {
            return $query;
        }

        $owner = OwnerContext::resolve();

        if ($owner === null && ! OwnerContext::isExplicitGlobal()) {
            return $query->whereRaw('1 = 0');
        }

        return $this->scopeSignalQueryToOwner->handle($query, $owner, false);
    }

    public function findTrackedPropertyForExperiment(Experiment $experiment): ?TrackedProperty
    {
        return $this->resolveTrackedPropertyForExperiment($experiment, forMutation: false);
    }

    public function findWritableTrackedPropertyForExperiment(Experiment $experiment): ?TrackedProperty
    {
        return $this->resolveTrackedPropertyForExperiment($experiment, forMutation: true);
    }

    public function findExperiment(mixed $id): ?Experiment
    {
        if (! is_scalar($id) || (string) $id === '') {
            return null;
        }

        $experiment = $this->experiments(Experiment::query())
            ->whereKey((string) $id)
            ->first();

        return $experiment instanceof Experiment ? $experiment : null;
    }

    public function findWritableExperiment(mixed $id): ?Experiment
    {
        if (! is_scalar($id) || (string) $id === '') {
            return null;
        }

        $experiment = $this->writableExperiments(Experiment::query())
            ->whereKey((string) $id)
            ->first();

        return $experiment instanceof Experiment ? $experiment : null;
    }

    public function canViewExperiment(Experiment $experiment): bool
    {
        return $this->findExperiment($experiment->getKey()) instanceof Experiment;
    }

    public function canViewAnyExperiments(): bool
    {
        if ($this->canCreateExperiments()) {
            return true;
        }

        if (Experiment::ownerScopeConfig()->enabled && ! OwnerUiScope::canCreate(Experiment::class)) {
            return false;
        }

        return $this->experiments(Experiment::query())->exists();
    }

    public function canCreateExperiments(): bool
    {
        if (Experiment::ownerScopeConfig()->enabled && ! OwnerUiScope::canCreate(Experiment::class)) {
            return false;
        }

        if (! TrackedProperty::ownerScopeConfig()->enabled) {
            return true;
        }

        return $this->writableTrackedProperties(TrackedProperty::query())->exists();
    }

    public function canMutateExperiment(Experiment $experiment): bool
    {
        return $this->findWritableTrackedPropertyForExperiment($experiment) instanceof TrackedProperty;
    }

    public function canViewAnyVariants(): bool
    {
        if ($this->canCreateVariants()) {
            return true;
        }

        if (Variant::ownerScopeConfig()->enabled && ! OwnerUiScope::canCreate(Variant::class)) {
            return false;
        }

        return $this->variants(Variant::query())->exists();
    }

    public function canCreateVariants(): bool
    {
        if (Variant::ownerScopeConfig()->enabled && ! OwnerUiScope::canCreate(Variant::class)) {
            return false;
        }

        if (! Experiment::ownerScopeConfig()->enabled && ! TrackedProperty::ownerScopeConfig()->enabled) {
            return true;
        }

        return $this->writableExperiments(Experiment::query())->exists();
    }

    public function canMutateVariant(Variant $variant): bool
    {
        if (Variant::ownerScopeConfig()->enabled && ! OwnerUiScope::canMutateRecord($variant)) {
            return false;
        }

        $experiment = $this->findWritableExperiment($variant->experiment_id);

        return $experiment instanceof Experiment
            && $this->canMutateExperiment($experiment);
    }

    public function canViewVariant(Variant $variant): bool
    {
        return $this->variants(Variant::query())
            ->whereKey($variant->getKey())
            ->exists();
    }

    private function resolveTrackedPropertyForExperiment(Experiment $experiment, bool $forMutation): ?TrackedProperty
    {
        $resolvedExperiment = $forMutation
            ? $this->findWritableExperiment($experiment->getKey())
            : $this->findExperiment($experiment->getKey());

        if (! $resolvedExperiment instanceof Experiment) {
            return null;
        }

        $query = TrackedProperty::query();

        if (Experiment::ownerScopeConfig()->enabled) {
            if ($forMutation) {
                $owner = OwnerContext::resolve();

                if ($owner === null && ! OwnerContext::isExplicitGlobal()) {
                    return null;
                }
            } else {
                try {
                    $owner = OwnerContext::fromTypeAndId($resolvedExperiment->owner_type, $resolvedExperiment->owner_id);
                } catch (InvalidArgumentException) {
                    return null;
                }
            }

            $query = $this->scopeSignalQueryToOwner->handle(
                $query,
                $owner,
                false,
            );
        } elseif (TrackedProperty::ownerScopeConfig()->enabled) {
            $query = $forMutation
                ? $this->writableTrackedProperties($query)
                : $this->accessibleTrackedProperties($query);
        }

        $trackedProperty = $query
            ->whereKey((string) $resolvedExperiment->tracked_property_id)
            ->first();

        return $trackedProperty instanceof TrackedProperty ? $trackedProperty : null;
    }

    /**
     * @param  Builder<Experiment>  $query
     * @return Builder<Experiment>
     */
    private function constrainExperimentsToConsistentTrackedProperties(Builder $query): Builder
    {
        $experimentTable = $query->getModel()->getTable();
        $trackedProperty = new TrackedProperty;
        $trackedPropertyTable = $trackedProperty->getTable();
        $experimentOwnerColumns = OwnerTupleColumns::forModelClass(Experiment::class);
        $trackedPropertyOwnerColumns = OwnerTupleColumns::forModelClass(TrackedProperty::class);

        /** @var Builder<TrackedProperty> $trackedPropertyQuery */
        $trackedPropertyQuery = TrackedProperty::query();

        if (method_exists(TrackedProperty::class, 'scopeWithoutOwnerScope')) {
            /** @phpstan-ignore-next-line dynamic Eloquent scope */
            $trackedPropertyQuery = $trackedPropertyQuery->withoutOwnerScope();
        }

        return $query->whereExists(
            $trackedPropertyQuery
                ->selectRaw('1')
                ->whereColumn($trackedPropertyTable . '.id', $experimentTable . '.tracked_property_id')
                ->where(function (Builder $query) use ($experimentOwnerColumns, $experimentTable, $trackedPropertyOwnerColumns, $trackedPropertyTable): void {
                    $query
                        ->where(function (Builder $ownerMatchedQuery) use ($experimentOwnerColumns, $experimentTable, $trackedPropertyOwnerColumns, $trackedPropertyTable): void {
                            $ownerMatchedQuery
                                ->whereColumn(
                                    $trackedPropertyTable . '.' . $trackedPropertyOwnerColumns->ownerTypeColumn,
                                    $experimentTable . '.' . $experimentOwnerColumns->ownerTypeColumn,
                                )
                                ->whereColumn(
                                    $trackedPropertyTable . '.' . $trackedPropertyOwnerColumns->ownerIdColumn,
                                    $experimentTable . '.' . $experimentOwnerColumns->ownerIdColumn,
                                );
                        })
                        ->orWhere(function (Builder $globalQuery) use ($experimentOwnerColumns, $experimentTable, $trackedPropertyOwnerColumns, $trackedPropertyTable): void {
                            $globalQuery
                                ->whereNull($trackedPropertyTable . '.' . $trackedPropertyOwnerColumns->ownerTypeColumn)
                                ->whereNull($trackedPropertyTable . '.' . $trackedPropertyOwnerColumns->ownerIdColumn)
                                ->whereNull($experimentTable . '.' . $experimentOwnerColumns->ownerTypeColumn)
                                ->whereNull($experimentTable . '.' . $experimentOwnerColumns->ownerIdColumn);
                        });
                }),
        );
    }
}
