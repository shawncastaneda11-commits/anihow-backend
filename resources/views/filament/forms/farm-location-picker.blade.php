@php
    $latitudePath = is_string($latitudePath ?? null) ? $latitudePath : '';
    $longitudePath = is_string($longitudePath ?? null) ? $longitudePath : '';
    $latitudeValue = isset($latitude) && is_numeric($latitude) ? (float) $latitude : null;
    $longitudeValue = isset($longitude) && is_numeric($longitude) ? (float) $longitude : null;
@endphp

<div
    wire:ignore
    x-data="farmLocationMap(@js([
        'latitude' => $latitudeValue,
        'longitude' => $longitudeValue,
        'interactive' => true,
        'latitudePath' => $latitudePath,
        'longitudePath' => $longitudePath,
    ]))"
    x-init="bootMap()"
    style="display:grid;gap:12px"
>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <input x-model="query" type="text" placeholder="Search a place" style="flex:1;min-width:180px;border:1px solid #d1d5db;border-radius:8px;padding:8px 10px">
        <button type="button" x-on:click="search()" x-bind:disabled="searching" style="border-radius:8px;padding:8px 12px;background:#166534;color:white">Search</button>
        <button type="button" x-on:click="useCurrent()" style="border-radius:8px;padding:8px 12px">Use my current location</button>
    </div>
    <template x-if="places.length">
        <div style="display:grid;gap:6px">
            <template x-for="place in places" :key="place.label + place.latitude">
                <button type="button" x-on:click="choose(place)" x-text="place.label" style="text-align:left;border:1px solid #e5e7eb;border-radius:8px;padding:8px 10px"></button>
            </template>
            <p style="font-size:12px;color:#6b7280;margin:0">Search by Nominatim · © OpenStreetMap</p>
        </div>
    </template>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <input x-model="link" type="url" placeholder="Paste a Google Maps link" style="flex:1;min-width:180px;border:1px solid #d1d5db;border-radius:8px;padding:8px 10px">
        <button type="button" x-on:click="pasteLink()" style="border-radius:8px;padding:8px 12px">Use this link</button>
    </div>
    <p x-show="message" x-text="message" style="margin:0;font-size:13px;color:#92400e"></p>
    <div wire:ignore x-ref="canvas" style="height:320px;border-radius:12px;overflow:hidden"></div>
    <p x-show="label" x-text="label" style="margin:0;font-size:12px;color:#4b5563"></p>
</div>
