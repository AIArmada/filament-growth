<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Resources\ExperimentResource\Pages;

use AIArmada\FilamentGrowth\Resources\ExperimentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

final class CreateExperiment extends CreateRecord
{
    protected static string $resource = ExperimentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! is_string($data['slug'] ?? null) || $data['slug'] === '') {
            $data['slug'] = Str::slug((string) ($data['name'] ?? 'experiment'));
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
