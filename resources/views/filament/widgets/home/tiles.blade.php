{{-- One row of "needs a look" tiles. Each tile links to the screen that acts on it. --}}
<div class="anihow-home-tiles">
    @foreach ($tiles as $tile)
        <a href="{{ $tile['url'] }}" class="anihow-home-tile">
            <span class="anihow-home-tile-label">{{ $tile['label'] }}</span>
            <span @class(['anihow-home-tile-count', 'anihow-home-warn' => $tile['warn']])>{{ number_format($tile['count']) }}</span>
            <span class="anihow-home-tile-cta">{{ $tile['cta'] }} →</span>
        </a>
    @endforeach
</div>
