<?php
/**
 * passenger/map.php
 *
 * ROLE: Passenger Portal
 * PURPOSE: Real-time fleet tracker.
 * Uses Server-Sent Events (SSE) so bus positions update the instant
 * new GPS data arrives — no polling lag.
 */
$requiredRole = 'passenger';
$pageTitle    = 'Live Bus Map';
$currentPage  = 'map.php';

require_once '../config/db.php';
require_once '../includes/auth_guard.php';
require_once '../config/kiosk_settings.php';

$stations = $pdo->query(
    "SELECT station_name, km_marker, latitude, longitude, is_terminal
     FROM stations WHERE is_active=1 ORDER BY km_marker ASC"
)->fetchAll();

include '../includes/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<style>
.bus-marker-wrap {
    position: relative;
    width: 36px; height: 36px;
}
.bus-marker-dot {
    width: 36px; height: 36px;
    border-radius: 50%;
    background: #f59e0b;
    border: 3px solid #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,.35);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 15px; font-weight: 900;
    font-family: monospace;
}
.bus-marker-pulse {
    position: absolute; top: -4px; left: -4px;
    width: 44px; height: 44px; border-radius: 50%;
    background: rgba(245,158,11,.35);
    animation: bus-pulse 2s ease-out infinite;
    pointer-events: none;
}
@keyframes bus-pulse {
    0%   { transform: scale(1);   opacity: .8; }
    100% { transform: scale(1.9); opacity: 0;  }
}
.custom-leaflet-icon { background: none; border: none; }
</style>

<div class="flex min-h-screen">
    <?php include '../includes/sidebar_passenger.php'; ?>

    <main class="flex-1 p-4 md:p-8 pb-24">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="text-3xl font-black text-[#0F172A] tracking-tight">Live Fleet Tracker</h2>
                <p class="text-[#64748B] font-medium text-sm">Updates instantly as buses move</p>
            </div>
            <div id="status-pill" class="flex items-center gap-2 bg-white border border-[#E2E8F0] rounded-full px-5 py-2 shadow-sm">
                <span id="status-dot" class="w-2.5 h-2.5 rounded-full bg-amber-400 animate-pulse"></span>
                <span id="status-text" class="text-sm font-bold text-[#64748B]">Connecting…</span>
            </div>
        </div>

        <div class="relative bg-white rounded-[2rem] shadow-2xl border border-[#E2E8F0] overflow-hidden" style="height:600px">
            <div id="map" class="w-full h-full z-0"></div>

            <!-- Fleet panel (desktop only) -->
            <div class="hidden lg:flex flex-col absolute top-4 right-4 z-[1000] w-64 bg-white/95 backdrop-blur rounded-2xl shadow-xl border border-slate-100 overflow-hidden max-h-[560px]">
                <div class="px-4 py-3 border-b border-slate-100">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Active Buses</p>
                </div>
                <div id="fleet-panel" class="overflow-y-auto flex-1 p-2 space-y-1">
                    <p class="text-xs text-slate-400 text-center py-6">Waiting for buses…</p>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
// ── 1. MAP SETUP ───────────────────────────────────────────────────────────
const stations       = <?= json_encode($stations) ?>;
const defaultPos     = [<?= KIOSK_LAT ?>, <?= KIOSK_LNG ?>];

const map = L.map('map', { zoomControl: false }).setView(defaultPos, 13);
L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
    attribution: '© OpenStreetMap | PARE'
}).addTo(map);

// Route polyline removed as requested

// Draw station markers
stations.forEach(s => {
    if (!s.latitude || !s.longitude) return;
    const pos = [parseFloat(s.latitude), parseFloat(s.longitude)];
    if (s.is_terminal) {
        L.circleMarker(pos, { radius: 8, fillColor: '#2563eb', color: '#fff', weight: 3, fillOpacity: 1 })
            .addTo(map).bindPopup(`<b>Terminal: ${s.station_name}</b>`);
    }
    // Gray non-terminal dots removed as requested
});

// ── 2. BUS MARKERS ────────────────────────────────────────────────────────
let busMarkers = {};  // bus_id → { marker, targetPos, currentPos }

function makeBusIcon(label) {
    return L.divIcon({
        html: `<div class="bus-marker-wrap">
                 <div class="bus-marker-pulse"></div>
                 <div class="bus-marker-dot">${label}</div>
               </div>`,
        className: 'custom-leaflet-icon',
        iconSize: [36, 36], iconAnchor: [18, 18]
    });
}

// Smooth animation: interpolate marker from current to target position
function animateMarker(id, fromPos, toPos, durationMs) {
    const steps  = 30;
    const delay  = durationMs / steps;
    let   step   = 0;
    const timer  = setInterval(() => {
        if (!busMarkers[id] || step >= steps) { clearInterval(timer); return; }
        step++;
        const t   = step / steps;
        const lat = fromPos[0] + (toPos[0] - fromPos[0]) * t;
        const lng = fromPos[1] + (toPos[1] - fromPos[1]) * t;
        busMarkers[id].marker.setLatLng([lat, lng]);
    }, delay);
}

