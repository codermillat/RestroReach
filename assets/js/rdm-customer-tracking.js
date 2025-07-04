/**
 * Customer Order Tracking JavaScript
 * 
 * Handles map initialization, agent location updates, and status timeline.
 */

// Map variables
let map, restaurantMarker, customerMarker, agentMarker, deliveryRoute;
let updateTimer;
let isUpdating = false; // Prevent concurrent updates
let consecutiveErrors = 0; // Track errors for exponential backoff

/**
 * Initialize the tracking map
 */
function initTrackingMap() {
    // Check if tracking data exists
    if (!window.rdmTrackingData || !window.rdmTrackingData.locations) {
        console.error('Tracking data not found');
        document.getElementById('rdm-tracking-map').innerHTML = 
            '<div class="rdm-map-error">Map data not available</div>';
        return;
    }
    
    // Create map centered between restaurant and customer
    const restaurant = {
        lat: parseFloat(window.rdmTrackingData.locations.restaurant.lat),
        lng: parseFloat(window.rdmTrackingData.locations.restaurant.lng)
    };
    
    const customer = {
        lat: parseFloat(window.rdmTrackingData.locations.customer.lat),
        lng: parseFloat(window.rdmTrackingData.locations.customer.lng)
    };
    
    // Calculate center point
    const center = {
        lat: (restaurant.lat + customer.lat) / 2,
        lng: (restaurant.lng + customer.lng) / 2
    };
    
    // Initialize map
    map = new google.maps.Map(document.getElementById('rdm-tracking-map'), {
        zoom: 13,
        center: center,
        mapTypeId: google.maps.MapTypeId.ROADMAP,
        disableDefaultUI: true,
        zoomControl: true,
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: true,
        styles: [
            {
                featureType: 'poi',
                elementType: 'labels',
                stylers: [{ visibility: 'off' }]
            }
        ]
    });
    
    // Add restaurant marker
    restaurantMarker = new google.maps.Marker({
        position: restaurant,
        map: map,
        icon: {
            url: window.rdmMapsConfig ? window.rdmMapsConfig.markerIcons.restaurant : getDefaultMarkerIcon('restaurant'),
            scaledSize: new google.maps.Size(40, 40),
            anchor: new google.maps.Point(20, 40)
        },
        title: window.rdmTrackingData.locations.restaurant.name
    });
    
    // Add restaurant info window
    const restaurantInfoWindow = new google.maps.InfoWindow({
        content: `<div class="rdm-info-window">
            <h4>${window.rdmTrackingData.locations.restaurant.name}</h4>
            <p>${window.rdmTrackingData.locations.restaurant.address}</p>
        </div>`
    });
    
    restaurantMarker.addListener('click', () => {
        closeAllInfoWindows();
        restaurantInfoWindow.open(map, restaurantMarker);
    });
    
    // Add customer marker
    customerMarker = new google.maps.Marker({
        position: customer,
        map: map,
        icon: {
            url: window.rdmMapsConfig ? window.rdmMapsConfig.markerIcons.customer : getDefaultMarkerIcon('customer'),
            scaledSize: new google.maps.Size(40, 40),
            anchor: new google.maps.Point(20, 40)
        },
        title: window.rdmTrackingData.locations.customer.address
    });
    
    // Add customer info window
    const customerInfoWindow = new google.maps.InfoWindow({
        content: `<div class="rdm-info-window">
            <h4>Delivery Address</h4>
            <p>${window.rdmTrackingData.locations.customer.address}</p>
        </div>`
    });
    
    customerMarker.addListener('click', () => {
        closeAllInfoWindows();
        customerInfoWindow.open(map, customerMarker);
    });
    
    // Add agent marker if available
    if (window.rdmTrackingData.locations.agent) {
        createAgentMarker();
    }
    
    // Fit bounds to show all markers
    fitMapBounds();
    
    // Start periodic updates
    startTrackingUpdates();
}

/**
 * Create agent marker
 */
