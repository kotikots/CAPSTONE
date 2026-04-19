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
                        brand: {
                            50:  '#eff6ff',
                            100: '#dbeafe',
                            500: '#2563eb',
                            600: '#1d4ed8',
                            700: '#1e40af',
                            900: '#1e3a8a',
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

        /* Hide Scrollbar helper */
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar {
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        }
    </style>
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
