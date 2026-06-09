<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\FilamentGrowth\Resources\VariantResource\Pages;
use AIArmada\FilamentGrowth\Resources\VariantResource\Schemas\VariantForm;
use AIArmada\FilamentGrowth\Resources\VariantResource\Tables\VariantsTable;
use AIArmada\Growth\Enums\ExperimentModuleType;
use AIArmada\Growth\Models\Variant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

final class VariantResource extends Resource
{
    protected static ?string $model = Variant::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string | UnitEnum | null $navigationGroup = 'Growth';

    protected static ?int $navigationSort = 21;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * @return Builder<Variant>
     */
    public static function getEloquentQuery(): Builder
    {
        return OwnerUiScope::apply(Variant::query(), includeGlobal: false)
            ->with(['experiment:id,name']);
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-growth.navigation_group', 'Growth');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-growth.resources.navigation_sort.variants', 21);
    }

    public static function form(Schema $schema): Schema
    {
        return VariantForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VariantsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return parent::canCreate();
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof Variant
            && parent::canEdit($record)
            && OwnerUiScope::canMutateRecord($record);
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof Variant
            && parent::canDelete($record)
            && OwnerUiScope::canMutateRecord($record);
    }

    public static function canDeleteAny(): bool
    {
        return parent::canDeleteAny()
            && Gate::allows('deleteAny', static::getModel());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeFormData(array $data): array
    {
        $data['settings'] = static::normalizeSettingsForExperiment(
            $data['experiment_id'] ?? null,
            $data['settings'] ?? null,
        );

        return $data;
    }

    /**
     * @param  array<string, mixed>|null  $settings
     * @return array<string, mixed>|null
     */
    private static function normalizeSettingsForExperiment(mixed $experimentId, mixed $settings): ?array
    {
        $moduleType = VariantForm::selectedExperimentModuleType($experimentId);
        $existingSettings = is_array($settings) ? $settings : [];
        $allowedKeys = match ($moduleType) {
            ExperimentModuleType::SalesPageTest->value => ['destination_url', 'headline', 'cta_copy'],
            ExperimentModuleType::FunnelTest->value => ['entry_path', 'step_key', 'offer_label'],
            ExperimentModuleType::PricingTest->value => ['price_label', 'price_minor', 'currency'],
            default => [],
        };

        if ($allowedKeys === []) {
            return null;
        }

        $normalizedSettings = [];

        foreach ($allowedKeys as $allowedKey) {
            if (! array_key_exists($allowedKey, $existingSettings)) {
                continue;
            }

            $normalizedSettings[$allowedKey] = $existingSettings[$allowedKey];
        }

        return $normalizedSettings === [] ? null : $normalizedSettings;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVariants::route('/'),
            'create' => Pages\CreateVariant::route('/create'),
            'edit' => Pages\EditVariant::route('/{record}/edit'),
        ];
    }
}
