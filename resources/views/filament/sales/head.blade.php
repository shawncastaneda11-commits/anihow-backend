{{-- The title, the Orders | Reservations switch, the chips and the search. --}}
<div class="anihow-dock-head">
    <div>
        <h1 class="anihow-split-heading anihow-dock-title">Orders</h1>
        <p class="anihow-muted">{{ $this->intro() }}</p>
    </div>

    <x-filament::actions :actions="$this->getCachedHeaderActions()" />
</div>

<nav class="anihow-segment anihow-segment-inline" aria-label="Orders or reservations">
    @foreach ($this->salesSwitch() as $item)
        <a href="{{ $item['url'] }}" wire:navigate @class(['anihow-segment-on' => $item['on']]) aria-current="{{ $item['on'] ? 'page' : 'false' }}">{{ $item['label'] }}</a>
    @endforeach
</nav>

<div class="anihow-dock-tools">
    @foreach ($this->getCachedTabs() as $key => $tab)
        <button
            type="button"
            wire:click="$set('activeTab', @js((string) $key))"
            aria-pressed="{{ $activeTab === (string) $key ? 'true' : 'false' }}"
            @class(['anihow-chip', 'anihow-chip-on' => $activeTab === (string) $key])
        >
            {{ $tab->getLabel() }}
            @if (filled($badge = $tab->getBadge()))
                <span class="anihow-chip-count">{{ $badge }}</span>
            @endif
        </button>
    @endforeach

    <label class="anihow-search">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3"></path></svg>
        <span class="anihow-sr-only">Search</span>
        <input type="search" wire:model.live.debounce.400ms="search" placeholder="{{ $placeholder }}">
    </label>
</div>
