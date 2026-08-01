<?php
/**
 * includes/header.php
 * Universal HTML head opener — include at top of every page.
 * $pageTitle must be set before including this file.
 */
$pageTitle = $pageTitle ?? 'PARE System';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="PARE – Web-Based Passenger Monitoring and Fare System with Real-Time Bus Tracking">
    <title><?= htmlspecialchars($pageTitle) ?> | PARE System</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/PARE/assets/img/logo.png">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Phosphor Icons -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <script>
        // Extend Tailwind config
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        slate: {
                            50:  'rgba(255, 255, 255, 0.02)',
                            100: 'rgba(255, 255, 255, 0.04)',
                            200: 'rgba(255, 255, 255, 0.08)',
                            300: 'rgba(255, 255, 255, 0.15)',
                            400: 'rgba(255, 255, 255, 0.4)',
                            500: 'rgba(255, 255, 255, 0.6)',
                            600: 'rgba(255, 255, 255, 0.8)',
                            700: '#f8fafc',
                            800: '#ffffff',
                            900: 'rgba(0, 0, 0, 0.2)',
                            950: 'rgba(0, 0, 0, 0.4)',
                        },
                        brand: {
                            50:  'rgba(14, 165, 233, 0.1)',
                            100: 'rgba(14, 165, 233, 0.2)',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                            950: '#082f49',
                        },
                        accent: {
                            50:  'rgba(6, 182, 212, 0.1)',
                            100: 'rgba(6, 182, 212, 0.2)',
                            200: '#a5f3fc',
                            300: '#67e8f9',
                            400: '#22d3ee',
                            500: '#06b6d4',
                            600: '#0891b2',
                            700: '#0e7490',
                            800: '#155e75',
                            900: '#164e63',
                            950: '#083344',
                        }
                    }
                }
            }
        }
    </script>

    <style>
        /* Smooth transition defaults */
        * { transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease; }

        /* Toast Animations */
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes fadeOut {
            from { opacity: 1; transform: scale(1); }
            to { opacity: 0; transform: scale(0.95); }
        }
        .animate-slide-in { animation: slideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        .animate-fade-out { animation: fadeOut 0.3s ease-in forwards; }

        /* Thermal print media query – ticket only */
        @media print {
            body * { visibility: hidden !important; }
            #print-ticket, #print-ticket * { visibility: visible !important; }
            #print-ticket {
                position: absolute !important;
                left: 0; top: 0;
                width: 80mm;
                font-family: 'Courier New', monospace;
            }
        }

        /* Hide browser-native show password/clear buttons (Edge/IE) */
        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear {
            display: none;
        }

        /* Hide Scrollbar helper */
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar {
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        }

        /* ========================================================
           PREMIUM LIGHT GLASSMORPHISM (Sky Edition)
           ======================================================== */
        
        /* 1. Dynamic Light Sky Blue Gradient Body */
        body {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 40%, #bae6fd 100%) !important;
            background-attachment: fixed !important;
            color: #0f172a !important;
        }

        /* 2. Light Frosted Glass Cards */
        main { background-color: transparent !important; }
        .bg-white {
            background-color: rgba(255, 255, 255, 0.45) !important;
            backdrop-filter: blur(24px) !important;
            -webkit-backdrop-filter: blur(24px) !important;
            border: 1px solid rgba(255, 255, 255, 0.4) !important;
            box-shadow: inset 0 1px 1px rgba(255, 255, 255, 0.8), 0 8px 32px rgba(0, 0, 0, 0.05) !important;
            color: #0f172a !important;
        }

        /* 3. Text Color Fixes for Light Theme
           Overrides the inverted Tailwind config to force text to be dark and readable
           Applies to glass cards AND the main content area */
        .bg-white [class*="text-slate-"], 
        .bg-white [class*="text-white"], 
        .bg-white .text-white,
        main [class*="text-slate-"], 
        main [class*="text-white"], 
        main .text-white {
            color: #0f172a !important;
        }
        .bg-white .text-slate-400, 
        .bg-white .text-slate-500,
        main .text-slate-400, 
        main .text-slate-500 {
            color: #475569 !important; /* Muted subtitle text */
        }

        /* Protect colored buttons so they keep white text */
        .bg-white [class*="bg-blue-500"], .bg-white [class*="bg-blue-600"],
        .bg-white [class*="bg-emerald-500"], .bg-white [class*="bg-emerald-600"],
        .bg-white [class*="bg-orange-500"], .bg-white [class*="bg-orange-600"],
        .bg-white [class*="bg-amber-500"], .bg-white [class*="bg-amber-600"],
        .bg-white [class*="bg-red-500"], .bg-white [class*="bg-red-600"],
        main [class*="bg-blue-500"], main [class*="bg-blue-600"],
        main [class*="bg-emerald-500"], main [class*="bg-emerald-600"],
        main [class*="bg-orange-500"], main [class*="bg-orange-600"],
        main [class*="bg-amber-500"], main [class*="bg-amber-600"],
        main [class*="bg-red-500"], main [class*="bg-red-600"],
        main [class*="bg-slate-800"] {
            color: #ffffff !important;
        }
        .bg-white [class*="bg-blue-500"] [class*="text-"], .bg-white [class*="bg-blue-600"] [class*="text-"],
        .bg-white [class*="bg-emerald-500"] [class*="text-"], .bg-white [class*="bg-emerald-600"] [class*="text-"],
        .bg-white [class*="bg-orange-500"] [class*="text-"], .bg-white [class*="bg-orange-600"] [class*="text-"],
        .bg-white [class*="bg-amber-500"] [class*="text-"], .bg-white [class*="bg-amber-600"] [class*="text-"],
        .bg-white [class*="bg-red-500"] [class*="text-"], .bg-white [class*="bg-red-600"] [class*="text-"],
        main [class*="bg-blue-500"] [class*="text-"], main [class*="bg-blue-600"] [class*="text-"],
        main [class*="bg-emerald-500"] [class*="text-"], main [class*="bg-emerald-600"] [class*="text-"],
        main [class*="bg-orange-500"] [class*="text-"], main [class*="bg-orange-600"] [class*="text-"],
        main [class*="bg-amber-500"] [class*="text-"], main [class*="bg-amber-600"] [class*="text-"],
        main [class*="bg-red-500"] [class*="text-"], main [class*="bg-red-600"] [class*="text-"] {
            color: #ffffff !important;
        }

        /* Prevent modals from becoming totally transparent */
        #confirm-modal .bg-white {
            background-color: rgba(255, 255, 255, 0.95) !important;
            border: 1px solid #ffffff !important;
            box-shadow: 0 0 40px rgba(14, 165, 233, 0.15) !important;
        }

        /* 4. Soft Sky Blue Shadows */
        .shadow-2xl, .shadow-xl, .shadow-lg, .shadow-md, .shadow {
            box-shadow: 0 10px 30px -10px rgba(14, 165, 233, 0.2) !important;
        }

        /* 5. Interactive Tactile Details */
        button:active, a:active {
            transform: scale(0.97) !important;
            transition: transform 0.1s ease !important;
        }
        
        /* 6. Clean Focus Rings & Inputs */
        input, select, textarea {
            background-color: rgba(255, 255, 255, 0.6) !important;
            color: #0f172a !important;
            border: 1px solid rgba(14, 165, 233, 0.2) !important;
        }
        input:focus, select:focus, textarea:focus {
            background-color: rgba(255, 255, 255, 0.9) !important;
            border-color: #0ea5e9 !important;
            box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.2) !important;
            outline: none !important;
        }

        /* 6.1 Fix Placeholder Colors */
        ::placeholder {
            color: #64748b !important;
            opacity: 1 !important;
        }
        ::-ms-input-placeholder { color: #64748b !important; }
        
        /* Fix for Browser Autofill */
        input:-webkit-autofill,
        input:-webkit-autofill:hover, 
        input:-webkit-autofill:focus, 
        input:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 30px #f0f9ff inset !important;
            -webkit-text-fill-color: #0f172a !important;
            transition: background-color 5000s ease-in-out 0s;
        }

        option { background-color: #ffffff !important; color: #0f172a !important; }

        /* 7. Leaflet Map Popups Light Theme */
        .leaflet-popup-content-wrapper {
            background-color: rgba(255, 255, 255, 0.9) !important;
            backdrop-filter: blur(16px) !important;
            -webkit-backdrop-filter: blur(16px) !important;
            border: 1px solid rgba(255, 255, 255, 0.9) !important;
            color: #0f172a !important;
            box-shadow: 0 10px 30px -10px rgba(14, 165, 233, 0.15) !important;
        }
        .leaflet-popup-content-wrapper [class*="text-"] {
            color: #0f172a !important;
        }
        .leaflet-popup-tip {
            background-color: rgba(255, 255, 255, 0.9) !important;
            border: 1px solid rgba(255, 255, 255, 0.9) !important;
            border-top: none !important;
            border-left: none !important;
        }
        .leaflet-popup-close-button {
            color: rgba(15, 23, 42, 0.6) !important;
        }
        .leaflet-popup-close-button:hover {
            color: #0f172a !important;
        }

        /* 8. Emphasize nested fields/boxes on light cards
           (Overrides transparent slate backgrounds to give them visible boundaries) */
        .bg-white .bg-slate-50, .bg-white .bg-slate-100 {
            background-color: rgba(14, 165, 233, 0.06) !important; /* Subtle blue tint */
            border-color: rgba(14, 165, 233, 0.15) !important;
        }
    </style>

    <script>
        /**
         * Back-Forward Cache (bfcache) Session Guard
         * ─────────────────────────────────────────────────────────────────────
         * Modern browsers cache a full snapshot of the page in memory and
         * restore it instantly when the user presses the back/forward button
         * (event.persisted = true). This bypasses the server entirely, so PHP
         * session checks never run — meaning a logged-out user can press Back
         * and see the previous authenticated page.
         *
         * This listener fires on every pageshow. When the page is restored
         * from bfcache, it does a lightweight fetch to /PARE/auth/session_check.php.
         * If the session is gone (logged out), the page is immediately
         * replaced with the login page before the user can interact with it.
         */
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                // Page was restored from bfcache — verify session is still alive
                fetch('/PARE/auth/session_check.php', {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store'
                })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data.loggedIn) {
                        // Session is gone — replace history entry so back won't loop
                        window.location.replace('/PARE/auth/login.php');
                    }
                })
                .catch(function () {
                    // On any network error, fall back to login for safety
                    window.location.replace('/PARE/auth/login.php');
                });
            }
        });
    </script>
