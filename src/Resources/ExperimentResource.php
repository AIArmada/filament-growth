<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Resources;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\CommerceSupport\Support\OwnerScopeKey;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
use AIArmada\FilamentGrowth\Pages\ExperimentResultsPage;
use AIArmada\FilamentGrowth\Resources\ExperimentResource\Pages;
use AIArmada\FilamentGrowth\Support\AccessibleGrowthRecords;
use AIArmada\Growth\Actions\ResolveExperimentPreset;
use AIArmada\Growth\Enums\ExperimentModuleType;
use AIArmada\Growth\Enums\ExperimentStatus;
use AIArmada\Growth\Models\Assignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Models\TrackedProperty;
use BackedEnum;
use Filament\Actions\Action;
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
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use UnitEnum;

final class ExperimentResource extends Resource
{
    protected static ?string $model = Experiment::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-beaker';

    protected static string | UnitEnum | null $navigationGroup = 'Growth';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * @return Builder<Experiment>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Experiment> $query */
        $query = static::applyOwnerSafeRelationCounts(Experiment::query())
            ->with([
                'trackedProperty' => function ($query) {
                    $builder = $query instanceof Relation ? $query->getQuery() : $query;

                    /** @var Builder<TrackedProperty> $builder */
                    return app(AccessibleGrowthRecords::class)
                        ->accessibleTrackedProperties($builder)
                        ->select(['id', 'name']);
                },
            ]);

