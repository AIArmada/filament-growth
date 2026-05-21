<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Resources\ExperimentResource\Pages;

use AIArmada\FilamentGrowth\Resources\ExperimentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListExperiments extends ListRecords
{
    protected static string $resource = ExperimentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
