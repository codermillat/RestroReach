/**
 * RestroReach Google Maps Frontend JavaScript
 * Handles interactive maps for delivery tracking and agent location
 */

// Global variables
let rdmMap = null;
let rdmDirectionsService = null;
let rdmDirectionsRenderer = null;
let rdmAgentMarkers = [];
let rdmOrderMarkers = [];
let rdmInfoWindows = [];

/**
 * Initialize maps when Google Maps API is loaded
 * This function is called by the Google Maps API callback
 */
function rdmInitMap() {
    if (typeof google === 'undefined') {
        console.error('RestroReach: Google Maps API not loaded');
        return;
    }

    console.log('RestroReach: Initializing Google Maps');

    // Initialize different map types based on page context
    if (document.getElementById('rdm-tracking-map')) {
        rdmInitOrderTracking();
    }

    if (document.getElementById('rdm-agent-location-map')) {
        rdmInitAgentLocationMap();
    }

    if (document.getElementById('rdm-address-picker-map')) {
        rdmInitAddressPicker();
    }

    // Trigger custom event for other scripts that need to initialize after maps
    document.dispatchEvent(new CustomEvent('rdm:mapsReady'));
}

/**
 * Backward compatibility - keep the old function name as well
 */
function rdmInitMaps() {
    rdmInitMap();
}

/**
 * Initialize order tracking map for customers
 */
function rdmInitOrderTracking(config = {}) {
    const mapElement = document.getElementById('rdm-tracking-map');
    if (!mapElement) return;

    const defaultConfig = {
        orderId: 0,
        zoom: 13,
        trackingKey: ''
    };

    config = Object.assign(defaultConfig, config);

    // Initialize map
    rdmMap = new google.maps.Map(mapElement, {
        zoom: config.zoom,
        center: rdmMapsConfig.mapDefaults.center,
        mapTypeId: rdmMapsConfig.mapDefaults.mapTypeId,
        disableDefaultUI: false,
        zoomControl: true,
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: true
    });

    // Initialize directions service
    rdmDirectionsService = new google.maps.DirectionsService();
    rdmDirectionsRenderer = new google.maps.DirectionsRenderer({
        suppressMarkers: false,
        draggable: false,
        polylineOptions: {
            strokeColor: '#007cba',
            strokeWeight: 4,
            strokeOpacity: 0.8
        }
    });
    rdmDirectionsRenderer.setMap(rdmMap);

    // Start tracking updates
    if (config.orderId) {
        rdmStartOrderTracking(config.orderId, config.trackingKey);
    }
}

/**
 * Initialize agent location map for mobile interface
 */
