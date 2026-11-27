<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Policies;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\FilamentGrowth\Authorization\ExperimentAccess;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Features\Ingestion\Models\TrackedProperty;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class VariantPolicy
{
    public function viewAny(Authorizable $user): bool
    {
        if (! OwnerUiScope::canCreate(Variant::class)) {
            return ExperimentAccess::hasReadableVariant();
        }

        if (! Experiment::ownerScopeConfig()->enabled && ! TrackedProperty::ownerScopeConfig()->enabled) {
            return true;
        }

        return ExperimentAccess::hasWritableExperiment()
            || ExperimentAccess::hasReadableVariant();
    }

    public function view(Authorizable $user, Variant $variant): bool
    {
        return ExperimentAccess::canViewVariant($variant);
    }

    public function create(Authorizable $user): bool
    {
        return ExperimentAccess::canCreateVariant();
    }

    public function update(Authorizable $user, Variant $variant): bool
    {
        return ExperimentAccess::canMutateVariant($variant);
    }

    public function delete(Authorizable $user, Variant $variant): bool
    {
        return ExperimentAccess::canMutateVariant($variant);
    }

    public function deleteAny(Authorizable $user): bool
    {
        return ExperimentAccess::hasWritableVariant();
    }

    public function restore(Authorizable $user, Variant $variant): bool
    {
        return ExperimentAccess::canMutateVariant($variant);
    }

    public function restoreAny(Authorizable $user): bool
    {
        return ExperimentAccess::hasWritableVariant();
    }

    public function forceDelete(Authorizable $user, Variant $variant): bool
    {
        return ExperimentAccess::canMutateVariant($variant);
    }

    public function forceDeleteAny(Authorizable $user): bool
    {
        return ExperimentAccess::hasWritableVariant();
    }
}
