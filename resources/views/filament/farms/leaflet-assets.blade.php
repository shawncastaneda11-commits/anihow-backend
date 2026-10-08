<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha384-sHL9NAb7lN7rfvG5lfHpm643Xkcjzp4jFvuavGOndn6pjVqS6ny56CAt3nsEVT4H"
    crossorigin="anonymous"
>
<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha384-cxOPjt7s7Iz04uaHJceBmS+qpjv2JkIHNVcuOrM+YHwZOmJGBXI00mdUXEq65HTH"
    crossorigin="anonymous"
></script>
<style>
    .farm-leaflet {
        position: relative;
        z-index: 0;
        isolation: isolate;
        width: 100%;
        max-width: 100%;
    }

    .farm-leaflet-preview {
        height: 200px;
    }

    .farm-leaflet-picker {
        height: 320px;
    }

    .farm-muted {
        color: #4b5563;
    }

    html.dark .farm-muted {
        color: #d1d5db;
    }

    .farm-picker-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
    }

    .farm-picker-input {
        flex: 1 1 12rem;
        min-width: 12rem;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        background: #fff;
        color: #030712;
        padding: 8px 10px;
        font-size: 0.875rem;
    }

    html.dark .farm-picker-input {
        border-color: #4b5563;
        background: #111827;
        color: #f9fafb;
    }

    .farm-picker-results {
        display: grid;
        gap: 6px;
    }

    .farm-picker-result {
        border: 0;
        border-radius: 0.5rem;
        background: transparent;
        color: inherit;
        padding: 8px 10px;
        text-align: left;
        box-shadow: inset 0 0 0 1px #e5e7eb;
        cursor: pointer;
    }

    html.dark .farm-picker-result {
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.12);
    }

    .farm-picker-stack {
        display: grid;
        gap: 12px;
    }

    @media (min-width: 640px) {
        .farm-leaflet-picker {
            height: 420px;
        }
    }
