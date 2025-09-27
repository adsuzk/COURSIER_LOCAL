<?php
// sections/js_client_tracking.php - Suivi temps réel du coursier côté client
?>
    <script>
    (function(){
        const LOG_PREFIX = '🛰️ ClientLiveTracking';
        const FETCH_INTERVAL_MS = 5000;
        const MAX_GEO_ATTEMPTS = 3;
        const state = {
            orderId: null,
            pollTimer: null,
            courierMarker: null,
            routeLine: null,
            destination: null,
            departure: null,
            lastStatus: null,
            lastPosition: null,
            fitBoundsDone: false,
            geocoder: null,
            geoAttempts: 0,
            lastReason: null
        };

        function log(){
            if (console && typeof console.log === 'function') {
                console.log(LOG_PREFIX, ...arguments);
            }
        }

        function warn(){
            if (console && typeof console.warn === 'function') {
                console.warn(LOG_PREFIX, ...arguments);
            }
        }

        function ensureMapReady() {
            return new Promise((resolve, reject) => {
                if (window.google && google.maps && window.map) {
                    resolve(window.map);
                    return;
                }
                let tries = 0;
                const interval = setInterval(() => {
                    tries++;
                    if (window.google && google.maps && window.map) {
                        clearInterval(interval);
                        resolve(window.map);
                    } else if (tries >= 40) {
                        clearInterval(interval);
                        reject(new Error('Google Maps non initialisé'));
                    }
                }, 250);
            });
        }

        function parseLatLng(value) {
            if (value === null || value === undefined) return null;
            const num = parseFloat(value);
            return Number.isFinite(num) ? num : null;
        }

        function extractLatLngFromMarker(marker) {
            try {
                if (marker && typeof marker.getPosition === 'function') {
                    const pos = marker.getPosition();
                    if (pos) {
                        const lat = typeof pos.lat === 'function' ? pos.lat() : pos.lat;
                        const lng = typeof pos.lng === 'function' ? pos.lng() : pos.lng;
                        if (Number.isFinite(lat) && Number.isFinite(lng)) {
                            return { lat, lng };
                        }
                    }
                }
            } catch (e) {
                warn('extractLatLngFromMarker error', e);
            }
            return null;
        }

        function haversineDistanceKm(a, b) {
            if (!a || !b || !Number.isFinite(a.lat) || !Number.isFinite(a.lng) || !Number.isFinite(b.lat) || !Number.isFinite(b.lng)) {
                return null;
            }
            const toRad = (deg) => deg * Math.PI / 180;
            const R = 6371; // km
            const dLat = toRad(b.lat - a.lat);
            const dLng = toRad(b.lng - a.lng);
            const lat1 = toRad(a.lat);
            const lat2 = toRad(b.lat);
            const h = Math.sin(dLat/2) * Math.sin(dLat/2) + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng/2) * Math.sin(dLng/2);
            const c = 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1-h));
            return R * c;
        }

        function formatTimeAgo(date) {
            if (!date) return '—';
            const now = Date.now();
            const ts = date instanceof Date ? date.getTime() : Date.parse(date);
            if (!Number.isFinite(ts)) return '—';
            const diff = Math.max(0, now - ts);
            if (diff < 1000) return "à l'instant";
            const seconds = Math.round(diff / 1000);
            if (seconds < 60) return `${seconds}s`;
            const minutes = Math.round(seconds / 60);
            if (minutes < 60) return `${minutes} min`;
            const hours = Math.round(minutes / 60);
            if (hours < 24) return `${hours} h`;
            const days = Math.round(hours / 24);
            return `${days} j`;
        }

        function ensureOverlayVisible(statusLabel, lastUpdate) {
            const info = document.getElementById('routeInfo');
            if (info) {
                info.style.display = 'flex';
            }
            const distanceEl = document.getElementById('routeDistance');
            const durationEl = document.getElementById('routeDuration');
            const priceEl = document.getElementById('routePrice');
            if (distanceEl && state.destination && state.lastPosition) {
                const dist = haversineDistanceKm(state.lastPosition, state.destination);
                distanceEl.textContent = dist ? `${dist.toFixed(1)} km` : '—';
            }
            if (durationEl) {
                durationEl.textContent = formatTimeAgo(lastUpdate);
            }
            if (priceEl) {
                const label = statusLabel || (state.lastStatus && state.lastStatus.statut) || 'en attente';
                const coursier = state.lastStatus && state.lastStatus.coursier_id ? `Coursier #${state.lastStatus.coursier_id}` : '';
                priceEl.textContent = coursier ? `${coursier} • ${label}` : label;
            }
        }

        function setRouteContext(ctx) {
            if (!ctx || !ctx.route) return;
            if (ctx.route.destination) {
                const lat = parseLatLng(ctx.route.destination.lat);
                const lng = parseLatLng(ctx.route.destination.lng);
                const address = ctx.route.destination.address || null;
                if (Number.isFinite(lat) && Number.isFinite(lng)) {
                    state.destination = { lat, lng, address };
                } else if (!state.destination) {
                    state.destination = { lat: null, lng: null, address };
                } else if (address && !state.destination.address) {
                    state.destination.address = address;
                }
            }
            if (ctx.route.departure) {
                const lat = parseLatLng(ctx.route.departure.lat);
                const lng = parseLatLng(ctx.route.departure.lng);
                const address = ctx.route.departure.address || null;
                if (Number.isFinite(lat) && Number.isFinite(lng)) {
                    state.departure = { lat, lng, address };
                } else if (!state.departure) {
                    state.departure = { lat: null, lng: null, address };
                } else if (address && !state.departure.address) {
                    state.departure.address = address;
                }
            }
        }

        function ensureGeocoder() {
            if (!state.geocoder && window.google && google.maps) {
                state.geocoder = new google.maps.Geocoder();
            }
            return state.geocoder;
        }

        function geocodeAddress(address) {
            return new Promise((resolve) => {
                if (!address) {
                    resolve(null);
                    return;
                }
                const geocoder = ensureGeocoder();
                if (!geocoder) {
                    resolve(null);
                    return;
                }
                geocoder.geocode({ address, region: 'ci' }, (results, status) => {
                    if (status === 'OK' && results && results[0]) {
                        const loc = results[0].geometry && results[0].geometry.location;
                        if (loc) {
                            resolve({
                                lat: typeof loc.lat === 'function' ? loc.lat() : loc.lat,
                                lng: typeof loc.lng === 'function' ? loc.lng() : loc.lng
                            });
                            return;
                        }
                    }
                    resolve(null);
                });
            });
        }

        async function resolveMissingCoordinates() {
            if (!state.destination || !Number.isFinite(state.destination.lat) || !Number.isFinite(state.destination.lng)) {
                const markerPos = extractLatLngFromMarker(window.markerB);
                if (markerPos) {
                    state.destination = { ...state.destination, ...markerPos };
                } else if (state.destination && state.destination.address && state.geoAttempts < MAX_GEO_ATTEMPTS) {
                    const geo = await geocodeAddress(state.destination.address);
                    state.geoAttempts++;
                    if (geo) {
                        state.destination = { ...state.destination, ...geo };
                    }
                }
            }
            if (!state.departure || !Number.isFinite(state.departure.lat) || !Number.isFinite(state.departure.lng)) {
                const markerPos = extractLatLngFromMarker(window.markerA);
                if (markerPos) {
                    state.departure = { ...state.departure, ...markerPos };
                }
            }
        }

        function updateRouteLine(map, courierLatLng) {
            if (!state.destination || !Number.isFinite(state.destination.lat) || !Number.isFinite(state.destination.lng)) {
                return;
            }
            const destinationLatLng = new google.maps.LatLng(state.destination.lat, state.destination.lng);
            if (!state.routeLine) {
                state.routeLine = new google.maps.Polyline({
                    map,
                    strokeColor: '#D4A853',
                    strokeOpacity: 0.85,
                    strokeWeight: 3,
                    geodesic: true
                });
            }
            state.routeLine.setPath([courierLatLng, destinationLatLng]);
            if (!state.fitBoundsDone) {
                const bounds = new google.maps.LatLngBounds();
                bounds.extend(courierLatLng);
                bounds.extend(destinationLatLng);
                map.fitBounds(bounds, 80);
                state.fitBoundsDone = true;
            }
        }

        function updateCourierMarker(map, latLng, updatedAt) {
            if (!state.courierMarker) {
                state.courierMarker = new google.maps.Marker({
                    map,
                    position: latLng,
                    zIndex: 1000,
                    title: 'Coursier assigné',
                    icon: {
                        url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 48 48">
                                <circle cx="24" cy="24" r="20" fill="#D4A853" stroke="#1A1A2E" stroke-width="3" />
                                <text x="24" y="30" font-size="18" text-anchor="middle" fill="#1A1A2E" font-family="Arial" font-weight="bold">🛵</text>
                            </svg>
                        `),
                        scaledSize: new google.maps.Size(48, 48)
                    }
                });
            } else {
                state.courierMarker.setPosition(latLng);
                state.courierMarker.setVisible(true);
            }
            if (!state.lastPosition) {
                map.panTo(latLng);
            }
            state.lastPosition = { lat: latLng.lat(), lng: latLng.lng(), updatedAt };
        }

        async function fetchCourierPosition() {
            if (!state.orderId) return;
            try {
                const response = await fetch(`/api/get_courier_position_for_order.php?commande_id=${encodeURIComponent(state.orderId)}`, {
                    cache: 'no-store'
                });
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                const payload = await response.json();
                if (!payload.success || !payload.data || payload.data.live !== true) {
                    return;
                }
                const position = payload.data.position;
                if (!position || position.lat === null || position.lng === null) {
                    return;
                }
                const lat = parseLatLng(position.lat);
                const lng = parseLatLng(position.lng);
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                    return;
                }
                const map = await ensureMapReady();
                await resolveMissingCoordinates();
                const latLng = new google.maps.LatLng(lat, lng);
                const updatedAt = position.updated_at || new Date().toISOString();
                updateCourierMarker(map, latLng, updatedAt);
                updateRouteLine(map, latLng);
                ensureOverlayVisible(state.lastStatus ? state.lastStatus.statut : 'en cours', updatedAt);
            } catch (error) {
                warn('fetchCourierPosition error', error.message || error);
            }
        }

        function startPolling() {
            if (state.pollTimer) return;
            fetchCourierPosition();
            state.pollTimer = setInterval(fetchCourierPosition, FETCH_INTERVAL_MS);
        }

        function clearVisuals(removeMarkers = true) {
            if (removeMarkers && state.courierMarker) {
                state.courierMarker.setMap(null);
                state.courierMarker = null;
            }
            if (state.routeLine) {
                state.routeLine.setMap(null);
                state.routeLine = null;
            }
            state.fitBoundsDone = false;
            state.lastPosition = null;
        }

        function stop(reason) {
            if (state.pollTimer) {
                clearInterval(state.pollTimer);
                state.pollTimer = null;
            }
            state.lastReason = reason || null;
            if (window.OrderTrackingBridge && typeof window.OrderTrackingBridge.updateBadge === 'function') {
                if (reason === 'delivered') {
                    window.OrderTrackingBridge.updateBadge('delivered');
                } else if (reason === 'désactivé') {
                    window.OrderTrackingBridge.updateBadge('stopped', 'Suivi désactivé');
                } else if (reason === 'fallback') {
                    window.OrderTrackingBridge.updateBadge('stopped', 'Retour au mode standard');
                } else if (reason) {
                    window.OrderTrackingBridge.updateBadge('stopped', `Suivi interrompu (${reason})`);
                } else {
                    window.OrderTrackingBridge.updateBadge('stopped');
                }
            }
            if (reason === 'delivered') {
                ensureOverlayVisible('Livraison terminée', Date.now());
                if (state.courierMarker) {
                    state.courierMarker.setIcon({
                        url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 48 48">
                                <circle cx="24" cy="24" r="20" fill="#2ECC71" stroke="#1A1A2E" stroke-width="3" />
                                <text x="24" y="30" font-size="18" text-anchor="middle" fill="#1A1A2E" font-family="Arial" font-weight="bold">✅</text>
                            </svg>
                        `),
                        scaledSize: new google.maps.Size(48, 48)
                    });
                }
            } else {
                clearVisuals(true);
            }
            if (reason !== 'delivered') {
                const priceEl = document.getElementById('routePrice');
                if (priceEl) priceEl.textContent = reason ? `Suivi interrompu (${reason})` : 'Suivi en pause';
            }
            state.orderId = null;
            state.lastStatus = null;
        }

        async function start(orderId, ctx, status) {
            if (!orderId) return;
            state.orderId = orderId;
            state.lastStatus = status || null;
            state.geoAttempts = 0;
            setRouteContext(ctx);
            await resolveMissingCoordinates();
            startPolling();
        }

        function notifyStatus(status, ctx) {
            try {
                const shouldTrack = status && status.live_tracking === true;
                state.lastStatus = status;
                if (window.OrderTrackingBridge && typeof window.OrderTrackingBridge.updateBadge === 'function') {
                    window.OrderTrackingBridge.updateBadge(shouldTrack ? 'live' : 'pending');
                }
                if (!shouldTrack) {
                    if (status && status.statut === 'livree') {
                        stop('delivered');
                    } else if (state.orderId) {
                        stop('désactivé');
                    }
                    return;
                }
                const orderId = status.order_id || (ctx && ctx.orderId) || state.orderId;
                if (!orderId) return;
                if (state.orderId && state.orderId !== orderId) {
                    stop('nouvelle commande');
                }
                setRouteContext(ctx);
                start(orderId, ctx, status);
            } catch (error) {
                warn('notifyStatus error', error.message || error);
            }
        }

        window.ClientLiveTracking = window.ClientLiveTracking || {};
        window.ClientLiveTracking.notifyStatus = notifyStatus;
        window.ClientLiveTracking.stop = stop;
    })();
    </script>
