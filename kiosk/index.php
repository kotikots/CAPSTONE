<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PARE Kiosk System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        /* Thermal Printer Formatting */
        @media print {
            body * { visibility: hidden; }
            #print-receipt, #print-receipt * { visibility: visible; }
            #print-receipt { 
                display: block !important;
                position: absolute; 
                left: 0; 
                top: 0; 
                width: 58mm; /* Standard narrow thermal paper */
                font-family: 'Courier New', Courier, monospace;
            }
        }
    </style>
</head>
<body class="bg-slate-50 font-sans h-screen flex flex-col overflow-hidden">

    <header class="bg-blue-600 p-6 shadow-xl text-white flex items-center justify-between">
        <div class="flex items-center gap-4">
            <i class="ph ph-bus text-4xl"></i>
            <h1 class="text-3xl font-black italic">PARE</h1>
        </div>
        <div class="bg-blue-700 px-6 py-2 rounded-2xl border border-blue-400/30">
            <span id="loc-text" class="font-bold italic uppercase tracking-widest">Finding Location...</span>
        </div>
    </header>

    <main class="flex-grow flex items-center justify-center p-6">
        <div id="step-1" class="w-full max-w-6xl grid grid-cols-3 gap-8">
            <button onclick="toStep2('regular')" class="bg-white p-10 rounded-3xl shadow-xl border-t-8 border-blue-600">
                <i class="ph ph-user text-6xl text-blue-600 mb-4"></i>
                <h2 class="text-3xl font-bold">Regular</h2>
            </button>
            <button onclick="toStep2('student')" class="bg-white p-10 rounded-3xl shadow-xl border-t-8 border-yellow-500 flex flex-col items-center justify-center">
                <i class="ph ph-student text-6xl text-yellow-600 mb-4"></i>
                <h2 class="text-xl font-bold leading-tight">Student / SR / PWD</h2>
            </button>
            <button onclick="toStep2('special')" class="bg-white p-10 rounded-3xl shadow-xl border-t-8 border-pink-500 flex flex-col items-center justify-center">
                <i class="ph ph-heart text-6xl text-pink-600 mb-4"></i>
                <h2 class="text-xl font-bold leading-tight">Teachers & Nurses</h2>
            </button>
        </div>

        <div id="step-2" class="w-full max-w-5xl hidden">
            <h2 class="text-4xl font-black mb-8 text-slate-800">Where are you going?</h2>
            <div id="dest-grid" class="grid grid-cols-3 gap-6"></div>
        </div>

        <div id="step-3" class="w-full max-w-md hidden">
            <div class="bg-white p-10 rounded-3xl shadow-2xl border-b-8 border-green-600 text-center">
                <h2 class="text-3xl font-black mb-6">Confirm Pass</h2>
                <div class="text-left space-y-4 border-y py-6 my-6">
                    <p>Type: <b id="sum-type"></b></p>
                    <p>To: <b id="sum-dest"></b></p>
                    <p class="text-2xl text-green-600">Total: <b id="sum-fare"></b></p>
                </div>
                <button id="print-btn" onclick="printPass()" class="w-full bg-green-600 text-white py-6 rounded-2xl text-2xl font-black uppercase shadow-lg disabled:bg-slate-300">
                    Print Pass
                </button>
            </div>
        </div>
    </main>

    <div id="print-receipt" class="hidden">
        <div style="text-align: center; border-bottom: 1px dashed #000; padding-bottom: 10px;">
            <h2 style="margin: 0;">PARE SYSTEM</h2>
            <p style="font-size: 10px; margin: 0;">RPMVFMPC E-JEEP</p>
        </div>
        <div style="padding: 10px 0; font-size: 12px; line-height: 1.5;">
            <p>TYPE: <span id="rcpt-type"></span></p>
            <p>FROM: <span id="rcpt-origin"></span></p>
            <p>TO: <span id="rcpt-dest"></span></p>
            <p style="font-weight: bold; font-size: 16px;">FARE: <span id="rcpt-fare"></span></p>
        </div>
        <div style="border-top: 1px dashed #000; padding-top: 10px; text-align: center; font-size: 10px;">
            <p>Issued: <?php echo date('Y-m-d H:i'); ?></p>
            <p style="font-weight: bold; margin-top: 5px; line-height: 1.4;">Please present this ticket<br>and pay fare to the driver.</p>
        </div>
    </div>

    <script>
        let currentLoc = { name: "", km: 0 };
        let ticket = { type: "", dest: "", fare: 0 };
        let gpsActive = false;

        // ─── GPS: dual purpose ────────────────────────────────────────────
        // 1. Match nearest stop → show in header + use as boarding point
        // 2. Push raw coords → update_location.php (powers the live map)
        // ─────────────────────────────────────────────────────────────────
        function initGPS() {
            if (!navigator.geolocation) {
                document.getElementById('loc-text').innerText = 'GPS not available';
                return;
            }
            document.getElementById('loc-text').innerText = '⏳ Acquiring GPS…';

            navigator.geolocation.watchPosition(
                pos => {
                    const lat = pos.coords.latitude;
                    const lng = pos.coords.longitude;
                    const spd = pos.coords.speed ? (pos.coords.speed * 3.6).toFixed(1) : 0;
                    const acc = pos.coords.accuracy ? pos.coords.accuracy.toFixed(0) : null;
                    gpsActive = true;

                    // 1️⃣ Match nearest station for boarding point
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

                    // 2️⃣ Push live coordinates → powers the passenger live map
                    fetch('push_location.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ lat, lng, speed: spd, accuracy: acc })
                    }).catch(() => {}); // Silent fail — non-critical
                },
                err => {
                    const msgs = {
                        1: 'Location denied',
                        2: 'GPS unavailable',
                        3: 'GPS timeout'
                    };
                    document.getElementById('loc-text').innerText =
                        '⚠️ ' + (msgs[err.code] || 'GPS error');
                },
                { enableHighAccuracy: true, maximumAge: 4000, timeout: 15000 }
            );
        }

        // Auto-start GPS on load
        window.addEventListener('DOMContentLoaded', initGPS);

        function toStep2(type) {
            ticket.type = type;
            document.getElementById('step-1').classList.add('hidden');
            document.getElementById('step-2').classList.remove('hidden');
            
            fetch(`get_destinations.php?current_km=${currentLoc.km}`)
                .then(r => r.json())
                .then(data => {
                    const grid = document.getElementById('dest-grid');
                    grid.innerHTML = data.map(s => {
                        let fare = s.regular_fare;
                        let typeLabel = "Regular";
                        if (ticket.type === 'student') { fare = s.student_fare; typeLabel = "Student/SR/PWD"; }
                        else if (ticket.type === 'special') { fare = s.special_fare; typeLabel = "Teacher/Nurse"; }

                        return `
                        <button onclick="toStep3('${s.station_name}', ${fare}, '${typeLabel}')" class="bg-white p-8 rounded-2xl shadow-md border-2 border-transparent hover:border-blue-600 text-center flex flex-col items-center">
                            <span class="block text-lg font-black mb-2">${s.station_name}</span>
                            <span class="text-blue-600 font-black text-xl bg-blue-50 px-4 py-1 rounded-full">₱ ${parseFloat(fare).toFixed(2)}</span>
                        </button>
                        `;
                    }).join('');
                });
        }

        function toStep3(name, fare, typeLabel) {
            ticket.dest = name;
            ticket.fare = fare;
            ticket.typeLabel = typeLabel;

            // Update screen summary
            document.getElementById('sum-type').innerText = typeLabel;
            document.getElementById('sum-dest').innerText = name;
            document.getElementById('sum-fare').innerText = "₱ " + parseFloat(fare).toFixed(2);
            
            // Update hidden receipt data
            document.getElementById('rcpt-type').innerText = typeLabel;
            document.getElementById('rcpt-origin').innerText = currentLoc.name;
            document.getElementById('rcpt-dest').innerText = name;
            document.getElementById('rcpt-fare').innerText = "₱ " + parseFloat(fare).toFixed(2);

            document.getElementById('step-2').classList.add('hidden');
            document.getElementById('step-3').classList.remove('hidden');
        }

        function printPass() {
            const btn = document.getElementById('print-btn');
            btn.disabled = true;
            btn.innerText = "Processing...";

            // 1. Save Transaction to 'pare' database
            fetch('process_ticket.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    type: ticket.typeLabel,
                    origin: currentLoc.name,
                    dest: ticket.dest,
                    fare: ticket.fare
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    // 2. Trigger the browser's print dialog
                    window.print();
                    
                    // 3. Reset Kiosk
                    setTimeout(() => { location.reload(); }, 2000);
                } else {
                    alert("Error saving transaction: " + data.message);
                    btn.disabled = false;
                }
            });
        }
    </script>
</body>
</html>