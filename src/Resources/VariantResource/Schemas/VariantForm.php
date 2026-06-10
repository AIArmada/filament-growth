<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Resources\VariantResource\Schemas;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeKey;
use AIArmada\Growth\Enums\ExperimentModuleType;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class VariantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Variant')
                ->schema([
                    Forms\Components\Select::make('experiment_id')
                        ->label('Experiment')
                        ->relationship(
                            name: 'experiment',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query): Builder => OwnerUiScope::apply($query, includeGlobal: false),
                        )
                        ->live()
                        ->afterStateUpdated(fn (Get $get, Set $set, mixed $state): mixed => $set('settings', VariantForm::normalizeSettingsForExperiment($state, $get('settings'))))
                        ->disabledOn('edit')
                        ->required()
                        ->preload()
                        ->searchable(),

                    Forms\Components\TextInput::make('code')
                        ->required()
                        ->maxLength(50)
                        ->scopedUnique(
                            model: Variant::class,
                            column: 'code',
                            ignoreRecord: true,
                            modifyQueryUsing: fn (Builder $query, Get $get): Builder => VariantForm::scopeCodeUniquenessToExperiment($query, $get('experiment_id')),
                        ),

                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('traffic_percentage')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(100)
                        ->default(50),

                    Forms\Components\TextInput::make('position')
                        ->numeric()
                        ->required()
                        ->default(0),

                    Forms\Components\Toggle::make('is_control')
                        ->default(false),

                    Forms\Components\Select::make('status')
                        ->options(\AIArmada\Growth\Enums\VariantStatus::class)
                        ->default('active')
                        ->required(),

                    Forms\Components\Textarea::make('description')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Variant Settings')
                ->schema([
                    Forms\Components\TextInput::make('settings.destination_url')
                        ->label('Destination URL')
                        ->visible(fn (Get $get): bool => VariantForm::selectedExperimentModuleType($get('experiment_id')) === ExperimentModuleType::SalesPageTest->value),

                    Forms\Components\TextInput::make('settings.headline')
                        ->label('Headline')
                        ->visible(fn (Get $get): bool => VariantForm::selectedExperimentModuleType($get('experiment_id')) === ExperimentModuleType::SalesPageTest->value),

                    Forms\Components\TextInput::make('settings.cta_copy')
                        ->label('CTA Copy')
                        ->visible(fn (Get $get): bool => VariantForm::selectedExperimentModuleType($get('experiment_id')) === ExperimentModuleType::SalesPageTest->value),

                    Forms\Components\TextInput::make('settings.entry_path')
                        ->label('Entry Path')
                        ->visible(fn (Get $get): bool => VariantForm::selectedExperimentModuleType($get('experiment_id')) === ExperimentModuleType::FunnelTest->value),

                    Forms\Components\TextInput::make('settings.step_key')
                        ->label('Step Key')
                        ->visible(fn (Get $get): bool => VariantForm::selectedExperimentModuleType($get('experiment_id')) === ExperimentModuleType::FunnelTest->value),

                    Forms\Components\TextInput::make('settings.offer_label')
                        ->label('Offer Label')
                        ->visible(fn (Get $get): bool => VariantForm::selectedExperimentModuleType($get('experiment_id')) === ExperimentModuleType::FunnelTest->value),

                    Forms\Components\TextInput::make('settings.price_label')
                        ->label('Price Label')
                        ->visible(fn (Get $get): bool => VariantForm::selectedExperimentModuleType($get('experiment_id')) === ExperimentModuleType::PricingTest->value),

                    Forms\Components\TextInput::make('settings.price_minor')
                        ->label('Price (Minor Units)')
                        ->numeric()
                        ->minValue(0)
                        ->visible(fn (Get $get): bool => VariantForm::selectedExperimentModuleType($get('experiment_id')) === ExperimentModuleType::PricingTest->value),

                    Forms\Components\TextInput::make('settings.currency')
                        ->label('Currency')
                        ->default(config('signals.defaults.currency', 'MYR'))
                        ->visible(fn (Get $get): bool => VariantForm::selectedExperimentModuleType($get('experiment_id')) === ExperimentModuleType::PricingTest->value),
                ])
                ->columns(2),
        ]);
    }

    /**
     * @param  Builder<Variant>  $query
     * @return Builder<Variant>
     */
    private static function scopeCodeUniquenessToExperiment(Builder $query, mixed $experimentId): Builder
    {
        $experiment = OwnerUiScope::apply(Experiment::query(), includeGlobal: false)
            ->whereKey($experimentId)
            ->first();

        if (! $experiment instanceof Experiment) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('experiment_id', $experiment->getKey());
    }

    public static function selectedExperimentModuleType(mixed $experimentId): ?string
    {
        if (! is_scalar($experimentId) || $experimentId === '') {
            return null;
        }

        $normalizedExperimentId = (string) $experimentId;
        $moduleTypeCache = VariantForm::selectedExperimentModuleTypeCache();

        if (array_key_exists($normalizedExperimentId, $moduleTypeCache)) {
            $cachedModuleType = $moduleTypeCache[$normalizedExperimentId];

            return is_string($cachedModuleType) ? $cachedModuleType : null;
        }

        $moduleType = OwnerUiScope::apply(Experiment::query(), includeGlobal: false)
            ->whereKey($normalizedExperimentId)
            ->value('module_type');

        VariantForm::storeSelectedExperimentModuleType(
            $normalizedExperimentId,
            is_string($moduleType) ? $moduleType : null,
        );

        return is_string($moduleType) ? $moduleType : null;
    }

    /**
     * @return array<string, string|null>
     */
    private static function selectedExperimentModuleTypeCache(): array
    {
        if (! app()->bound('request')) {
            return [];
        }

        $cache = request()->attributes->get('filament-growth.variant-resource.selected-experiment-module-types', []);

        if (! is_array($cache)) {
            return [];
        }

        $scopedCache = $cache[VariantForm::selectedExperimentModuleTypeCacheKey()] ?? [];

        return is_array($scopedCache) ? $scopedCache : [];
    }

    private static function storeSelectedExperimentModuleType(string $experimentId, ?string $moduleType): void
    {
        if (! app()->bound('request')) {
            return;
        }

        $cache = request()->attributes->get('filament-growth.variant-resource.selected-experiment-module-types', []);

        if (! is_array($cache)) {
            $cache = [];
        }

        $cacheKey = VariantForm::selectedExperimentModuleTypeCacheKey();
        $scopedCache = $cache[$cacheKey] ?? [];

        if (! is_array($scopedCache)) {
            $scopedCache = [];
        }

        $scopedCache[$experimentId] = $moduleType;
        $cache[$cacheKey] = $scopedCache;

        request()->attributes->set('filament-growth.variant-resource.selected-experiment-module-types', $cache);
    }

    private static function selectedExperimentModuleTypeCacheKey(): string
    {
        if (OwnerContext::isExplicitGlobal()) {
            return OwnerScopeKey::GLOBAL . '::explicit';
        }

        $owner = OwnerContext::resolve();

        if ($owner === null) {
            return OwnerScopeKey::GLOBAL . '::unresolved';
        }

        return OwnerScopeKey::forOwner($owner);
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
}
