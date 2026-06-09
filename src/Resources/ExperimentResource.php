<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\FilamentGrowth\Resources\ExperimentResource\Pages;
use AIArmada\FilamentGrowth\Resources\ExperimentResource\Schemas\ExperimentForm;
use AIArmada\FilamentGrowth\Resources\ExperimentResource\Tables\ExperimentsTable;
use AIArmada\FilamentGrowth\Support\ExperimentHelpers;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Signals\Models\TrackedProperty;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
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
        return ExperimentHelpers::applyOwnerSafeRelationCounts(Experiment::query())
            ->with([
                'trackedProperty' => function ($query) {
                    $builder = $query instanceof Relation ? $query->getQuery() : $query;

                    /** @var Builder<TrackedProperty> $builder */
                    return OwnerUiScope::apply($builder)
                        ->select(['id', 'name']);
                },
            ]);
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
        return ExperimentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExperimentsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return parent::canCreate()
            && ExperimentHelpers::canCreateExperiment();
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof Experiment
            && parent::canEdit($record)
            && OwnerUiScope::canMutateRecord($record);
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof Experiment
            && parent::canDelete($record)
            && OwnerUiScope::canMutateRecord($record);
    }

    public static function canDeleteAny(): bool
    {
        return parent::canDeleteAny()
            && Gate::allows('deleteAny', static::getModel());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExperiments::route('/'),
            'create' => Pages\CreateExperiment::route('/create'),
            'edit' => Pages\EditExperiment::route('/{record}/edit'),
        ];
    }
}
