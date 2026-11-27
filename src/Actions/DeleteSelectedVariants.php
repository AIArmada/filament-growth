<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Actions;

use AIArmada\FilamentGrowth\Authorization\ExperimentAccess;
use AIArmada\Growth\Actions\SaveVariant;
use AIArmada\Growth\Models\Variant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

final readonly class DeleteSelectedVariants
{
    public function __construct(private SaveVariant $saveVariant) {}

    /** @param Collection<int|string, Variant> $records */
    public function handle(Collection $records): void
    {
        ExperimentAccess::authorize('deleteAny', Variant::class);

        foreach ($records as $record) {
            if (! $record instanceof Variant) {
                throw new AuthorizationException('The growth variant selection contains an invalid record.');
            }

            ExperimentAccess::authorize('delete', $record);
        }

        $this->saveVariant->deleteMany(
            $records->map(static fn (Variant $record): string => (string) $record->getKey())->values()->all(),
        );
    }
}
