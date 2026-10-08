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
    class="grid gap-3"
>
    <div class="flex flex-wrap items-center gap-2">
        <input x-model="query" type="text" placeholder="Search a place" class="min-w-48 flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 dark:border-gray-600 dark:bg-gray-900 dark:text-white">
        <x-filament::button type="button" size="sm" color="primary" x-on:click="search()" x-bind:disabled="searching">
            Search
        </x-filament::button>
        <x-filament::button type="button" size="sm" color="gray" outlined icon="heroicon-o-map-pin" x-on:click="useCurrent()">
            Use my current location
        </x-filament::button>
    </div>

    <template x-if="places.length">
        <div class="grid gap-1.5">
            <template x-for="place in places" :key="place.label + place.latitude">
                <button type="button" x-on:click="choose(place)" x-text="place.label" class="rounded-lg px-3 py-2 text-left text-sm text-gray-950 ring-1 ring-gray-200 hover:bg-gray-50 dark:text-white dark:ring-white/10 dark:hover:bg-white/5"></button>
            </template>
            <p class="m-0 text-xs text-gray-600 dark:text-gray-300">Search by Nominatim · © OpenStreetMap</p>
        </div>
    </template>

    <div class="flex flex-wrap items-center gap-2">
        <input x-model="link" type="url" placeholder="Paste a Google Maps link" class="min-w-48 flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 dark:border-gray-600 dark:bg-gray-900 dark:text-white">
        <x-filament::button type="button" size="sm" color="gray" x-on:click="pasteLink()">
            Use this link
        </x-filament::button>
    </div>

    <p x-show="message" x-text="message" class="m-0 text-sm text-gray-700 dark:text-gray-200"></p>

    <p class="m-0 text-sm text-gray-600 dark:text-gray-300">Click the map or drag the pin to the spot where buyers pick up their orders.</p>

    <div
        wire:ignore
        x-ref="canvas"
        class="farm-leaflet farm-leaflet-picker"
        style="position:relative;z-index:0;isolation:isolate;width:100%;max-width:100%;overflow:hidden;border-radius:0.75rem;"
    ></div>

    <p x-show="label" x-text="label" class="m-0 text-sm text-gray-600 dark:text-gray-300"></p>

    <div x-show="suggestionLabel" class="grid gap-2">
        <p x-text="suggestionLabel" class="m-0 text-sm text-gray-950 dark:text-white"></p>
        <label class="flex items-center gap-2 text-sm text-gray-950 dark:text-white">
            <input type="checkbox" x-model="updateAddress" x-on:change="writeSuggestion(updateAddress)">
            Update the farm's address to match the pin
        </label>
    </div>

    <p x-show="lookupMessage" x-text="lookupMessage" class="m-0 text-sm text-gray-600 dark:text-gray-300"></p>
</div>
