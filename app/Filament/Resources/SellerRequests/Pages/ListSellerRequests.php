<?php

namespace App\Filament\Resources\SellerRequests\Pages;

use App\Actions\Accounts\DecideSellerRequest;
use App\Actions\Accounts\RequestFarmerSellers;
use App\Enums\Role;
use App\Enums\SellerRequestStatus;
use App\Filament\Resources\SellerRequests\SellerRequestResource;
use App\Models\SellerRequest;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

/**
 * Seller requests, as two screens.
 *
 * COSEL: the waiting requests on the left, oldest first, and the chosen one
 * open on the right with the checks and the decision. Creating the account
 * shows its temporary password once, to hand over in person.
 *
 * A Content Editor: the requests they sent and the farm's farmer-sellers,
 * with the form to ask COSEL for more docked at the right.
 */
class ListSellerRequests extends Page
{
    protected static string $resource = SellerRequestResource::class;

    protected static ?string $title = 'Seller requests';

    protected string $view = 'filament.seller-requests.page';

    protected Width|string|null $maxContentWidth = Width::Full;

    /** COSEL: "waiting" or "decided". */
    public string $tab = 'waiting';

    public ?int $selectedId = null;

    public bool $declining = false;

    public string $reason = '';

    /**
     * The account just created, shown once. Never stored anywhere else.
     *
     * @var array{name: string, email: string, farm: string, password: string}|null
     */
    #[Locked]
    public ?array $created = null;

    /** Content Editor: "one" or "many". */
    public string $mode = 'one';

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $rows = '';

    public string $note = '';

    public bool $sent = false;

    public function mount(): void
    {
        if ($this->decides()) {
            $this->selectedId = $this->items()->first()?->getKey();
        }
    }

    public function getHeading(): string
    {
        return '';
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return ['anihow-split-page'];
    }

    public function decides(): bool
    {
        return SellerRequestResource::decides(auth()->user());
    }

    public function intro(): string
    {
        return $this->decides()
            ? 'Content Editors ask; you decide. Oldest first.'
            : 'Your farm\'s farmer-sellers. To add someone, send a request; COSEL creates the account.';
    }

    // ---- COSEL ----

    public function showTab(string $tab): void
    {
        $this->tab = $tab === 'decided' ? 'decided' : 'waiting';
        $this->created = null;
        $this->resetDecision();
        $this->selectedId = $this->items()->first()?->getKey();
    }

    public function select(int $id): void
    {
        $this->created = null;
        $this->resetDecision();
        $this->selectedId = $this->items()->firstWhere('id', $id)?->getKey();
    }

    public function startDecline(): void
    {
        $this->declining = true;
    }

    public function cancelDecline(): void
    {
        $this->resetDecision();
    }

    public function createAccount(): void
    {
        $this->resetErrorBag();
        $request = $this->waitingSelected();

        if ($request === null) {
            return;
        }

        try {
            $result = app(DecideSellerRequest::class)->add($this->cosel(), $request);
        } catch (ValidationException $exception) {
            $this->addError('decision', (string) collect($exception->errors())->flatten()->first());

            return;
        }

        $this->created = [
            'name' => (string) $result['user']->name,
            'email' => (string) $result['user']->email,
            'farm' => (string) ($request->farm?->name ?? 'the farm'),
            'password' => $result['password'],
        ];
        $this->selectedId = null;
    }

