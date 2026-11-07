<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Filament\Widgets\SystemHealthStats;
use App\Models\SystemSetting;
use App\Support\AppVersion;
use App\Support\System\SystemHealth;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use UnitEnum;

/**
 * ICTD's System health page: the server's state at a glance, and the switches
 * that affect every app user (minimum app version, maintenance notice) and
 * every administrator (required two-step sign-in). Changes are in the
 * activity log.
 */
class SystemHealthPage extends Page
{
    protected static ?string $slug = 'system-health';

    protected static ?string $title = 'System health';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 0;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::ManageSystemSettings->value) ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'min_app_version' => SystemSetting::minimumAppVersion(),
            'maintenance_on' => SystemSetting::maintenanceMessage() !== null,
            'maintenance_message' => SystemSetting::maintenanceMessage()
                ?? 'AniHow is being updated and will be back shortly. Thank you for waiting.',
            'require_admin_two_step' => SystemSetting::requiresAdminTwoStep(),
        ]);
    }

    protected function getHeaderWidgets(): array
    {
        return [SystemHealthStats::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retryFailedJobs')
                ->label('Retry failed jobs')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (): bool => SystemHealth::failedJobs() > 0)
                ->action(function (): void {
                    Artisan::call('queue:retry', ['id' => ['all']]);

                    Notification::make()->title('Failed jobs sent back to the queue.')->success()->send();
                }),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('App')
                    ->description('These affect everyone using the Android app.')
                    ->schema([
                        TextInput::make('min_app_version')
                            ->label('Minimum app version')
                            ->placeholder('No minimum')
                            ->maxLength(20)
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if (filled($value) && AppVersion::normalize((string) $value) === null) {
                                    $fail('Use a version like 1.0.3.');
                                }
                            })
                            ->helperText('Apps older than this are asked to update before they can continue. Apps from before 1.0.3 do not report their version and are not blocked.'),
                        Toggle::make('maintenance_on')
                            ->label('Maintenance mode')
                            ->live()
                            ->helperText('The app shows the message below instead of working. The CMS stays open.'),
                        Textarea::make('maintenance_message')
                            ->label('Message shown in the app')
                            ->rows(2)
                            ->maxLength(300)
                            ->required(fn ($get): bool => (bool) $get('maintenance_on'))
                            ->visible(fn ($get): bool => (bool) $get('maintenance_on')),
                    ]),
                Section::make('Administrators')
                    ->schema([
                        Toggle::make('require_admin_two_step')
                            ->label('Require two-step sign-in for administrators')
                            ->helperText('ICTD and COSEL accounts must enter a code sent to their email each time they sign in to the CMS. Each sets it up at their next sign-in.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Save settings')
                                ->submit('save'),
                        ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->getSchema('form')->getState();

        SystemSetting::put(SystemSetting::MIN_APP_VERSION, AppVersion::normalize($data['min_app_version'] ?? null));
        SystemSetting::put(
            SystemSetting::MAINTENANCE_MESSAGE,
            ($data['maintenance_on'] ?? false) ? trim((string) ($data['maintenance_message'] ?? '')) : null,
        );
        SystemSetting::put(SystemSetting::REQUIRE_ADMIN_TWO_STEP, ($data['require_admin_two_step'] ?? false) ? '1' : null);

        Notification::make()->title('Settings saved.')->success()->send();
    }
}
