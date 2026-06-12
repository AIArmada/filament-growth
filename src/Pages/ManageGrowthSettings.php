<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Pages;

use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Settings\GrowthSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelSettings\Exceptions\MissingSettings;
use UnitEnum;

final class ManageGrowthSettings extends Page
{
    public ?array $data = [];

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $title = 'Growth Settings';

    protected static ?string $slug = 'growth/settings';

    protected string $view = 'filament-growth::pages.manage-growth-settings';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-growth.navigation.group', 'Growth');
    }

    public static function getNavigationSort(): int
    {
        return (int) config('filament-growth.resources.navigation_sort.settings', 99);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-growth.features.settings_page', true)
            && static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user !== null
            && parent::canAccess()
            && Gate::forUser($user)->allows('viewAny', Experiment::class);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $settings = $this->resolveGrowthSettings();

        $this->data = [
            'experimentMiddlewareEnabled' => $settings->experimentMiddlewareEnabled ?? true,
        ];

        $this->getSchema('form')?->fill($this->data);
    }

    public function getTitle(): string
    {
        return 'Growth Settings';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Toggle::make('experimentMiddlewareEnabled')
                    ->label('Enable global experiment middleware')
                    ->helperText('Global kill switch: disable this to bypass request-time assignment for every Growth experiment while keeping the experiment records intact.'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        /** @var array<string, mixed> $state */
        $state = $this->data ?? [];

        $settings = $this->resolveGrowthSettings();
        $settings->experimentMiddlewareEnabled = (bool) Arr::get($state, 'experimentMiddlewareEnabled', true);
        $settings->save();

        Notification::make()
            ->title('Saved')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->action('save'),
        ];
    }

    private function resolveGrowthSettings(): GrowthSettings
    {
        try {
            $settings = app(GrowthSettings::class);
            $experimentMiddlewareEnabled = $settings->experimentMiddlewareEnabled;

            if (is_bool($experimentMiddlewareEnabled)) {
                return $settings;
            }

            return $settings;
        } catch (MissingSettings) {
            $settings = new GrowthSettings([
                'experimentMiddlewareEnabled' => true,
            ]);

            $settings->save();

            return $settings;
        }
    }
}
