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
<script>
    document.addEventListener('alpine:init', function () {
        Alpine.data('farmLocationMap', function (config) {
            return {
                latitude: config.latitude,
                longitude: config.longitude,
                interactive: config.interactive !== false,
                latitudePath: config.latitudePath || '',
                longitudePath: config.longitudePath || '',
                query: '',
                link: '',
                places: [],
                message: '',
                searching: false,
                map: null,
                marker: null,
                label: '',
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
                    this.map = window.L.map(this.$refs.canvas, {
                        scrollWheelZoom: this.interactive,
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
                            this.marker.on('dragend', function () {
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
