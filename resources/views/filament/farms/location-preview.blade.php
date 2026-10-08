@php
    /** @var \App\Models\Farm $farm */
@endphp

<div class="grid gap-3">
    @if ($farm->hasPin())
        <div
            wire:ignore
            x-data="farmLocationMap(@js([
                'latitude' => (float) $farm->latitude,
                'longitude' => (float) $farm->longitude,
                'interactive' => false,
                'latitudePath' => '',
                'longitudePath' => '',
            ]))"
            x-init="bootMap()"
        >
            <div
                wire:ignore
                x-ref="canvas"
                class="farm-leaflet farm-leaflet-preview"
                style="position:relative;z-index:0;isolation:isolate;width:100%;max-width:100%;height:200px;overflow:hidden;border-radius:0.75rem;"
            ></div>
        </div>
        <x-filament::link :href="$farm->mapsUrl()" target="_blank">
            Open in Google Maps
        </x-filament::link>
        <p class="farm-muted" style="margin:0;font-size:0.875rem">
            Lat {{ number_format((float) $farm->latitude, 5, '.', '') }}, Lng {{ number_format((float) $farm->longitude, 5, '.', '') }}
        </p>
    @else
        <p class="farm-muted" style="margin:0;font-size:0.875rem">No location set yet</p>
        <div>
            <x-filament::button type="button" size="sm" color="gray" outlined wire:click="mountAction('editLocation')">
                Set location
            </x-filament::button>
        </div>
    @endif
</div>
