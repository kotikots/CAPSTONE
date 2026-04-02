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
    </style>
</head>
<body class="bg-slate-100 font-sans text-slate-800 min-h-screen">
