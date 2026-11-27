<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Resources\ExperimentResource\Pages;

use AIArmada\FilamentGrowth\Authorization\ExperimentAccess;
use AIArmada\FilamentGrowth\Resources\ExperimentResource;
use AIArmada\Growth\Actions\SaveExperiment;
use AIArmada\Growth\Models\Experiment;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateExperiment extends CreateRecord
{
    protected static string $resource = ExperimentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        ExperimentAccess::authorize('create', Experiment::class);

        return app(SaveExperiment::class)->create($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
