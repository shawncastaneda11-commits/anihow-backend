<x-filament-widgets::widget>
    <div class="anihow-home">
        <div class="anihow-home-actions">
            <p class="anihow-muted">{{ $farmName }}: what your farmer-sellers need from you today.</p>
            <div class="anihow-home-actions-buttons">
                <a href="{{ $faqUrl }}" class="anihow-home-button anihow-home-button-quiet">FAQ answers</a>
                <a href="{{ $announceUrl }}" class="anihow-home-button">Post an announcement</a>
            </div>
        </div>

        <section aria-labelledby="home-todo" class="anihow-home-block">
            <h2 id="home-todo" class="anihow-home-heading">Needs a look</h2>
            @include('filament.widgets.home.tiles', ['tiles' => $tiles])
        </section>

        <section aria-labelledby="home-month" class="anihow-home-strip">
            <h2 id="home-month" class="anihow-home-heading">This month</h2>
            <div><span class="anihow-muted">Sales</span><span class="anihow-home-strip-value">₱{{ number_format($month['sales'], 2) }}</span></div>
            <div><span class="anihow-muted">Completed orders</span><span class="anihow-home-strip-value">{{ $month['orders'] }}</span></div>
            <div><span class="anihow-muted">Top crop</span><span class="anihow-home-strip-value">{{ $month['top_crop'] ?? '—' }}</span></div>
            <a href="{{ $analyticsUrl }}" class="anihow-home-link anihow-home-push">See analytics →</a>
        </section>

        <div class="anihow-home-columns">
            <x-filament::section heading="Your farmer-sellers" class="anihow-home-wide">
                <x-slot name="afterHeader">
                    <a href="{{ $listingsUrl }}" class="anihow-home-link">See all listings</a>
                </x-slot>
                <div class="anihow-home-table-wrap">
                    <table class="anihow-home-table">
                        <thead>
                            <tr>
                                <th scope="col">Seller</th>
                                <th scope="col">Listings shown</th>
                                <th scope="col">Orders this week</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($sellers as $row)
                                <tr>
                                    <td class="anihow-home-strong">
                                        {{ $row['seller']->name }}
                                        @if ($row['seller']->must_change_password)
                                            <span class="anihow-home-note">Still on the temporary password</span>
                                        @endif
                                    </td>
                                    <td @class(['anihow-home-warn' => $row['listings'] === 0])>{{ $row['listings'] === 0 ? 'None yet' : $row['listings'] }}</td>
                                    <td>{{ $row['orders'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="anihow-muted">No farmer-sellers on this farm yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            <x-filament::section heading="Posted to your sellers" class="anihow-home-narrow">
                @if ($posts->isEmpty())
                    <p class="anihow-muted">Nothing posted yet.</p>
                @else
                    <ul class="anihow-home-posts">
                        @foreach ($posts as $post)
                            <li>
                                <span class="anihow-home-kind">{{ $post['kind'] }}</span>
                                <span class="anihow-home-strong">{{ $post['title'] }}</span>
                                <span class="anihow-muted">{{ $post['when'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <p class="anihow-muted">Only your farm's sellers see these. Buyers see the AniHow-wide FAQ.</p>
            </x-filament::section>
        </div>
    </div>
</x-filament-widgets::widget>
