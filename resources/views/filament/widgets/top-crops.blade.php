<x-filament-widgets::widget>
    <x-filament::section heading="Top crops" :description="'Share of '.$total.' in sales'">
        @if ($rows === [])
            <p class="anihow-muted">No completed sales in this period.</p>
        @else
            <ol class="anihow-rank-list">
                @foreach ($rows as $row)
                    <li class="anihow-rank-item">
                        <div class="anihow-rank-head">
                            <span @class(['anihow-rank-crop', 'anihow-rank-others' => $row['rank'] === null])>
                                {{ $row['rank'] === null ? $row['crop'] : $row['rank'].'. '.$row['crop'] }}
                            </span>
                            <span @class(['anihow-rank-sales', 'anihow-rank-others' => $row['rank'] === null])>{{ $row['sales'] }}</span>
                        </div>
                        <div class="anihow-share">
                            <span class="anihow-bar anihow-bar-thin" role="img" aria-label="{{ $row['share'] }} of sales">
                                <span
                                    @class(['anihow-bar-part', 'anihow-tone-primary' => $row['rank'] !== null, 'anihow-tone-neutral' => $row['rank'] === null])
                                    style="width: {{ $row['percent'] }}%"
                                ></span>
                            </span>
                            <span class="anihow-share-label anihow-rank-share">{{ $row['share'] }}</span>
                        </div>
                        @if ($row['sold'] !== null)
                            <span class="anihow-muted">{{ $row['sold'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
