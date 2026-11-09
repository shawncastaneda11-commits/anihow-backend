<x-filament-panels::page>
    @php
        $reservation = $this->openedReservation();
        $seesAll = \App\Support\Sales\SalesAccess::seesAll(auth()->user());
        $number = fn ($value) => rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
    @endphp

    <div class="anihow-dock anihow-dock-open">
        <div class="anihow-dock-main">
            @include('filament.sales.head', ['placeholder' => 'Crop or seller'])

            {{ $this->content }}
        </div>

        <aside class="anihow-dock-panel" aria-label="Reservation">
            @if ($reservation === null)
                <p class="anihow-muted">Click a reservation to see what was reserved, by whom, and when it opens.</p>
            @else
                <header class="anihow-dock-panel-head" wire:key="reservation-panel-{{ $reservation->getKey() }}">
                    <div>
                        <h2 class="anihow-split-name">Reservation #{{ $reservation->getKey() }}</h2>
                        <p class="anihow-muted">
                            Reserved {{ $reservation->created_at?->format('M j, g:i A') }}
                            @if ($reservation->listing?->available_from)
                                · opens {{ $reservation->listing->available_from->format('M j, g:i A') }}
                            @endif
                        </p>
                    </div>
                    <button type="button" wire:click="closeRecord" class="anihow-dock-close" aria-label="Close">×</button>
                </header>

                <span @class([
                    'anihow-pill', 'anihow-pill-self',
                    'anihow-pill-wait' => $reservation->status === \App\Enums\ReservationStatus::Active,
                    'anihow-pill-ok' => $reservation->status === \App\Enums\ReservationStatus::Converted,
                    'anihow-pill-no' => $reservation->status === \App\Enums\ReservationStatus::Cancelled,
                ])>{{ match ($reservation->status) {
                    \App\Enums\ReservationStatus::Active => 'Coming up',
                    \App\Enums\ReservationStatus::Converted => 'Became an order',
                    \App\Enums\ReservationStatus::Cancelled => 'Cancelled',
                } }}</span>

                <div class="anihow-card anihow-lines">
                    <div class="anihow-lines-row">
                        <span>{{ $reservation->listing_name }} × {{ $number($reservation->quantity) }} {{ $reservation->unit?->value }}</span>
                        <span>₱{{ number_format((float) $reservation->line_subtotal, 2) }}</span>
                    </div>
                    @if ((float) $reservation->tawad_amount > 0)
                        <div class="anihow-lines-row anihow-lines-note">
                            <span>Tawad</span>
                            <span>−₱{{ number_format((float) $reservation->tawad_amount, 2) }}</span>
                        </div>
                    @endif
                    <div class="anihow-lines-row anihow-lines-total">
                        <span>Locked total</span>
                        <span>₱{{ number_format((float) $reservation->line_total, 2) }}</span>
                    </div>
                </div>

                <dl class="anihow-id-facts anihow-facts-stack">
                    <div><dt>Seller</dt><dd>{{ $reservation->farmerSeller?->name ?? '—' }}</dd></div>
                    <div><dt>Buyer</dt><dd>{{ \App\Support\Sales\SalesAccess::buyerName($reservation->buyer?->name, auth()->user()) ?? '—' }}</dd></div>
                    <div><dt>Payment</dt><dd>{{ $reservation->payment_status?->label() ?? '—' }}</dd></div>
                    <div><dt>Pickup</dt><dd>{{ $reservation->fulfillment_preference?->label() ?? '—' }}</dd></div>
                    @if ($seesAll)
                        <div><dt>Farm</dt><dd>{{ $reservation->farm?->name ?? '—' }}</dd></div>
                    @endif
                    @if ($reservation->order)
                        <div><dt>Order</dt><dd>{{ $reservation->order->order_number }}</dd></div>
                    @endif
                </dl>

                @unless ($seesAll)
                    <p class="anihow-muted">You see the buyer's first name only. Phone numbers, chats and payment screenshots stay with COSEL.</p>
                @endunless
            @endif
        </aside>
    </div>
</x-filament-panels::page>
