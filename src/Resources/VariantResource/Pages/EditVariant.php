<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Resources\VariantResource\Pages;

use AIArmada\FilamentGrowth\Authorization\ExperimentAccess;
use AIArmada\FilamentGrowth\Resources\VariantResource;
use AIArmada\Growth\Actions\SaveVariant;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditVariant extends EditRecord
{
    protected static string $resource = VariantResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        ExperimentAccess::authorize('update', $record);

        return app(SaveVariant::class)->update((string) $record->getKey(), $data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