</head>
<body class="bg-slate-100 font-sans text-slate-800 min-h-screen">

    <!-- Global Toast Container -->
    <div id="toast-container" class="fixed top-6 right-6 z-[9999] flex flex-col gap-3 pointer-events-none"></div>

    <!-- Global Confirm Modal -->
    <div id="confirm-modal" class="fixed inset-0 z-[9998] bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden animate-in fade-in zoom-in duration-200">
            <div class="p-8 text-center">
                <div id="confirm-icon-bg" class="w-20 h-20 rounded-full mx-auto mb-6 flex items-center justify-center">
                    <i id="confirm-icon" class="ph text-4xl"></i>
                </div>
                <h3 id="confirm-title" class="text-2xl font-black text-slate-800 mb-2">Are you sure?</h3>
                <p id="confirm-msg" class="text-slate-500 font-medium"></p>
            </div>
            <div class="p-6 bg-slate-50 border-t border-slate-100 flex gap-3">
                <button id="confirm-cancel" class="flex-1 py-4 bg-white border border-slate-200 text-slate-600 font-bold rounded-2xl hover:bg-slate-50 transition">Cancel</button>
                <button id="confirm-ok" class="flex-1 py-4 bg-blue-600 text-white font-black rounded-2xl shadow-lg hover:bg-blue-500 transition active:scale-95">Confirm</button>
            </div>
        </div>
    </div>

    <script>
    /**
     * Premium Toast Engine
     */
    window.showToast = function(title, message, type = 'success') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        
        const colors = {
            success: { bg: 'bg-emerald-500', icon: 'ph-check-circle', glow: 'shadow-emerald-500/40' },
            error:   { bg: 'bg-rose-500',    icon: 'ph-warning-circle', glow: 'shadow-rose-500/40' },
            info:    { bg: 'bg-blue-500',    icon: 'ph-info',           glow: 'shadow-blue-500/40' }
        }[type];

        toast.className = `min-w-[320px] max-w-md pointer-events-auto bg-white rounded-2xl shadow-xl overflow-hidden flex items-stretch animate-slide-in border border-slate-100`;
        toast.innerHTML = `
            <div class="${colors.bg} w-2 flex-shrink-0"></div>
            <div class="p-4 flex gap-4 items-center">
                <div class="w-10 h-10 rounded-xl ${colors.bg}/10 flex items-center justify-center text-2xl ${colors.bg.replace('bg-','text-')}">
                    <i class="ph ${colors.icon}"></i>
                </div>
                <div>
                    <p class="font-black text-slate-800 text-sm">${title}</p>
                    <p class="text-slate-500 text-xs font-medium">${message}</p>
                </div>
            </div>
        `;

        container.appendChild(toast);

        // Auto-remove
        setTimeout(() => {
            toast.classList.replace('animate-slide-in', 'animate-fade-out');
            setTimeout(() => toast.remove(), 350);
        }, 5000);
    };

    /**
     * Modern Theme-Matched Confirm Dialog
     */
    window.showConfirm = function({ title, message, type = 'info', confirmText = 'Confirm' }) {
        return new Promise((resolve) => {
            const modal = document.getElementById('confirm-modal');
            const titleEl = document.getElementById('confirm-title');
            const msgEl = document.getElementById('confirm-msg');
            const iconBg = document.getElementById('confirm-icon-bg');
            const icon = document.getElementById('confirm-icon');
            const okBtn = document.getElementById('confirm-ok');
            const cancelBtn = document.getElementById('confirm-cancel');

            const themes = {
                danger: { bg: 'bg-rose-100', text: 'text-rose-600', icon: 'ph-warning-octagon', btn: 'bg-rose-600 hover:bg-rose-500 shadow-rose-500/30' },
                info:   { bg: 'bg-blue-100', text: 'text-blue-600', icon: 'ph-question',       btn: 'bg-blue-600 hover:bg-blue-500 shadow-blue-500/30' },
                warning:{ bg: 'bg-amber-100',text: 'text-amber-600',icon: 'ph-warning',        btn: 'bg-amber-600 hover:bg-amber-500 shadow-amber-500/30' }
            }[type];

            titleEl.textContent = title;
            msgEl.textContent = message;
            okBtn.textContent = confirmText;
            
            iconBg.className = `w-20 h-20 rounded-full mx-auto mb-6 flex items-center justify-center ${themes.bg}`;
            icon.className = `ph ${themes.icon} text-4xl ${themes.text}`;
            okBtn.className = `flex-1 py-4 text-white font-black rounded-2xl shadow-lg transition active:scale-95 ${themes.btn}`;

            modal.classList.remove('hidden');
            
            const handleAction = (val) => {
                modal.classList.add('hidden');
                okBtn.removeEventListener('click', okHandler);
                cancelBtn.removeEventListener('click', cancelHandler);
                resolve(val);
            };

            const okHandler = () => handleAction(true);
            const cancelHandler = () => handleAction(false);

            okBtn.addEventListener('click', okHandler);
            cancelBtn.addEventListener('click', cancelHandler);
        });
    };
    </script>
