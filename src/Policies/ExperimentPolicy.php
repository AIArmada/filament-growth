<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Policies;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\FilamentGrowth\Authorization\ExperimentAccess;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Signals\Features\Ingestion\Models\TrackedProperty;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class ExperimentPolicy
{
    public function viewAny(Authorizable $user): bool
    {
        if (! OwnerUiScope::canCreate(Experiment::class)) {
            return ExperimentAccess::hasReadableExperiment();
        }

        if (! TrackedProperty::ownerScopeConfig()->enabled) {
            return true;
        }

        return ExperimentAccess::hasWritableTrackedProperty()
            || ExperimentAccess::hasReadableExperiment();
    }

    public function view(Authorizable $user, Experiment $experiment): bool
    {
        return ExperimentAccess::canViewExperiment($experiment);
    }

    public function create(Authorizable $user): bool
    {
        return ExperimentAccess::canCreateExperiment();
    }

    public function update(Authorizable $user, Experiment $experiment): bool
    {
        return ExperimentAccess::canMutateExperiment($experiment);
    }

    public function delete(Authorizable $user, Experiment $experiment): bool
    {
        return ExperimentAccess::canMutateExperiment($experiment);
    }

    public function deleteAny(Authorizable $user): bool
    {
        return ExperimentAccess::hasWritableExperiment();
    }

    public function restore(Authorizable $user, Experiment $experiment): bool
    {
        return ExperimentAccess::canMutateExperiment($experiment);
    }

    public function restoreAny(Authorizable $user): bool
    {
        return ExperimentAccess::hasWritableExperiment();
    }

    public function forceDelete(Authorizable $user, Experiment $experiment): bool
    {
        return ExperimentAccess::canMutateExperiment($experiment);
    }

    public function forceDeleteAny(Authorizable $user): bool
    {
        return ExperimentAccess::hasWritableExperiment();
    }
}
