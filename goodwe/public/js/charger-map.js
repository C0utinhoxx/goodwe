/* Mapa de carregadores — mesma lógica do app NFC (js/map.js), adaptada ao
   portal Laravel:
     - Carregadores GoodWe vêm de GET /api/chargers (API própria, pública).
     - Eletropostos públicos vêm da Overpass API (OpenStreetMap).
   O resultado é renderizado com Leaflet e permite traçar rota até o ponto. */

(function () {
    'use strict';

    var DEFAULT_CENTER = { lat: -23.5505, lng: -46.6333 };
    var PUBLIC_CHARGING_URL = 'https://overpass-api.de/api/interpreter';
    var PUBLIC_CHARGING_RADIUS = 20000;
    var PUBLIC_CHARGING_CACHE_KEY = 'goodwe_public_chargers_v1';

    // Fallback estático usado quando a Overpass API e o cache local falham.
    var DEFAULT_PUBLIC_CHARGERS = [
        ['osm-node-5649812371', 'Assai Vila Guilherme', -23.5220436, -46.6017625],
        ['osm-node-7098550069', 'Eletroposto', -23.5015679, -46.733278],
        ['osm-node-12190026409', 'ChargePoint', -23.524961, -46.6670982],
        ['osm-node-12303216043', 'Eletroposto', -23.618914, -46.602196],
        ['osm-node-13235612198', 'Eletroposto', -23.667906, -46.6780725],
        ['osm-way-371432992', 'Go Eletric', -23.5684088, -46.6117302],
        ['osm-way-909127223', 'Enel', -23.5485132, -46.7359186],
        ['osm-way-1349984736', 'AES Brasil', -23.6083194, -46.6964068]
    ].map(function (row) {
        return {
            id: row[0],
            name: row[1],
            location: 'Local informado no OpenStreetMap',
            power_kw: 7,
            connector: 'Não informado',
            lat: row[2],
            lng: row[3],
            status: 'available',
            is_available: true,
            source: 'openstreetmap',
            source_label: 'OpenStreetMap'
        };
    });

    var map = null;
    var chargersLayer = null;
    var userMarker = null;
    var routeLine = null;
    var userPosition = null;
    var chargersById = {};

    function el(id) {
        return document.getElementById(id);
    }

    function calculateDistance(lat1, lng1, lat2, lng2) {
        var R = 6371;
        var dLat = (lat2 - lat1) * Math.PI / 180;
        var dLng = (lng2 - lng1) * Math.PI / 180;
        var a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
            + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180)
            * Math.sin(dLng / 2) * Math.sin(dLng / 2);
        var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c;
    }

    function formatDistance(km) {
        return km < 1 ? (km * 1000).toFixed(0) + ' m' : km.toFixed(1) + ' km';
    }

    function showLoading(on) {
        var loading = el('pt-map-loading');
        if (loading) loading.classList.toggle('is-active', !!on);
    }

    function showCount(html) {
        var count = el('pt-map-count');
        if (!count) return;
        if (!html) {
            count.classList.remove('is-active');
            count.innerHTML = '';
            return;
        }
        count.innerHTML = html;
        count.classList.add('is-active');
    }

    /* Busca carregadores públicos na Overpass API (OpenStreetMap). */
    function fetchPublicChargingStations(lat, lng) {
        var query = '[out:json][timeout:20];('
            + 'node["amenity"="charging_station"](around:' + PUBLIC_CHARGING_RADIUS + ',' + lat + ',' + lng + ');'
            + 'way["amenity"="charging_station"](around:' + PUBLIC_CHARGING_RADIUS + ',' + lat + ',' + lng + ');'
            + 'relation["amenity"="charging_station"](around:' + PUBLIC_CHARGING_RADIUS + ',' + lat + ',' + lng + ');'
            + ');out center tags;';

        var cached = [];
        try {
            cached = JSON.parse(localStorage.getItem(PUBLIC_CHARGING_CACHE_KEY) || '[]');
            if (!Array.isArray(cached)) cached = [];
        } catch (e) {
            cached = [];
        }

        var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var timeout = controller ? setTimeout(function () { controller.abort(); }, 6000) : null;

        return fetch(PUBLIC_CHARGING_URL + '?data=' + encodeURIComponent(query), controller ? { signal: controller.signal } : undefined)
            .then(function (response) {
                if (!response.ok) throw new Error('Overpass respondeu ' + response.status);
                return response.json();
            })
            .then(function (data) {
                var chargers = (data.elements || []).map(function (element) {
                    var tags = element.tags || {};
                    var point = element.center || { lat: element.lat, lon: element.lon };
                    var power = Number(tags['charging:power'] || tags.power || 0);
                    return {
                        id: 'osm-' + element.type + '-' + element.id,
                        name: tags.name || tags.brand || 'Eletroposto',
                        location: tags['addr:street'] || tags['addr:city'] || 'Local informado no OpenStreetMap',
                        power_kw: power > 0 ? power : 7,
                        connector: tags.connector || tags['charging:connector'] || 'Não informado',
                        lat: point.lat,
                        lng: point.lon,
                        status: 'available',
                        is_available: true,
                        source: 'openstreetmap',
                        source_label: 'OpenStreetMap'
                    };
                }).filter(function (c) { return c.lat != null && c.lng != null; });

                try {
                    localStorage.setItem(PUBLIC_CHARGING_CACHE_KEY, JSON.stringify(chargers));
                } catch (e) {
                    /* Cache indisponível (modo privado): segue sem persistir. */
                }

                return chargers;
            })
            .catch(function () {
                var fallback = cached.length ? cached : DEFAULT_PUBLIC_CHARGERS;
                return fallback.filter(function (c) {
                    return calculateDistance(lat, lng, c.lat, c.lng) <= PUBLIC_CHARGING_RADIUS;
                });
            })
            .finally(function () {
                if (timeout) clearTimeout(timeout);
            });
    }

    /* Busca os carregadores da rede GoodWe pela API do próprio projeto. */
    function fetchGoodWeChargers() {
        return fetch('/api/chargers', { headers: { Accept: 'application/json' } })
            .then(function (response) {
                if (!response.ok) throw new Error('API respondeu ' + response.status);
                return response.json();
            })
            .then(function (data) {
                var list = [];
                (data.chargers || []).forEach(function (c) {
                    list.push(Object.assign({}, c, { source: 'goodwe', source_label: 'GoodWe' }));
                });
                (data.registered_chargers || []).forEach(function (c) {
                    list.push(Object.assign({}, c, { source: 'goodwe', source_label: 'GoodWe' }));
                });
                return list;
            });
    }

    function renderStations(chargers) {
        if (!chargersLayer) return;
        chargersLayer.clearLayers();
        chargersById = {};

        var boltSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>';

        chargers.forEach(function (charger) {
            chargersById[charger.id] = charger;
            var available = charger.is_available !== false;
            var color = available ? '#e53935' : '#8a8f98';
            var icon = L.divIcon({
                className: 'pt-map-marker',
                html: '<div class="pt-map-marker-dot" style="background:' + color + ';">' + boltSvg + '</div>',
                iconSize: [32, 32],
                iconAnchor: [16, 16],
                popupAnchor: [0, -18]
            });
            var marker = L.marker([charger.lat, charger.lng], { icon: icon }).addTo(chargersLayer);

            var statusLabel = charger.source === 'openstreetmap'
                ? '<span style="color:#c62828;font-weight:600;">Informado no mapa</span>'
                : (available
                    ? '<span style="color:#168047;font-weight:600;">Disponível</span>'
                    : '<span style="color:#c62828;font-weight:600;">Ocupado</span>');

            marker.bindPopup(
                '<div class="pt-map-popup">'
                + '<h3>' + charger.name + '</h3>'
                + '<span class="pt-map-popup-type">' + (charger.source_label || 'GoodWe') + ' · ' + (charger.connector || 'Conector') + '</span>'
                + '<p><strong>Potência:</strong> ' + (charger.power_kw || '—') + ' kW</p>'
                + '<p><strong>Status:</strong> ' + statusLabel + '</p>'
                + '<button type="button" class="pt-map-popup-btn" data-route-to="' + charger.id + '">Ver rota</button>'
                + '</div>',
                { maxWidth: 260 }
            );
        });

        var goodweCount = chargers.filter(function (c) { return c.source === 'goodwe'; }).length;
        var publicCount = chargers.length - goodweCount;
        if (chargers.length > 0) {
            showCount('<strong>' + chargers.length + '</strong> eletroposto(s) · ' + goodweCount + ' GoodWe + ' + publicCount + ' públicos');
        } else {
            showCount('Nenhum carregador encontrado por perto');
        }
    }

    function loadNearbyChargers(lat, lng) {
        showLoading(true);
        showCount(null);

        var prepare = function (chargers) {
            return chargers
                .filter(function (c) { return c.lat != null && c.lng != null; })
                .map(function (c) { return Object.assign({}, c, { _dist: calculateDistance(lat, lng, c.lat, c.lng) }); })
                .sort(function (a, b) { return a._dist - b._dist; });
        };

        var goodweChargers = [];
        fetchGoodWeChargers()
            .then(function (list) {
                goodweChargers = list;
                renderStations(prepare(goodweChargers));
            })
            .catch(function () {
                /* Rede GoodWe indisponível: segue só com os públicos. */
            })
            .then(function () {
                return fetchPublicChargingStations(lat, lng);
            })
            .then(function (publicChargers) {
                renderStations(prepare(goodweChargers.concat(publicChargers)));
            })
            .catch(function () {
                if (goodweChargers.length === 0) {
                    showCount('Não foi possível carregar os eletropostos.');
                }
            })
            .finally(function () {
                showLoading(false);
            });
    }

    function startRoute(destLat, destLng, name) {
        if (!userPosition || !map) return;
        map.closePopup();

        var distance = calculateDistance(userPosition.lat, userPosition.lng, destLat, destLng);
        var minutes = Math.ceil(distance * 3);

        if (routeLine) map.removeLayer(routeLine);
        routeLine = L.polyline(
            [[userPosition.lat, userPosition.lng], [destLat, destLng]],
            { color: '#e53935', weight: 4, opacity: .8, dashArray: '10, 10', lineCap: 'round' }
        ).addTo(map);
        map.fitBounds(routeLine.getBounds(), { padding: [40, 40] });

        var route = el('pt-map-route');
        if (route) {
            route.classList.add('is-active');
            route.innerHTML =
                '<div class="pt-map-route-box">'
                + '<p class="pt-map-route-title">Rota para ' + name + '</p>'
                + '<p class="pt-map-route-detail">' + formatDistance(distance) + ' · ~' + minutes + ' min</p>'
                + '<a class="pt-map-route-link" target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&origin='
                + userPosition.lat + ',' + userPosition.lng + '&destination=' + destLat + ',' + destLng
                + '&travelmode=driving">Abrir no Google Maps</a>'
                + '</div>'
                + '<button type="button" class="pt-map-route-cancel" data-route-cancel>Cancelar rota</button>';
        }
    }

    function cancelRoute() {
        if (routeLine && map) {
            map.removeLayer(routeLine);
            routeLine = null;
        }
        var route = el('pt-map-route');
        if (route) {
            route.classList.remove('is-active');
            route.innerHTML = '';
        }
    }

    function initMap() {
        var container = el('pt-map-canvas');

        // Sem Leaflet (CDN/asset fora do ar) mostramos o motivo em vez de
        // deixar a área do mapa simplesmente vazia e sem nenhum erro.
        if (typeof L === 'undefined') {
            showLoading(false);
            showCount('Não foi possível carregar o mapa. Recarregue a página.');
            if (container) {
                container.innerHTML = '<div class="pt-map-fallback">'
                    + '<strong>Mapa indisponível</strong>'
                    + '<p>A biblioteca de mapas não foi carregada. Verifique sua conexão e recarregue a página.</p>'
                    + '</div>';
            }
            return;
        }

        if (map || !container) return;

        map = L.map(container, {
            center: [DEFAULT_CENTER.lat, DEFAULT_CENTER.lng],
            zoom: 13,
            zoomControl: true,
            preferCanvas: true
        });

        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);

        chargersLayer = L.layerGroup().addTo(map);

        var usePosition = function (lat, lng, label) {
            userPosition = { lat: lat, lng: lng };
            var icon = L.divIcon({ className: 'pt-map-user-marker', html: '', iconSize: [20, 20], iconAnchor: [10, 10] });
            userMarker = L.marker([lat, lng], { icon: icon }).addTo(map);
            if (label) userMarker.bindPopup(label);
            map.setView([lat, lng], 14);
            loadNearbyChargers(lat, lng);
        };

        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function (position) {
                    usePosition(position.coords.latitude, position.coords.longitude, 'Você está aqui');
                },
                function () {
                    usePosition(DEFAULT_CENTER.lat, DEFAULT_CENTER.lng, 'Localização padrão');
                },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
            );
        } else {
            usePosition(DEFAULT_CENTER.lat, DEFAULT_CENTER.lng, 'Localização padrão');
        }

        // O container pode ainda estar sendo layoutado quando o Leaflet é
        // instanciado; sem isto os tiles saem fora de escala ou a área fica
        // cinza até um zoom manual.
        map.whenReady(function () {
            map.invalidateSize();
        });
        window.addEventListener('load', function () {
            if (map) map.invalidateSize();
        });
    }

    // Delegação de cliques: botões "Ver rota" (popup) e "Cancelar rota".
    document.addEventListener('click', function (event) {
        var routeBtn = event.target.closest('[data-route-to]');
        if (routeBtn) {
            var charger = chargersById[routeBtn.getAttribute('data-route-to')];
            if (charger && userPosition) {
                startRoute(charger.lat, charger.lng, charger.name);
            }
            return;
        }
        if (event.target.closest('[data-route-cancel]')) {
            cancelRoute();
        }
    });

    // Inicia o mapa quando o container existir no DOM.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMap);
    } else {
        initMap();
    }
})();