function createAgentMarker() {
    const agent = {
        lat: parseFloat(window.rdmTrackingData.locations.agent.lat),
        lng: parseFloat(window.rdmTrackingData.locations.agent.lng)
    };
    
    agentMarker = new google.maps.Marker({
        position: agent,
        map: map,
        icon: {
            url: window.rdmMapsConfig ? window.rdmMapsConfig.markerIcons.agent : getDefaultMarkerIcon('agent'),
            scaledSize: new google.maps.Size(40, 40),
            anchor: new google.maps.Point(20, 40)
        },
        title: window.rdmTrackingData.locations.agent.name,
        animation: google.maps.Animation.BOUNCE
    });
    
    // Add agent info window
    const agentInfoWindow = new google.maps.InfoWindow({
        content: `<div class="rdm-info-window">
            <h4>${window.rdmTrackingData.locations.agent.name}</h4>
            <p>Delivery Agent</p>
            <p><small>Last updated: ${formatLastUpdate(window.rdmTrackingData.locations.agent.last_update)}</small></p>
        </div>`
    });
    
    agentMarker.addListener('click', () => {
        closeAllInfoWindows();
        agentInfoWindow.open(map, agentMarker);
    });
    
    // Stop bouncing after 3 seconds
    setTimeout(() => {
        if (agentMarker) {
            agentMarker.setAnimation(null);
        }
    }, 3000);
    
    // Draw delivery route
    drawDeliveryRoute(agent);
}

/**
 * Close all info windows
 */
function closeAllInfoWindows() {
    // This will be populated with info window instances
    if (window.openInfoWindows) {
        window.openInfoWindows.forEach(infoWindow => infoWindow.close());
    }
}

/**
 * Fit map bounds to show all markers
 */
function fitMapBounds() {
    const bounds = new google.maps.LatLngBounds();
    bounds.extend(restaurantMarker.getPosition());
    bounds.extend(customerMarker.getPosition());
    if (agentMarker) bounds.extend(agentMarker.getPosition());
    
    map.fitBounds(bounds);
    
    // Add some padding
    google.maps.event.addListenerOnce(map, 'bounds_changed', () => {
        if (map.getZoom() > 15) {
            map.setZoom(15);
        }
    });
}

/**
 * Draw the delivery route from agent to customer
 */
function drawDeliveryRoute(agentPosition) {
    // Clear existing route
    if (deliveryRoute) {
        deliveryRoute.setMap(null);
    }
    
    // Create directional service
    const directionsService = new google.maps.DirectionsService();
    const directionsRenderer = new google.maps.DirectionsRenderer({
        map: map,
        suppressMarkers: true,
        polylineOptions: {
            strokeColor: '#FF5722',
            strokeWeight: 5,
            strokeOpacity: 0.7
        }
    });
    
    // Request directions
    directionsService.route({
        origin: agentPosition,
        destination: customerMarker.getPosition(),
        travelMode: google.maps.TravelMode.DRIVING,
        avoidHighways: false,
        avoidTolls: false
    }, (response, status) => {
        if (status === 'OK') {
            directionsRenderer.setDirections(response);
            deliveryRoute = directionsRenderer;
            
            // Update ETA based on route
            if (response.routes[0] && response.routes[0].legs[0]) {
                const duration = response.routes[0].legs[0].duration.text;
                const distance = response.routes[0].legs[0].distance.text;
                updateETADisplay(duration, distance);
            }
        } else {
            console.warn('Directions request failed due to ' + status);
            // Show fallback route as straight line
            showStraightLineRoute(agentPosition);
        }
    });
}

/**
 * Show straight line route as fallback
 */
function showStraightLineRoute(agentPosition) {
    const routePath = new google.maps.Polyline({
        path: [agentPosition, customerMarker.getPosition()],
        geodesic: true,
        strokeColor: '#FF5722',
        strokeOpacity: 0.5,
        strokeWeight: 3,
        map: map
    });
}

/**
 * Start periodic tracking updates
 */
function startTrackingUpdates() {
    // Update immediately
    updateTrackingData();
    
    // Set up interval for updates
    const refreshInterval = parseInt(window.rdmTrackingData.refresh_interval) || 30;
    updateTimer = setInterval(updateTrackingData, refreshInterval * 1000);
}

/**
 * Update tracking data via AJAX with improved error handling
 */
