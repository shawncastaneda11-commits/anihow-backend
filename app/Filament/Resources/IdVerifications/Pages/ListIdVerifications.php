<?php

namespace App\Filament\Resources\IdVerifications\Pages;

use App\Actions\IdVerification\ReviewIdVerificationAction;
use App\Enums\IdVerificationStatus;
use App\Filament\Resources\IdVerifications\IdVerificationResource;
use App\Filament\Resources\IdVerifications\Tables\IdVerificationsTable;
use App\Models\IdVerification;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * COSEL's ID checks as a queue and a review side by side: the waiting IDs
 * on the left, oldest first, and the chosen one open on the right with both
 * photos, the details to check, and the decision. Approving opens the next
 * one. The Decided tab shows each outcome.
 */
class ListIdVerifications extends Page
{
    protected static string $resource = IdVerificationResource::class;

    protected static ?string $title = 'ID checks';

    protected string $view = 'filament.id-checks.queue';

    protected Width|string|null $maxContentWidth = Width::Full;

    /** "waiting" or "decided". */
    public string $tab = 'waiting';

    public ?int $selectedId = null;

    public bool $rejecting = false;

    public string $reason = 'blurry';

    public string $note = '';

    public function mount(): void
    {
        $this->selectedId = $this->items()->first()?->getKey();
    }

    /**
     * The title and its line sit at the top of the queue column, as in the
     * design, so the page draws no header of its own.
     */
    public function getHeading(): string
    {
        return '';
    }

    public function intro(): string
    {
        return 'Buyers send an ID before their first order. Oldest first.';
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return ['anihow-split-page'];
    }

    public function showTab(string $tab): void
    {
        $this->tab = $tab === 'decided' ? 'decided' : 'waiting';
        $this->rejecting = false;
        $this->selectedId = $this->items()->first()?->getKey();
    }

    public function select(int $id): void
    {
        $this->selectedId = $this->items()->firstWhere('id', $id)?->getKey();
        $this->rejecting = false;
        $this->reason = 'blurry';
        $this->note = '';
    }

    public function startReject(): void
    {
        $this->rejecting = true;
    }

    public function cancelReject(): void
    {
        $this->rejecting = false;
        $this->resetErrorBag();
    }

    public function approve(): void
    {
        $record = $this->waitingSelected();

        if ($record === null) {
            return;
        }

        $this->decide(fn (ReviewIdVerificationAction $review, User $admin) => $review->approve($admin, $record));
        Notification::make()->title("{$record->user?->name}'s ID approved.")->success()->send();
        $this->openNext();
    }

    public function reject(): void
    {
        $this->resetErrorBag();
        $record = $this->waitingSelected();

        if ($record === null) {
            return;
        }

        if (! array_key_exists($this->reason, IdVerificationsTable::REJECTION_REASONS)) {
            throw ValidationException::withMessages(['reason' => 'Choose a reason.']);
        }

        if ($this->reason === 'other' && trim($this->note) === '') {
            throw ValidationException::withMessages(['note' => 'Write the reason the buyer will see.']);
        }

        $text = IdVerificationsTable::rejectionText(['reason' => $this->reason, 'note' => $this->note]);

        $this->decide(fn (ReviewIdVerificationAction $review, User $admin) => $review->reject($admin, $record, $text));
        Notification::make()->title("{$record->user?->name}'s ID rejected. They can send a new photo.")->success()->send();
        $this->openNext();
    }

    /**
     * @return Collection<int, IdVerification>
     */
    public function items(): Collection
    {
        $query = IdVerificationResource::getEloquentQuery();

        return $this->tab === 'decided'
            ? $query->where('status', '!=', IdVerificationStatus::Pending)->latest('reviewed_at')->limit(50)->get()
            : $query->where('status', IdVerificationStatus::Pending)->oldest()->get();
    }

    public function selected(): ?IdVerification
    {
        return $this->selectedId === null ? null : $this->items()->firstWhere('id', $this->selectedId);
    }

    public function waitingCount(): int
    {
        return IdVerificationResource::getEloquentQuery()->where('status', IdVerificationStatus::Pending)->count();
    }

    public function earlierTries(IdVerification $record): string
    {
        $rejected = IdVerification::query()
            ->where('user_id', $record->user_id)
            ->whereKeyNot($record->getKey())
            ->where('status', IdVerificationStatus::Rejected)
            ->count();

        return $rejected === 0 ? 'First try' : $rejected.' rejected before';
    }

    public function photoUrl(IdVerification $record, string $side): ?string
    {
        $path = $side === 'front' ? $record->front_path : $record->selfie_path;

        return filled($path) ? route('id-verifications.photo', ['idVerification' => $record, 'side' => $side]) : null;
    }

    /**
     * @return array<string, string>
     */
    public function reasons(): array
    {
        return IdVerificationsTable::REJECTION_REASONS;
    }

    private function waitingSelected(): ?IdVerification
    {
        $record = $this->selected();

        return $record !== null && $record->isPending() ? $record : null;
    }

    private function decide(callable $run): void
    {
        $admin = auth()->user();
        abort_unless($admin instanceof User, 403);

        $run(app(ReviewIdVerificationAction::class), $admin);
    }

    private function openNext(): void
    {
        $this->rejecting = false;
        $this->reason = 'blurry';
        $this->note = '';
        $this->selectedId = $this->items()->first()?->getKey();
    }
}
