<?php
/**
 * config/kiosk_settings.php
 * Hardware-specific settings for the on-bus Kiosk tablet.
 * 
 * IMPORTANT: This file should be unique to the tablet installed on the bus.
 * Change the KIOSK_BUS_ID to match the actual vehicle ID from the 'buses' table.
 */

// Deployment setting: The database ID of the bus this kiosk is physically mounted in.
define('KIOSK_BUS_ID', 1); 

// Optional: Kiosk display name / location tag
define('KIOSK_TAG', 'Bus 001 Monitor');

// Station/Terminal location for the stationary kiosk or map default
define('KIOSK_STATION_NAME', 'San Roque, San Leonardo');
define('KIOSK_LAT', 15.3611);
define('KIOSK_LNG', 120.9622);
?>