function rdmInitAgentLocationMap() {
    const mapElement = document.getElementById('rdm-agent-location-map');
    if (!mapElement) return;

    rdmMap = new google.maps.Map(mapElement, {
        zoom: 15,
        center: rdmMapsConfig.mapDefaults.center,
        mapTypeId: rdmMapsConfig.mapDefaults.mapTypeId,
        disableDefaultUI: true,
        zoomControl: true,
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: false
    });

    // Try to get current location
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            position => {
                const currentLocation = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude
                };

                rdmMap.setCenter(currentLocation);

                // Add current location marker
                const currentMarker = new google.maps.Marker({
                    position: currentLocation,
                    map: rdmMap,
                    title: rdmMapsConfig.strings.currentLocation || 'Your Location',
                    icon: {
                        url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="12" cy="12" r="8" fill="#007cba"/>
                                <circle cx="12" cy="12" r="3" fill="white"/>
                            </svg>
                        `),
                        scaledSize: new google.maps.Size(24, 24),
                        anchor: new google.maps.Point(12, 12)
                    }
                });

                rdmAgentMarkers.push(currentMarker);
            },
            error => {
                console.log('RestroReach: Could not get current location:', error);
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 300000
            }
        );
    }
}

/**
 * Initialize address picker with autocomplete
 */
function rdmInitAddressPicker() {
    const mapElement = document.getElementById('rdm-address-picker-map');
    const addressInput = document.getElementById('rdm-address-input');
    
    if (!mapElement || !addressInput) return;

    rdmMap = new google.maps.Map(mapElement, {
        zoom: 13,
        center: rdmMapsConfig.mapDefaults.center,
        mapTypeId: rdmMapsConfig.mapDefaults.mapTypeId
    });

    // Initialize autocomplete
    const autocomplete = new google.maps.places.Autocomplete(addressInput, {
        types: ['address'],
        componentRestrictions: { country: rdmMapsConfig.countryCode || 'US' }
    });

    autocomplete.bindTo('bounds', rdmMap);

    let marker = new google.maps.Marker({
        map: rdmMap,
        draggable: true
    });

    // Handle place selection
    autocomplete.addListener('place_changed', () => {
        const place = autocomplete.getPlace();
        
        if (!place.geometry || !place.geometry.location) {
            return;
        }

        rdmMap.setCenter(place.geometry.location);
        rdmMap.setZoom(15);
        marker.setPosition(place.geometry.location);

        // Trigger custom event
        rdmTriggerAddressSelected(place);
    });

    // Handle marker drag
    marker.addListener('dragend', () => {
        const position = marker.getPosition();
        rdmReverseGeocode(position.lat(), position.lng());
    });

    // Handle map click
    rdmMap.addListener('click', (event) => {
        marker.setPosition(event.latLng);
        rdmReverseGeocode(event.latLng.lat(), event.latLng.lng());
    });
}

/**
 * Start real-time order tracking
 */
function rdmStartOrderTracking(orderId, trackingKey = '') {
    const updateInterval = 30000; // 30 seconds

    function updateTracking() {
        jQuery.ajax({
            url: rdmMapsConfig.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'rdm_get_agent_locations',
                order_id: orderId,
                tracking_key: trackingKey,
                nonce: rdmMapsConfig.nonce
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    rdmUpdateAgentMarkers(response.data);
                    rdmUpdateOrderStatus(orderId);
                }
            },
            error: function(xhr, status, error) {
                console.error('RestroReach: Error updating tracking:', error);
            }
        });
    }

    // Initial update
    updateTracking();

    // Set up interval
    setInterval(updateTracking, updateInterval);
}

/**
 * Update agent markers on the map
 */
function rdmUpdateAgentMarkers(agents) {
    // Clear existing markers
    rdmAgentMarkers.forEach(marker => marker.setMap(null));
    rdmAgentMarkers = [];

    agents.forEach(agent => {
        const marker = new google.maps.Marker({
            position: { lat: parseFloat(agent.lat), lng: parseFloat(agent.lng) },
            map: rdmMap,
            title: agent.agent_name,
            icon: {
                url: rdmGetAgentMarkerIcon(agent.battery_level),
                scaledSize: new google.maps.Size(32, 32),
                anchor: new google.maps.Point(16, 32)
            }
        });

        // Info window
        const infoWindow = new google.maps.InfoWindow({
            content: rdmCreateAgentInfoWindow(agent)
        });

        marker.addListener('click', () => {
            // Close other info windows
            rdmInfoWindows.forEach(window => window.close());
            infoWindow.open(rdmMap, marker);
        });

        rdmAgentMarkers.push(marker);
        rdmInfoWindows.push(infoWindow);
    });

    // Adjust map bounds to show all markers
    if (rdmAgentMarkers.length > 0) {
        const bounds = new google.maps.LatLngBounds();
        rdmAgentMarkers.forEach(marker => bounds.extend(marker.getPosition()));
        rdmMap.fitBounds(bounds);
    }
}

/**
 * Create info window content for agent marker
 */
function rdmCreateAgentInfoWindow(agent) {
    const lastUpdate = new Date(agent.timestamp);
    const timeAgo = rdmTimeAgo(lastUpdate);
    
    return `
        <div class="rdm-agent-info">
            <h4>${agent.agent_name}</h4>
            <p><strong>${rdmMapsConfig.strings.lastUpdate || 'Last Update'}:</strong> ${timeAgo}</p>
            <p><strong>${rdmMapsConfig.strings.battery || 'Battery'}:</strong> ${agent.battery_level}%</p>
            <div class="rdm-battery-indicator">
                <div class="rdm-battery-bar" style="width: ${agent.battery_level}%"></div>
            </div>
        </div>
    `;
}

/**
 * Get appropriate marker icon based on battery level
 */
function rdmGetAgentMarkerIcon(batteryLevel) {
    let color = '#22c55e'; // Green for good battery
    
    if (batteryLevel < 30) {
        color = '#ef4444'; // Red for low battery
    } else if (batteryLevel < 50) {
        color = '#f59e0b'; // Orange for medium battery
    }

    return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
        <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M16 30L6 20H26L16 30Z" fill="${color}"/>
            <circle cx="16" cy="16" r="8" fill="${color}" stroke="white" stroke-width="2"/>
            <circle cx="16" cy="16" r="4" fill="white"/>
        </svg>
    `);
}

/**
 * Update order status display
 */
function rdmUpdateOrderStatus(orderId) {
    jQuery.ajax({
        url: rdmMapsConfig.ajaxUrl,
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'rdm_get_order_status',
            order_id: orderId,
            nonce: rdmMapsConfig.nonce
        },
        success: function(response) {
            if (response.success) {
                const statusElement = document.getElementById('rdm-order-status-text');
                const etaElement = document.getElementById('rdm-delivery-eta');
                const distanceElement = document.getElementById('rdm-delivery-distance');

                if (statusElement) {
                    statusElement.textContent = response.data.status_text;
                }

                if (etaElement && response.data.eta) {
                    etaElement.textContent = `${rdmMapsConfig.strings.eta} ${response.data.eta}`;
                }

                if (distanceElement && response.data.distance) {
                    distanceElement.textContent = `${rdmMapsConfig.strings.distance} ${response.data.distance}`;
                }
            }
        }
    });
}

/**
 * Reverse geocode coordinates to address
 */
function rdmReverseGeocode(lat, lng) {
    const geocoder = new google.maps.Geocoder();
    const latlng = { lat: lat, lng: lng };

    geocoder.geocode({ location: latlng }, (results, status) => {
        if (status === 'OK' && results[0]) {
            const addressInput = document.getElementById('rdm-address-input');
            if (addressInput) {
                addressInput.value = results[0].formatted_address;
            }

            // Trigger custom event
            rdmTriggerAddressSelected({
                formatted_address: results[0].formatted_address,
                geometry: {
                    location: { lat: () => lat, lng: () => lng }
                }
            });
        }
    });
}

/**
 * Trigger address selected event
 */
function rdmTriggerAddressSelected(place) {
    const event = new CustomEvent('rdm_address_selected', {
        detail: {
            address: place.formatted_address,
            lat: place.geometry.location.lat(),
            lng: place.geometry.location.lng(),
            place: place
        }
    });
    document.dispatchEvent(event);
}

/**
 * Calculate and display route
 */
function rdmShowRoute(origin, destination) {
    if (!rdmDirectionsService || !rdmDirectionsRenderer) {
        console.error('RestroReach: Directions service not initialized');
        return;
    }

    const request = {
        origin: origin,
        destination: destination,
        travelMode: google.maps.TravelMode.DRIVING,
        unitSystem: google.maps.UnitSystem.METRIC,
        avoidHighways: false,
        avoidTolls: false
    };

    rdmDirectionsService.route(request, (result, status) => {
        if (status === 'OK') {
            rdmDirectionsRenderer.setDirections(result);
            
            // Extract route information
            const route = result.routes[0];
            const leg = route.legs[0];
            
            // Update UI with route info
            rdmUpdateRouteInfo({
                distance: leg.distance.text,
                duration: leg.duration.text,
                start_address: leg.start_address,
                end_address: leg.end_address
            });
        } else {
            console.error('RestroReach: Directions request failed:', status);
            rdmShowMessage(rdmMapsConfig.strings.routeError, 'error');
        }
    });
}

/**
 * Update route information display
 */
function rdmUpdateRouteInfo(routeInfo) {
    const routeInfoElement = document.getElementById('rdm-route-info');
    if (routeInfoElement) {
        routeInfoElement.innerHTML = `
            <div class="rdm-route-summary">
                <span class="rdm-route-distance">${routeInfo.distance}</span>
                <span class="rdm-route-duration">${routeInfo.duration}</span>
            </div>
        `;
    }

    // Trigger custom event
    const event = new CustomEvent('rdm_route_calculated', {
        detail: routeInfo
    });
    document.dispatchEvent(event);
}

/**
 * Show message to user
 */
function rdmShowMessage(message, type = 'info') {
    const messageContainer = document.getElementById('rdm-map-messages');
    if (messageContainer) {
        const messageElement = document.createElement('div');
        messageElement.className = `rdm-message rdm-message-${type}`;
        messageElement.textContent = message;
        
        messageContainer.appendChild(messageElement);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (messageElement.parentNode) {
                messageElement.parentNode.removeChild(messageElement);
            }
        }, 5000);
    }
}

