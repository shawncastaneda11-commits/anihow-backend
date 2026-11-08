<x-filament-widgets::widget>
    <div class="anihow-home">
        <section aria-labelledby="home-todo" class="anihow-home-block">
            <h2 id="home-todo" class="anihow-home-heading">Things waiting for you</h2>
            @include('filament.widgets.home.tiles', ['tiles' => $tiles])
        </section>

        <div class="anihow-home-columns">
            <x-filament::section heading="Partner farms" class="anihow-home-wide">
                <x-slot name="afterHeader">
                    <a href="{{ $farmsUrl }}" class="anihow-home-link">See all farms</a>
                </x-slot>
                <div class="anihow-home-table-wrap">
                    <table class="anihow-home-table">
                        <thead>
                            <tr>
                                <th scope="col">Farm</th>
                                <th scope="col">Farmer-sellers</th>
                                <th scope="col">Listings</th>
                                <th scope="col">Partner since</th>
                                <th scope="col">Content Editor</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($farms as $row)
                                <tr>
                                    <td><a href="{{ $farmUrl($row['farm']) }}" class="anihow-home-strong">{{ $row['farm']->name }}</a></td>
                                    <td>{{ $row['sellers'] }}</td>
                                    <td>{{ $row['listings'] }}</td>
                                    <td>{{ $row['farm']->partnered_since?->format('M j, Y') ?? '—' }}</td>
                                    <td @class(['anihow-home-warn' => $row['editor'] === null])>{{ $row['editor'] ?? 'Not assigned' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="anihow-muted">No farms yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            <div class="anihow-home-narrow">
                @include('filament.widgets.home.activity', ['activity' => $activity])
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
