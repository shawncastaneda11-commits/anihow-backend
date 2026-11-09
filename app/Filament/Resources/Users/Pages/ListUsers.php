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
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
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

    /** Name, email or phone. */
    public string $search = '';

    /** COSEL's farm choice above the list. */
    public ?string $farm = null;

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

    /**
     * The list without Filament's tab bar: the chips render in the row with
     * the search and the farm (filament.accounts.list).
     */
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
            EmbeddedTable::make(),
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
        ]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFarm(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<int|string, string>
     */
    public function farmOptions(): array
    {
        return AccountAuthority::managesPeople(auth()->user())
            ? Farm::query()->orderBy('name')->pluck('name', 'id')->all()
            : [];
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function applyListFilters(Builder $query): Builder
    {
        $term = trim($this->search);

        return $query
            ->when($term !== '', fn (Builder $query): Builder => $query->where(fn (Builder $match): Builder => $match
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")))
            ->when(filled($this->farm) && $this->farmOptions() !== [], fn (Builder $query): Builder => $query->where('farm_id', (int) $this->farm));
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
        $record->keepBuyingInStep();
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

    /**
     * "Can also buy": COSEL turns buying on or off for a farmer-seller. The
     * seller can also turn it on in the app.
     */
    public function canSetBuying(): bool
    {
        $record = $this->openRecord();

        return $record !== null
            && $record->isFarmerSeller()
            && AccountAuthority::managesPeople(auth()->user())
            && AccountAuthority::canManageOther(auth()->user(), $record);
    }

    public function turnOnBuyingAction(): Action
    {
        return Action::make('turnOnBuying')
            ->label('Turn on')
            ->link()
            ->requiresConfirmation()
            ->modalHeading('Let this seller also buy?')
            ->modalDescription('They keep one login and switch between Selling and Buying in the app. Like any buyer, they send an ID before their first order. They cannot buy their own listings.')
            ->visible(fn (): bool => $this->canSetBuying() && ! $this->openRecord()?->isBuyer())
            ->action(function (): void {
                $record = $this->openRecord();
                abort_unless($record !== null && $this->canSetBuying(), 403);

                $record->turnOnBuying('cms');
                ActivityRecorder::note('buying_turned_on', $record, ['via' => 'cms']);

                Notification::make()->title('Buying turned on.')->success()->send();
                $this->openAccount($record->getKey());
            });
    }

    public function turnOffBuyingAction(): Action
    {
        return Action::make('turnOffBuying')
            ->label('Turn off')
            ->link()
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Turn off buying?')
            ->modalDescription('This ends buying only. Their open orders as a buyer stay as they are, and selling is not affected.')
            ->visible(fn (): bool => $this->canSetBuying() && (bool) $this->openRecord()?->isBuyer())
            ->action(function (): void {
                $record = $this->openRecord();
                abort_unless($record !== null && $this->canSetBuying(), 403);

                $record->turnOffBuying();
                ActivityRecorder::note('buying_turned_off', $record);

                Notification::make()->title('Buying turned off.')->success()->send();
                $this->openAccount($record->getKey());
            });
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
            // Buyer-only accounts; a farmer-seller who also buys stays a seller.
            'buyers' => ['Buyers', fn (Builder $query): Builder => $role(Role::Buyer)($query)
                ->whereDoesntHave('roles', fn (Builder $roles): Builder => $roles->where('name', Role::FarmerSeller->value))],
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
