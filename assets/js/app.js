/**
 * Eswatini Police CAS - Interactive Frontend Script
 */

document.addEventListener('DOMContentLoaded', function () {
    // Theme Toggle Initialization
    const themeToggleBtn = document.getElementById('theme-toggle');
    if (themeToggleBtn) {
        // Check stored theme
        const currentTheme = localStorage.getItem('theme');
        if (currentTheme === 'dark') {
            document.body.classList.add('dark-mode');
            themeToggleBtn.innerHTML = '☀️ Light Mode';
        } else {
            themeToggleBtn.innerHTML = '🌙 Dark Mode';
        }

        themeToggleBtn.addEventListener('click', function () {
            document.body.classList.toggle('dark-mode');
            if (document.body.classList.contains('dark-mode')) {
                localStorage.setItem('theme', 'dark');
                themeToggleBtn.innerHTML = '☀️ Light Mode';
            } else {
                localStorage.setItem('theme', 'light');
                themeToggleBtn.innerHTML = '🌙 Dark Mode';
            }
        });
    }

    // Auto-calculate Age from DOB
    const dobInput = document.getElementById('dob');
    const ageInput = document.getElementById('auto_age');
    if (dobInput && ageInput) {
        dobInput.addEventListener('change', function () {
            if (this.value) {
                const dob = new Date(this.value);
                const diffMs = Date.now() - dob.getTime();
                const ageDt = new Date(diffMs);
                const age = Math.abs(ageDt.getUTCFullYear() - 1970);
                ageInput.value = age + ' Years Old';
            } else {
                ageInput.value = '';
            }
        });
    }

    // GPS Auto-Capture and Routing (for emergency and standard reporting)
    const btnLocation = document.getElementById('btn-get-location');
    const latInput = document.getElementById('latitude');
    const lonInput = document.getElementById('longitude');
    const locationStatus = document.getElementById('location-status');
    const stationSelect = document.getElementById('station_id');

    // Default Stations list (matching DB seeding for proximity calculation)
    const stations = [
        { id: 1, name: 'Mbabane Police Station', lat: -26.305537, lon: 31.136724 },
        { id: 2, name: 'Manzini Police Station', lat: -26.491378, lon: 31.371298 },
        { id: 3, name: 'Piggs Peak Police Station', lat: -25.961054, lon: 31.250000 },
        { id: 4, name: 'Nhlangano Police Station', lat: -27.116670, lon: 31.200000 },
        { id: 5, name: 'Siteki Police Station', lat: -26.450000, lon: 31.950000 }
    ];

    if (btnLocation) {
        btnLocation.addEventListener('click', function () {
            if (navigator.geolocation) {
                locationStatus.innerText = "Acquiring GPS coordinates...";
                locationStatus.className = "text-warning small d-block mt-1";
                
                navigator.geolocation.getCurrentPosition(function (position) {
                    const lat = position.coords.latitude;
                    const lon = position.coords.longitude;
                    
                    if (latInput) latInput.value = lat.toFixed(6);
                    if (lonInput) lonInput.value = lon.toFixed(6);
                    
                    locationStatus.innerText = `GPS Fixed: ${lat.toFixed(4)}, ${lon.toFixed(4)}`;
                    locationStatus.className = "text-success small d-block mt-1";
                    
                    // Route to nearest station if station select dropdown is present
                    if (stationSelect) {
                        const nearest = findNearestStation(lat, lon);
                        stationSelect.value = nearest.id;
                        locationStatus.innerText += ` | Auto-routed to: ${nearest.name}`;
                    }
                }, function (error) {
                    locationStatus.innerText = "Error: Geolocation denied or unavailable.";
                    locationStatus.className = "text-danger small d-block mt-1";
                }, {
                    enableHighAccuracy: true,
                    timeout: 10000
                });
            } else {
                locationStatus.innerText = "Error: Geolocation not supported by browser.";
                locationStatus.className = "text-danger small d-block mt-1";
            }
        });
    }

    // Helper: Euclidean distance routing
    function findNearestStation(userLat, userLon) {
        let nearestStation = stations[0];
        let minDistance = Infinity;

        stations.forEach(station => {
            const dist = Math.sqrt(
                Math.pow(userLat - station.lat, 2) + 
                Math.pow(userLon - station.lon, 2)
            );
            if (dist < minDistance) {
                minDistance = dist;
                nearestStation = station;
            }
        });
        return nearestStation;
    }

    // Citizen dashboard: emergency buttons send live location
    const emergencyButtons = document.querySelectorAll('[data-emergency-trigger="true"]');
    const emergencyConfirmButton = document.getElementById('btn-emergency-confirm');
    const emergencyStatus = document.getElementById('emergency-status');

    function setEmergencyButtonState(button, isBusy, label) {
        if (!button) return;
        button.disabled = isBusy;
        if (label) {
            button.innerHTML = label;
        }
    }

    function sendEmergencyReport(button, reportUrl, lat, lon) {
        if (button) {
            const originalHtml = button.dataset.originalHtml || button.innerHTML;
            button.dataset.originalHtml = originalHtml;
            setEmergencyButtonState(button, true, '<span class="spinner-border spinner-border-sm me-2"></span> Reporting...');
        }

        if (emergencyStatus) {
            emergencyStatus.className = 'small mt-1 text-warning';
            emergencyStatus.textContent = 'Capturing live location...';
            emergencyStatus.classList.remove('d-none');
        }

        fetch(reportUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ lat: lat, lon: lon })
        })
        .then(function (response) { return response.json(); })
        .then(function (result) {
            if (button) {
                if (result.success) {
                    setEmergencyButtonState(button, false, '<i class="fa-solid fa-circle-check text-white me-2"></i> Emergency Reported');
                    button.classList.remove('btn-danger');
                    button.classList.add('btn-success');
                } else {
                    setEmergencyButtonState(button, false, button.dataset.originalHtml || button.innerHTML);
                    if (emergencyStatus) {
                        emergencyStatus.className = 'small mt-1 text-danger';
                        emergencyStatus.textContent = 'Failed to report emergency. Please try again.';
                    }
                }
            }
            if (result.success && emergencyStatus) {
                emergencyStatus.classList.add('d-none');
            }
            if (result.success && typeof bootstrap !== 'undefined') {
                const modalEl = document.getElementById('emergencyResponseModal');
                if (modalEl) {
                    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                    modal.hide();
                }
            }
        })
        .catch(function () {
            if (button) {
                setEmergencyButtonState(button, false, button.dataset.originalHtml || button.innerHTML);
            }
            if (emergencyStatus) {
                emergencyStatus.className = 'small mt-1 text-danger';
                emergencyStatus.textContent = 'Network error. Please try again.';
            }
        });
    }

    function captureLiveLocation(button) {
        const reportUrl = button && button.dataset.deskReportUrl ? button.dataset.deskReportUrl : '../desk_report.php';

        if (!navigator.geolocation) {
            sendEmergencyReport(button, reportUrl, null, null);
            return;
        }

        var locationSent = false;
        var watchId = null;

        function finishWithLocation(lat, lon) {
            if (locationSent) return;
            locationSent = true;
            if (watchId !== null) {
                navigator.geolocation.clearWatch(watchId);
            }
            sendEmergencyReport(button, reportUrl, lat, lon);
        }

        watchId = navigator.geolocation.watchPosition(
            function (position) {
                finishWithLocation(position.coords.latitude, position.coords.longitude);
            },
            function () {
                navigator.geolocation.getCurrentPosition(
                    function (position) {
                        finishWithLocation(position.coords.latitude, position.coords.longitude);
                    },
                    function () {
                        finishWithLocation(null, null);
                    },
                    { enableHighAccuracy: true, maximumAge: 0, timeout: 10000 }
                );
            },
            { enableHighAccuracy: true, maximumAge: 0, timeout: 15000 }
        );

        setTimeout(function () {
            if (!locationSent) {
                navigator.geolocation.getCurrentPosition(
                    function (position) {
                        finishWithLocation(position.coords.latitude, position.coords.longitude);
                    },
                    function () {
                        finishWithLocation(null, null);
                    },
                    { enableHighAccuracy: true, maximumAge: 0, timeout: 5000 }
                );
            }
        }, 12000);
    }

    emergencyButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            if (button.disabled) return;
            captureLiveLocation(button);
        });
    });

    if (emergencyConfirmButton) {
        emergencyConfirmButton.addEventListener('click', function () {
            const triggerButton = document.querySelector('[data-emergency-trigger="true"]');
            captureLiveLocation(triggerButton || emergencyConfirmButton);
        });
    }
});

/**
 * Initializes a Leaflet Map at the specified GPS point
 * @param {string} elementId 
 * @param {number} lat 
 * @param {number} lon 
 * @param {string} markerText 
 */
function initCaseMap(elementId, lat, lon, markerText) {
    if (!document.getElementById(elementId)) return;
    
    // Default coordinates (Mbabane) if empty/invalid
    const mapLat = (lat && !isNaN(lat)) ? parseFloat(lat) : -26.305537;
    const mapLon = (lon && !isNaN(lon)) ? parseFloat(lon) : 31.136724;
    
    const map = L.map(elementId).setView([mapLat, mapLon], 13);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    
    L.marker([mapLat, mapLon]).addTo(map)
        .bindPopup(markerText || "Incident Location")
        .openPopup();
}
