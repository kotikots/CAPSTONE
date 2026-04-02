let currentKM = 0;
let selectedDestinationKM = 0;
let passengerType = "Regular";

// 1. Get Bus Location
function updateBusLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.watchPosition((position) => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;

            // Send to PHP to find the nearest KM marker from your Fare Matrix
            fetch(`match_km.php?lat=${lat}&lng=${lng}`)
                .then(res => res.json())
                .then(data => {
                    currentKM = data.km_marker;
                    document.getElementById('current-location-box').innerText = 
                        "Current Station: " + data.station_name + " (KM " + data.km_marker + ")";
                });
        });
    }
}

// 2. Set Destination
function selectDestination(km) {
    selectedDestinationKM = km;
    document.getElementById('confirm-btn').disabled = false;
}

// 3. Set Discount
function setPassenger(type) {
    passengerType = type;
    // UI logic to highlight active button
}

updateBusLocation();