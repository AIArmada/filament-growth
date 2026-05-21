<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Policies;

use AIArmada\FilamentGrowth\Support\AccessibleGrowthRecords;
use AIArmada\Growth\Models\Experiment;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class ExperimentPolicy
{
    public function viewAny(Authorizable $user): bool
    {
        return app(AccessibleGrowthRecords::class)->canViewAnyExperiments();
    }

    public function view(Authorizable $user, Experiment $experiment): bool
    {
        return app(AccessibleGrowthRecords::class)->canViewExperiment($experiment);
    }

    public function create(Authorizable $user): bool
    {
        return app(AccessibleGrowthRecords::class)->canCreateExperiments();
    }

    public function update(Authorizable $user, Experiment $experiment): bool
    {
        return app(AccessibleGrowthRecords::class)->canMutateExperiment($experiment);
    }

    public function delete(Authorizable $user, Experiment $experiment): bool
    {
        return app(AccessibleGrowthRecords::class)->canMutateExperiment($experiment);
    }

    public function deleteAny(Authorizable $user): bool
    {
        return $this->hasWritableExperiments();
    }

    public function restore(Authorizable $user, Experiment $experiment): bool
    {
        return app(AccessibleGrowthRecords::class)->canMutateExperiment($experiment);
    }

    public function restoreAny(Authorizable $user): bool
    {
        return $this->hasWritableExperiments();
    }

    public function forceDelete(Authorizable $user, Experiment $experiment): bool
    {
        return app(AccessibleGrowthRecords::class)->canMutateExperiment($experiment);
    }

    public function forceDeleteAny(Authorizable $user): bool
    {
        return $this->hasWritableExperiments();
    }

    private function hasWritableExperiments(): bool
    {
        $accessibleRecords = app(AccessibleGrowthRecords::class);

        if (! $accessibleRecords->canCreateExperiments()) {
            return false;
        }

        return $accessibleRecords
            ->writableExperiments(Experiment::query())
            ->exists();
    }
}
