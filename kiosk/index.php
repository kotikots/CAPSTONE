<?php require_once '../config/db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PARE Kiosk System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        /* Thermal Printer Formatting (58mm Standard) */
        @media print {
            @page { margin: 0; size: 58mm auto; }
            body { margin: 0; padding: 0; background: #fff !important; }
            body * { visibility: hidden; }
            #print-receipt, #print-receipt * { 
                visibility: visible; 
                color: #000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            #print-receipt { 
                display: block !important;
                position: absolute; 
                left: 0; 
                top: 0; 
                width: 58mm; 
                padding: 1mm;
                font-family: 'Courier New', Courier, monospace;
                font-size: 10pt;
                font-weight: 700 !important; /* Force Bold for clarity */
                line-height: 1.1;
                -webkit-font-smoothing: none; /* Disable smoothing for sharp pixels */
                -moz-osx-font-smoothing: grayscale;
            }
            .no-print { display: none !important; }
        }

        /* Prevent text cursor on UI elements but keep inputs typable */
        input {
            user-select: auto !important;
            cursor: auto !important;
        }

        /* Toast Animations */
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(100%); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes fadeOut {
            from { opacity: 1; transform: translateX(0); }
            to { opacity: 0; transform: translateX(100%); }
        }
        .animate-slide-in { animation: slideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        .animate-fade-out { animation: fadeOut 0.3s ease-in forwards; }
    </style>
</head>
<body class="font-sans h-screen flex flex-col overflow-hidden select-none cursor-default text-blue-900" style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 40%, #93c5fd 100%) !important;">

    <header class="bg-white/40 backdrop-blur-md border-b border-white/50 p-6 shadow-sm flex items-center justify-between">
        <div class="flex items-center gap-4 select-none tracking-tight" onclick="handleLogoClick()">
            <img src="<?= BASE_PATH ?>/assets/img/logo.png?v=2" alt="PARE Logo" class="w-12 h-12 object-contain drop-shadow-sm">

        </div>
        <div class="bg-white/60 px-6 py-2 rounded-2xl border border-white/80 shadow-sm">
            <span id="loc-text" class="font-bold italic uppercase tracking-widest text-blue-800">Finding Location...</span>
        </div>
    </header>

    <!-- Global Toast Container -->
    <div id="toast-container" class="fixed top-6 right-6 z-[9999] flex flex-col gap-3 pointer-events-none w-full max-w-sm px-4"></div>

    <!-- Admin Unbind Modal -->
    <div id="unbind-modal" class="hidden flex fixed inset-0 bg-slate-900/40 z-50 items-center justify-center p-6 backdrop-blur-md">
        <div class="bg-white/80 backdrop-blur-xl p-8 rounded-3xl shadow-2xl max-w-sm w-full border border-white/50 border-t-8 border-t-red-500">
            <h2 class="text-2xl font-black mb-2 text-red-900 flex items-center gap-3">
                <i class="ph ph-warning-circle text-red-600"></i> Disconnect Kiosk
            </h2>
            <p class="text-red-900/70 mb-6 text-sm font-medium">Enter admin password to release this tablet from its currently assigned bus.</p>

            <div class="space-y-4">
                <div>
                    <input type="password" id="unbind-pin" class="w-full bg-white/70 border-2 border-white focus:border-red-400 rounded-xl px-4 py-3 font-bold focus:ring-4 focus:ring-red-400/20 outline-none transition text-red-900 placeholder-red-900/40 shadow-inner" placeholder="Admin password">
                </div>
                <div id="unbind-msg" class="hidden text-sm font-bold p-3 rounded-xl mt-2"></div>
                
                <div class="flex gap-3 mt-4 pt-2">
                    <button onclick="closeUnbindModal()" class="flex-1 bg-white/60 hover:bg-white border border-white/80 text-red-800 font-bold py-3.5 rounded-xl transition active:scale-95 shadow-sm">Cancel</button>
                    <button onclick="confirmUnbind()" class="flex-[2] bg-gradient-to-r from-red-600 to-red-500 hover:from-red-500 hover:to-red-400 text-white font-black py-3.5 rounded-xl shadow-lg shadow-red-500/30 transition active:scale-95">Unbind Device</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Setup Mode UI -->
    <main id="setup-ui" class="flex-grow flex items-center justify-center p-6 hidden">
        <div class="bg-white/50 backdrop-blur-xl p-10 rounded-3xl shadow-2xl border border-white/60 max-w-lg w-full">
            <h2 class="text-3xl font-black mb-2 text-blue-900 flex items-center gap-3">
                <i class="ph ph-device-tablet text-blue-600"></i> Setup Device
            </h2>
            <p class="text-blue-900/70 mb-6 font-medium">This tablet requires binding to a physical vehicle. Please select the assigned Bus and authenticate.</p>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-bold text-blue-900/80 mb-2 uppercase tracking-wide">Select Bus</label>
                    <select id="bus-select" class="w-full bg-white/60 border-2 border-white/80 rounded-xl px-4 py-3 font-bold focus:border-blue-400 outline-none text-blue-900 shadow-inner">
                        <option value="">Loading buses...</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-blue-900/80 mb-2 uppercase tracking-wide">Admin Password</label>
                    <input type="password" id="setup-pin" class="w-full bg-white/60 border-2 border-white/80 rounded-xl px-4 py-3 focus:border-blue-400 outline-none text-blue-900 shadow-inner placeholder-blue-900/40" placeholder="Enter admin password">
                </div>

                <div id="setup-msg" class="hidden text-sm font-bold p-3 rounded-xl mt-2"></div>

                <button onclick="bindDevice()" class="w-full bg-gradient-to-r from-blue-600 to-blue-500 hover:from-blue-500 hover:to-blue-400 text-white font-black py-4 rounded-xl shadow-lg shadow-blue-500/30 mt-4 transition-all active:scale-95">
                    Lock Device to Bus
                </button>
            </div>
        </div>
    </main>

    <!-- Application UI -->
    <main id="app-ui" class="flex-grow overflow-y-auto flex flex-col justify-start py-8 hidden">
        <div class="max-w-6xl mx-auto w-full p-6 md:p-10">
            
            <!-- Step 1: Category Selection -->
            <div id="step-1" class="w-full min-h-[60vh] flex flex-col justify-center">
                <div class="w-full grid grid-cols-1 md:grid-cols-3 gap-8">
                <button onclick="toVerify('regular')" class="bg-white/50 backdrop-blur-md p-10 rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-white/60 border-t-8 border-t-blue-500 flex flex-col items-center justify-center transition hover:scale-[1.02] hover:bg-white/60 active:scale-95 group">
                    <i class="ph ph-user text-6xl text-blue-600 mb-4 group-hover:scale-110 transition-transform duration-300"></i>
                    <h2 class="text-3xl font-black text-blue-900">Regular</h2>
                </button>
                <button onclick="toVerify('student')" class="bg-white/50 backdrop-blur-md p-10 rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-white/60 border-t-8 border-t-orange-400 flex flex-col items-center justify-center transition hover:scale-[1.02] hover:bg-white/60 active:scale-95 group">
                    <i class="ph ph-student text-6xl text-orange-500 mb-4 group-hover:scale-110 transition-transform duration-300"></i>
                    <h2 class="text-xl font-black text-blue-900 leading-tight">Student / Senior / PWD</h2>
                </button>
                <button onclick="toVerify('special')" class="bg-white/50 backdrop-blur-md p-10 rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-white/60 border-t-8 border-t-rose-400 flex flex-col items-center justify-center transition hover:scale-[1.02] hover:bg-white/60 active:scale-95 group">
                    <i class="ph ph-heart text-6xl text-rose-500 mb-4 group-hover:scale-110 transition-transform duration-300"></i>
                    <h2 class="text-xl font-black text-blue-900 leading-tight">Teachers & Healthcare Workers</h2>
                </button>
                </div>
            </div>

            <!-- Step 1.5: ID Verification -->
            <div id="step-verify" class="w-full max-w-lg mx-auto hidden">
                <div class="bg-white/60 backdrop-blur-xl p-10 rounded-3xl shadow-[0_15px_40px_-10px_rgba(0,0,0,0.1)] border border-white/80">
                    <h2 class="text-3xl font-black mb-2 text-blue-900">Enter Your ID Number</h2>
                    <p class="text-blue-900/70 mb-6 font-medium" id="verify-subtitle">Type your ID number to verify your discount.</p>
                    
                    <input type="text" id="id-input" placeholder="e.g. SUM2023-01996"
                           class="w-full text-center text-3xl font-mono font-bold bg-white/70 border-2 border-white rounded-2xl px-6 py-5 focus:outline-none focus:border-orange-400 focus:ring-4 focus:ring-orange-400/30 text-blue-900 placeholder-blue-900/30 tracking-widest mb-4 shadow-inner"
                           autocomplete="off" autofocus>
                    
                    <div id="verify-result" class="hidden mb-4 p-4 rounded-2xl text-left border bg-white/50 border-white/60"></div>
                    
                    <div class="flex gap-4">
                        <button onclick="goHome()" class="flex-1 bg-white/60 border border-white/80 text-blue-800 py-4 rounded-2xl text-lg font-bold transition hover:bg-white/80 shadow-sm">
                            ← Back
                        </button>
                        <button id="verify-btn" onclick="verifyId()" class="flex-[2] bg-gradient-to-r from-orange-500 to-amber-500 text-white py-4 rounded-2xl text-lg font-black shadow-lg shadow-orange-500/30 hover:from-orange-400 hover:to-amber-400 transition active:scale-95">
                            Verify & Continue
                        </button>
                    </div>
                </div>
            </div>

            <!-- Step 2: Destination Selection -->
            <div id="step-2" class="w-full hidden">
                <div class="relative w-full flex justify-center items-center mb-4 min-h-[50px]">
                    <button onclick="goHome()" class="absolute left-0 bg-white/50 backdrop-blur-md hover:bg-white/80 border border-white/60 text-blue-900 font-black px-5 py-2.5 rounded-xl shadow-sm transition active:scale-95 flex items-center gap-2">
                        <i class="ph ph-arrow-left text-xl"></i> Back
                    </button>
                    <h2 class="text-4xl font-black text-blue-900 text-center drop-shadow-sm">Where are you going?</h2>
                </div>
                <div id="origin-banner" class="flex items-center justify-center gap-2 mb-8">
                    <span class="text-blue-900/70 text-sm font-black uppercase tracking-widest drop-shadow-sm">Boarding from</span>
                    <span id="origin-station-label" class="bg-gradient-to-r from-blue-600 to-blue-500 text-white text-sm font-black px-4 py-1.5 rounded-full uppercase tracking-wide shadow-md">Detecting…</span>
                </div>
                <div id="dest-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"></div>
            </div>

            <!-- Step 3: Confirmation & Print -->
            <div id="step-3" class="w-full max-w-xl mx-auto hidden">
                <div class="bg-white/60 backdrop-blur-xl p-10 rounded-[2rem] shadow-[0_20px_50px_-10px_rgba(0,0,0,0.1)] border border-white/80 border-t-[12px] border-t-emerald-500 text-center">
                    <h2 class="text-4xl font-black mb-6 text-blue-900">Confirm Pass</h2>
                    <div id="passenger-banner" class="hidden bg-emerald-50/80 border border-emerald-200/60 rounded-2xl p-4 mb-4 text-left shadow-inner">
                        <p class="text-emerald-900 font-black text-lg" id="banner-name"></p>
                        <p class="text-emerald-700 text-sm font-bold" id="banner-status"></p>
                    </div>
                    <div class="text-left space-y-4 border-y border-white/60 py-6 my-6 relative">
                        <div class="absolute -left-10 w-6 h-6 rounded-full bg-blue-100 top-1/2 -translate-y-1/2 shadow-inner"></div>
                        <div class="absolute -right-10 w-6 h-6 rounded-full bg-blue-100 top-1/2 -translate-y-1/2 shadow-inner"></div>
                        
                        <p class="text-blue-900/50 font-black uppercase text-[10px] tracking-widest">Ticket Details</p>
                        <p class="text-lg font-medium text-blue-900/80">Type: <b id="sum-type" class="text-blue-900 font-black"></b></p>
                        <p class="text-lg font-medium text-blue-900/80">From: <b id="sum-origin" class="text-blue-900 font-black"></b></p>
                        <p class="text-lg font-medium text-blue-900/80">To: <b id="sum-dest" class="text-blue-900 font-black"></b></p>
                        <p class="text-4xl text-emerald-600 font-black mt-4 drop-shadow-sm">Total: <span id="sum-fare"></span></p>
                    </div>
                    <button id="print-btn" onclick="printPass()" class="w-full bg-gradient-to-r from-emerald-500 to-green-500 text-white py-6 rounded-2xl text-2xl font-black uppercase shadow-[0_10px_30px_rgba(16,185,129,0.3)] hover:from-emerald-400 hover:to-green-400 transition active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="ph ph-printer mr-2"></i> Print Pass
                    </button>
                    <button onclick="goHome()" class="w-full mt-4 bg-white border-2 border-slate-200 text-slate-500 py-4 rounded-2xl text-lg font-bold uppercase shadow-sm hover:border-red-200 hover:text-red-500 hover:bg-red-50 transition active:scale-95 flex items-center justify-center gap-2">
                        <i class="ph ph-x-circle text-xl"></i> Cancel Transaction
                    </button>
                </div>
            </div>

        </div>
    </main>

    <div id="print-receipt" class="hidden">
        <div style="text-align: center; border-bottom: 2px solid #000; padding-bottom: 5px; margin-bottom: 5px;">
            <h2 style="margin: 0; font-size: 16pt; font-weight: 900;">PARE SYSTEM</h2>
            <p style="font-size: 8pt; margin: 0; font-weight: 700;">RPMVFMPC MINI BUS</p>
            <p style="font-size: 10pt; font-weight: 900; margin-top: 2px;">BUS: <span id="rcpt-bus-id">--</span></p>
            <p style="font-size: 8pt; font-weight: 700; margin: 0;">DRIVER: <span id="rcpt-driver" style="text-transform: uppercase;">--</span></p>
        </div>
        <div style="padding: 5px 0; font-size: 11pt; border-bottom: 2px solid #000;">
            <p style="margin: 2px 0;">CODE: <span id="rcpt-code" style="font-weight: 900;">TKT-LOAD...</span></p>
            <p style="margin: 2px 0;">NAME: <span id="rcpt-pax" style="font-weight: 700; text-transform: uppercase;"></span></p>
            <p style="margin: 2px 0;">TYPE: <span id="rcpt-type" style="font-weight: 700;"></span></p>
            <p style="margin: 2px 0;">FROM: <span id="rcpt-origin" style="font-weight: 700;"></span></p>
            <p style="margin: 2px 0;">TO:   <span id="rcpt-dest" style="font-weight: 700;"></span></p>
            <div id="rcpt-status-container" style="margin-top: 5px; text-align: center; border: 1px dashed #000; padding: 2px; font-weight: 900; font-size: 8pt; display: none;">
                <span id="rcpt-status">--</span>
            </div>
            <div style="margin-top: 8px; text-align: center;">
                <p style="font-weight: 900; font-size: 16pt; margin: 0; border: 2px solid #000; padding: 2px;">FARE: <span id="rcpt-fare"></span></p>
            </div>
        </div>
        <div style="padding-top: 8px; text-align: center; font-size: 9pt;">
            <p style="margin: 0;">Issued: <span id="rcpt-time"><?php echo date('Y-m-d h:i A'); ?></span></p>
            <p style="font-weight: 900; margin-top: 5px; line-height: 1.2; text-transform: uppercase;">Present to driver for payment upon exit</p>
            <div style="margin-top: 5px; font-family: 'Libre Barcode 39', cursive, monospace; font-size: 24pt; color: #000;">
                ||||||||||||||||||||||
            </div>
            <p style="font-size: 7pt; font-weight: 700; margin-top: 5px;">Thank you for riding with us!</p>
            <p style="font-size: 6pt; margin: 2px 0;">For lost & found: 0968-438-0147</p>
            <p style="font-size: 6pt; margin: 0; font-style: italic;">Managed by: RPMVFMPC</p>
        </div>
    </div>

    <script>
        let currentLoc = { name: "", km: 0 };
        let ticket = { type: "", dest: "", fare: 0, specificType: null };
        let passenger = { id: null, name: null, verified: false, idNumber: null };
        let gpsActive = false;

        // ─── Toast System ───
        function showToast(title, message, type = 'error') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            
            const colors = {
                success: { bg: 'bg-emerald-500', icon: 'ph-check-circle', glow: 'shadow-emerald-500/40' },
                error:   { bg: 'bg-rose-500',    icon: 'ph-warning-circle', glow: 'shadow-rose-500/40' },
                info:    { bg: 'bg-blue-500',    icon: 'ph-info',           glow: 'shadow-blue-500/40' }
            }[type] || { bg: 'bg-blue-500', icon: 'ph-info', glow: 'shadow-blue-500/40' };

            toast.className = `min-w-[320px] max-w-md pointer-events-auto bg-white rounded-2xl shadow-xl overflow-hidden flex items-stretch border border-slate-100 animate-slide-in`;
            toast.innerHTML = `
                <div class="${colors.bg} w-2 flex-shrink-0"></div>
                <div class="p-4 flex gap-4 items-center w-full">
                    <div class="w-10 h-10 rounded-xl ${colors.bg}/10 flex items-center justify-center text-2xl ${colors.bg.replace('bg-','text-')} shrink-0">
                        <i class="ph ${colors.icon}"></i>
                    </div>
                    <div class="flex-1">
                        <p class="font-black text-slate-800 text-sm">${title}</p>
                        <p class="text-slate-500 text-xs font-medium">${message}</p>
                    </div>
                </div>
            `;
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.classList.replace('animate-slide-in', 'animate-fade-out');
                setTimeout(() => toast.remove(), 350);
            }, 5000);
        }

        // ─── Hidden Admin Unbind Logic ───
        let logoClicks = 0;
        let logoClickTimer = null;

        function handleLogoClick() {
            // Only allow unbinding if we are currently bound
            if (!localStorage.getItem('kiosk_bus_id')) return;

            logoClicks++;
            if (logoClicks >= 5) {
                document.getElementById('unbind-modal').classList.remove('hidden');
                document.getElementById('unbind-pin').value = '';
                document.getElementById('unbind-msg').classList.add('hidden');
                setTimeout(() => document.getElementById('unbind-pin').focus(), 100);
                logoClicks = 0; // reset
            }

            clearTimeout(logoClickTimer);
            logoClickTimer = setTimeout(() => { logoClicks = 0; }, 2000);
        }

        function closeUnbindModal() {
            document.getElementById('unbind-modal').classList.add('hidden');
            logoClicks = 0;
        }

        function confirmUnbind() {
            const pin = document.getElementById('unbind-pin').value;
            const msg = document.getElementById('unbind-msg');

            msg.classList.remove('hidden', 'bg-red-50', 'text-red-600', 'bg-green-50', 'text-green-600');

            if (!pin) {
                msg.classList.add('bg-red-50', 'text-red-600');
                msg.innerText = 'Admin password is required.';
                return;
            }

            msg.classList.add('bg-blue-50', 'text-blue-600');
            msg.innerText = 'Verifying...';

            fetch('verify_setup.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ password: pin })
            }).then(r => r.json()).then(data => {
                msg.className = 'text-sm font-bold p-3 rounded-xl mt-2'; // reset classes
                if(data.status === 'success') {
                    localStorage.removeItem('kiosk_bus_id');
                    msg.classList.add('bg-green-50', 'text-green-600');
                    msg.innerText = 'Device Unbound! Reloading...';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    msg.classList.add('bg-red-50', 'text-red-600');
                    msg.innerText = "Incorrect Admin Password";
                }
            }).catch(e => {
                msg.className = 'text-sm font-bold p-3 rounded-xl mt-2 bg-red-50 text-red-600';
                msg.innerText = 'Network Error.';
            });
        }

        // ─── GPS: watchPosition + movement threshold ──────────────────────
        // Strategy:
        //  1. watchPosition fires the moment the device detects movement — no
        //     fixed polling delay. On real GPS hardware this is ~1-2s.
        //  2. We only push to the server if the bus moved >5m OR >2s passed
        //     since the last push — avoids flooding the server when stationary.
        //  3. A safety-net setInterval re-triggers getCurrentPosition every 8s
        //     in case watchPosition stalls (common on Android Chrome when the
        //     screen dims or the browser throttles background tabs).

        let watchId       = null;   // ID returned by watchPosition
        let gpsLoopTimer  = null;   // Fallback polling timer
        let lastPushTime  = 0;
        let lastPushLat   = null;
        let lastPushLng   = null;
        let cachedTripId  = null;   // Cache trip_id so server skips a SELECT
        const MIN_PUSH_INTERVAL_MS = 2000;  // Never push faster than 2s
        const MIN_MOVE_METRES      = 5;     // Don't push if moved less than 5m

        // Haversine in JS (metres) — fast, no server round-trip needed
        function haversineM(lat1, lng1, lat2, lng2) {
            const R  = 6371000;
            const f1 = lat1 * Math.PI / 180, f2 = lat2 * Math.PI / 180;
            const df = (lat2 - lat1) * Math.PI / 180;
            const dl = (lng2 - lng1) * Math.PI / 180;
            const a  = Math.sin(df/2)**2 + Math.cos(f1)*Math.cos(f2)*Math.sin(dl/2)**2;
            return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        }

        function pushLocation(lat, lng, acc) {
            const now    = Date.now();
            const movedM = (lastPushLat !== null)
                ? haversineM(lastPushLat, lastPushLng, lat, lng) : Infinity;

            // Skip push: too soon AND hasn't moved enough
            if (now - lastPushTime < MIN_PUSH_INTERVAL_MS && movedM < MIN_MOVE_METRES) return;

            lastPushTime = now;
            lastPushLat  = lat;
            lastPushLng  = lng;

            fetch('push_location.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    lat, lng,
                    accuracy : acc,
                    trip_id  : cachedTripId,   // let server skip SELECT trips
                    bus_id   : localStorage.getItem('kiosk_bus_id')
                })
            })
            .then(r => r.json())
            .then(d => { if (d.trip_id) cachedTripId = d.trip_id; })
            .catch(() => {});
        }

        function onGPSFix(pos) {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            const acc = pos.coords.accuracy ? Math.round(pos.coords.accuracy) : null;

            // Reject IP-based garbage locations
            if (acc !== null && acc > 1000) {
                console.warn('Rejected inaccurate GPS fix: ±' + acc + 'm');
                return;
            }

            gpsActive = true;

            fetch(`match_km.php?lat=${lat}&lng=${lng}`)
                .then(r => r.json())
                .then(data => {
                    currentLoc = { name: data.station_name, km: data.km_marker };
                    document.getElementById('loc-text').innerText =
                        '📍 ' + data.station_name + (acc ? ' ±' + acc + 'm' : '');
                })
                .catch(() => {
                    document.getElementById('loc-text').innerText = '📍 GPS Active';
                });

            pushLocation(lat, lng, acc);
        }

        function initGPS() {
            if (!navigator.geolocation) {
                document.getElementById('loc-text').innerText = 'GPS not available';
                return;
            }
            document.getElementById('loc-text').innerText = '⏳ Acquiring GPS…';

            // Clear any previous watchers / timers
            if (watchId !== null)    navigator.geolocation.clearWatch(watchId);
            if (gpsLoopTimer)        clearInterval(gpsLoopTimer);

            const gpsOpts = {
                enableHighAccuracy: true,
                maximumAge        : 5000,   // accept cached fix up to 5s old
                timeout           : 15000
            };

            // ── Primary: watchPosition fires on every device movement ──────
            watchId = navigator.geolocation.watchPosition(
                pos  => onGPSFix(pos),
                err  => {
                    const msgs = { 1: 'Location denied', 2: 'GPS unavailable', 3: 'GPS timeout' };
                    if (Date.now() - lastPushTime > 20000) {
                        document.getElementById('loc-text').innerText = '⚠️ ' + (msgs[err.code] || 'GPS error');
                    }
                },
                gpsOpts
            );

            // ── Fallback: every 8s force a fresh fix in case watchPosition stalls ─
            // (Android Chrome throttles watchPosition in background tabs)
            gpsLoopTimer = setInterval(() => {
                if (Date.now() - lastPushTime > 8000) {
                    navigator.geolocation.getCurrentPosition(
                        pos => onGPSFix(pos),
                        ()  => {},   // silent — watchPosition may still be alive
                        { ...gpsOpts, maximumAge: 0 }  // force fresh fix
                    );
                }
            }, 8000);
        }


        // ─── Setup Logic ───
        function initDevice() {
            const boundId = localStorage.getItem('kiosk_bus_id');
            if (!boundId) {
                // Show Setup
                document.getElementById('setup-ui').classList.remove('hidden');
                document.getElementById('app-ui').classList.add('hidden');
                
                // Fetch Buses
                fetch('get_buses.php').then(r => r.json()).then(data => {
                    if(data.status === 'success') {
                        const sel = document.getElementById('bus-select');
                        sel.innerHTML = '<option value="">-- Select Bus --</option>';
                        data.buses.forEach(b => {
                            sel.innerHTML += `<option value="${b.id}">${b.body_number} (${b.plate_number})</option>`;
                        });
                    }
                });
            } else {
                // Device is bound, show UI and start GPS
                document.getElementById('setup-ui').classList.add('hidden');
                document.getElementById('app-ui').classList.remove('hidden');
                document.getElementById('rcpt-bus-id').innerText = boundId;
                initGPS();
            }
        }

        function bindDevice() {
            const busId = document.getElementById('bus-select').value;
            const pin = document.getElementById('setup-pin').value;
            const msg = document.getElementById('setup-msg');

            msg.classList.remove('hidden', 'bg-red-50', 'text-red-600', 'bg-green-50', 'text-green-600');

            if(!busId || !pin) {
                msg.classList.add('bg-red-50', 'text-red-600');
                msg.innerText = 'Please select a bus and enter password.';
                return;
            }

            msg.innerText = 'Verifying...';

            fetch('verify_setup.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ password: pin })
            }).then(r => r.json()).then(data => {
                if(data.status === 'success') {
                    localStorage.setItem('kiosk_bus_id', busId);
                    msg.classList.add('bg-green-50', 'text-green-600');
                    msg.innerText = 'Device Locked! Initializing...';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    msg.classList.add('bg-red-50', 'text-red-600');
                    msg.innerText = data.message;
                }
            }).catch(e => {
                msg.classList.add('bg-red-50', 'text-red-600');
                msg.innerText = 'Network Error.';
            });
        }

        window.addEventListener('DOMContentLoaded', initDevice);

        // ─── Utility: hide all steps ───
        function hideAllSteps() {
            ['step-1','step-verify','step-2','step-3'].forEach(id => {
                document.getElementById(id).classList.add('hidden');
            });
        }

        function goHome() { location.reload(); }

        // ─── Step 1 → Verify (new) ───
        function toVerify(type) {
            ticket.type = type;
            ticket.specificType = null;
            // Reset passenger state
            passenger = { id: null, name: null, verified: false, idNumber: null };

            if (type === 'regular') {
                toStep2('regular');
                return;
            }

            // For discounted passengers, show ID entry
            const subtitle = document.getElementById('verify-subtitle');
            const verifyBtn = document.getElementById('verify-btn');

            subtitle.textContent = 'Required: Enter your ID number to claim your discount.';
            verifyBtn.textContent = 'Verify & Continue';

            // Reset UI
            document.getElementById('id-input').value = '';
            document.getElementById('verify-result').classList.add('hidden');

            hideAllSteps();
            document.getElementById('step-verify').classList.remove('hidden');
            document.getElementById('id-input').focus();
        }

        // ─── Verify ID number ───
        function verifyId() {
            const idNumber = document.getElementById('id-input').value.trim();
            const resultDiv = document.getElementById('verify-result');
            const verifyBtn = document.getElementById('verify-btn');

            if (!idNumber) {
                // Clear any previous found state
                passenger.id = null;
                passenger.name = null;
                passenger.verified = false;
                passenger.idNumber = null;

                // For discounted passengers, they MUST enter an ID
                if (ticket.type !== 'regular') {
                    resultDiv.className = 'mb-4 p-4 rounded-2xl text-left bg-red-50 border border-red-200';
                    resultDiv.innerHTML = '<p class="text-red-700 font-bold">⚠️ Please enter your ID number.</p>';
                    resultDiv.classList.remove('hidden');
                    return;
                }
                // Regular passengers without ID → just proceed
                toStep2(ticket.type);
                return;
            }

            // Store the ID number for logging
            passenger.idNumber = idNumber;

            verifyBtn.disabled = true;
            verifyBtn.textContent = 'Checking...';

            fetch(`verify_discount.php?id_number=${encodeURIComponent(idNumber)}`)
                .then(r => r.json())
                .then(data => {
                    verifyBtn.disabled = false;

                    if (data.found) {
                        // Passenger found in the system
                        passenger.id = data.passenger_id;
                        passenger.name = data.name;
                        passenger.verified = data.verified;

                        if (data.verified) {
                            // ✅ Registered AND has discount
                            // Determine actual category based on their registered ID
                            const dt = data.discount_type.toLowerCase();
                            let actualCategory = 'regular';
                            if (dt === 'student' || dt === 'pwd' || dt === 'senior') {
                                actualCategory = 'student';
                            } else if (dt === 'special' || dt === 'teacher' || dt === 'nurse') {
                                actualCategory = 'special';
                            }
                            
                            let warningHtml = "";
                            if (ticket.type !== actualCategory && ticket.type !== 'regular') {
                                let clickedText = ticket.type === 'special' ? 'Teacher/Healthcare Worker' : 'Student/SR/PWD';
                                let actualText = actualCategory === 'special' ? 'Teacher/Healthcare Worker' : 'Student/SR/PWD';
                                warningHtml = `
                                    <div class="mt-3 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 leading-snug">
                                        <b class="text-red-800">⚠️ Category Auto-Corrected:</b><br/> 
                                        You selected <b>${clickedText}</b>, but this ID is registered as <b>${actualText}</b>. We have updated your ticket.
                                    </div>
                                `;
                            }
                            
                            // Auto-correct ticket state
                            ticket.type = actualCategory;
                            ticket.specificType = data.discount_type.charAt(0).toUpperCase() + data.discount_type.slice(1);
                            
                            resultDiv.className = 'mb-4 p-4 rounded-2xl text-left bg-green-50 border border-green-200';
                            resultDiv.innerHTML = `
                                <p class="text-green-800 font-bold text-lg">✅ Verified</p>
                                <p class="text-green-700">Name: <b>${data.name}</b></p>
                                <p class="text-green-600 text-sm font-semibold mb-1">Discount: ${data.discount_type.toUpperCase()}</p>
                                ${warningHtml}
                            `;
                        } else {
                            // Found but no discount type on file
                            ticket.type = 'regular'; // Force regular fare if they try to use a non-discount account
                            ticket.specificType = null;
                            
                            resultDiv.className = 'mb-4 p-4 rounded-2xl text-left bg-blue-50 border border-blue-200';
                            resultDiv.innerHTML = `
                                <p class="text-blue-800 font-bold">👤 Account Found</p>
                                <p class="text-blue-700">Name: <b>${data.name}</b></p>
                                <p class="text-blue-600 text-sm">Ticket will be linked to your account.</p>
                            `;
                        }
                        verifyBtn.textContent = 'Continue →';
                        verifyBtn.onclick = function() { toStep2(ticket.type); };
                    } else {
                        // ID not found — but still allow discount (log as unverified)
                        passenger.verified = false;
                        passenger.name = null;
                        passenger.id = null;

                        if (ticket.type !== 'regular') {
                            // Discounted but unregistered — BLOCK discount
                            resultDiv.className = 'mb-4 p-4 rounded-2xl text-left bg-red-50 border border-red-200';
                            resultDiv.innerHTML = `
                                <p class="text-red-800 font-bold">⚠️ ID Not Registered</p>
                                <p class="text-red-700 text-sm">You must have a registered PARE account to claim this discount.</p>
                                <p class="text-red-600 text-xs mt-1">Please register online or proceed as a regular passenger.</p>
                            `;
                            verifyBtn.textContent = 'Proceed as Regular →';
                            verifyBtn.onclick = function() { 
                                ticket.type = 'regular';
                                toStep2('regular'); 
                            };
                        } else {
                            resultDiv.className = 'mb-4 p-4 rounded-2xl text-left bg-slate-50 border border-slate-200';
                            resultDiv.innerHTML = `
                                <p class="text-slate-700 font-bold">ID not found in the system.</p>
                                <p class="text-slate-500 text-sm">You can still proceed as a regular passenger.</p>
                            `;
                            verifyBtn.textContent = 'Continue →';
                            verifyBtn.onclick = function() { toStep2(ticket.type); };
                        }
                    }
                    resultDiv.classList.remove('hidden');
                })
                .catch(err => {
                    verifyBtn.disabled = false;
                    verifyBtn.textContent = ticket.type === 'regular' ? 'Link & Continue' : 'Verify & Continue';
                    resultDiv.className = 'mb-4 p-4 rounded-2xl text-left bg-red-50 border border-red-200';
                    resultDiv.innerHTML = '<p class="text-red-700 font-bold">Network error. Please try again.</p>';
                    resultDiv.classList.remove('hidden');
                });
        }

        // Listen for ID input changes to update button text
        document.addEventListener('DOMContentLoaded', () => {
            const idInput = document.getElementById('id-input');
            const verifyBtn = document.getElementById('verify-btn');
            
            idInput?.addEventListener('input', e => {
                const val = e.target.value.trim();
                const resultDiv = document.getElementById('verify-result');
                
                // Hide results if they start typing again
                if (val && resultDiv) resultDiv.classList.add('hidden');

                if (ticket.type === 'regular') {
                    verifyBtn.textContent = val ? 'Link & Continue' : 'Continue';
                } else {
                    verifyBtn.textContent = val ? 'Verify & Continue' : 'Verify & Continue';
                }
            });

            idInput?.addEventListener('keydown', e => {
                if (e.key === 'Enter') verifyId();
            });
        });

        // ─── Step 2: Destination Selection (UNCHANGED fare logic) ───
        function toStep2(type) {
            ticket.type = type;
            hideAllSteps();
            document.getElementById('step-2').classList.remove('hidden');
            
            fetch(`get_destinations.php?current_km=${currentLoc.km}&bus_id=${localStorage.getItem('kiosk_bus_id')}&_t=${Date.now()}`)
                .then(r => r.json())
                .then(response => {
                    // Use the direction-aware origin from the backend
                    if (response.origin) {
                        currentLoc.name = response.origin;
                    }
                    // Show the origin station label on Step 2
                    const originLabel = document.getElementById('origin-station-label');
                    if (originLabel) {
                        originLabel.textContent = currentLoc.name || 'Current Location';
                    }

                    const data = response.stations || response;
                    const grid = document.getElementById('dest-grid');
                    grid.innerHTML = data.map(s => {
                        let fare = s.regular_fare;
                        let typeLabel = "Regular";
                        if (ticket.type === 'student') { fare = s.student_fare; typeLabel = ticket.specificType || "Student/SR/PWD"; }
                        else if (ticket.type === 'special') { fare = s.special_fare; typeLabel = ticket.specificType || "Teacher/Healthcare Worker"; }

                        return `
                        <button onclick="toStep3('${s.station_name}', ${fare}, '${typeLabel}')" class="bg-white/60 backdrop-blur-md p-8 rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-white/80 hover:bg-white/80 transition text-center flex flex-col items-center">
                            <span class="block text-lg font-black mb-2 text-blue-900 drop-shadow-sm">${s.station_name}</span>
                            <span class="text-blue-900 font-black text-xl bg-white/70 shadow-inner border border-white/50 px-4 py-1 rounded-full">₱ ${parseFloat(fare).toFixed(2)}</span>
                        </button>
                        `;
                    }).join('');
                });
        }

        // ─── Helper: get the current origin station name ───
        function getOriginName() {
            // Primary: use the GPS-matched station name
            if (currentLoc.name) return currentLoc.name;
            // Fallback: read from the header location display
            const locText = document.getElementById('loc-text')?.innerText || '';
            const cleaned = locText.replace(/^📍\s*/, '').replace(/\s*±\d+m$/i, '').trim();
            if (cleaned && !cleaned.includes('GPS') && !cleaned.includes('Finding')) return cleaned;
            // Last resort
            return 'Current Location';
        }

        // ─── Step 3: Confirm (now shows passenger info if verified) ───
        function toStep3(name, fare, typeLabel) {
            ticket.dest = name;
            ticket.fare = fare;
            ticket.typeLabel = typeLabel;
            ticket.origin = getOriginName();

            // Update screen summary
            document.getElementById('sum-type').innerText = typeLabel;
            document.getElementById('sum-origin').innerText = ticket.origin;
            document.getElementById('sum-dest').innerText = name;
            document.getElementById('sum-fare').innerText = "₱ " + parseFloat(fare).toFixed(2);
            
            // Update hidden receipt data
            document.getElementById('rcpt-type').innerText = typeLabel.toUpperCase();
            document.getElementById('rcpt-origin').innerText = ticket.origin;
            document.getElementById('rcpt-dest').innerText = name;
            document.getElementById('rcpt-fare').innerText = "₱ " + parseFloat(fare).toFixed(2);

            let statusContainer = document.getElementById('rcpt-status-container');
            if (passenger.verified && passenger.name) {
                document.getElementById('rcpt-pax').innerText = passenger.name;
                statusContainer.style.display = 'block';
                document.getElementById('rcpt-status').innerText = '✅ ID SYSTEM VERIFIED';
            } else {
                document.getElementById('rcpt-pax').innerText = 'WALK-IN';
                if (ticket.type !== 'regular') {
                    statusContainer.style.display = 'block';
                    document.getElementById('rcpt-status').innerText = '⚠️ CHECK PHYSICAL ID';
                } else {
                    statusContainer.style.display = 'none';
                }
            }

            // Update live timestamp for the ticket
            const now = new Date();
            const dateStr = now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
            const timeStr = now.toLocaleTimeString('en-US', {hour: '2-digit', minute:'2-digit'});
            document.getElementById('rcpt-time').innerText = dateStr + ' ' + timeStr;

            // Show passenger banner if identified
            const banner = document.getElementById('passenger-banner');
            if (passenger.name) {
                document.getElementById('banner-name').textContent = passenger.name;
                document.getElementById('banner-status').textContent = passenger.verified 
                    ? '✅ Discount Verified' 
                    : '👤 Account Linked';
                banner.classList.remove('hidden');
            } else if (passenger.idNumber && ticket.type !== 'regular') {
                document.getElementById('banner-name').textContent = 'ID: ' + passenger.idNumber;
                document.getElementById('banner-status').textContent = '⚠️ Unverified — Discount Applied';
                banner.className = 'bg-orange-50 border border-orange-200 rounded-2xl p-4 mb-4 text-left';
                document.getElementById('banner-name').className = 'text-orange-800 font-bold';
                document.getElementById('banner-status').className = 'text-orange-600 text-sm';
                banner.classList.remove('hidden');
            } else {
                banner.classList.add('hidden');
            }

            hideAllSteps();
            document.getElementById('step-3').classList.remove('hidden');
        }

        // ─── Print Pass (now sends passenger data too) ───
        function printPass() {
            const btn = document.getElementById('print-btn');
            const originalText = btn.innerText;
            btn.disabled = true;
            btn.innerText = "🖨️ Printing Ticket...";

            fetch('process_ticket.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    type: ticket.typeLabel,
                    origin: ticket.origin,
                    dest: ticket.dest,
                    fare: ticket.fare,
                    passenger_id: passenger.id || null,
                    passenger_name: passenger.name || null,
                    passenger_id_number: passenger.idNumber || null,
                    discount_verified: passenger.verified || false,
                    bus_id: localStorage.getItem('kiosk_bus_id')
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    document.getElementById('rcpt-code').innerText = data.ticket_code;
                    document.getElementById('rcpt-bus-id').innerText = data.bus_number || data.bus_id || '--';
                    document.getElementById('rcpt-driver').innerText = data.driver_name || '--';
                    window.print();
                    setTimeout(() => { location.reload(); }, 3000);
                } else {
                    showToast("Error", data.message, 'error');
                    btn.disabled = false;
                    btn.innerText = originalText;
                }
            })
            .catch(err => {
                showToast("Network Error", "Could not connect to the server.", 'error');
                btn.disabled = false;
                btn.innerText = originalText;
            });
        }
    </script>
</body>
</html>
