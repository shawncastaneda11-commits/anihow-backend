<x-filament-widgets::widget>
    <div class="anihow-analytics">
        <div class="anihow-analytics-heading">
            <h2 class="anihow-analytics-title">Harvest</h2>
            <p class="anihow-muted">Harvests recorded {{ $window }}, and what those listings have sold so far. Sales above also count stock harvested earlier, so the two sections can differ.</p>
        </div>

        @if ($empty)
            <x-filament::section>
                <p class="anihow-muted">No harvests recorded in this period.</p>
            </x-filament::section>
        @else
            <div class="anihow-harvest-grid">
                <x-filament::section>
                    <div class="anihow-stat">
                        <span class="anihow-stat-label">Value of harvested stock</span>
                        <span class="anihow-stat-value">{{ $stockValue }}</span>
                        <span class="anihow-muted">{{ $stockDetail }}</span>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <div class="anihow-stat">
                        <span class="anihow-stat-label">Earned so far</span>
                        <span class="anihow-stat-value">{{ $earned }}</span>
                        <span class="anihow-bar anihow-bar-thin" role="img" aria-label="{{ $earnedDetail }}">
                            <span class="anihow-bar-part anihow-tone-primary anihow-bar-min" style="width: {{ $earnedPercent }}%"></span>
                        </span>
                        <span class="anihow-muted">{{ $earnedDetail }}</span>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <div class="anihow-stat">
                        <span class="anihow-stat-label">Unsold stock</span>
                        <span class="anihow-stat-value">{{ $unsold }}</span>
                        <span class="anihow-muted">Value of these harvests not sold yet.</span>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <div class="anihow-stat">
                        @if ($profit === null)
                            <span class="anihow-stat-label">Profit</span>
                            <span class="anihow-stat-value anihow-stat-none" aria-label="Not available">—</span>
                            <span class="anihow-muted">No production cost recorded yet. Sellers add it when they record a harvest in the app.</span>
                        @else
                            <span class="anihow-stat-label">Profit so far</span>
                            <span @class(['anihow-stat-value', 'anihow-stat-loss' => $profit['loss']])>{{ $profit['value'] }}</span>
                            @foreach ($profit['lines'] as $line)
                                <span class="anihow-muted">{{ $line }}</span>
                            @endforeach
                        @endif
                    </div>
                </x-filament::section>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
