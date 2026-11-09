<?php

namespace App\Filament\Resources\Users\Pages;

use App\Actions\Accounts\BulkCreateFarmerSellers;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Filament\Resources\Users\Actions\SuspendAccountAction;
use App\Filament\Resources\Users\Actions\TemporaryPasswordAction;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\UserResource;
use App\Models\Farm;
use App\Models\User;
use App\Support\Accounts\AccountAuthority;
use App\Support\ActivityRecorder;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

/**
 * The account list with the chosen account open in a panel beside it: its
 * email, mobile, role and farm to edit, and the quick actions (temporary
 * password, sign out everywhere, suspend). The full form keeps the rest.
 */
class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected string $view = 'filament.accounts.list';

    protected Width|string|null $maxContentWidth = Width::Full;

    /** The account open in the panel. */
    public ?int $openId = null;

    /** @var array<string, mixed>|null */
    public ?array $account = [];

    private ?User $openRecordCache = null;

    /**
     * The title, its line and the buttons sit in the list column, as in the
     * design, so the page draws no header of its own.
     */
    public function getHeader(): ?View
    {
        return view('filament.blank');
    }

    public function intro(): string
    {
        $actor = auth()->user();

        if (AccountAuthority::managesPeople($actor)) {
            return 'Farmer-sellers, buyers and Content Editors. ICTD handles administrator accounts.';
        }

        if (AccountAuthority::managesAdmins($actor)) {
            return 'Administrator accounts for COSEL and ICTD. COSEL handles everyone else.';
        }

        return 'The farmer-sellers on your farm. COSEL adds and changes accounts.';
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return ['anihow-split-page', 'anihow-accounts-page'];
    }

    public function openAccount(int|string $key): void
    {
        $record = UserResource::getEloquentQuery()->find($key);

        if (! $record instanceof User) {
            $this->closeAccount();

            return;
        }

        $this->openId = (int) $record->getKey();
        $this->openRecordCache = $record;
        $this->resetErrorBag();
        $this->accountForm->model($record)->fill($record->attributesToArray());
    }

    public function closeAccount(): void
    {
        $this->openId = null;
        $this->openRecordCache = null;
        $this->account = [];
        $this->resetErrorBag();
    }

    /**
     * The account open in the panel, if this person may still see it.
     */
    public function openRecord(): ?User
    {
        if ($this->openId === null) {
            return null;
        }

        if ($this->openRecordCache?->getKey() !== $this->openId) {
            $this->openRecordCache = UserResource::getEloquentQuery()->find($this->openId);
        }

        return $this->openRecordCache;
    }

    public function initials(User $user): string
    {
        return collect(preg_split('/\s+/', trim((string) $user->name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    public function canEditOpenAccount(): bool
    {
        $record = $this->openRecord();

        return $record !== null && AccountAuthority::canManageOther(auth()->user(), $record);
    }

    public function accountForm(Schema $schema): Schema
    {
        return $schema
            ->model(fn (): User|string => $this->openRecord() ?? User::class)
            ->statePath('account')
            ->disabled(fn (): bool => ! $this->canEditOpenAccount())
            ->components([
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label('Mobile number')
                    ->tel()
                    ->maxLength(30),
                Grid::make(2)->schema([
                    UserForm::roleField()->helperText(null),
                    UserForm::farmField()->helperText(null),
                ]),
            ]);
    }

    public function saveAccount(): void
    {
        $record = $this->openRecord();

        abort_unless($record !== null && AccountAuthority::canManageOther(auth()->user(), $record), 403);

        $rolesBefore = $record->getRoleNames()->values()->all();

        // Saves the role through its relationship, as the full form does.
        $data = $this->accountForm->getState();

        $record->update($data);
        ActivityRecorder::roleChanged($record, $rolesBefore, $record->load('roles')->getRoleNames()->values()->all());

        Notification::make()->title('Saved.')->success()->send();
        $this->openAccount($record->getKey());
    }

    public function setTemporaryPasswordAction(): Action
    {
        return Action::make('setTemporaryPassword')
            ->label('Set')
            ->link()
            ->requiresConfirmation()
            ->modalHeading('Set a temporary password')
            ->modalDescription('This signs them out of the app and replaces their password. The new temporary password is shown once.')
            ->visible(fn (): bool => $this->canEditOpenAccount())
            ->action(function (): void {
                $record = $this->openRecord();
                abort_unless($record !== null && AccountAuthority::canManageOther(auth()->user(), $record), 403);

                $this->replaceMountedAction('temporaryPassword', [
                    'password' => $record->issueTemporaryPassword(),
                    'email' => $record->email,
                ]);
            });
    }

    public function temporaryPasswordAction(): Action
    {
        return TemporaryPasswordAction::make();
    }

    public function signOutEverywhereAction(): Action
    {
        return Action::make('signOutEverywhere')
            ->label('Sign out')
            ->link()
            ->requiresConfirmation()
            ->modalDescription('This signs the person out of the app on every phone and out of the CMS. Their password does not change.')
            ->visible(fn (): bool => $this->canEditOpenAccount())
            ->action(function (): void {
                $record = $this->openRecord();
                abort_unless($record !== null && AccountAuthority::canManageOther(auth()->user(), $record), 403);

                $record->signOutEverywhere();
                ActivityRecorder::note('signed_out_everywhere', $record);

                Notification::make()->title('Signed out everywhere.')->success()->send();
            });
    }

    public function suspendOpenAccountAction(): Action
    {
        return SuspendAccountAction::make('suspendOpenAccount')
            ->link()
            ->record(fn (): ?User => $this->openRecord())
            ->after(fn () => $this->openAccount((int) $this->openId));
    }

    public function liftSuspensionAction(): Action
    {
        return Action::make('liftSuspension')
            ->label('Lift')
            ->link()
            ->requiresConfirmation()
            ->modalDescription('They can sign in and use AniHow again.')
            ->visible(fn (): bool => ($this->openRecord()?->isActive() === false)
                && (auth()->user()?->can('suspend', $this->openRecord()) ?? false))
            ->action(function (): void {
                $record = $this->openRecord();
                abort_unless($record !== null && (auth()->user()?->can('suspend', $record) ?? false), 403);

                $record->update([
                    'status' => UserStatus::Active,
                    'suspended_at' => null,
                    'suspension_reason' => null,
                ]);

                Notification::make()->title('Suspension lifted.')->success()->send();
                $this->openAccount($record->getKey());
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            // COSEL only: many farmer-sellers for one farm at once.
            Action::make('bulkCreateFarmerSellers')
                ->label('Add many farmer-sellers')
                ->icon('heroicon-o-user-group')
                ->color('gray')
                ->visible(fn (): bool => AccountAuthority::managesPeople(auth()->user()))
                ->modalDescription('Paste one person per line: name, email, and mobile (optional), separated by commas or copied straight from a spreadsheet. '
                    .'No password is created you can see: hand each person a temporary password with "Reset temporary password" when you meet them.')
                ->schema([
                    Select::make('farm_id')
                        ->label('Farm')
                        ->options(fn (): array => Farm::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->required()
                        ->searchable()
                        ->native(false),
                    Textarea::make('rows')
                        ->label('People')
                        ->required()
                        ->rows(10)
                        ->placeholder("Juan Dela Cruz, juan@example.com, 09171234567\nMaria Santos, maria@example.com"),
                ])
                ->action(function (array $data): void {
                    abort_unless(AccountAuthority::managesPeople(auth()->user()), 403);

                    $result = app(BulkCreateFarmerSellers::class)
                        ->handle(Farm::query()->findOrFail($data['farm_id']), $data['rows']);

                    $created = count($result['created']);
                    $notification = Notification::make()
                        ->title("{$created} farmer-seller accounts created.")
                        ->body($result['skipped'] === [] ? null : 'Not created: '.implode(' ', $result['skipped']))
                        ->persistent();

                    ($result['skipped'] === [] ? $notification->success() : $notification->warning())->send();
                }),
            CreateAction::make()
                ->label('Add account'),
        ];
    }

    /**
     * COSEL's role chips. ICTD's list is administrators only, so it has none.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        if (! AccountAuthority::managesPeople(auth()->user())) {
            return [];
        }

        $role = fn (Role $role): Closure => fn (Builder $query): Builder => $query->whereHas(
            'roles',
            fn (Builder $roles): Builder => $roles->where('name', $role->value),
        );

        $tabs = [
            'all' => ['All', fn (Builder $query): Builder => $query],
            'sellers' => ['Farmer-sellers', $role(Role::FarmerSeller)],
            'buyers' => ['Buyers', $role(Role::Buyer)],
            'editors' => ['Content Editors', $role(Role::ContentEditor)],
            'suspended' => ['Suspended', fn (Builder $query): Builder => $query->where('status', UserStatus::Suspended)],
        ];

        return collect($tabs)
            ->map(fn (array $tab): Tab => Tab::make($tab[0])
                ->modifyQueryUsing($tab[1])
                ->badge(fn (): int => $tab[1](UserResource::getEloquentQuery())->count()))
            ->all();
    }
}
