<?php
/**
 * passenger/map.php — Live bus tracking map
 * Route: Cabanatuan Central Terminal → Rizal/Pob Sur Terminal (~40km)
 * Uses OSRM public routing API for road-accurate polyline
 */
$requiredRole = 'passenger';
$pageTitle    = 'Live Map';
$currentPage  = 'map.php';

require_once '../config/db.php';
require_once '../includes/auth_guard.php';

// Load all active stations
$stations = $pdo->query(
    "SELECT station_name, km_marker, latitude, longitude, is_terminal
     FROM   stations WHERE is_active=1 ORDER BY sort_order ASC"
)->fetchAll();

include '../includes/header.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

<div class="flex min-h-screen">
    <?php include '../includes/sidebar_passenger.php'; ?>

    <main class="flex-1 flex flex-col p-4 md:p-8 overflow-auto bg-slate-50 pb-24 md:pb-8">

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="text-2xl font-black text-slate-800">Live Bus Map</h2>
                <p class="text-slate-500 text-sm mt-1">Cabanatuan Central Terminal → Rizal/Pob Sur · Updates every 5 seconds</p>
            </div>
            <div id="map-status" class="flex items-center gap-2 bg-white border border-slate-200 rounded-full px-4 py-2 text-sm font-medium shadow-sm">
                <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                <span id="status-text">Connecting...</span>
            </div>
        </div>

        <!-- Map container -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden" style="height:520px;">
            <div id="map" class="w-full h-full"></div>
        </div>

        <!-- Legend -->
        <div class="flex items-center gap-4 mt-3 px-1">
            <div class="flex items-center gap-1.5 text-xs text-slate-500">
                <div class="w-6 h-1 rounded" style="background:#2563eb;"></div> Road Route
            </div>
            <div class="flex items-center gap-1.5 text-xs text-slate-500">
                <div class="w-3 h-3 rounded-full bg-blue-600 border-2 border-white shadow"></div> Terminal
            </div>
            <div class="flex items-center gap-1.5 text-xs text-slate-500">
                <div class="w-2.5 h-2.5 rounded-full bg-slate-400 border-2 border-white shadow"></div> Stop
            </div>
            <div class="flex items-center gap-1.5 text-xs text-slate-500">
                <span>🚌</span> Live Bus
            </div>
        </div>

        <!-- Station list -->
        <div class="mt-4 bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
            <h3 class="text-sm font-bold text-slate-600 mb-3 flex items-center gap-2">
                <i class="ph ph-map-pin text-blue-500"></i>
                Route Stops (<?= count($stations) ?> stops · 40 km total)
            </h3>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($stations as $s): ?>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold
                    <?= $s['is_terminal'] ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600' ?>">
                    <?= $s['is_terminal'] ? '<i class="ph ph-map-pin-fill"></i>' : '<i class="ph ph-circle text-slate-400"></i>' ?>
                    <?= htmlspecialchars($s['station_name']) ?>
                    <span class="opacity-60">KM <?= (int)$s['km_marker'] ?></span>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const stations = <?= json_encode(array_values($stations)) ?>;

// ─── Init map ───────────────────────────────────────────────────────────────
const map = L.map('map').setView([15.5950, 121.0200], 11);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://openstreetmap.org">OpenStreetMap</a>',
    maxZoom: 19
}).addTo(map);

// ─── Station markers ─────────────────────────────────────────────────────────
stations.forEach(s => {
    if (!s.latitude || !s.longitude) return;
    const ll = [parseFloat(s.latitude), parseFloat(s.longitude)];
    const circle = L.circleMarker(ll, {
        radius:      s.is_terminal ? 9 : 6,
        fillColor:   s.is_terminal ? '#2563eb' : '#94a3b8',
        color:       '#ffffff',
        weight:      2,
        fillOpacity: 1,
        zIndexOffset: s.is_terminal ? 1000 : 0
    }).addTo(map);
    circle.bindPopup(`
        <div style="min-width:140px">
            <b style="color:#1e40af">${s.station_name}</b><br>
            <span style="color:#64748b;font-size:12px">KM ${parseFloat(s.km_marker).toFixed(0)}</span>
            ${s.is_terminal ? '<br><span style="color:#16a34a;font-size:11px;font-weight:bold">● TERMINAL</span>' : ''}
        </div>
    `);
});

// ─── Road-accurate route via OSRM public API ────────────────────────────────
let routePolyline = null;

