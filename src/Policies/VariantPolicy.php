<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Policies;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class VariantPolicy
{
    public function viewAny(Authorizable $user): bool
    {
        if (OwnerUiScope::canCreate(Variant::class)) {
            if (! Experiment::ownerScopeConfig()->enabled && ! TrackedProperty::ownerScopeConfig()->enabled) {
                return true;
            }

            $experimentQuery = Experiment::ownerScopeConfig()->enabled
                ? OwnerUiScope::apply(Experiment::query(), includeGlobal: false)
                : OwnerUiScope::apply(TrackedProperty::query(), includeGlobal: false);

            if ($experimentQuery->exists()) {
                return true;
            }
        }

        if (Variant::ownerScopeConfig()->enabled) {
            return false;
        }

        return Variant::query()->exists();
    }

    public function view(Authorizable $user, Variant $variant): bool
    {
        return OwnerUiScope::canAccessRecord($variant);
    }

    public function create(Authorizable $user): bool
    {
        if (Variant::ownerScopeConfig()->enabled && ! OwnerUiScope::canCreate(Variant::class)) {
            return false;
        }

        if (! Experiment::ownerScopeConfig()->enabled && ! TrackedProperty::ownerScopeConfig()->enabled) {
            return true;
        }

        $experimentQuery = Experiment::ownerScopeConfig()->enabled
            ? OwnerUiScope::apply(Experiment::query(), includeGlobal: false)
            : OwnerUiScope::apply(TrackedProperty::query(), includeGlobal: false);

        return $experimentQuery->exists();
    }

    public function update(Authorizable $user, Variant $variant): bool
    {
        return OwnerUiScope::canMutateRecord($variant);
    }

    public function delete(Authorizable $user, Variant $variant): bool
    {
        return OwnerUiScope::canMutateRecord($variant);
    }

    public function deleteAny(Authorizable $user): bool
    {
        return $this->hasWritableVariants();
    }

    public function restore(Authorizable $user, Variant $variant): bool
    {
        return OwnerUiScope::canMutateRecord($variant);
    }

    public function restoreAny(Authorizable $user): bool
    {
        return $this->hasWritableVariants();
    }

    public function forceDelete(Authorizable $user, Variant $variant): bool
    {
        return OwnerUiScope::canMutateRecord($variant);
    }

    public function forceDeleteAny(Authorizable $user): bool
    {
        return $this->hasWritableVariants();
    }

    private function hasWritableVariants(): bool
    {
        if (Variant::ownerScopeConfig()->enabled && ! OwnerUiScope::canCreate(Variant::class)) {
            return false;
        }

        if (! Experiment::ownerScopeConfig()->enabled && ! TrackedProperty::ownerScopeConfig()->enabled) {
            return Variant::query()->exists();
        }

        $experimentQuery = Experiment::ownerScopeConfig()->enabled
            ? OwnerUiScope::apply(Experiment::query(), includeGlobal: false)
            : OwnerUiScope::apply(TrackedProperty::query(), includeGlobal: false);

        return Variant::query()
            ->whereIn(
                (new Variant)->qualifyColumn('experiment_id'),
                $experimentQuery->select('id'),
            )
            ->exists();
    }
}
