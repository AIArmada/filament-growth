<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Resources;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeKey;
use AIArmada\FilamentGrowth\Resources\VariantResource\Pages;
use AIArmada\FilamentGrowth\Support\AccessibleGrowthRecords;
use AIArmada\Growth\Enums\ExperimentModuleType;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
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
        /** @var Builder<Variant> $query */
        $query = Variant::query()->with(['experiment:id,name']);

        return app(AccessibleGrowthRecords::class)->variants($query);
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
        return $schema->schema([
            Section::make('Variant')
                ->schema([
                    Forms\Components\Select::make('experiment_id')
                        ->label('Experiment')
                        ->relationship(
                            name: 'experiment',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query): Builder => static::scopeExperimentQueryToCurrentOwner($query),
                        )
                        ->live()
                        ->afterStateUpdated(fn (Get $get, Set $set, mixed $state): mixed => $set('settings', static::normalizeSettingsForExperiment($state, $get('settings'))))
                        ->disabledOn('edit')
                        ->required()
                        ->preload()
                        ->searchable(),

                    Forms\Components\TextInput::make('code')
                        ->required()
                        ->maxLength(50)
                        ->scopedUnique(
                            model: static::getModel(),
                            column: 'code',
                            ignoreRecord: true,
                            modifyQueryUsing: fn (Builder $query, Get $get): Builder => static::scopeCodeUniquenessToExperiment($query, $get('experiment_id')),
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

                    Forms\Components\Toggle::make('is_active')
                        ->default(true),

                    Forms\Components\Textarea::make('description')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Variant Settings')
                ->schema([
                    Forms\Components\TextInput::make('settings.destination_url')
                        ->label('Destination URL')
                        ->visible(fn (Get $get): bool => static::selectedExperimentModuleType($get('experiment_id')) === ExperimentModuleType::SalesPageTest->value),

                    Forms\Components\TextInput::make('settings.headline')
                        ->label('Headline')
                        ->visible(fn (Get $get): bool => static::selectedExperimentModuleType($get('experiment_id')) === ExperimentModuleType::SalesPageTest->value),

                    Forms\Components\TextInput::make('settings.cta_copy')
                        ->label('CTA Copy')
                        ->visible(fn (Get $get): bool => static::selectedExperimentModuleType($get('experiment_id')) === ExperimentModuleType::SalesPageTest->value),

                    Forms\Components\TextInput::make('settings.entry_path')
                        ->label('Entry Path')
                        ->visible(fn (Get $get): bool => static::selectedExperimentModuleType($get('experiment_id')) === ExperimentModuleType::FunnelTest->value),

                    Forms\Components\TextInput::make('settings.step_key')
                        ->label('Step Key')
                        ->visible(fn (Get $get): bool => static::selectedExperimentModuleType($get('experiment_id')) === ExperimentModuleType::FunnelTest->value),

                    Forms\Components\TextInput::make('settings.offer_label')
                        ->label('Offer Label')
                        ->visible(fn (Get $get): bool => static::selectedExperimentModuleType($get('experiment_id')) === ExperimentModuleType::FunnelTest->value),

                    Forms\Components\TextInput::make('settings.price_label')
                        ->label('Price Label')
                        ->visible(fn (Get $get): bool => static::selectedExperimentModuleType($get('experiment_id')) === ExperimentModuleType::PricingTest->value),

                    Forms\Components\TextInput::make('settings.price_minor')
                        ->label('Price (Minor Units)')
                        ->numeric()
                        ->minValue(0)
                        ->visible(fn (Get $get): bool => static::selectedExperimentModuleType($get('experiment_id')) === ExperimentModuleType::PricingTest->value),

                    Forms\Components\TextInput::make('settings.currency')
                        ->label('Currency')
                        ->default(config('signals.defaults.currency', 'MYR'))
                        ->visible(fn (Get $get): bool => static::selectedExperimentModuleType($get('experiment_id')) === ExperimentModuleType::PricingTest->value),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('experiment_id')
                    ->label('Experiment')
                    ->state(fn (Variant $record): string => static::experimentName($record))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereIn(
                            $query->getModel()->qualifyColumn('experiment_id'),
                            app(AccessibleGrowthRecords::class)
                                ->experiments(Experiment::query())
                                ->where('name', 'like', '%' . $search . '%')
                                ->select('id'),
                        );
                    }),
                Tables\Columns\TextColumn::make('code')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('traffic_percentage')
                    ->label('Traffic %')
                    ->suffix('%')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_control')
                    ->label('Control')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('position')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('position')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (Variant $record): bool => static::canEdit($record)),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('deleteSelected')
                        ->label('Delete Selected')
                        ->visible(fn (): bool => static::canDeleteAny())
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            static::deleteSelectedVariants($records);
                        }),
                ]),
            ]);
    }

    public static function canCreate(): bool
    {
        return parent::canCreate()
            && app(AccessibleGrowthRecords::class)->canCreateVariants();
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof Variant
            && parent::canEdit($record)
            && app(AccessibleGrowthRecords::class)->canMutateVariant($record);
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof Variant
            && parent::canDelete($record)
            && app(AccessibleGrowthRecords::class)->canMutateVariant($record);
    }

    public static function canDeleteAny(): bool
    {
        return parent::canDeleteAny();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVariants::route('/'),
            'create' => Pages\CreateVariant::route('/create'),
            'edit' => Pages\EditVariant::route('/{record}/edit'),
        ];
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

    private static function selectedExperimentModuleType(mixed $experimentId): ?string
    {
        if (! is_scalar($experimentId) || $experimentId === '') {
            return null;
        }

        $normalizedExperimentId = (string) $experimentId;
        $moduleTypeCache = static::selectedExperimentModuleTypeCache();

        if (array_key_exists($normalizedExperimentId, $moduleTypeCache)) {
            $cachedModuleType = $moduleTypeCache[$normalizedExperimentId];

            return is_string($cachedModuleType) ? $cachedModuleType : null;
        }

        $moduleType = app(AccessibleGrowthRecords::class)
            ->findWritableExperiment($normalizedExperimentId)?->module_type;

        static::storeSelectedExperimentModuleType(
            $normalizedExperimentId,
            is_string($moduleType) ? $moduleType : null,
        );

        return is_string($moduleType) ? $moduleType : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function normalizeSettingsForExperiment(mixed $experimentId, mixed $settings): ?array
    {
        $moduleType = static::selectedExperimentModuleType($experimentId);
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

        $scopedCache = $cache[static::selectedExperimentModuleTypeCacheKey()] ?? [];

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

        $cacheKey = static::selectedExperimentModuleTypeCacheKey();
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

    private static function experimentName(Variant $record): string
    {
        if ($record->relationLoaded('experiment') && $record->experiment instanceof Experiment) {
            return (string) $record->experiment->name;
        }

        $experiment = app(AccessibleGrowthRecords::class)->findExperiment($record->experiment_id);

        return $experiment instanceof Experiment ? (string) $experiment->name : '—';
    }

    /**
     * @param  Builder<Experiment>  $query
     * @return Builder<Experiment>
     */
    private static function scopeExperimentQueryToCurrentOwner(Builder $query): Builder
    {
        return app(AccessibleGrowthRecords::class)->writableExperiments($query);
    }

    /**
     * @param  Builder<Variant>  $query
     * @return Builder<Variant>
     */
    private static function scopeCodeUniquenessToExperiment(Builder $query, mixed $experimentId): Builder
    {
        $experiment = app(AccessibleGrowthRecords::class)->findWritableExperiment($experimentId);

        if (! $experiment instanceof Experiment) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('experiment_id', $experiment->getKey());
    }

    /**
     * @param  Collection<int|string, Variant>  $records
     */
    private static function deleteSelectedVariants(Collection $records): void
    {
        DB::transaction(function () use ($records): void {
            foreach ($records as $record) {
                if (! $record instanceof Variant || ! static::canDelete($record)) {
                    throw new RuntimeException('Global growth variants can only be deleted from explicit global context.');
                }
            }

            foreach ($records as $record) {
                $record->delete();
            }
        });
    }
}