    public function decline(): void
    {
        $this->resetErrorBag();
        $request = $this->waitingSelected();

        if ($request === null) {
            return;
        }

        if (trim($this->reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Write the reason the Content Editor sees.']);
        }

        app(DecideSellerRequest::class)->decline($this->cosel(), $request, $this->reason);
        Notification::make()->title("{$request->name} was not added. The Content Editor sees your reason.")->success()->send();
        $this->openNext();
    }

    /** "Done, open the next request": the password is gone from the page. */
    public function finishCreated(): void
    {
        $this->created = null;
        $this->openNext();
    }

    /**
     * @return Collection<int, SellerRequest>
     */
    public function items(): Collection
    {
        $query = SellerRequestResource::getEloquentQuery()->with(['farm', 'requester']);

        return $this->tab === 'decided'
            ? $query->where('status', '!=', SellerRequestStatus::Waiting)->latest('decided_at')->limit(50)->get()
            : $query->where('status', SellerRequestStatus::Waiting)->oldest()->get();
    }

    public function selected(): ?SellerRequest
    {
        return $this->selectedId === null ? null : $this->items()->firstWhere('id', $this->selectedId);
    }

    public function waitingCount(): int
    {
        return SellerRequestResource::getEloquentQuery()->where('status', SellerRequestStatus::Waiting)->count();
    }

    /**
     * What COSEL checks before creating the account.
     *
     * @return list<array{ok: bool, label: string}>
     */
    public function checks(SellerRequest $request): array
    {
        $taken = User::withTrashed()->where('email', $request->email)->exists();

        return [
            ['ok' => ! $taken, 'label' => $taken ? "{$request->email} already has an AniHow account" : 'No AniHow account uses this email yet'],
            ['ok' => (bool) $request->farm?->is_active, 'label' => $request->farm?->is_active ? 'The farm is active' : 'The farm is not active'],
            ['ok' => true, 'label' => 'Role: farmer-seller. A temporary password is shown to you once'],
        ];
    }

    // ---- Content Editor ----

    /**
     * @return Collection<int, SellerRequest>
     */
    public function myRequests(): Collection
    {
        return SellerRequestResource::getEloquentQuery()->latest()->limit(20)->get();
    }

    /**
     * @return Collection<int, User>
     */
    public function farmSellers(): Collection
    {
        $farmId = auth()->user()?->farm_id;

        if ($farmId === null) {
            return collect();
        }

        return User::query()
            ->where('farm_id', $farmId)
            ->role(Role::FarmerSeller->value)
            ->withCount(['listings' => fn (Builder $query): Builder => $query->where('is_active', true)])
            ->orderBy('name')
            ->get();
    }

    public function farmName(): string
    {
        return (string) (auth()->user()?->farm?->name ?? 'your farm');
    }

    public function setMode(string $mode): void
    {
        $this->mode = $mode === 'many' ? 'many' : 'one';
        $this->sent = false;
        $this->resetErrorBag();
    }

    /**
     * @return array{people: list<array{label: string, name: ?string, email: ?string, phone: ?string, problem: ?string}>, truncated: bool}
     */
    public function preview(): array
    {
        return trim($this->rows) === ''
            ? ['people' => [], 'truncated' => false]
            : app(RequestFarmerSellers::class)->check($this->rows);
    }

    public function send(): void
    {
        $editor = auth()->user();
        abort_unless($editor instanceof User && SellerRequestResource::asks($editor), 403);

        $this->sent = false;
        $this->validate($this->mode === 'many'
            ? ['rows' => ['required', 'string']]
            : ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:30']],
            ['rows.required' => 'Add one person per line.'],
        );

        $rows = $this->mode === 'many'
            ? $this->rows
            : implode(', ', [str_replace(',', ' ', $this->name), $this->email, $this->phone]);

        $result = app(RequestFarmerSellers::class)->handle($editor, $rows, $this->note);

        if ($result['skipped'] !== []) {
            Notification::make()
                ->title(count($result['sent']) === 0 ? 'Nothing was sent.' : count($result['sent']).' sent to COSEL.')
                ->body('Not sent: '.implode(' ', $result['skipped']))
                ->warning()
                ->persistent()
                ->send();
        }

        if ($result['sent'] !== []) {
            $this->sent = true;
            $this->reset('name', 'email', 'phone', 'rows', 'note');
        }
    }

    // ----

    private function waitingSelected(): ?SellerRequest
    {
        $request = $this->selected();

        return $request !== null && $request->isWaiting() ? $request : null;
    }

    private function cosel(): User
    {
        $cosel = auth()->user();
        abort_unless($cosel instanceof User && $this->decides(), 403);

        return $cosel;
    }

    private function resetDecision(): void
    {
        $this->declining = false;
        $this->reason = '';
        $this->resetErrorBag();
    }

    private function openNext(): void
    {
        $this->resetDecision();
        $this->selectedId = $this->items()->first()?->getKey();
    }
}
