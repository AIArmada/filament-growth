<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Actions;

use AIArmada\FilamentGrowth\Authorization\ExperimentAccess;
use AIArmada\Growth\Actions\SaveExperiment;
use AIArmada\Growth\Models\Experiment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

final readonly class DeleteSelectedExperiments
{
    public function __construct(private SaveExperiment $saveExperiment) {}

    /** @param Collection<int|string, Experiment> $records */
    public function handle(Collection $records): void
    {
        ExperimentAccess::authorize('deleteAny', Experiment::class);

        foreach ($records as $record) {
            if (! $record instanceof Experiment) {
                throw new AuthorizationException('The growth experiment selection contains an invalid record.');
            }

            ExperimentAccess::authorize('delete', $record);
        }

        $this->saveExperiment->deleteMany(
            $records->map(static fn (Experiment $record): string => (string) $record->getKey())->values()->all(),
        );
    }
}