function buildOsrmUrl(stationList) {
    // Use all stations as waypoints for accurate road routing
    const coordStr = stationList
        .filter(s => s.latitude && s.longitude)
        .map(s => `${parseFloat(s.longitude).toFixed(6)},${parseFloat(s.latitude).toFixed(6)}`)
        .join(';');
    return `https://router.project-osrm.org/route/v1/driving/${coordStr}?overview=full&geometries=geojson`;
}

function drawRoadRoute() {
    const url = buildOsrmUrl(stations);
    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (data.code !== 'Ok' || !data.routes.length) {
                // Fallback: straight lines between stations
                drawFallbackRoute();
                return;
            }
            const coords = data.routes[0].geometry.coordinates.map(c => [c[1], c[0]]);
            if (routePolyline) map.removeLayer(routePolyline);
            routePolyline = L.polyline(coords, {
                color:   '#2563eb',
                weight:  5,
                opacity: 0.80
            }).addTo(map);
            map.fitBounds(routePolyline.getBounds(), { padding: [40, 40] });
        })
        .catch(() => drawFallbackRoute());
}

function drawFallbackRoute() {
    const coords = stations
        .filter(s => s.latitude && s.longitude)
        .map(s => [parseFloat(s.latitude), parseFloat(s.longitude)]);
    if (routePolyline) map.removeLayer(routePolyline);
    routePolyline = L.polyline(coords, {
        color: '#2563eb', weight: 4, opacity: 0.7, dashArray: '8,5'
    }).addTo(map);
    map.fitBounds(routePolyline.getBounds(), { padding: [40, 40] });
}

drawRoadRoute();

// ─── Live bus marker ─────────────────────────────────────────────────────────
const busIcon = L.divIcon({
    html: `<div style="background:#f59e0b;width:36px;height:36px;border-radius:50%;
                border:3px solid #fff;box-shadow:0 3px 10px rgba(0,0,0,.35);
                display:flex;align-items:center;justify-content:center;font-size:18px;
                transition:all .3s ease;">🚌</div>`,
    className: '',
    iconSize:   [36, 36],
    iconAnchor: [18, 18]
});
let busMarkers = {}; // Keep track of active buses

function updateMap() {
    fetch('get_bus_location.php')
        .then(r => r.json())
        .then(data => {
            const statusEl = document.getElementById('status-text');
            const dot      = document.querySelector('#map-status span:first-child');
            
            if (data.success && data.buses.length > 0) {
                // Keep track of which buses are still active in this payload
                const currentRouteBuses = new Set();

                data.buses.forEach(b => {
                    currentRouteBuses.add(b.bus_id);
                    const latLng = [parseFloat(b.latitude), parseFloat(b.longitude)];
                    const speed = b.speed_kmh ? `${parseFloat(b.speed_kmh).toFixed(0)} km/h` : '';
                    
                    if (!busMarkers[b.bus_id]) {
                        busMarkers[b.bus_id] = L.marker(latLng, { icon: busIcon, zIndexOffset: 9999 }).addTo(map);
                    } else {
                        busMarkers[b.bus_id].setLatLng(latLng);
                    }
                    
                    busMarkers[b.bus_id].bindPopup(`
                        <b>🚌 ${b.body_number}</b><br>
                        <span style="color:#64748b;font-size:12px">${b.start_name} &rarr; ${b.end_name}</span>
                        ${speed ? `<br><span style="color:#16a34a;font-size:12px">⚡ ${speed}</span>` : ''}
                    `);
                });

                // Remove stale markers (buses that ended their trips)
                Object.keys(busMarkers).forEach(busId => {
                    if (!currentRouteBuses.has(parseInt(busId))) {
                        map.removeLayer(busMarkers[busId]);
                        delete busMarkers[busId];
                    }
                });

                statusEl.textContent = `Tracking ${data.buses.length} bus(es)`;
                dot.className = 'w-2 h-2 rounded-full bg-green-500 animate-pulse';
            } else {
                statusEl.textContent = 'No active buses on route';
                dot.className = 'w-2 h-2 rounded-full bg-slate-400';
                // Clear all markers
                Object.values(busMarkers).forEach(m => map.removeLayer(m));
                busMarkers = {};
            }
        })
        .catch(() => {
            document.getElementById('status-text').textContent = 'Connection error';
        });
}

updateMap();
setInterval(updateMap, 5000);
</script>

<?php include '../includes/mobile_nav_passenger.php'; ?>
</body></html>