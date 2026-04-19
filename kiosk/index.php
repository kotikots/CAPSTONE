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
    </style>
</head>
<body class="bg-slate-50 font-sans h-screen flex flex-col overflow-hidden">

    <header class="bg-blue-600 p-6 shadow-xl text-white flex items-center justify-between">
        <div class="flex items-center gap-4 select-none tracking-tight" onclick="handleLogoClick()">
            <i class="ph ph-bus text-4xl"></i>
            <h1 class="text-3xl font-black italic">PARE</h1>
        </div>
        <div class="bg-blue-700 px-6 py-2 rounded-2xl border border-blue-400/30">
            <span id="loc-text" class="font-bold italic uppercase tracking-widest">Finding Location...</span>
        </div>
        </div>
    </header>

    <!-- Admin Unbind Modal -->
    <div id="unbind-modal" class="hidden flex fixed inset-0 bg-slate-900/60 z-50 items-center justify-center p-6 backdrop-blur-sm">
        <div class="bg-white p-8 rounded-3xl shadow-2xl max-w-sm w-full border-t-8 border-red-600">
            <h2 class="text-2xl font-black mb-2 text-slate-800 flex items-center gap-3">
                <i class="ph ph-warning-circle text-red-600"></i> Disconnect Kiosk
            </h2>
            <p class="text-slate-500 mb-6 text-sm font-medium">Enter admin password to release this tablet from its currently assigned bus.</p>

            <div class="space-y-4">
                <div>
                    <input type="password" id="unbind-pin" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 focus:border-red-500 font-bold focus:ring-2 focus:ring-red-500/20 outline-none transition" placeholder="Admin password">
                </div>
                <div id="unbind-msg" class="hidden text-sm font-bold p-3 rounded-xl mt-2"></div>
                
                <div class="flex gap-3 mt-4 pt-2">
                    <button onclick="closeUnbindModal()" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold py-3.5 rounded-xl transition active:scale-95">Cancel</button>
                    <button onclick="confirmUnbind()" class="flex-[2] bg-red-600 hover:bg-red-500 text-white font-black py-3.5 rounded-xl shadow-lg shadow-red-500/20 transition active:scale-95">Unbind Device</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Setup Mode UI -->
    <main id="setup-ui" class="flex-grow flex items-center justify-center p-6 hidden">
        <div class="bg-white p-10 rounded-3xl shadow-2xl border-t-8 border-blue-600 max-w-lg w-full">
            <h2 class="text-3xl font-black mb-2 text-slate-800 flex items-center gap-3">
                <i class="ph ph-device-tablet text-blue-600"></i> Setup Device
            </h2>
            <p class="text-slate-500 mb-6 font-medium">This tablet requires binding to a physical vehicle. Please select the assigned Bus and authenticate.</p>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-bold text-slate-600 mb-2">Select Bus</label>
                    <select id="bus-select" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 font-bold focus:border-blue-500 outline-none">
                        <option value="">Loading buses...</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-slate-600 mb-2">Admin Password</label>
                    <input type="password" id="setup-pin" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 focus:border-blue-500 outline-none" placeholder="Enter admin password">
                </div>

                <div id="setup-msg" class="hidden text-sm font-bold p-3 rounded-xl mt-2"></div>

                <button onclick="bindDevice()" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-4 rounded-xl shadow-lg mt-4 transition-colors">
                    Lock Device to Bus
                </button>
            </div>
        </div>
    </main>

    <!-- Application UI -->
    <main id="app-ui" class="flex-grow overflow-y-auto hidden">
        <div class="max-w-6xl mx-auto p-6 md:p-16">
            
            <!-- Step 1: Category Selection -->
            <div id="step-1" class="w-full grid grid-cols-1 md:grid-cols-3 gap-8">
                <button onclick="toVerify('regular')" class="bg-white p-10 rounded-3xl shadow-xl border-t-8 border-blue-600 flex flex-col items-center justify-center transition hover:scale-[1.02] active:scale-95">
                    <i class="ph ph-user text-6xl text-blue-600 mb-4"></i>
                    <h2 class="text-3xl font-bold">Regular</h2>
                </button>
                <button onclick="toVerify('student')" class="bg-white p-10 rounded-3xl shadow-xl border-t-8 border-orange-500 flex flex-col items-center justify-center transition hover:scale-[1.02] active:scale-95">
                    <i class="ph ph-student text-6xl text-orange-600 mb-4"></i>
                    <h2 class="text-xl font-bold leading-tight">Student / SR / PWD</h2>
                </button>
                <button onclick="toVerify('special')" class="bg-white p-10 rounded-3xl shadow-xl border-t-8 border-red-500 flex flex-col items-center justify-center transition hover:scale-[1.02] active:scale-95">
                    <i class="ph ph-heart text-6xl text-red-600 mb-4"></i>
                    <h2 class="text-xl font-bold leading-tight">Teachers & Nurses</h2>
                </button>
            </div>

            <!-- Step 1.5: ID Verification -->
            <div id="step-verify" class="w-full max-w-lg mx-auto hidden">
                <div class="bg-white p-10 rounded-3xl shadow-2xl border-t-8 border-orange-500">
                    <h2 class="text-3xl font-black mb-2 text-slate-800">Enter Your ID Number</h2>
                    <p class="text-slate-500 mb-6" id="verify-subtitle">Type your ID number to verify your discount.</p>
                    
                    <input type="text" id="id-input" placeholder="e.g. SUM2023-01996"
                           class="w-full text-center text-3xl font-mono font-bold border-2 border-slate-200 rounded-2xl px-6 py-5 focus:outline-none focus:border-orange-500 focus:ring-4 focus:ring-orange-500/20 tracking-widest mb-4"
                           autocomplete="off" autofocus>
                    
                    <div id="verify-result" class="hidden mb-4 p-4 rounded-2xl text-left"></div>
                    
                    <div class="flex gap-4">
                        <button onclick="goHome()" class="flex-1 bg-slate-200 text-slate-600 py-4 rounded-2xl text-lg font-bold transition hover:bg-slate-300">
                            ← Back
                        </button>
                        <button id="verify-btn" onclick="verifyId()" class="flex-[2] bg-orange-600 text-white py-4 rounded-2xl text-lg font-black shadow-lg hover:bg-orange-500 transition active:scale-95">
                            Verify & Continue
                        </button>
                        <button id="skip-btn" onclick="skipVerification()" class="hidden flex-1 bg-slate-100 text-slate-500 py-4 rounded-2xl text-sm font-bold transition hover:bg-slate-200">
                            Skip →
                        </button>
                    </div>
                </div>
            </div>

            <!-- Step 2: Destination Selection -->
            <div id="step-2" class="w-full hidden">
                <h2 class="text-4xl font-black mb-8 text-slate-800 text-center">Where are you going?</h2>
                <div id="dest-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"></div>
            </div>

            <!-- Step 3: Confirmation & Print -->
            <div id="step-3" class="w-full max-w-xl mx-auto hidden">
                <div class="bg-white p-10 rounded-4xl shadow-2xl border-b-8 border-green-600 text-center">
                    <h2 class="text-3xl font-black mb-6">Confirm Pass</h2>
                    <div id="passenger-banner" class="hidden bg-green-50 border border-green-200 rounded-2xl p-4 mb-4 text-left">
                        <p class="text-green-800 font-bold" id="banner-name"></p>
                        <p class="text-green-600 text-sm" id="banner-status"></p>
                    </div>
                    <div class="text-left space-y-4 border-y py-6 my-6">
                        <p class="text-slate-500 font-bold uppercase text-xs tracking-widest">Ticket Details</p>
                        <p class="text-lg">Type: <b id="sum-type" class="text-slate-800"></b></p>
                        <p class="text-lg">From: <b id="sum-origin" class="text-slate-800"></b></p>
                        <p class="text-lg">To: <b id="sum-dest" class="text-slate-800"></b></p>
                        <p class="text-3xl text-green-600 font-black mt-2">Total: <span id="sum-fare"></span></p>
                    </div>
                    <button id="print-btn" onclick="printPass()" class="w-full bg-green-600 text-white py-6 rounded-2xl text-2xl font-black uppercase shadow-xl hover:bg-green-500 transition active:scale-95 disabled:bg-slate-300">
                        <i class="ph ph-printer mr-2"></i> Print Pass
                    </button>
                    <button onclick="goHome()" class="mt-4 text-slate-400 font-bold hover:text-slate-600">Cancel Transaction</button>
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

        // ─── GPS: dual purpose ────────────────────────────────────────────
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

                    fetch('push_location.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ 
                            lat, lng, speed: spd, accuracy: acc,
                            bus_id: localStorage.getItem('kiosk_bus_id')
                        })
                    }).catch(() => {});
                },
                err => {
                    const msgs = { 1: 'Location denied', 2: 'GPS unavailable', 3: 'GPS timeout' };
                    document.getElementById('loc-text').innerText =
                        '⚠️ ' + (msgs[err.code] || 'GPS error');
                },
                { enableHighAccuracy: true, maximumAge: 4000, timeout: 15000 }
            );
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

            // For regular passengers, show optional ID entry
            const subtitle = document.getElementById('verify-subtitle');
            const skipBtn = document.getElementById('skip-btn');
            const verifyBtn = document.getElementById('verify-btn');

            if (type === 'regular') {
                subtitle.textContent = 'Optional: Enter your ID to link this ride to your account.';
                skipBtn.classList.remove('hidden');
                verifyBtn.textContent = 'Continue';
            } else {
                subtitle.textContent = 'Enter your ID number to verify your discount.';
                skipBtn.classList.add('hidden');
                verifyBtn.textContent = 'Verify & Continue';
            }

            // Reset UI
            document.getElementById('id-input').value = '';
            document.getElementById('verify-result').classList.add('hidden');

            hideAllSteps();
            document.getElementById('step-verify').classList.remove('hidden');
            document.getElementById('id-input').focus();
        }

        // ─── Skip verification (regular passengers only) ───
        function skipVerification() {
            toStep2(ticket.type);
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
                                let clickedText = ticket.type === 'special' ? 'Teacher/Nurse' : 'Student/SR/PWD';
                                let actualText = actualCategory === 'special' ? 'Teacher/Nurse' : 'Student/SR/PWD';
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
                            // Discounted but unregistered — STILL give the discount
                            resultDiv.className = 'mb-4 p-4 rounded-2xl text-left bg-orange-50 border border-orange-200';
                            resultDiv.innerHTML = `
                                <p class="text-orange-800 font-bold">⚠️ ID Not Registered</p>
                                <p class="text-orange-700 text-sm">Discount will still be applied. Your ID number will be logged for records.</p>
                                <p class="text-orange-600 text-xs mt-1">Register at PARE website for faster boarding next time!</p>
                            `;
                            verifyBtn.textContent = 'Continue with Discount →';
                            verifyBtn.onclick = function() { toStep2(ticket.type); };
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
            
            fetch(`get_destinations.php?current_km=${currentLoc.km}&bus_id=${localStorage.getItem('kiosk_bus_id')}`)
                .then(r => r.json())
                .then(response => {
                    // Use the direction-aware origin from the backend
                    if (response.origin) {
                        currentLoc.name = response.origin;
                    }

                    const data = response.stations || response;
                    const grid = document.getElementById('dest-grid');
                    grid.innerHTML = data.map(s => {
                        let fare = s.regular_fare;
                        let typeLabel = "Regular";
                        if (ticket.type === 'student') { fare = s.student_fare; typeLabel = ticket.specificType || "Student/SR/PWD"; }
                        else if (ticket.type === 'special') { fare = s.special_fare; typeLabel = ticket.specificType || "Teacher/Nurse"; }

                        return `
                        <button onclick="toStep3('${s.station_name}', ${fare}, '${typeLabel}')" class="bg-white p-8 rounded-2xl shadow-md border-2 border-transparent hover:border-blue-600 text-center flex flex-col items-center">
                            <span class="block text-lg font-black mb-2">${s.station_name}</span>
                            <span class="text-blue-600 font-black text-xl bg-blue-50 px-4 py-1 rounded-full">₱ ${parseFloat(fare).toFixed(2)}</span>
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
                    alert("Error saving transaction: " + data.message);
                    btn.disabled = false;
                    btn.innerText = originalText;
                }
            })
            .catch(err => {
                alert("Network error: " + err.message);
                btn.disabled = false;
                btn.innerText = originalText;
            });
        }
    </script>
</body>
</html>