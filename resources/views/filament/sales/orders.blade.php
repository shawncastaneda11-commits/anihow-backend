<x-filament-panels::page>
    @php
        $order = $this->openedOrder();
        $seesAll = \App\Support\Sales\SalesAccess::seesAll(auth()->user());
    @endphp

    <div @class(['anihow-dock', 'anihow-dock-open' => $order !== null])>
        <div class="anihow-dock-main">
            @include('filament.sales.head', ['placeholder' => 'Order number, crop or seller'])

            {{ $this->content }}
        </div>

        @if ($order)
        <aside class="anihow-dock-panel" aria-label="Order">
                <header class="anihow-dock-panel-head" wire:key="order-panel-{{ $order->getKey() }}">
                    <div>
                        <h2 class="anihow-split-name">Order {{ $order->order_number }}</h2>
                        <p class="anihow-muted">
                            Placed {{ $order->created_at?->format('M j, g:i A') }} · {{ $order->fulfillment_preference?->label() }}
                        </p>
                    </div>
                    <button type="button" wire:click="closeRecord" class="anihow-dock-close" aria-label="Close">×</button>
                </header>

                <span @class([
                    'anihow-pill', 'anihow-pill-self',
                    'anihow-pill-wait' => in_array($order->status, [\App\Enums\OrderStatus::Placed, \App\Enums\OrderStatus::Confirmed], true),
                    'anihow-pill-ok' => in_array($order->status, [\App\Enums\OrderStatus::Ready, \App\Enums\OrderStatus::Completed], true),
                    'anihow-pill-no' => $order->status === \App\Enums\OrderStatus::Cancelled,
                ])>{{ $order->status->label() }} · {{ \App\Filament\Resources\Orders\Tables\OrdersTable::age($order) }}</span>

                <div class="anihow-card anihow-lines">
                    @foreach ($order->items as $item)
                        <div class="anihow-lines-row">
                            <span>{{ $item->listing_name }} × {{ \App\Filament\Resources\Orders\Tables\OrdersTable::number($item->quantity) }} {{ $item->unit?->value }}</span>
                            <span>₱{{ number_format((float) $item->line_total, 2) }}</span>
                        </div>
                    @endforeach
                    @if ((float) $order->tawad_total > 0)
                        <div class="anihow-lines-row anihow-lines-note">
                            <span>Tawad given</span>
                            <span>−₱{{ number_format((float) $order->tawad_total, 2) }}</span>
                        </div>
                    @endif
                    <div class="anihow-lines-row anihow-lines-total">
                        <span>Total</span>
                        <span>₱{{ number_format((float) $order->total, 2) }}</span>
                    </div>
                </div>

                <dl class="anihow-id-facts anihow-facts-stack">
                    <div><dt>Seller</dt><dd>{{ $order->farmerSeller?->name ?? '—' }}</dd></div>
                    <div><dt>Buyer</dt><dd>{{ \App\Support\Sales\SalesAccess::buyerName($order->buyer?->name, auth()->user()) ?? ($order->walk_in_buyer_name ?: 'Walk-in') }}</dd></div>
                    <div><dt>Payment</dt><dd>{{ collect([\App\Enums\PaymentMethod::tryFrom((string) $order->payment_method)?->label(), $order->payment_status?->label()])->filter()->implode(', ') ?: '—' }}</dd></div>
                    @if ($order->amount_received !== null)
                        <div><dt>Received</dt><dd>₱{{ number_format((float) $order->amount_received, 2) }}</dd></div>
                    @endif
                    @if ($seesAll)
                        <div><dt>Farm</dt><dd>{{ $order->farm?->name ?? '—' }}</dd></div>
                    @endif
                    @if ($order->status === \App\Enums\OrderStatus::Cancelled && ($order->cancellation_reason || $order->cancellation_note))
                        <div class="anihow-facts-wide"><dt>Why it was cancelled</dt><dd class="anihow-facts-plain">{{ collect([$order->cancellation_reason?->label(), $order->cancellation_note])->filter()->implode('. ') }}</dd></div>
                    @endif
                </dl>

                @if ($order->canBeCancelledForSuspendedSeller(auth()->user()))
                    <p class="anihow-dock-note anihow-dock-note-no">{{ $order->farmerSeller?->name ?? 'The seller' }} is suspended and can't finish this order.</p>
                    <div>{{ $this->cancelForSuspendedSellerAction }}</div>
                @endif

                @if (\App\Filament\Resources\Orders\Tables\OrdersTable::isLate($order))
                    <p class="anihow-dock-note anihow-dock-note-no">Waiting for the seller to confirm for over a day. Remind {{ $order->farmerSeller?->name ?? 'the seller' }} at the farm or by phone.</p>
                @endif

                @unless ($seesAll)
                    <p class="anihow-muted">You see the buyer's first name only. Phone numbers, chats and payment screenshots stay with COSEL.</p>
                @endunless
        </aside>
        @endif
    </div>
</x-filament-panels::page>
