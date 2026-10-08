<?php

namespace App\Actions\Listings;

use App\Enums\HarvestRecordKind;
use App\Enums\ReservationStatus;
use App\Models\Listing;
use App\Models\User;
use App\Support\HarvestInput;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordActualHarvestAction
{
    public function __construct(private WriteHarvestRecord $records) {}

    /**
     * @param  array{
     *     harvested_on: string,
     *     quantity_harvested: string,
     *     quantity_rejected: string,
     *     quantity_good: string,
     *     rejection_reason: ?string,
     *     rejection_note: ?string,
     *     production_cost: ?string,
     *     cost_breakdown: ?array<string, string>
     * }  $harvest
     */
    public function handle(Listing $listing, array $harvest, User $actor, bool $confirmCancelReservations): Listing
    {
        return DB::transaction(function () use ($listing, $harvest, $actor, $confirmCancelReservations): Listing {
            $locked = Listing::withTrashed()->whereKey($listing->id)->lockForUpdate()->firstOrFail();

            if ($locked->trashed()) {
                throw ValidationException::withMessages([
                    'listing' => 'This listing has been deleted.',
                ]);
            }

            if (! $locked->needs_actual_harvest) {
                throw ValidationException::withMessages([
                    'listing' => 'This listing does not need an actual harvest.',
                ]);
            }

            $reserved = $locked->activeReservedQuantity();

            if (bccomp($harvest['quantity_good'], $reserved, 2) === -1 && ! $confirmCancelReservations) {
                throw ValidationException::withMessages([
                    'confirm_cancel_reservations' => $this->shortfallMessage($locked, $harvest['quantity_good'], $reserved),
                ]);
            }

            $this->records->write($locked, HarvestRecordKind::Actual, $harvest, $actor->id);

            return $locked->refresh();
        });
    }

    private function shortfallMessage(Listing $listing, string $good, string $reserved): string
    {
        $count = $listing->reservations()->where('status', ReservationStatus::Active)->count();
        $unit = $listing->unit?->value ?? '';
        $shortfall = bcsub($reserved, $good, 2);
        $formatted = HarvestInput::scale($reserved);

        return "This listing has {$count} active reservation(s) ({$formatted} {$unit}). The harvest is {$shortfall} {$unit} short.";
    }
}
