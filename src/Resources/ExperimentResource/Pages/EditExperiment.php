<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Resources\ExperimentResource\Pages;

use AIArmada\FilamentGrowth\Resources\ExperimentResource;
use Filament\Resources\Pages\EditRecord;

final class EditExperiment extends EditRecord
{
    protected static string $resource = ExperimentResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