// ── 3. UI HELPERS ─────────────────────────────────────────────────────────
const statusDot  = document.getElementById('status-dot');
const statusText = document.getElementById('status-text');
const fleetPanel = document.getElementById('fleet-panel');

function setStatus(type, text) {
    const classes = {
        connecting : 'bg-amber-400 animate-pulse',
        online     : 'bg-green-500 shadow-[0_0_8px_rgba(34,197,94,.6)]',
        waiting    : 'bg-slate-300',
        error      : 'bg-red-500'
    };
    statusDot.className  = 'w-2.5 h-2.5 rounded-full ' + (classes[type] || classes.waiting);
    statusText.textContent = text;
}

function buildFleetPanel(buses) {
    if (!buses.length) {
        fleetPanel.innerHTML = '<p class="text-xs text-slate-400 text-center py-6">No active buses</p>';
        return;
    }
    fleetPanel.innerHTML = buses.map(b => `
        <button onclick="flyToBus(${b.bus_id})"
            class="w-full text-left px-3 py-2.5 rounded-xl hover:bg-slate-50 transition-colors">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-full bg-amber-500 flex items-center justify-center text-white text-xs font-black shrink-0">
                    ${String(b.body_number).slice(-2)}
                </div>
                <div>
                    <p class="text-xs font-bold text-slate-700 leading-none">${b.body_number}</p>
                    <p class="text-[10px] text-slate-400 mt-0.5">${b.driver_name || '—'}</p>
                </div>
                <span class="ml-auto text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">
                    ${Math.round(b.speed_kmh || 0)} km/h
                </span>
            </div>
        </button>
    `).join('');
}

function flyToBus(busId) {
    const b = busMarkers[busId];
    if (b) map.flyTo(b.marker.getLatLng(), 15, { duration: 0.8 });
}

// ── 4. SSE — Server-Sent Events ───────────────────────────────────────────
// The browser connects ONCE; the server pushes data as it changes.
// EventSource reconnects automatically if the connection drops.
let activeBusIds = new Set();

function handleBusData(data) {
    if (!data.success) return;

    const buses     = data.buses || [];
    const newIds    = new Set();
    let   count     = 0;

    buses.forEach(bus => {
        if (!bus.latitude || !bus.longitude) return;
        count++;
        const id  = bus.bus_id;
        const pos = [parseFloat(bus.latitude), parseFloat(bus.longitude)];
        newIds.add(id);

        if (!busMarkers[id]) {
            // First time we see this bus — create the marker
            const label = String(bus.body_number).replace(/\D/g, '').replace(/^0+/, '') || String(bus.body_number).slice(-2);
            const marker = L.marker(pos, {
                icon: makeBusIcon(label),
                zIndexOffset: 1000
            }).addTo(map);
            busMarkers[id] = { marker, currentPos: pos };
        } else {
            // Bus already on map — animate it to the new position
            const from = busMarkers[id].currentPos;
            busMarkers[id].currentPos = pos;
            animateMarker(id, from, pos, 900);
        }

        busMarkers[id].marker.bindPopup(`
            <div style="min-width:180px;padding:6px">
                <p style="font-weight:900;font-size:14px;margin:0">${bus.body_number}</p>
                <p style="font-size:11px;color:#64748b;margin:4px 0 2px">
                    🧑‍✈️ <b>${bus.driver_name || 'Unknown'}</b>
                </p>
                <p style="font-size:11px;color:#64748b;margin:2px 0">
                    📍 ${bus.start_name || '?'} → ${bus.end_name || '?'}
                </p>
                <p style="font-size:11px;color:#64748b;margin:2px 0">
                    🚌 ${Math.round(bus.speed_kmh || 0)} km/h &nbsp;|&nbsp;
                    👥 ${bus.passenger_count || 0} aboard
                </p>
            </div>
        `);
    });

    // Remove buses that went offline
    Object.keys(busMarkers).forEach(id => {
        if (!newIds.has(parseInt(id))) {
            map.removeLayer(busMarkers[id].marker);
            delete busMarkers[id];
        }
    });

    activeBusIds = newIds;
    buildFleetPanel(buses);

    if (count > 0) {
        setStatus('online', `${count} Bus${count > 1 ? 'es' : ''} Online`);
    } else {
        setStatus('waiting', 'Waiting for active buses…');
    }
}

function connectSSE() {
    setStatus('connecting', 'Connecting…');

    const es = new EventSource('stream_bus_location.php');

    es.onmessage = function(e) {
        try {
            const data = JSON.parse(e.data);
            handleBusData(data);
        } catch (err) {
            console.warn('SSE parse error', err);
        }
    };

    es.onerror = function() {
        setStatus('error', 'Reconnecting…');
        // EventSource reconnects automatically — no manual retry needed
    };

    es.onopen = function() {
        setStatus('connecting', 'Connected — waiting for data…');
    };
}

// Start SSE connection
connectSSE();
</script>

<?php include '../includes/mobile_nav_passenger.php'; ?>