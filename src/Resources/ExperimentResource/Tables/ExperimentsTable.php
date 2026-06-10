<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Resources\ExperimentResource\Tables;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
use AIArmada\FilamentGrowth\Pages\ExperimentResultsPage;
use AIArmada\FilamentGrowth\Support\ExperimentHelpers;
use AIArmada\Growth\Enums\ExperimentModuleType;
use AIArmada\Growth\Enums\ExperimentStatus;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Signals\Models\TrackedProperty;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExperimentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Experiment $record): string => $record->slug),
                Tables\Columns\TextColumn::make('tracked_property_id')
                    ->label('Tracked Property')
                    ->state(fn (Experiment $record): string => ExperimentsTable::trackedPropertyName($record))
                    ->searchable(query: fn (Builder $query, string $search): Builder => ExperimentsTable::filterByTrackedPropertyName($query, $search)),
                Tables\Columns\TextColumn::make('module_type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ExperimentModuleType::labelFor($state))
                    ->sortable(),
                Tables\Columns\ColumnGroup::make('Lifecycle', [
                    Tables\Columns\TextColumn::make('status')
                        ->badge()
                        ->formatStateUsing(fn (ExperimentStatus $state): string => $state->label())
                        ->description(fn (Experiment $record): string => ExperimentsTable::statusOperationalDescription($record->status))
                        ->color(fn (ExperimentStatus $state): string => $state->color()),
                    Tables\Columns\ToggleColumn::make('is_running')
                        ->label('Running')
                        ->state(fn (Experiment $record): bool => $record->status === ExperimentStatus::Active)
                        ->disabled(fn (Experiment $record): bool => ! ExperimentsTable::canEditRecord($record) || $record->status === ExperimentStatus::Concluded)
                        ->onColor('success')
                        ->offColor('warning')
                        ->updateStateUsing(function (Experiment $record, bool $state): bool {
                            if (! ExperimentsTable::canEditRecord($record) || $record->status === ExperimentStatus::Concluded) {
                                return $record->status === ExperimentStatus::Active;
                            }

                            $record->update([
                                'status' => $state ? ExperimentStatus::Active->value : ExperimentStatus::Paused->value,
                            ]);

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
                    ->visible(fn (Experiment $record): bool => ExperimentsTable::canEditRecord($record)),
            ])))
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('deleteSelected')
                        ->label('Delete Selected')
                        ->visible(fn (): bool => ExperimentHelpers::canDeleteAnyExperiment())
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            ExperimentsTable::deleteSelectedExperiments($records);
                        }),
                ]),
            ]);
    }

    private static function trackedPropertyName(Experiment $record): string
    {
        if ($record->relationLoaded('trackedProperty') && $record->trackedProperty instanceof TrackedProperty) {
            return (string) $record->trackedProperty->name;
        }

        $trackedProperty = ExperimentsTable::findTrackedPropertyForExperiment($record);

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

        $trackedPropertyQuery = TrackedProperty::query();

        $trackedPropertyQuery = $trackedPropertyQuery->withoutGlobalScope(OwnerScope::class);

        $trackedPropertyQuery = $trackedPropertyQuery
            ->selectRaw('1')
            ->whereColumn($trackedPropertyTable . '.id', $experimentTable . '.tracked_property_id')
            ->where($trackedPropertyTable . '.name', 'like', '%' . $normalizedSearch . '%');

        if (Experiment::ownerScopeConfig()->enabled) {
            return $query->whereExists(
                ExperimentsTable::scopeTrackedPropertyQueryToExperimentOwner(
                    $trackedPropertyQuery,
                    $trackedPropertyTable,
                    $experimentTable,
                ),
            );
        }

        return $query->whereExists(
            OwnerUiScope::apply($trackedPropertyQuery),
        );
    }

    private static function findTrackedPropertyForExperiment(Experiment $record): ?TrackedProperty
    {
        $query = OwnerUiScope::applyForRecordOwner(
            TrackedProperty::query(),
            $record,
            recordConfigKey: 'growth.features.owner',
            queryConfigKey: 'signals.owner',
        );

        $trackedProperty = $query
            ->whereKey($record->tracked_property_id)
            ->first();

        return $trackedProperty instanceof TrackedProperty ? $trackedProperty : null;
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

    private static function canEditRecord(Experiment $record): bool
    {
        return OwnerUiScope::canMutateRecord($record);
    }

    private static function statusOperationalDescription(ExperimentStatus $status): string
    {
        return match ($status) {
            ExperimentStatus::Active => 'Assigning traffic',
            ExperimentStatus::Paused => 'Bypassing middleware',
            ExperimentStatus::Draft => 'Not live',
            ExperimentStatus::Concluded => 'Locked',
            ExperimentStatus::Archived => 'Archived',
        };
    }

    /**
     * @param  Collection<int|string, Experiment>  $records
     */
    private static function deleteSelectedExperiments(Collection $records): void
    {
        DB::transaction(function () use ($records): void {
            foreach ($records as $record) {
                if (! $record instanceof Experiment || ! OwnerUiScope::canMutateRecord($record)) {
                    throw new RuntimeException('Global growth experiments can only be deleted from explicit global context.');
                }
            }

            foreach ($records as $record) {
                $record->delete();
            }
        });
    }
}
