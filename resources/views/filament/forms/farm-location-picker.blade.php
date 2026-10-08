@php
    $latitudePath = is_string($latitudePath ?? null) ? $latitudePath : '';
    $longitudePath = is_string($longitudePath ?? null) ? $longitudePath : '';
    $updateAddressPath = is_string($updateAddressPath ?? null) ? $updateAddressPath : '';
    $suggestedBarangayPath = is_string($suggestedBarangayPath ?? null) ? $suggestedBarangayPath : '';
    $suggestedMunicipalityPath = is_string($suggestedMunicipalityPath ?? null) ? $suggestedMunicipalityPath : '';
    $suggestedAddressPath = is_string($suggestedAddressPath ?? null) ? $suggestedAddressPath : '';
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
        'updateAddressPath' => $updateAddressPath,
        'suggestedBarangayPath' => $suggestedBarangayPath,
        'suggestedMunicipalityPath' => $suggestedMunicipalityPath,
        'suggestedAddressPath' => $suggestedAddressPath,
    ]))"
    x-init="bootMap()"
    class="farm-picker-stack"
>
    <div class="farm-picker-row">
        <input x-model="query" type="text" placeholder="Search a place" class="farm-picker-input">
        <x-filament::button type="button" size="sm" color="primary" x-on:click="search()" x-bind:disabled="searching">
            Search
        </x-filament::button>
        <x-filament::button type="button" size="sm" color="gray" outlined icon="heroicon-o-map-pin" x-on:click="useCurrent()">
            Use my current location
        </x-filament::button>
    </div>

    <template x-if="places.length">
        <div class="farm-picker-results">
            <template x-for="place in places" :key="place.label + place.latitude">
                <button type="button" x-on:click="choose(place)" x-text="place.label" class="farm-picker-result"></button>
            </template>
            <p class="farm-muted" style="font-size:0.75rem;margin:0">Search by Nominatim · © OpenStreetMap</p>
        </div>
    </template>

    <div class="farm-picker-row">
        <input x-model="link" type="url" placeholder="Paste a Google Maps link" class="farm-picker-input">
        <x-filament::button type="button" size="sm" color="gray" x-on:click="pasteLink()">
            Use this link
        </x-filament::button>
    </div>

    <p x-show="message" x-text="message" class="farm-muted" style="margin:0;font-size:0.875rem"></p>

    <p class="farm-muted" style="margin:0;font-size:0.875rem">Click the map or drag the pin to the spot where buyers pick up their orders.</p>

    <div
        wire:ignore
        x-ref="canvas"
        class="farm-leaflet farm-leaflet-picker"
        style="position:relative;z-index:0;isolation:isolate;width:100%;max-width:100%;overflow:hidden;border-radius:0.75rem;"
    ></div>

    <p x-show="label" x-text="label" class="farm-muted" style="margin:0;font-size:0.875rem"></p>

    <div x-show="suggestionLabel" class="farm-picker-stack">
        <p x-text="suggestionLabel" style="margin:0;font-size:0.875rem"></p>
        <label style="display:flex;align-items:center;gap:8px;font-size:0.875rem">
            <input type="checkbox" x-model="updateAddress" x-on:change="writeSuggestion(updateAddress)">
            Update the farm's address to match the pin
        </label>
    </div>

    <p x-show="lookupMessage" x-text="lookupMessage" class="farm-muted" style="margin:0;font-size:0.875rem"></p>
</div>
