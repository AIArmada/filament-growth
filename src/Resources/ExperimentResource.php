<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
use AIArmada\FilamentGrowth\Authorization\ExperimentAccess;
use AIArmada\FilamentGrowth\Resources\ExperimentResource\Pages;
use AIArmada\FilamentGrowth\Resources\ExperimentResource\Schemas\ExperimentForm;
use AIArmada\FilamentGrowth\Resources\ExperimentResource\Tables\ExperimentsTable;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Queries\ExperimentOwnership;
use AIArmada\Signals\Features\Ingestion\Models\TrackedProperty;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use UnitEnum;

final class ExperimentResource extends Resource
{
    protected static ?string $model = Experiment::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * @return Builder<Experiment>
     */
    public static function getEloquentQuery(): Builder
    {
        $propertyColumns = OwnerTupleColumns::forModelClass(TrackedProperty::class);

        return ExperimentAccess::applyOwnerSafeRelationCounts(
            ExperimentOwnership::scopeExperiments(Experiment::query()),
        )->with([
            'trackedProperty' => function ($query) use ($propertyColumns) {
                $builder = $query instanceof Relation ? $query->getQuery() : $query;

                /** @var Builder<TrackedProperty> $builder */
                return OwnerUiScope::apply($builder)
                    ->select(array_values(array_unique([
                        'id',
                        'name',
                        $propertyColumns->ownerTypeColumn,
                        $propertyColumns->ownerIdColumn,
                    ])));
            },
        ]);
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-growth.navigation.group');
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
        return ExperimentAccess::can('create', Experiment::class);
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof Experiment
            && ExperimentAccess::can('update', $record);
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof Experiment
            && ExperimentAccess::can('delete', $record);
    }

    public static function canDeleteAny(): bool
    {
        return ExperimentAccess::can('deleteAny', Experiment::class);
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