function updateTrackingData() {
    // Prevent concurrent updates
    if (isUpdating) {
        return;
    }
    
    isUpdating = true;
    
    // Get nonce from multiple sources
    let nonce = '';
    if (window.rdmParams && window.rdmParams.nonce) {
        nonce = window.rdmParams.nonce;
    } else if (window.rdmTrackingData && window.rdmTrackingData.nonce) {
        nonce = window.rdmTrackingData.nonce;
    }
    
    const formData = new FormData();
    formData.append('action', 'rdm_get_order_status');
    formData.append('order_id', window.rdmTrackingData.order.id);
    formData.append('tracking_key', window.rdmTrackingData.tracking_key);
    formData.append('security', nonce);
    
    // Add loading indicator
    setLoadingState(true);
    
    fetch(window.rdmParams.ajax_url, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            updateInterface(data.data);
            updateLastRefreshTime();
            consecutiveErrors = 0; // Reset error counter on success
            hideErrorMessage();
        } else {
            console.error('Error updating tracking data:', data.data ? data.data.message : 'Unknown error');
            handleUpdateError(data.data ? data.data.message : 'Failed to update tracking information');
        }
    })
    .catch(error => {
        console.error('Failed to update tracking data:', error);
        handleUpdateError('Connection error. Please check your internet connection.');
    })
    .finally(() => {
        isUpdating = false;
        setLoadingState(false);
    });
}

/**
 * Handle update errors with exponential backoff
 */
function handleUpdateError(message) {
    consecutiveErrors++;
    
    // Show error message
    showErrorMessage(message);
    
    // Implement exponential backoff for frequent errors
    if (consecutiveErrors >= 3) {
        const newInterval = Math.min(300, 30 * Math.pow(2, consecutiveErrors - 3)); // Max 5 minutes
        clearInterval(updateTimer);
        updateTimer = setInterval(updateTrackingData, newInterval * 1000);
        
        showErrorMessage(`Connection issues detected. Update interval increased to ${newInterval} seconds.`);
    }
}

/**
 * Set loading state
 */
function setLoadingState(loading) {
    const refreshButton = document.getElementById('rdm-refresh-button');
    if (refreshButton) {
        if (loading) {
            refreshButton.classList.add('loading');
            refreshButton.disabled = true;
        } else {
            refreshButton.classList.remove('loading', 'spinning');
            refreshButton.disabled = false;
        }
    }
}

/**
 * Update the tracking interface with new data
 */
function updateInterface(newData) {
    // Update order status
    if (newData.order.status !== window.rdmTrackingData.order.status) {
        window.rdmTrackingData.order.status = newData.order.status;
        updateStatusTimeline(newData.status_timeline);
        showStatusNotification(newData.order.status_name);
    }
    
    // Update agent position if available
    if (newData.locations.agent) {
        const newAgentPos = {
            lat: parseFloat(newData.locations.agent.lat),
            lng: parseFloat(newData.locations.agent.lng)
        };
        
        // Create agent marker if it doesn't exist
        if (!agentMarker) {
            window.rdmTrackingData.locations.agent = newData.locations.agent;
            createAgentMarker();
            fitMapBounds(); // Refit bounds to include agent
        } else {
            // Animate marker to new position
            animateMarker(agentMarker, newAgentPos);
        }
        
        // Update route if order is out for delivery
        if (newData.order.status === 'out-for-delivery') {
            drawDeliveryRoute(newAgentPos);
        }
        
        // Update agent info
        window.rdmTrackingData.locations.agent = newData.locations.agent;
    }
    
    // Update ETA
    if (newData.order.estimated_delivery !== window.rdmTrackingData.order.estimated_delivery) {
        window.rdmTrackingData.order.estimated_delivery = newData.order.estimated_delivery;
        const etaElement = document.getElementById('rdm-eta-time');
        if (etaElement) {
            etaElement.textContent = newData.order.estimated_delivery;
            // Add pulse animation for ETA updates
            etaElement.classList.add('updated');
            setTimeout(() => etaElement.classList.remove('updated'), 1000);
        }
    }
}

/**
 * Animate marker movement to new position
 */
function animateMarker(marker, newPosition) {
    const numDeltas = 10;
    const delay = 100; // milliseconds
    let i = 0;
    let deltaLat, deltaLng;
    
    const currentPosition = marker.getPosition();
    deltaLat = (newPosition.lat - currentPosition.lat()) / numDeltas;
    deltaLng = (newPosition.lng - currentPosition.lng()) / numDeltas;
    
    function moveMarker() {
        i++;
        const lat = currentPosition.lat() + (deltaLat * i);
        const lng = currentPosition.lng() + (deltaLng * i);
        
        const latlng = new google.maps.LatLng(lat, lng);
        marker.setPosition(latlng);
        
        if (i < numDeltas) {
            setTimeout(moveMarker, delay);
        }
    }
    
    moveMarker();
}

/**
 * Update the status timeline with new data
 */
