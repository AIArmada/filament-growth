<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Resources\VariantResource\Pages;

use AIArmada\FilamentGrowth\Resources\VariantResource;
use Filament\Resources\Pages\EditRecord;

final class EditVariant extends EditRecord
{
    protected static string $resource = VariantResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return VariantResource::normalizeFormData($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
