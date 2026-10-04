<?php

namespace App\Actions\Chat;

use App\Models\Listing;
use App\Models\StallMessage;
use Illuminate\Support\Collection;

class PresentStallMessages
{
    /**
     * A product card links only while the listing is still on the buyer catalogue.
     *
     * @param  iterable<int, StallMessage>  $messages
     */
    public function links(iterable $messages): void
    {
        $messages = Collection::make($messages);
        $ids = $messages
            ->map(fn (StallMessage $message): ?int => $message->listing_id === null ? null : (int) $message->listing_id)
            ->filter()
            ->unique()
            ->values();

        $visible = $ids->isEmpty()
            ? []
            : Listing::query()
                ->buyerVisible()
                ->whereIn('id', $ids)
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

        foreach ($messages as $message) {
            $id = $message->listing_id === null ? null : (int) $message->listing_id;
            $message->setAttribute(
                'listing_link_id',
                $id !== null && in_array($id, $visible, true) ? $id : null,
            );
        }
    }
}