function updateStatusTimeline(newTimeline) {
    const timelineContainer = document.getElementById('rdm-status-timeline');
    if (!timelineContainer) return;
    
    // Update each status step
    newTimeline.forEach((step, index) => {
        const stepElement = document.getElementById(`rdm-status-${step.status}`);
        if (stepElement) {
            if (step.completed && !stepElement.classList.contains('completed')) {
                stepElement.classList.add('completed');
                stepElement.querySelector('.rdm-step-time').textContent = step.time;
                
                // Add animation for newly completed steps
                stepElement.style.animation = 'rdm-step-complete 0.5s ease-in-out';
                setTimeout(() => {
                    stepElement.style.animation = '';
                }, 500);
            }
        }
    });
}

/**
 * Update ETA display
 */
function updateETADisplay(duration, distance) {
    const etaElement = document.getElementById('rdm-eta-time');
    const distanceElement = document.getElementById('rdm-distance');
    
    if (etaElement) {
        etaElement.textContent = duration;
    }
    
    if (distanceElement) {
        distanceElement.textContent = distance;
    }
}

/**
 * Show status notification
 */
function showStatusNotification(statusName) {
    const notification = document.createElement('div');
    notification.className = 'rdm-status-notification';
    notification.textContent = `Order status updated: ${statusName}`;
    
    document.body.appendChild(notification);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

/**
 * Show error message
 */
function showErrorMessage(message) {
    const errorElement = document.getElementById('rdm-error-message');
    if (errorElement) {
        errorElement.textContent = message;
        errorElement.classList.remove('rdm-hidden');
        errorElement.style.display = 'block';
        
        // Add click to dismiss functionality
        errorElement.onclick = () => {
            hideErrorMessage();
        };
        
        // Auto-hide after 10 seconds for non-critical errors
        if (!message.includes('Connection issues detected')) {
            setTimeout(() => {
                hideErrorMessage();
            }, 10000);
        }
    }
}

/**
 * Hide error message
 */
function hideErrorMessage() {
    const errorElement = document.getElementById('rdm-error-message');
    if (errorElement) {
        errorElement.style.display = 'none';
        errorElement.classList.add('rdm-hidden');
        errorElement.onclick = null;
    }
}

/**
 * Update last refresh time
 */
function updateLastRefreshTime() {
    const lastUpdateElement = document.getElementById('rdm-last-update');
    if (lastUpdateElement) {
        const now = new Date();
        lastUpdateElement.textContent = `Last updated: ${now.toLocaleTimeString()}`;
    }
}

/**
 * Format last update time
 */
function formatLastUpdate(timestamp) {
    if (!timestamp) return 'Unknown';
    
    const date = new Date(timestamp);
    const now = new Date();
    const diffMinutes = Math.floor((now - date) / (1000 * 60));
    
    if (diffMinutes < 1) {
        return 'Just now';
    } else if (diffMinutes < 60) {
        return `${diffMinutes} minutes ago`;
    } else {
        return date.toLocaleTimeString();
    }
}

/**
 * Get default marker icon for different types
 */
function getDefaultMarkerIcon(type) {
    const colors = {
        restaurant: '#FF5722',
        customer: '#2196F3',
        agent: '#4CAF50'
    };
    
    const color = colors[type] || '#666666';
    
    return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(`
        <svg width="40" height="40" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
            <circle cx="20" cy="20" r="15" fill="${color}" stroke="#ffffff" stroke-width="3"/>
            <circle cx="20" cy="20" r="5" fill="#ffffff"/>
        </svg>
    `)}`;
}

/**
 * Initialize when document is ready
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialize map when Google Maps API is loaded
    window.rdmInitMap = initTrackingMap;
    
    // Manual refresh button
    const refreshButton = document.getElementById('rdm-refresh-button');
    if (refreshButton) {
        refreshButton.addEventListener('click', function() {
            updateTrackingData();
            this.classList.add('spinning');
            setTimeout(() => {
                this.classList.remove('spinning');
            }, 1000);
        });
    }
    
    // Call agent button
    const callButton = document.querySelector('.rdm-call-agent');
    if (callButton) {
        callButton.addEventListener('click', function(e) {
            // Track call action if analytics is available
            if (typeof gtag !== 'undefined') {
                gtag('event', 'call_agent', {
                    event_category: 'engagement',
                    event_label: 'order_tracking'
                });
            }
        });
    }
    
    // Auto-focus on tracking form inputs
    const trackingForm = document.getElementById('rdm-tracking-form');
    if (trackingForm) {
        const firstInput = trackingForm.querySelector('input[type="number"]');
        if (firstInput) {
            firstInput.focus();
        }
        
        // Form submission handling
        trackingForm.addEventListener('submit', function(e) {
            const orderIdInput = this.querySelector('#order_id');
            const trackingKeyInput = this.querySelector('#tracking_key');
            
            // Basic validation
            if (!orderIdInput.value || !trackingKeyInput.value) {
                e.preventDefault();
                showErrorMessage('Please enter both order number and tracking key.');
                return false;
            }
            
            // Show loading state
            const submitButton = this.querySelector('.rdm-track-button');
            if (submitButton) {
                submitButton.textContent = 'Tracking...';
                submitButton.disabled = true;
            }
        });
    }
    
    // Connection status monitoring
    monitorConnectionStatus();
    
    // Clean up on page unload
    window.addEventListener('beforeunload', function() {
        if (updateTimer) {
            clearInterval(updateTimer);
        }
    });
    
    // Initialize map if Google Maps is already loaded
    if (typeof google !== 'undefined' && google.maps) {
        initTrackingMap();
    }
    
    // Add keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Press 'R' to refresh (when not in input fields)
        if (e.key === 'r' && !e.target.matches('input, textarea') && !e.ctrlKey && !e.metaKey) {
            e.preventDefault();
            updateTrackingData();
        }
        
        // Press 'Escape' to hide error messages
        if (e.key === 'Escape') {
            hideErrorMessage();
        }
    });
});

/**
 * Monitor connection status
 */
function monitorConnectionStatus() {
    const createStatusIndicator = () => {
        const indicator = document.createElement('div');
        indicator.id = 'rdm-connection-status';
        indicator.className = 'rdm-connection-status';
        document.body.appendChild(indicator);
        return indicator;
    };
    
    let statusIndicator = document.getElementById('rdm-connection-status') || createStatusIndicator();
    
    const updateStatus = (isOnline) => {
        if (isOnline) {
            statusIndicator.className = 'rdm-connection-status online';
            statusIndicator.textContent = '🟢 Online';
            setTimeout(() => {
                statusIndicator.style.display = 'none';
            }, 3000);
        } else {
            statusIndicator.className = 'rdm-connection-status offline';
            statusIndicator.textContent = '🔴 Offline';
            statusIndicator.style.display = 'block';
        }
    };
    
    // Initial status
    updateStatus(navigator.onLine);
    
    // Listen for connection changes
    window.addEventListener('online', () => {
        updateStatus(true);
        // Resume updates when back online
        if (window.rdmTrackingData && !updateTimer) {
            startTrackingUpdates();
        }
    });
    
    window.addEventListener('offline', () => {
        updateStatus(false);
        // Pause updates when offline
        if (updateTimer) {
            clearInterval(updateTimer);
            updateTimer = null;
        }
    });
}

/**
 * Handle Google Maps API load error
 */
window.gm_authFailure = function() {
    console.error('Google Maps API authentication failed');
    const mapContainer = document.getElementById('rdm-tracking-map');
    if (mapContainer) {
        mapContainer.innerHTML = `
            <div class="rdm-map-error">
                <div style="text-align: center; padding: 40px;">
                    <div style="font-size: 48px; margin-bottom: 20px;">🗺️</div>
                    <div style="font-weight: 500; margin-bottom: 10px; font-size: 18px;">
                        Map Service Temporarily Unavailable
                    </div>
                    <div style="font-size: 14px; color: #666; margin-bottom: 15px;">
                        The mapping service is currently experiencing issues. Please check the status timeline above for order updates.
                    </div>
                    <button onclick="location.reload()" style="
                        background: var(--rdm-primary-color); 
                        color: white; 
                        border: none; 
                        padding: 10px 20px; 
                        border-radius: 5px; 
                        cursor: pointer;
                        font-size: 14px;
                    ">
                        Try Again
                    </button>
                </div>
            </div>
        `;
    }
};

/**
 * Handle general map loading errors
 */
window.addEventListener('error', function(e) {
    if (e.filename && e.filename.includes('maps.googleapis.com')) {
        console.error('Google Maps failed to load:', e.error);
        window.gm_authFailure();
    }
});

// Export functions for testing (if module system is available)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        initTrackingMap,
        updateTrackingData,
        formatLastUpdate,
        showErrorMessage,
        hideErrorMessage
    };
}