/**
 * Format time ago string
 */
function rdmTimeAgo(date) {
    const now = new Date();
    const diffMs = now - date;
    const diffSecs = Math.floor(diffMs / 1000);
    const diffMins = Math.floor(diffSecs / 60);
    const diffHours = Math.floor(diffMins / 60);

    if (diffSecs < 60) {
        return `${diffSecs} seconds ago`;
    } else if (diffMins < 60) {
        return `${diffMins} minutes ago`;
    } else if (diffHours < 24) {
        return `${diffHours} hours ago`;
    } else {
        return date.toLocaleDateString();
    }
}

/**
 * Utility function to get current position
 */
function rdmGetCurrentPosition() {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error('Geolocation not supported'));
            return;
        }

        navigator.geolocation.getCurrentPosition(
            position => resolve({
                lat: position.coords.latitude,
                lng: position.coords.longitude
            }),
            error => reject(error),
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 300000
            }
        );
    });
}

// Initialize maps when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Maps will be initialized when Google Maps API calls the callback
    console.log('RestroReach: DOM ready, waiting for Google Maps API');
});

// Expose functions globally for external use
window.rdmInitMaps = rdmInitMaps;
window.rdmInitOrderTracking = rdmInitOrderTracking;
window.rdmShowRoute = rdmShowRoute;
window.rdmGetCurrentPosition = rdmGetCurrentPosition;
