<?php
/**
 * PARE/passenger/map.php — Live Tracking Portal
 * Integrates stationary Kiosk location and live bus movement.
 */
$requiredRole = 'passenger';
$pageTitle    = 'Live Bus Map';
$currentPage  = 'map.php';

require_once '../config/db.php';
require_once '../includes/auth_guard.php';
require_once '../config/kiosk_settings.php'; // Crucial for the stationary logic

// Fetch route stops to display on the map
$stations = $pdo->query(
    "SELECT station_name, km_marker, latitude, longitude, is_terminal 
     FROM stations WHERE is_active=1 ORDER BY km_marker ASC"
)->fetchAll();

include '../includes/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<div class="flex min-h-screen bg-slate-50">
    <?php include '../includes/sidebar_passenger.php'; ?>

    <main class="flex-1 p-4 md:p-8 pb-24">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="text-3xl font-black text-slate-800 tracking-tight">Live Fleet Tracker</h2>
                <p class="text-slate-500 font-medium text-sm">
                    Real-time Bus Locations
                </p>
            </div>
            <div id="status-pill" class="flex items-center gap-2 bg-white border border-slate-200 rounded-full px-5 py-2 shadow-sm transition-all">
                <span class="w-2.5 h-2.5 rounded-full bg-amber-400 animate-pulse"></span>
                <span id="status-text" class="text-sm font-bold text-slate-600">Connecting...</span>
            </div>
        </div>

        <div class="relative bg-white rounded-[2rem] shadow-2xl border border-slate-100 overflow-hidden" style="height: 600px;">
            <div id="map" class="w-full h-full z-0"></div>
            
            <div class="hidden lg:block absolute top-6 right-6 z-[1000] w-72 bg-white/80 backdrop-blur-xl rounded-2xl shadow-xl border border-white/20 p-5 max-h-[500px] overflow-y-auto">
                <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-4">Route Waypoints</h3>
                <div class="space-y-4">
                    <?php foreach ($stations as $s): ?>
                        <div class="flex items-start gap-3">
                            <div class="mt-1 w-2.5 h-2.5 rounded-full <?= $s['is_terminal'] ? 'bg-blue-600 shadow-[0_0_8px_rgba(37,99,235,0.6)]' : 'bg-slate-300' ?>"></div>
                            <div>
                                <p class="text-sm font-bold text-slate-700 leading-none"><?= htmlspecialchars($s['station_name']) ?></p>
                                <p class="text-[10px] font-bold text-slate-400 mt-1">KM <?= number_format($s['km_marker'], 1) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
// 1. Initial Data & State
const stations = <?= json_encode($stations) ?>;
const defaultKioskPos = [<?= KIOSK_LAT ?>, <?= KIOSK_LNG ?>];

// 2. Setup Map with Premium Tiles
const map = L.map('map', { zoomControl: false }).setView(defaultKioskPos, 12);
L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
    attribution: '© OpenStreetMap'
}).addTo(map);

// 3. Highlight Stations
stations.forEach(s => {
    if (s.is_terminal) {
        L.circleMarker([s.latitude, s.longitude], {
            radius: 8,
            fillColor: '#2563eb',
            color: '#fff',
            weight: 3,
            fillOpacity: 1
        }).addTo(map).bindPopup(`<b>Terminal: ${s.station_name}</b>`);
    }
});

// 4. Live Bus Tracking
let busMarkers = {};
const busColors = ['#f59e0b', '#10b981', '#8b5cf6', '#ef4444', '#3b82f6', '#ec4899'];

function makeBusIcon(busId, bodyNumber) {
    const color = busColors[(busId - 1) % busColors.length];
    return L.divIcon({
        html: `<div style="background:${color}" class="flex items-center justify-center w-12 h-12 rounded-full border-4 border-white shadow-xl">
                 <span style="font-size:10px;font-weight:900;color:white;line-height:1;text-align:center">${bodyNumber.replace('BUS-','')}</span>
               </div>`,
        className: '', iconSize: [48, 48], iconAnchor: [24, 24]
    });
}

function syncFleet() {
    fetch('get_bus_location.php')
        .then(r => r.json())
        .then(data => {
            const statusText = document.getElementById('status-text');
            const statusDot = document.querySelector('#status-pill span');

            if (data.success && data.buses.length > 0) {
                const activeIds = new Set();
                let visibleCount = 0;

                data.buses.forEach(bus => {
                    // Skip buses with no coordinates
                    if (!bus.latitude || !bus.longitude) return;

                    visibleCount++;
                    activeIds.add(bus.bus_id);
                    const pos = [parseFloat(bus.latitude), parseFloat(bus.longitude)];
                    
                    if (!busMarkers[bus.bus_id]) {
                        busMarkers[bus.bus_id] = L.marker(pos, { 
                            icon: makeBusIcon(parseInt(bus.bus_id), bus.body_number),
                            zIndexOffset: 1000
                        }).addTo(map);
                    } else {
                        busMarkers[bus.bus_id].setLatLng(pos);
                    }
                    
                    busMarkers[bus.bus_id].bindPopup(`
                        <div class="p-2 min-w-[180px]">
                            <p class="font-black text-slate-800 text-base">${bus.body_number}</p>
                            <div class="mt-2 space-y-1 text-xs">
                                <p class="text-slate-500">🧑‍✈️ <b>${bus.driver_name || 'Unknown'}</b></p>
                                <p class="text-slate-500">📍 ${bus.start_name || '?'} → ${bus.end_name || '?'}</p>
                                <p class="text-slate-500">👥 ${bus.passenger_count || 0} passengers</p>
                                <p class="font-bold text-green-600">⚡ ${Math.round(bus.speed_kmh)} km/h</p>
                            </div>
                        </div>
                    `);
                });

                // Cleanup inactive buses
                Object.keys(busMarkers).forEach(id => {
                    if (!activeIds.has(parseInt(id))) {
                        map.removeLayer(busMarkers[id]);
                        delete busMarkers[id];
                    }
                });

                statusText.textContent = `${visibleCount} Bus(es) Online`;
                statusDot.className = "w-2.5 h-2.5 rounded-full bg-green-500 shadow-[0_0_8px_rgba(34,197,94,0.6)]";
            } else {
                statusText.textContent = "Waiting for active buses...";
                statusDot.className = "w-2.5 h-2.5 rounded-full bg-slate-300";
            }
        })
        .catch(() => {
            document.getElementById('status-text').textContent = "Connection Error";
        });
}

// Initial Sync and Loop
syncFleet();
setInterval(syncFleet, 5000);
</script>

<?php include '../includes/mobile_nav_passenger.php'; ?>