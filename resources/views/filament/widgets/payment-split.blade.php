<x-filament-widgets::widget>
    <div class="anihow-analytics">
        <div class="anihow-split-grid">
            @foreach ($cards as $card)
                <x-filament::section>
                    <div class="anihow-split-card">
                        <div class="anihow-split-head">
                            <h3 class="anihow-split-title">{{ $card['title'] }}</h3>
                            <span class="anihow-muted">{{ $total }} from {{ $orders }}</span>
                        </div>

                        <div
                            class="anihow-bar"
                            role="img"
                            aria-label="{{ collect($card['rows'])->map(fn (array $row): string => $row['label'].' '.$row['percent'].' percent')->join(', ') }}"
                        >
                            @foreach ($card['rows'] as $row)
                                <span class="anihow-bar-part anihow-tone-{{ $row['tone'] }}" style="width: {{ $row['percent'] }}%"></span>
                            @endforeach
                        </div>

                        <div class="anihow-split-rows">
                            @foreach ($card['rows'] as $row)
                                <div class="anihow-split-row">
                                    <span class="anihow-split-label">
                                        <span class="anihow-swatch anihow-tone-{{ $row['tone'] }}" aria-hidden="true"></span>
                                        {{ $row['label'] }}
                                    </span>
                                    <span class="anihow-split-amount">{{ $row['amount'] }}</span>
                                    <span class="anihow-muted anihow-split-detail">{{ $row['detail'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </x-filament::section>
            @endforeach
        </div>

        <p class="anihow-muted">Both cards split the same {{ $total }} in total sales, once by payment and once by where the sale was made.</p>
    </div>
</x-filament-widgets::widget>