        return app(AccessibleGrowthRecords::class)->experiments($query);
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-growth.navigation_group', 'Growth');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-growth.resources.navigation_sort.experiments', 20);
    }

    public static function form(Schema $schema): Schema
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
                        ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('owner_scope', static::ownerScopeKey())),

                    Forms\Components\Select::make('tracked_property_id')
                        ->label('Tracked Property')
                        ->relationship(
                            name: 'trackedProperty',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query): Builder => static::scopeTrackedPropertyQueryToCurrentOwner($query),
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
                            $set('settings', static::normalizeSettingsForModuleType($state, $existingSettings));
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Experiment $record): string => $record->slug),
                Tables\Columns\TextColumn::make('tracked_property_id')
                    ->label('Tracked Property')
                    ->state(fn (Experiment $record): string => static::trackedPropertyName($record))
                    ->searchable(query: fn (Builder $query, string $search): Builder => static::filterByTrackedPropertyName($query, $search)),
                Tables\Columns\TextColumn::make('module_type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ExperimentModuleType::labelFor($state))
                    ->sortable(),
                Tables\Columns\ColumnGroup::make('Lifecycle', [
                    Tables\Columns\TextColumn::make('status')
                        ->badge()
                        ->formatStateUsing(fn (ExperimentStatus $state): string => $state->label())
                        ->description(fn (Experiment $record): string => static::statusOperationalDescription($record->status))
                        ->color(fn (ExperimentStatus $state): string => $state->color()),
                    Tables\Columns\ToggleColumn::make('is_running')
                        ->label('Running')
                        ->state(fn (Experiment $record): bool => $record->status === ExperimentStatus::Active)
                        ->disabled(fn (Experiment $record): bool => ! static::canEdit($record) || $record->status === ExperimentStatus::Concluded)
                        ->onColor('success')
                        ->offColor('warning')
                        ->updateStateUsing(function (Experiment $record, bool $state): bool {
                            if (! static::canEdit($record) || $record->status === ExperimentStatus::Concluded) {
                                return $record->status === ExperimentStatus::Active;
                            }

                            static::setExperimentStatus(
                                $record,
                                $state ? ExperimentStatus::Active : ExperimentStatus::Paused,
                            );

                            return $state;
                        }),
                ])
                    ->alignCenter()
                    ->wrapHeader(),
                Tables\Columns\TextColumn::make('goal_event_name')
                    ->label('Goal')
                    ->badge(),
                Tables\Columns\TextColumn::make('variants_count')
                    ->label('Variants')
                    ->numeric(),
                Tables\Columns\TextColumn::make('assignments_count')
                    ->label('Assignments')
                    ->numeric(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(ExperimentStatus::cases())->mapWithKeys(fn (ExperimentStatus $status): array => [$status->value => $status->label()])),
            ])
            ->actions(array_values(array_filter([
                config('filament-growth.features.results', true)
                    ? Action::make('results')
                        ->label('Results')
                        ->icon('heroicon-o-chart-bar')
                        ->visible(fn (): bool => ExperimentResultsPage::canAccess())
                        ->url(fn (Experiment $record): string => ExperimentResultsPage::getUrl(['experiment' => $record->getKey()]))
                    : null,
                EditAction::make()
                    ->visible(fn (Experiment $record): bool => static::canEdit($record)),
            ])))
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('deleteSelected')
                        ->label('Delete Selected')
                        ->visible(fn (): bool => static::canDeleteAny())
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            static::deleteSelectedExperiments($records);
                        }),
                ]),
            ]);
    }

    public static function canCreate(): bool
    {
        return parent::canCreate()
            && app(AccessibleGrowthRecords::class)->canCreateExperiments();
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof Experiment
            && parent::canEdit($record)
            && app(AccessibleGrowthRecords::class)->canMutateExperiment($record);
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof Experiment
            && parent::canDelete($record)
            && app(AccessibleGrowthRecords::class)->canMutateExperiment($record);
    }

    public static function canDeleteAny(): bool
    {
        return parent::canDeleteAny();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExperiments::route('/'),
            'create' => Pages\CreateExperiment::route('/create'),
            'edit' => Pages\EditExperiment::route('/{record}/edit'),
        ];
    }

    /**
     * @param  Builder<Experiment>  $query
     * @return Builder<Experiment>
     */
    private static function applyOwnerSafeRelationCounts(Builder $query): Builder
    {
        $experimentTable = $query->getModel()->getTable();

        if ($query->getQuery()->columns === null) {
            $query->select($experimentTable . '.*');
        }

        return $query
            ->selectSub(static::ownerMatchedExperimentChildCount(Variant::class, $experimentTable), 'variants_count')
            ->selectSub(static::ownerMatchedExperimentChildCount(Assignment::class, $experimentTable), 'assignments_count');
    }

    /**
     * @template TChildModel of Model
     *
     * @param  class-string<TChildModel>  $childModelClass
     * @return Builder<TChildModel>
     */
    private static function ownerMatchedExperimentChildCount(string $childModelClass, string $experimentTable): Builder
    {
        $childModel = new $childModelClass;
        $childTable = $childModel->getTable();
        $experimentOwnerColumns = OwnerTupleColumns::forModelClass(Experiment::class);
        $childOwnerColumns = OwnerTupleColumns::forModelClass($childModelClass);

        /** @var Builder<TChildModel> $childQuery */
        $childQuery = $childModelClass::query();

        if (method_exists($childModelClass, 'scopeWithoutOwnerScope')) {
            /** @var Builder<TChildModel> $childQuery */
            $childQuery = $childQuery->withoutGlobalScope(OwnerScope::class);
        }

        $childQuery = $childQuery
            ->selectRaw('count(*)')
            ->whereColumn($childTable . '.experiment_id', $experimentTable . '.id')
            ->where(function (Builder $query) use ($childOwnerColumns, $childTable, $experimentOwnerColumns, $experimentTable): void {
                $query
                    ->where(function (Builder $ownerMatchedQuery) use ($childOwnerColumns, $childTable, $experimentOwnerColumns, $experimentTable): void {
                        $ownerMatchedQuery
                            ->whereColumn(
                                $childTable . '.' . $childOwnerColumns->ownerTypeColumn,
                                $experimentTable . '.' . $experimentOwnerColumns->ownerTypeColumn,
                            )
                            ->whereColumn(
                                $childTable . '.' . $childOwnerColumns->ownerIdColumn,
                                $experimentTable . '.' . $experimentOwnerColumns->ownerIdColumn,
                            );
                    })
                    ->orWhere(function (Builder $globalQuery) use ($childOwnerColumns, $childTable, $experimentOwnerColumns, $experimentTable): void {
                        $globalQuery
                            ->whereNull($childTable . '.' . $childOwnerColumns->ownerTypeColumn)
                            ->whereNull($childTable . '.' . $childOwnerColumns->ownerIdColumn)
                            ->whereNull($experimentTable . '.' . $experimentOwnerColumns->ownerTypeColumn)
                            ->whereNull($experimentTable . '.' . $experimentOwnerColumns->ownerIdColumn);
                    });
            });

        /** @var Builder<TChildModel> $childQuery */
        return $childQuery;
    }

    private static function ownerScopeKey(): string
    {
        if (! Experiment::ownerScopeConfig()->enabled && ! TrackedProperty::ownerScopeConfig()->enabled) {
            return OwnerScopeKey::GLOBAL;
        }

        return OwnerScopeKey::forOwner(OwnerContext::resolve());
    }

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

    private static function trackedPropertyName(Experiment $record): string
    {
        if ($record->relationLoaded('trackedProperty') && $record->trackedProperty instanceof TrackedProperty) {
            return (string) $record->trackedProperty->name;
        }

        $trackedProperty = static::findTrackedPropertyForExperiment($record);

        return $trackedProperty instanceof TrackedProperty ? (string) $trackedProperty->name : '—';
    }

    /**
     * @param  Builder<Experiment>  $query
     * @return Builder<Experiment>
     */
    private static function filterByTrackedPropertyName(Builder $query, string $search): Builder
    {
        $normalizedSearch = mb_trim($search);

        if ($normalizedSearch === '') {
            return $query;
        }

        $experimentTable = $query->getModel()->getTable();
        $trackedPropertyTable = (new TrackedProperty)->getTable();

        /** @var Builder<TrackedProperty> $trackedPropertyQuery */
        $trackedPropertyQuery = TrackedProperty::query();

        $trackedPropertyQuery = $trackedPropertyQuery->withoutGlobalScope(OwnerScope::class);

        $trackedPropertyQuery = $trackedPropertyQuery
            ->selectRaw('1')
            ->whereColumn($trackedPropertyTable . '.id', $experimentTable . '.tracked_property_id')
            ->where($trackedPropertyTable . '.name', 'like', '%' . $normalizedSearch . '%');

        if (Experiment::ownerScopeConfig()->enabled) {
            return $query->whereExists(
                static::scopeTrackedPropertyQueryToExperimentOwner(
                    $trackedPropertyQuery,
                    $trackedPropertyTable,
                    $experimentTable,
                ),
            );
        }

        return $query->whereExists(
            app(AccessibleGrowthRecords::class)->accessibleTrackedProperties($trackedPropertyQuery),
        );
    }

    /**
     * @param  Builder<TrackedProperty>  $query
     * @return Builder<TrackedProperty>
     */
    private static function scopeTrackedPropertyQueryToCurrentOwner(Builder $query): Builder
    {
        return app(AccessibleGrowthRecords::class)->writableTrackedProperties($query);
    }

    /**
     * @param  Builder<TrackedProperty>  $query
     * @return Builder<TrackedProperty>
     */
    private static function scopeTrackedPropertyQueryToExperimentOwner(
        Builder $query,
        string $trackedPropertyTable,
        string $experimentTable,
    ): Builder {
        $experimentOwnerColumns = OwnerTupleColumns::forModelClass(Experiment::class);
        $trackedPropertyOwnerColumns = OwnerTupleColumns::forModelClass(TrackedProperty::class);

        return $query->where(function (Builder $query) use ($experimentOwnerColumns, $experimentTable, $trackedPropertyOwnerColumns, $trackedPropertyTable): void {
            $query
                ->where(function (Builder $ownerMatchedQuery) use ($experimentOwnerColumns, $experimentTable, $trackedPropertyOwnerColumns, $trackedPropertyTable): void {
                    $ownerMatchedQuery
                        ->whereColumn(
                            $trackedPropertyTable . '.' . $trackedPropertyOwnerColumns->ownerTypeColumn,
                            $experimentTable . '.' . $experimentOwnerColumns->ownerTypeColumn,
                        )
                        ->whereColumn(
                            $trackedPropertyTable . '.' . $trackedPropertyOwnerColumns->ownerIdColumn,
                            $experimentTable . '.' . $experimentOwnerColumns->ownerIdColumn,
                        );
                })
                ->orWhere(function (Builder $globalQuery) use ($experimentOwnerColumns, $experimentTable, $trackedPropertyOwnerColumns, $trackedPropertyTable): void {
                    $globalQuery
                        ->whereNull($trackedPropertyTable . '.' . $trackedPropertyOwnerColumns->ownerTypeColumn)
                        ->whereNull($trackedPropertyTable . '.' . $trackedPropertyOwnerColumns->ownerIdColumn)
                        ->whereNull($experimentTable . '.' . $experimentOwnerColumns->ownerTypeColumn)
                        ->whereNull($experimentTable . '.' . $experimentOwnerColumns->ownerIdColumn);
                });
        });
    }

    private static function findTrackedPropertyForExperiment(Experiment $record): ?TrackedProperty
    {
        return app(AccessibleGrowthRecords::class)->findTrackedPropertyForExperiment($record);
    }

    private static function setExperimentStatus(Experiment $record, ExperimentStatus $status): bool
    {
        return $record->update([
            'status' => $status->value,
        ]);
    }

    private static function statusOperationalDescription(ExperimentStatus $status): string
    {
        return match ($status) {
            ExperimentStatus::Active => 'Assigning traffic',
            ExperimentStatus::Paused => 'Bypassing middleware',
            ExperimentStatus::Draft => 'Not live',
            ExperimentStatus::Concluded => 'Locked',
        };
    }

    /**
     * @param  Collection<int|string, Experiment>  $records
     */
    private static function deleteSelectedExperiments(Collection $records): void
    {
        DB::transaction(function () use ($records): void {
            foreach ($records as $record) {
                if (! $record instanceof Experiment || ! static::canDelete($record)) {
                    throw new RuntimeException('Global growth experiments can only be deleted from explicit global context.');
                }
            }

            foreach ($records as $record) {
                $record->delete();
            }
        });
    }
}
