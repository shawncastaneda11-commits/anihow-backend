<x-filament-panels::page>
    @php($listing = $this->openRecord())

    <div @class(['anihow-dock', 'anihow-dock-open' => $listing !== null])>
        {{-- The list --}}
        <div class="anihow-dock-main">
            <div class="anihow-dock-head">
                <div>
                    <h1 class="anihow-split-heading anihow-dock-title">{{ $this->getTitle() }}</h1>
                    <p class="anihow-muted">{{ $this->intro() }}</p>
                </div>

                <label class="anihow-search">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3"></path></svg>
                    <span class="anihow-sr-only">Search listings</span>
                    <input type="search" wire:model.live.debounce.400ms="search" placeholder="Search a crop or seller">
                </label>
            </div>

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

                @if ($owners = $this->ownerOptions())
                    <label class="anihow-dock-farm">
                        {{ $this->ownerLabel() }}
                        <select wire:model.live="owner" class="anihow-input">
                            <option value="">All {{ \Illuminate\Support\Str::plural(strtolower($this->ownerLabel())) }}</option>
                            @foreach ($owners as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
            </div>

            {{ $this->content }}

            @if (auth()->user()?->farm_id !== null)
                <p class="anihow-muted">Click a listing to edit it. Photos stay the seller's; everything else you can fix for them.</p>
            @endif
        </div>

        {{-- The open listing --}}
        @if ($listing)
            <aside class="anihow-dock-panel anihow-listing-panel" aria-label="Edit listing" wire:key="listing-panel-{{ $listing->getKey() }}">
                <header class="anihow-dock-panel-head">
                    <div class="anihow-dock-who">
                        @if ($listing->image_path)
                            <img class="anihow-thumb" src="{{ \Illuminate\Support\Facades\Storage::disk(config('anihow.listing_disk', 'public'))->url($listing->image_path) }}" alt="">
                        @else
                            <span class="anihow-thumb anihow-thumb-letters" aria-hidden="true">{{ $this->initials($listing) }}</span>
                        @endif
                        <div>
                            <h2 class="anihow-split-name">{{ $listing->title }}</h2>
                            <p class="anihow-muted">
                                {{ collect([$listing->farmerSeller?->name, $listing->cropType?->name, $listing->created_at ? 'listed '.$listing->created_at->format('M j') : null])->filter()->implode(' · ') }}
                            </p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeListing" class="anihow-dock-close" aria-label="Close">×</button>
                </header>

                <form wire:submit="saveListing" class="anihow-dock-form">
                    {{ $this->listingForm }}

                    <div class="anihow-dock-foot">
                        <button type="button" wire:click="closeListing" class="anihow-btn anihow-btn-quiet">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" class="anihow-btn anihow-btn-primary">Save and tell the seller</button>
                    </div>
                </form>
            </aside>
        @endif
    </div>
</x-filament-panels::page>
