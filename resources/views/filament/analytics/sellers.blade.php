<x-filament-panels::page>
    @php
        $rows = $this->rows();
        $seller = $this->openSellerRecord();
        $peso = fn ($value) => '₱'.number_format((float) $value, 2);
    @endphp

    <div @class(['anihow-dock', 'anihow-dock-open' => $seller !== null])>
        <div class="anihow-dock-main">
            <div>
                <h1 class="anihow-split-heading anihow-dock-title">Analytics</h1>
                <p class="anihow-muted anihow-dock-intro">{{ $this->farmName() }}. Completed orders only.</p>
            </div>

            @include('filament.analytics.switch', ['on' => 'sellers'])

            <div class="anihow-dock-tools" role="group" aria-label="Period">
                @foreach (\App\Support\Analytics\SellerFigures::PERIODS as $key => $label)
                    <button type="button" wire:click="setPeriod('{{ $key }}')" aria-pressed="{{ $period === (string) $key ? 'true' : 'false' }}" @class(['anihow-chip', 'anihow-chip-on' => $period === (string) $key])>{{ $label }}</button>
                @endforeach
            </div>

            <section class="anihow-card anihow-table-card" aria-label="Sellers">
                <div class="anihow-table-scroll">
                    <table class="anihow-table">
                        <thead>
                            <tr>
                                <th scope="col">Seller</th>
                                <th scope="col" class="anihow-num">Sales</th>
                                <th scope="col" class="anihow-num">Orders</th>
                                <th scope="col">Trend</th>
                                <th scope="col">Top crop</th>
                                <th scope="col">Needs a look</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                @php
                                    $max = max(1, max($row['trend']));
                                    $points = collect($row['trend'])->values()->map(fn ($value, $i) => round($i * 28, 1).','.round(21 - ($value / $max) * 18, 1))->implode(' ');
                                    $last = collect($row['trend'])->last();
                                @endphp
                                <tr
                                    wire:key="seller-row-{{ $row['seller']->id }}"
                                    wire:click="openSeller({{ $row['seller']->id }})"
                                    tabindex="0"
                                    wire:keydown.enter="openSeller({{ $row['seller']->id }})"
                                    @class(['anihow-table-row', 'anihow-row-on' => $seller?->is($row['seller'])])
                                >
                                    <td>
                                        <span class="anihow-line-title">{{ $row['seller']->name }}</span>
                                        <span class="anihow-line-meta">{{ $row['listings'] }} {{ \Illuminate\Support\Str::plural('listing', $row['listings']) }} shown</span>
                                    </td>
                                    <td class="anihow-num"><strong>{{ $peso($row['sales']) }}</strong></td>
                                    <td class="anihow-num">{{ $row['orders'] }}</td>
                                    <td>
                                        <svg width="88" height="24" viewBox="0 0 88 24" aria-label="Sales in four parts of the period: {{ collect($row['trend'])->map($peso)->implode(', ') }}" role="img">
                                            <polyline points="{{ $points }}" fill="none" stroke="var(--anihow-green)" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"></polyline>
                                            <circle cx="84" cy="{{ round(21 - ($last / $max) * 18, 1) }}" r="2.5" fill="var(--anihow-green)"></circle>
                                        </svg>
                                    </td>
                                    <td>{{ $row['top_crop'] ?? '—' }}</td>
                                    <td>
                                        @if ($row['watch'])
                                            <span @class(['anihow-pill', 'anihow-pill-wait' => $row['watch_level'] === 'warn', 'anihow-pill-no' => $row['watch_level'] === 'no'])>{{ $row['watch'] }}</span>
                                        @else
                                            <span class="anihow-pill anihow-pill-ok">Fine</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="anihow-muted">No farmer-sellers on this farm yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <p class="anihow-muted">Click a seller to see their sales over time, crops, how buyers order, and tawad given.</p>
        </div>

        @if ($seller)
            @php
                $d = $this->detail($seller);
                $change = $d['previous'] > 0 ? (int) round(($d['sales'] - $d['previous']) / $d['previous'] * 100) : null;
                $weekMax = max(1, collect($d['weeks'])->max('sales'));
                $cropMax = max(1, collect($d['crops'])->max('sales'));
            @endphp
            <aside class="anihow-dock-panel" aria-label="Seller figures" wire:key="seller-panel-{{ $seller->id }}">
                <header class="anihow-dock-panel-head">
                    <div class="anihow-dock-who">
                        <span class="anihow-avatar" aria-hidden="true">{{ $this->initials($seller) }}</span>
                        <div>
                            <h2 class="anihow-split-name">{{ $seller->name }}</h2>
                            <p class="anihow-muted">{{ \App\Support\Analytics\SellerFigures::PERIODS[$period] }}</p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeSeller" class="anihow-dock-close" aria-label="Close">×</button>
                </header>

                <div class="anihow-figures">
                    <div class="anihow-figure">
                        <span>Sales</span>
                        <strong>{{ $peso($d['sales']) }}</strong>
                        <small @class(['anihow-figure-up' => $change !== null && $change > 0])>{{ $change === null ? 'No sales the period before' : ($change >= 0 ? '+' : '').$change.'% from the period before' }}</small>
                    </div>
                    <div class="anihow-figure">
                        <span>Completed orders</span>
                        <strong>{{ $d['orders'] }}</strong>
                        <small>{{ $d['orders'] > 0 ? 'Average '.$peso($d['average']) : 'None yet' }}</small>
                    </div>
                    <div class="anihow-figure">
                        <span>Tawad given</span>
                        <strong>{{ $peso($d['tawad']) }}</strong>
                        <small>{{ $d['tawad_orders'] }} of {{ $d['orders'] }} orders</small>
                    </div>
                    <div class="anihow-figure">
                        <span>Listings shown</span>
                        <strong>{{ (int) $seller->listings_count }}</strong>
                        <small>Right now</small>
                    </div>
                </div>

                <div>
                    <p class="anihow-section-label">Sales over the period</p>
                    <div class="anihow-weeks" role="img" aria-label="Sales: {{ collect($d['weeks'])->map(fn ($w) => $w['label'].' '.$peso($w['sales']))->implode(', ') }}">
                        @foreach ($d['weeks'] as $week)
                            <div class="anihow-week">
                                <span class="anihow-week-value">{{ $week['sales'] > 0 ? $peso($week['sales']) : '' }}</span>
                                <span class="anihow-week-bar" style="height: {{ max(2, round($week['sales'] / $weekMax * 100)) }}%" @class(['anihow-week-latest' => $loop->last])></span>
                                <span class="anihow-week-label">{{ $week['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div>
                    <p class="anihow-section-label">Crops sold</p>
                    @if ($d['crops'] === [])
                        <p class="anihow-muted">No completed orders in this period.</p>
                    @else
                        <ul class="anihow-bars">
                            @foreach ($d['crops'] as $crop)
                                <li>
                                    <span>{{ $crop['crop'] }}</span>
                                    <span class="anihow-bar-track"><i style="width: {{ round($crop['sales'] / $cropMax * 100) }}%"></i></span>
                                    <strong>{{ $peso($crop['sales']) }}</strong>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div>
                    <p class="anihow-section-label">How buyers ordered</p>
                    @if ($d['app_share'] === null)
                        <p class="anihow-muted">No completed orders in this period.</p>
                    @else
                        <div class="anihow-split-bar" aria-hidden="true"><i class="anihow-split-app" style="width: {{ $d['app_share'] }}%"></i><i class="anihow-split-walk" style="width: {{ 100 - $d['app_share'] }}%"></i></div>
                        <p class="anihow-legend"><span><i class="anihow-split-app"></i>In the app {{ $d['app_share'] }}%</span><span><i class="anihow-split-walk"></i>Walk-in {{ 100 - $d['app_share'] }}%</span></p>
                    @endif
                </div>

                <p class="anihow-muted">Only you, as this farm's Content Editor, see figures per seller. The seller sees the same numbers in My Sales in the app. Buyers' names are not shown.</p>
            </aside>
        @endif
    </div>
</x-filament-panels::page>
