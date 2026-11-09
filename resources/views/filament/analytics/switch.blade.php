{{-- Whole farm | By seller, for a farm's Content Editor. --}}
<nav class="anihow-segment anihow-segment-inline" aria-label="Whole farm or by seller">
    <a href="{{ \App\Filament\Pages\Dashboard::getUrl() }}" wire:navigate @class(['anihow-segment-on' => $on === 'farm']) aria-current="{{ $on === 'farm' ? 'page' : 'false' }}">Whole farm</a>
    <a href="{{ \App\Filament\Pages\SellerAnalytics::getUrl() }}" wire:navigate @class(['anihow-segment-on' => $on === 'sellers']) aria-current="{{ $on === 'sellers' ? 'page' : 'false' }}">By seller</a>
</nav>
