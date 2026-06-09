<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Resources\ExperimentResource\Schemas;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeKey;
use AIArmada\Growth\Actions\ResolveExperimentPreset;
use AIArmada\Growth\Enums\ExperimentModuleType;
use AIArmada\Growth\Enums\ExperimentStatus;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Signals\Models\TrackedProperty;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ExperimentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Experiment')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', $state ? Str::slug($state) : '')),

                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->alphaDash()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('owner_scope', self::ownerScopeKey())),

                    Forms\Components\Select::make('tracked_property_id')
                        ->label('Tracked Property')
                        ->relationship(
                            name: 'trackedProperty',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query): Builder => OwnerUiScope::apply($query, includeGlobal: false),
                        )
                        ->disabledOn('edit')
                        ->required()
                        ->preload()
                        ->searchable(),

                    Forms\Components\Select::make('module_type')
                        ->required()
                        ->options(ExperimentModuleType::options())
                        ->default(config('growth.defaults.module_type', 'ab_test'))
                        ->live()
                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                            if (! config('growth.features.preset_modules.enabled', true)) {
                                return;
                            }

                            $preset = app(ResolveExperimentPreset::class)->handle($state);
                            $existingSettings = $get('settings');

                            $set('goal_event_name', $preset['goal_event_name']);
                            $set('goal_event_category', $preset['goal_event_category']);
                            $set('winner_metric', $preset['winner_metric']);
                            $set('settings', ExperimentForm::normalizeSettingsForModuleType($state, $existingSettings));
                        }),

                    Forms\Components\Select::make('status')
                        ->options(collect(ExperimentStatus::cases())->mapWithKeys(fn (ExperimentStatus $status): array => [$status->value => $status->label()]))
                        ->required()
                        ->default(ExperimentStatus::Draft->value),
                ])
                ->columns(2),

            Section::make('Outcome')
                ->schema([
                    Forms\Components\TextInput::make('goal_event_name')
                        ->required()
                        ->default(config('growth.integrations.signals.purchase_event_name', 'order.paid')),

                    Forms\Components\TextInput::make('goal_event_category')
                        ->required()
                        ->default('conversion')
                        ->maxLength(100),

                    Forms\Components\Select::make('winner_metric')
                        ->options([
                            'revenue_per_visitor' => 'Revenue Per Visitor',
                            'revenue_minor' => 'Revenue',
                            'conversion_rate' => 'Conversion Rate',
                            'checkout_starts' => 'Checkout Starts',
                            'purchases' => 'Purchases',
                        ])
                        ->required()
                        ->default(config('growth.defaults.winner_metric', 'revenue_per_visitor')),

                    Forms\Components\Textarea::make('description')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Module Settings')
                ->schema([
                    Forms\Components\TagsInput::make('settings.entry_paths')
                        ->label('Entry Paths')
                        ->placeholder('/offers/course-v1')
                        ->visible(fn (Get $get): bool => $get('module_type') === ExperimentModuleType::SalesPageTest->value),

                    Forms\Components\TagsInput::make('settings.destination_urls')
                        ->label('Destination URLs')
                        ->placeholder('/checkout/course')
                        ->visible(fn (Get $get): bool => $get('module_type') === ExperimentModuleType::SalesPageTest->value),

                    Forms\Components\TextInput::make('settings.cta_event_name')
                        ->label('CTA Event Name')
                        ->default('cta_click')
                        ->visible(fn (Get $get): bool => $get('module_type') === ExperimentModuleType::SalesPageTest->value),

                    Forms\Components\Repeater::make('settings.funnel_steps')
                        ->label('Funnel Steps')
                        ->schema([
                            Forms\Components\TextInput::make('label')
                                ->required(),
                            Forms\Components\TextInput::make('event_name')
                                ->required(),
                            Forms\Components\TextInput::make('event_category')
                                ->required(),
                        ])
                        ->columns(3)
                        ->visible(fn (Get $get): bool => $get('module_type') === ExperimentModuleType::FunnelTest->value),

                    Forms\Components\TextInput::make('settings.checkout_event_name')
                        ->label('Checkout Event Name')
                        ->default('checkout.started')
                        ->visible(fn (Get $get): bool => $get('module_type') === ExperimentModuleType::PricingTest->value),

                    Forms\Components\TagsInput::make('settings.price_labels')
                        ->label('Price Labels')
                        ->placeholder('Starter')
                        ->visible(fn (Get $get): bool => $get('module_type') === ExperimentModuleType::PricingTest->value),
                ])
                ->columns(2)
                ->visible(fn (): bool => config('growth.features.preset_modules.enabled', true)),
        ]);
    }

    private static function ownerScopeKey(): string
    {
        if (! Experiment::ownerScopeConfig()->enabled && ! TrackedProperty::ownerScopeConfig()->enabled) {
            return OwnerScopeKey::GLOBAL;
        }

        return OwnerScopeKey::forOwner(OwnerContext::resolve());
    }

    /**
     * @param  array<string, mixed>|null  $settings
     * @return array<string, mixed>|null
     */
    private static function normalizeSettingsForModuleType(mixed $moduleType, mixed $settings): ?array
    {
        if (! config('growth.features.preset_modules.enabled', true)) {
            return is_array($settings) ? $settings : null;
        }

        if (! is_scalar($moduleType)) {
            return is_array($settings) ? $settings : null;
        }

        $resolvedModuleType = ExperimentModuleType::tryFrom((string) $moduleType);

        if (! $resolvedModuleType instanceof ExperimentModuleType) {
            return is_array($settings) ? $settings : null;
        }

        $preset = app(ResolveExperimentPreset::class)->handle($resolvedModuleType->value);
        $presetSettings = is_array($preset['settings'] ?? null) ? $preset['settings'] : [];

        if ($presetSettings === []) {
            return null;
        }

        $existingSettings = is_array($settings) ? $settings : [];
        $normalizedSettings = [];

        foreach ($presetSettings as $key => $defaultValue) {
            $existingValue = $existingSettings[$key] ?? null;

            if (is_array($defaultValue)) {
                $normalizedSettings[$key] = is_array($existingValue)
                    ? array_replace_recursive($defaultValue, $existingValue)
                    : $defaultValue;

                continue;
            }

            $normalizedSettings[$key] = $existingValue ?? $defaultValue;
        }

        return $normalizedSettings;
    }
}
