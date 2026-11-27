<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Actions;

use AIArmada\FilamentGrowth\Authorization\ExperimentAccess;
use AIArmada\Growth\Actions\SaveExperiment;
use AIArmada\Growth\Models\Experiment;

final readonly class SetExperimentRunning
{
    public function __construct(private SaveExperiment $saveExperiment) {}

    public function handle(Experiment $record, bool $running): bool
    {
        ExperimentAccess::authorize('update', $record);

        return $this->saveExperiment->setRunning((string) $record->getKey(), $running);
    }
}