</style>
<script>
    document.addEventListener('alpine:init', function () {
        Alpine.data('farmLocationMap', function (config) {
            return {
                latitude: config.latitude,
                longitude: config.longitude,
                interactive: config.interactive !== false,
                latitudePath: config.latitudePath || '',
                longitudePath: config.longitudePath || '',
                updateAddressPath: config.updateAddressPath || '',
                suggestedBarangayPath: config.suggestedBarangayPath || '',
                suggestedMunicipalityPath: config.suggestedMunicipalityPath || '',
                suggestedAddressPath: config.suggestedAddressPath || '',
                query: '',
                link: '',
                places: [],
                message: '',
                searching: false,
                map: null,
                marker: null,
                label: '',
                suggestionLabel: '',
                lookupMessage: '',
                updateAddress: true,
                suggestion: null,
                lookupTimer: null,
                dragging: false,
                bootMap: function () {
                    const start = function () {
                        this.mountMap();
                    }.bind(this);

                    if (window.L) {
                        this.$nextTick(start);

                        return;
                    }

                    window.setTimeout(start, 50);
                },
                mountMap: function () {
                    if (! window.L || this.map) {
                        return;
                    }

                    const fallback = [14.386, 120.880];
                    const hasPin = this.latitude !== null && this.latitude !== '' && this.longitude !== null && this.longitude !== '';
                    const center = hasPin
                        ? [Number(this.latitude), Number(this.longitude)]
                        : fallback;

                    window.L.Icon.Default.imagePath = 'https://unpkg.com/leaflet@1.9.4/dist/images/';
                    const canvas = this.$refs.canvas;
                    canvas.style.position = 'relative';
                    canvas.style.zIndex = '0';
                    canvas.style.isolation = 'isolate';
                    this.map = window.L.map(canvas, {
                        dragging: this.interactive,
                        zoomControl: this.interactive,
                        scrollWheelZoom: this.interactive,
                        doubleClickZoom: this.interactive,
                        boxZoom: this.interactive,
                        keyboard: this.interactive,
                        touchZoom: this.interactive,
                    }).setView(center, 15);
                    window.L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap contributors',
                        referrerPolicy: 'strict-origin-when-cross-origin',
                    }).addTo(this.map);

                    if (hasPin) {
                        this.setMarker(center[0], center[1], false);
                    }

                    if (this.interactive) {
                        this.map.on('click', function (event) {
                            this.setMarker(event.latlng.lat, event.latlng.lng, true);
                        }.bind(this));
                    }

                    const fix = function () {
                        if (this.map) {
                            this.map.invalidateSize();
                        }
                    }.bind(this);

                    window.requestAnimationFrame(fix);
                    window.setTimeout(fix, 250);
                    window.setTimeout(fix, 600);
                    window.addEventListener('resize', fix);

                    if (window.ResizeObserver && this.$refs.canvas) {
                        new window.ResizeObserver(fix).observe(this.$refs.canvas);
                    }
                },
                setMarker: function (lat, lng, persist) {
                    const latlng = window.L.latLng(lat, lng);

                    if (! this.marker) {
                        this.marker = window.L.marker(latlng, { draggable: this.interactive }).addTo(this.map);

                        if (this.interactive) {
                            this.marker.on('dragstart', function () {
                                this.dragging = true;

                                if (this.lookupTimer) {
                                    window.clearTimeout(this.lookupTimer);
                                    this.lookupTimer = null;
                                }
                            }.bind(this));
                            this.marker.on('dragend', function () {
                                this.dragging = false;
                                const point = this.marker.getLatLng();
                                this.sync(point.lat, point.lng);
                            }.bind(this));
                        }
                    } else {
                        this.marker.setLatLng(latlng);
                    }

                    this.map.setView(latlng, Math.max(this.map.getZoom(), 15));

                    if (persist) {
                        this.sync(lat, lng);

                        return;
                    }

                    this.latitude = lat;
                    this.longitude = lng;
                    this.writeLabel();
                },
                sync: function (lat, lng) {
                    this.latitude = lat;
                    this.longitude = lng;
                    this.writeLabel();

                    if (! this.latitudePath || ! this.longitudePath) {
                        return;
                    }

                    this.$wire.set(this.latitudePath, Number(lat).toFixed(7));
                    this.$wire.set(this.longitudePath, Number(lng).toFixed(7));
                    this.scheduleLookup();
                },
                scheduleLookup: function () {
                    if (! this.interactive || this.dragging) {
                        return;
                    }

                    if (this.lookupTimer) {
                        window.clearTimeout(this.lookupTimer);
                    }

                    this.lookupTimer = window.setTimeout(function () {
                        this.lookupTimer = null;

                        if (this.dragging) {
                            return;
                        }

                        this.lookupAddress();
                    }.bind(this), 800);
                },
                lookupAddress: async function () {
                    if (this.latitude === null || this.latitude === '' || this.longitude === null || this.longitude === '') {
                        return;
                    }

                    const result = await this.$wire.reverseFarmPlace(Number(this.latitude), Number(this.longitude));

                    if (! result || result.message) {
                        this.suggestion = null;
                        this.suggestionLabel = '';
                        this.lookupMessage = (result && result.message)
                            ? result.message
                            : 'Couldn\'t look up this spot. You can still save the pin.';
                        this.updateAddress = false;
                        this.writeSuggestion(false);

                        return;
                    }

                    this.suggestion = result;
                    this.suggestionLabel = result.label || '';
                    this.lookupMessage = '';
                    this.updateAddress = true;
                    this.writeSuggestion(true);
                },
                writeSuggestion: function (enabled) {
                    const suggestion = enabled ? (this.suggestion || {}) : {};

                    if (this.updateAddressPath) {
                        this.$wire.set(this.updateAddressPath, !! enabled);
                    }

                    if (this.suggestedBarangayPath) {
                        this.$wire.set(this.suggestedBarangayPath, enabled ? (suggestion.barangay || '') : '');
                    }

                    if (this.suggestedMunicipalityPath) {
                        this.$wire.set(this.suggestedMunicipalityPath, enabled ? (suggestion.municipality || '') : '');
                    }

                    if (this.suggestedAddressPath) {
                        this.$wire.set(this.suggestedAddressPath, enabled ? (suggestion.address || '') : '');
                    }
                },
                writeLabel: function () {
                    if (this.latitude === null || this.latitude === '' || this.longitude === null || this.longitude === '') {
                        this.label = '';

                        return;
                    }

                    this.label = 'Lat ' + Number(this.latitude).toFixed(5) + ', Lng ' + Number(this.longitude).toFixed(5);
                },
                search: async function () {
                    this.searching = true;
                    this.message = '';
                    const result = await this.$wire.searchFarmPlaces(this.query || '');
                    this.places = result.places || [];
                    this.message = result.message || '';
                    this.searching = false;
                },
                choose: function (place) {
                    this.setMarker(place.latitude, place.longitude, true);
                },
                useCurrent: function () {
                    if (! navigator.geolocation) {
                        this.message = 'Location is unavailable in this browser.';

                        return;
                    }

                    navigator.geolocation.getCurrentPosition(function (position) {
                        this.setMarker(position.coords.latitude, position.coords.longitude, true);
                    }.bind(this), function () {
                        this.message = 'Location is unavailable in this browser.';
                    });
                },
                pasteLink: async function () {
                    const result = await this.$wire.resolveFarmMapLink(this.link || '');

                    if (result.latitude !== null && result.latitude !== undefined && result.longitude !== null && result.longitude !== undefined) {
                        this.setMarker(result.latitude, result.longitude, true);
                        this.message = '';

                        return;
                    }

                    this.message = result.message || 'Couldn\'t read a location from this link. Try searching or drop the pin on the map.';
                },
            };
        });
    });
</script>
