<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Policies;

use AIArmada\FilamentGrowth\Support\AccessibleGrowthRecords;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class VariantPolicy
{
    public function viewAny(Authorizable $user): bool
    {
        return app(AccessibleGrowthRecords::class)->canViewAnyVariants();
    }

    public function view(Authorizable $user, Variant $variant): bool
    {
        return app(AccessibleGrowthRecords::class)->canViewVariant($variant);
    }

    public function create(Authorizable $user): bool
    {
        return app(AccessibleGrowthRecords::class)->canCreateVariants();
    }

    public function update(Authorizable $user, Variant $variant): bool
    {
        return app(AccessibleGrowthRecords::class)->canMutateVariant($variant);
    }

    public function delete(Authorizable $user, Variant $variant): bool
    {
        return app(AccessibleGrowthRecords::class)->canMutateVariant($variant);
    }

    public function deleteAny(Authorizable $user): bool
    {
        return $this->hasWritableVariants();
    }

    public function restore(Authorizable $user, Variant $variant): bool
    {
        return app(AccessibleGrowthRecords::class)->canMutateVariant($variant);
    }

    public function restoreAny(Authorizable $user): bool
    {
        return $this->hasWritableVariants();
    }

    public function forceDelete(Authorizable $user, Variant $variant): bool
    {
        return app(AccessibleGrowthRecords::class)->canMutateVariant($variant);
    }

    public function forceDeleteAny(Authorizable $user): bool
    {
        return $this->hasWritableVariants();
    }

    private function hasWritableVariants(): bool
    {
        $accessibleRecords = app(AccessibleGrowthRecords::class);

        if (! $accessibleRecords->canCreateVariants()) {
            return false;
        }

        $variants = $accessibleRecords->variants(Variant::query());

        return $variants
            ->whereIn(
                $variants->getModel()->qualifyColumn('experiment_id'),
                $accessibleRecords->writableExperiments(Experiment::query())->select('id'),
            )
            ->exists();
    }
}
