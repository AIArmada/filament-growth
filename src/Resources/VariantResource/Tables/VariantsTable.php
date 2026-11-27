<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Resources\VariantResource\Tables;

use AIArmada\CommerceSupport\Support\ConnectionDriver;
use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\FilamentGrowth\Actions\DeleteSelectedVariants;
use AIArmada\FilamentGrowth\Authorization\ExperimentAccess;
use AIArmada\Growth\Enums\VariantStatus;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class VariantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('experiment_id')
                    ->label('Experiment')
                    ->state(fn (Variant $record): string => VariantsTable::experimentName($record))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $operator = match (ConnectionDriver::name($query->getConnection())) {
                            'pgsql' => 'ilike',
                            default => 'like',
                        };

                        return $query->whereIn(
                            $query->getModel()->qualifyColumn('experiment_id'),
                            OwnerUiScope::apply(Experiment::query())
                                ->where('name', $operator, '%' . $search . '%')
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
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (VariantStatus $state): string => $state->color()),
                Tables\Columns\TextColumn::make('position')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('position')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(VariantStatus::class),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (Variant $record): bool => ExperimentAccess::can('update', $record)),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('deleteSelected')
                        ->label('Delete Selected')
                        ->visible(fn (): bool => VariantsTable::canDeleteAnyVariant())
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            app(DeleteSelectedVariants::class)->handle($records);
                        }),
                ]),
            ]);
    }

    private static function experimentName(Variant $record): string
    {
        if ($record->relationLoaded('experiment') && $record->experiment instanceof Experiment) {
            return (string) $record->experiment->name;
        }

        $experiment = OwnerUiScope::apply(Experiment::query())->whereKey($record->experiment_id)->first();

        return $experiment instanceof Experiment ? (string) $experiment->name : '—';
    }

    private static function canDeleteAnyVariant(): bool
    {
        return ExperimentAccess::can('deleteAny', Variant::class);
    }
}
