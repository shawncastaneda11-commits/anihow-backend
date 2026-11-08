<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Filament\Widgets\SystemHealthStats;
use App\Models\ActivityLog;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\ActivityRecorder;
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

    protected static ?int $navigationSort = 2;

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
            'app_download_url' => SystemSetting::appDownloadUrl(),
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
                    $failed = SystemHealth::failedJobs();
                    Artisan::call('queue:retry', ['id' => ['all']]);
                    ActivityRecorder::note('retried_failed_jobs', 'Background jobs', ['jobs' => $failed], ActivityLog::AREA_SYSTEM);

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
                        TextInput::make('app_download_url')
                            ->label('Where to get the new app')
                            ->placeholder('https://')
                            ->url()
                            ->maxLength(500)
                            ->required(fn ($get): bool => filled($get('min_app_version')))
                            ->helperText('The update screen opens this link, for example the shared folder with the APK. Needed when a minimum version is set.'),
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
                            ->helperText('ICTD and COSEL accounts must enter a code sent to their email each time they sign in to the CMS. Turn this on only when every administrator account uses a real email they can open, or they cannot sign in. Turning it off stops the codes for everyone.'),
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
        SystemSetting::put(SystemSetting::APP_DOWNLOAD_URL, trim((string) ($data['app_download_url'] ?? '')));

        $requireTwoStep = (bool) ($data['require_admin_two_step'] ?? false);
        SystemSetting::put(SystemSetting::REQUIRE_ADMIN_TWO_STEP, $requireTwoStep ? '1' : null);

        // Off means off: nobody keeps being asked for a code they set up
        // while it was required.
        if (! $requireTwoStep) {
            $stopped = User::query()->where('has_email_authentication', true)->update(['has_email_authentication' => false]);

            if ($stopped > 0) {
                ActivityRecorder::note('two_step_turned_off', 'Two-step sign-in', ['accounts' => $stopped], ActivityLog::AREA_ADMINS);
            }
        }

        Notification::make()->title('Settings saved.')->success()->send();
    }
}
