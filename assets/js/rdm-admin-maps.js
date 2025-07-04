/**
 * RestroReach Google Maps Admin JavaScript
 * Handles maps functionality in WordPress admin area
 */

// Global variables for admin maps
let rdmAdminMap = null;
let rdmAdminMarkers = [];
let rdmAdminInfoWindows = [];
let rdmAgentTrackingInterval = null;

/**
 * Initialize admin maps when Google Maps API is loaded
 */
function rdmInitAdminMaps() {
    if (typeof google === 'undefined') {
        console.error('RestroReach Admin: Google Maps API not loaded');
        return;
    }

    console.log('RestroReach Admin: Initializing Google Maps');

    // Initialize different admin map types
    if (document.getElementById('rdm-admin-agents-map')) {
        rdmInitAdminAgentsMap();
    }

    if (document.getElementById('rdm-admin-orders-map')) {
        rdmInitAdminOrdersMap();
    }

    if (document.getElementById('rdm-admin-analytics-map')) {
        rdmInitAdminAnalyticsMap();
    }
    
    // Initialize agent live view map
    if (document.getElementById('rdm-agent-live-map-canvas')) {
        rdmInitAgentLiveViewMap();
    }

    // Initialize order route map for meta box
    if (document.getElementById('rdm-order-route-map-canvas')) {
        rdmInitAdminOrderRouteMap();
    }
}

/**
 * Initialize agents overview map in admin
 */
function rdmInitAdminAgentsMap() {
    const mapElement = document.getElementById('rdm-admin-agents-map');
    if (!mapElement) return;

    rdmAdminMap = new google.maps.Map(mapElement, {
        zoom: rdmAdminMapsConfig.mapDefaults.zoom,
        center: rdmAdminMapsConfig.mapDefaults.center,
        mapTypeId: rdmAdminMapsConfig.mapDefaults.mapTypeId,
        styles: [
            {
                featureType: 'poi',
                elementType: 'labels',
                stylers: [{ visibility: 'off' }]
            }
        ]
    });

    // Load all active agents
    rdmLoadAllAgentLocations();

    // Start real-time updates
    rdmStartAdminAgentTracking();

    // Add map controls
    rdmAddAdminMapControls();
}

/**
 * Initialize orders map in admin
 */
function rdmInitAdminOrdersMap() {
    const mapElement = document.getElementById('rdm-admin-orders-map');
    if (!mapElement) return;

    rdmAdminMap = new google.maps.Map(mapElement, {
        zoom: rdmAdminMapsConfig.mapDefaults.zoom,
        center: rdmAdminMapsConfig.mapDefaults.center,
        mapTypeId: rdmAdminMapsConfig.mapDefaults.mapTypeId
    });

    // Load active orders with delivery locations
    rdmLoadActiveOrders();
}

/**
 * Initialize analytics heat map
 */
function rdmInitAdminAnalyticsMap() {
    const mapElement = document.getElementById('rdm-admin-analytics-map');
    if (!mapElement) return;

    rdmAdminMap = new google.maps.Map(mapElement, {
        zoom: rdmAdminMapsConfig.mapDefaults.zoom,
        center: rdmAdminMapsConfig.mapDefaults.center,
        mapTypeId: rdmAdminMapsConfig.mapDefaults.mapTypeId
    });

    // Load delivery analytics data
    rdmLoadDeliveryAnalytics();
}

/**
 * Load all agent locations for admin overview
 */
function rdmLoadAllAgentLocations() {
    jQuery.ajax({
        url: rdmAdminMapsConfig.ajaxUrl,
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'rdm_get_agent_locations',
            order_id: 0, // 0 = all agents
            nonce: rdmAdminMapsConfig.nonce
        },
        success: function(response) {
            if (response.success) {
                rdmUpdateAdminAgentMarkers(response.data);
                rdmUpdateAgentsList(response.data);
            }
        },
        error: function(xhr, status, error) {
            console.error('RestroReach Admin: Error loading agent locations:', error);
        }
    });
}

/**
 * Update agent markers in admin map
 */
function rdmUpdateAdminAgentMarkers(agents) {
    // Clear existing markers
    rdmAdminMarkers.forEach(marker => marker.setMap(null));
    rdmAdminMarkers = [];

    // Clear existing info windows
    rdmAdminInfoWindows.forEach(infoWindow => infoWindow.close());
    rdmAdminInfoWindows = [];

    agents.forEach(agent => {
        const isOnline = rdmIsAgentOnline(agent.timestamp);
        
        const marker = new google.maps.Marker({
            position: { lat: parseFloat(agent.lat), lng: parseFloat(agent.lng) },
            map: rdmAdminMap,
            title: `${agent.agent_name} - ${isOnline ? 'Online' : 'Offline'}`,
            icon: rdmGetAdminAgentMarkerIcon(agent.battery_level, isOnline)
        });

        // Create info window with admin-specific content
        const infoWindow = new google.maps.InfoWindow({
            content: rdmCreateAdminAgentInfoWindow(agent, isOnline)
        });

        marker.addListener('click', () => {
            // Close other info windows
            rdmAdminInfoWindows.forEach(window => window.close());
            infoWindow.open(rdmAdminMap, marker);
        });

        rdmAdminMarkers.push(marker);
        rdmAdminInfoWindows.push(infoWindow);
    });

    // Adjust map bounds if there are markers
    if (rdmAdminMarkers.length > 0) {
        const bounds = new google.maps.LatLngBounds();
        rdmAdminMarkers.forEach(marker => bounds.extend(marker.getPosition()));
        
        // Add some padding to the bounds
        rdmAdminMap.fitBounds(bounds, { padding: 50 });
        
        // Don't zoom in too much for single markers
        if (rdmAdminMarkers.length === 1) {
            const listener = google.maps.event.addListener(rdmAdminMap, 'bounds_changed', () => {
                if (rdmAdminMap.getZoom() > 15) {
                    rdmAdminMap.setZoom(15);
                }
                google.maps.event.removeListener(listener);
            });
        }
    }
}

/**
 * Create admin-specific info window for agents
 */
function rdmCreateAdminAgentInfoWindow(agent, isOnline) {
    const lastUpdate = new Date(agent.timestamp);
    const timeAgo = rdmAdminTimeAgo(lastUpdate);
    const statusClass = isOnline ? 'online' : 'offline';
    
    return `
        <div class="rdm-admin-agent-info">
            <div class="rdm-agent-header">
                <h4>${agent.agent_name}</h4>
                <span class="rdm-agent-status rdm-status-${statusClass}">
                    ${isOnline ? 'Online' : 'Offline'}
                </span>
            </div>
            
            <div class="rdm-agent-details">
                <p><strong>Last Update:</strong> ${timeAgo}</p>
                <p><strong>Battery:</strong> 
                    <span class="rdm-battery-level" data-level="${agent.battery_level}">
                        ${agent.battery_level}%
                    </span>
                </p>
                
                <div class="rdm-admin-battery-bar">
                    <div class="rdm-battery-fill" style="width: ${agent.battery_level}%"></div>
                </div>
            </div>
            
            <div class="rdm-agent-actions">
                <button class="button button-small" onclick="rdmViewAgentHistory(${agent.agent_id})">
                    View History
                </button>
                <button class="button button-small" onclick="rdmContactAgent(${agent.agent_id})">
                    Contact
                </button>
            </div>
            
            <div class="rdm-agent-coordinates">
                <small>Lat: ${parseFloat(agent.lat).toFixed(6)}, Lng: ${parseFloat(agent.lng).toFixed(6)}</small>
            </div>
        </div>
    `;
}

/**
 * Get admin-specific marker icon
 */
function rdmGetAdminAgentMarkerIcon(batteryLevel, isOnline) {
    let color = isOnline ? '#22c55e' : '#6b7280'; // Green for online, gray for offline
    let strokeColor = '#ffffff';
    
    if (isOnline) {
        if (batteryLevel < 20) {
            color = '#ef4444'; // Red for critical battery
        } else if (batteryLevel < 40) {
            color = '#f59e0b'; // Orange for low battery
        }
    }

    return {
        path: google.maps.SymbolPath.CIRCLE,
        fillColor: color,
        fillOpacity: 1,
        strokeColor: strokeColor,
        strokeWeight: 2,
        scale: isOnline ? 8 : 6
    };
}

/**
 * Check if agent is considered online
 */
function rdmIsAgentOnline(timestamp) {
    const lastUpdate = new Date(timestamp);
    const now = new Date();
    const diffMinutes = (now - lastUpdate) / (1000 * 60);
    
    return diffMinutes <= 5; // Consider online if last update within 5 minutes
}

/**
 * Start real-time agent tracking in admin
 */
function rdmStartAdminAgentTracking() {
    // Update every 30 seconds
    const updateInterval = 30000;
    
    rdmAgentTrackingInterval = setInterval(() => {
        rdmLoadAllAgentLocations();
    }, updateInterval);
}

/**
 * Stop agent tracking
 */
function rdmStopAdminAgentTracking() {
    if (rdmAgentTrackingInterval) {
        clearInterval(rdmAgentTrackingInterval);
        rdmAgentTrackingInterval = null;
    }
}

/**
 * Update agents list in sidebar
 */
function rdmUpdateAgentsList(agents) {
    const agentsListElement = document.getElementById('rdm-agents-list');
    if (!agentsListElement) return;

    let html = '';
    
    if (agents.length === 0) {
        html = '<p class="rdm-no-agents">No active agents found</p>';
    } else {
        html = '<div class="rdm-agents-grid">';
        
        agents.forEach(agent => {
            const isOnline = rdmIsAgentOnline(agent.timestamp);
            const statusClass = isOnline ? 'online' : 'offline';
            const lastUpdate = rdmAdminTimeAgo(new Date(agent.timestamp));
            
            html += `
                <div class="rdm-agent-card rdm-agent-${statusClass}" data-agent-id="${agent.agent_id}">
                    <div class="rdm-agent-card-header">
                        <h5>${agent.agent_name}</h5>
                        <span class="rdm-status-indicator rdm-status-${statusClass}"></span>
                    </div>
                    
                    <div class="rdm-agent-card-body">
                        <p class="rdm-agent-status">${isOnline ? 'Online' : 'Offline'}</p>
                        <p class="rdm-agent-battery">Battery: ${agent.battery_level}%</p>
                        <p class="rdm-agent-update">Updated: ${lastUpdate}</p>
                    </div>
                    
                    <div class="rdm-agent-card-actions">
                        <button class="button button-small rdm-btn-center-agent" 
                                onclick="rdmCenterOnAgent(${agent.agent_id}, ${agent.lat}, ${agent.lng})">
                            Center Map
                        </button>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
    }
    
    agentsListElement.innerHTML = html;
}

/**
 * Center map on specific agent
 */
function rdmCenterOnAgent(agentId, lat, lng) {
    if (rdmAdminMap) {
        const position = new google.maps.LatLng(lat, lng);
        rdmAdminMap.setCenter(position);
        rdmAdminMap.setZoom(16);
        
        // Find and open the info window for this agent
        rdmAdminMarkers.forEach((marker, index) => {
            if (marker.getPosition().equals(position)) {
                rdmAdminInfoWindows[index].open(rdmAdminMap, marker);
            }
        });
    }
}

/**
 * View agent location history
 */
function rdmViewAgentHistory(agentId) {
    // Open modal or navigate to history page
    const url = `admin.php?page=rdm-agents&action=history&agent_id=${agentId}`;
    window.location.href = url;
}

/**
 * Contact agent (placeholder for future implementation)
 */
function rdmContactAgent(agentId) {
    alert('Contact agent functionality will be implemented in future updates.');
}

/**
 * Load active orders for orders map
 */
function rdmLoadActiveOrders() {
    jQuery.ajax({
        url: rdmAdminMapsConfig.ajaxUrl,
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'rdm_get_active_orders_map',
            nonce: rdmAdminMapsConfig.nonce
        },
        success: function(response) {
            if (response.success) {
                rdmDisplayOrdersOnMap(response.data);
            }
        },
        error: function(xhr, status, error) {
            console.error('RestroReach Admin: Error loading orders:', error);
        }
    });
}

/**
 * Display orders on map
 */
function rdmDisplayOrdersOnMap(orders) {
    // Clear existing markers
    rdmAdminMarkers.forEach(marker => marker.setMap(null));
    rdmAdminMarkers = [];

    orders.forEach(order => {
        if (order.delivery_lat && order.delivery_lng) {
            const marker = new google.maps.Marker({
                position: { 
                    lat: parseFloat(order.delivery_lat), 
                    lng: parseFloat(order.delivery_lng) 
                },
                map: rdmAdminMap,
                title: `Order #${order.order_id}`,
                icon: rdmGetOrderStatusIcon(order.status)
            });

            const infoWindow = new google.maps.InfoWindow({
                content: rdmCreateOrderInfoWindow(order)
            });

            marker.addListener('click', () => {
                rdmAdminInfoWindows.forEach(window => window.close());
                infoWindow.open(rdmAdminMap, marker);
            });

            rdmAdminMarkers.push(marker);
            rdmAdminInfoWindows.push(infoWindow);
        }
    });

    // Fit bounds to show all orders
    if (rdmAdminMarkers.length > 0) {
        const bounds = new google.maps.LatLngBounds();
        rdmAdminMarkers.forEach(marker => bounds.extend(marker.getPosition()));
        rdmAdminMap.fitBounds(bounds);
    }
}

/**
 * Get icon for order status
 */
function rdmGetOrderStatusIcon(status) {
    const iconColors = {
        'preparing': '#f59e0b',      // Orange
        'ready': '#3b82f6',          // Blue
        'out-for-delivery': '#8b5cf6', // Purple
        'delivered': '#22c55e'       // Green
    };

    const color = iconColors[status] || '#6b7280';

    return {
        path: google.maps.SymbolPath.CIRCLE,
        fillColor: color,
        fillOpacity: 0.8,
        strokeColor: '#ffffff',
        strokeWeight: 2,
        scale: 6
    };
}

/**
 * Create order info window
 */
function rdmCreateOrderInfoWindow(order) {
    return `
        <div class="rdm-order-info">
            <h4>Order #${order.order_id}</h4>
            <p><strong>Status:</strong> ${order.status_text}</p>
            <p><strong>Customer:</strong> ${order.customer_name}</p>
            <p><strong>Address:</strong> ${order.delivery_address}</p>
            ${order.agent_name ? `<p><strong>Agent:</strong> ${order.agent_name}</p>` : ''}
            <div class="rdm-order-actions">
                <a href="post.php?post=${order.order_id}&action=edit" class="button button-small">
                    View Order
                </a>
            </div>
        </div>
    `;
}

/**
 * Add admin map controls
 */
function rdmAddAdminMapControls() {
    // Create control panel
    const controlDiv = document.createElement('div');
    controlDiv.className = 'rdm-map-controls';
    controlDiv.innerHTML = `
        <div class="rdm-control-group">
            <button id="rdm-refresh-agents" class="button">Refresh Agents</button>
            <button id="rdm-center-all" class="button">Center All</button>
            <button id="rdm-toggle-tracking" class="button button-primary">
                <span id="rdm-tracking-status">Stop Tracking</span>
            </button>
        </div>
    `;

    rdmAdminMap.controls[google.maps.ControlPosition.TOP_RIGHT].push(controlDiv);

    // Bind control events
    document.getElementById('rdm-refresh-agents').addEventListener('click', () => {
        rdmLoadAllAgentLocations();
    });

    document.getElementById('rdm-center-all').addEventListener('click', () => {
        if (rdmAdminMarkers.length > 0) {
            const bounds = new google.maps.LatLngBounds();
            rdmAdminMarkers.forEach(marker => bounds.extend(marker.getPosition()));
            rdmAdminMap.fitBounds(bounds);
        }
    });

    document.getElementById('rdm-toggle-tracking').addEventListener('click', (e) => {
        const button = e.target;
        const statusSpan = document.getElementById('rdm-tracking-status');
        
        if (rdmAgentTrackingInterval) {
            rdmStopAdminAgentTracking();
            statusSpan.textContent = 'Start Tracking';
            button.classList.remove('button-primary');
        } else {
            rdmStartAdminAgentTracking();
            statusSpan.textContent = 'Stop Tracking';
            button.classList.add('button-primary');
        }
    });
}

/**
 * Admin-specific time ago function
 */
function rdmAdminTimeAgo(date) {
    const now = new Date();
    const diffMs = now - date;
    const diffSecs = Math.floor(diffMs / 1000);
    const diffMins = Math.floor(diffSecs / 60);
    const diffHours = Math.floor(diffMins / 60);

    if (diffSecs < 60) {
        return 'Just now';
    } else if (diffMins < 60) {
        return `${diffMins}m ago`;
    } else if (diffHours < 24) {
        return `${diffHours}h ago`;
    } else {
        return date.toLocaleDateString();
    }
}

/**
 * Load delivery analytics for heat map
 */
function rdmLoadDeliveryAnalytics() {
    jQuery.ajax({
        url: rdmAdminMapsConfig.ajaxUrl,
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'rdm_get_delivery_analytics',
            nonce: rdmAdminMapsConfig.nonce
        },
        success: function(response) {
            if (response.success) {
                rdmCreateHeatMap(response.data);
            }
        },
        error: function(xhr, status, error) {
            console.error('RestroReach Admin: Error loading analytics:', error);
        }
    });
}

/**
 * Create heat map from delivery data
 */
function rdmCreateHeatMap(deliveryData) {
    const heatmapData = deliveryData.map(point => ({
        location: new google.maps.LatLng(point.lat, point.lng),
        weight: point.count
    }));

    const heatmap = new google.maps.visualization.HeatmapLayer({
        data: heatmapData,
        map: rdmAdminMap
    });

    heatmap.setOptions({
        radius: 20,
        opacity: 0.6
    });
}

/**
 * Initialize agent live view map for single agent tracking
 */
function rdmInitAgentLiveViewMap() {
    const mapElement = document.getElementById('rdm-agent-live-map-canvas');
    if (!mapElement) return;

    // Check if agent location data is available
    if (typeof rdmAgentLocationData === 'undefined') {
        console.error('RestroReach Admin: Agent location data not found');
        mapElement.innerHTML = '<div style="padding: 20px; text-align: center; color: #d63638;">Error: Agent location data not available</div>';
        return;
    }

    // Set default map center
    let defaultCenter = rdmAdminMapsConfig && rdmAdminMapsConfig.mapDefaults 
        ? rdmAdminMapsConfig.mapDefaults.center 
        : { lat: 40.7128, lng: -74.0060 }; // Default to NYC if config not available

    // If agent has location, center on agent; otherwise use default
    let mapCenter = defaultCenter;
    let mapZoom = 10;

    if (rdmAgentLocationData.has_location && rdmAgentLocationData.lat && rdmAgentLocationData.lng) {
        mapCenter = {
            lat: parseFloat(rdmAgentLocationData.lat),
            lng: parseFloat(rdmAgentLocationData.lng)
        };
        mapZoom = 15; // Closer zoom for agent location
    }

    // Create the map
    const map = new google.maps.Map(mapElement, {
        zoom: mapZoom,
        center: mapCenter,
        mapTypeId: 'roadmap',
        styles: [
            {
                featureType: 'poi',
                elementType: 'labels',
                stylers: [{ visibility: 'off' }]
            }
        ]
    });

    // If agent has location, add marker and info window
    if (rdmAgentLocationData.has_location && rdmAgentLocationData.lat && rdmAgentLocationData.lng) {
        const agentPosition = {
            lat: parseFloat(rdmAgentLocationData.lat),
            lng: parseFloat(rdmAgentLocationData.lng)
        };

        // Create custom marker icon based on battery level
        const markerIcon = rdmGetAgentLiveViewMarkerIcon(rdmAgentLocationData.battery_level);

        // Create marker
        const marker = new google.maps.Marker({
            position: agentPosition,
            map: map,
            title: rdmAgentLocationData.agent_name,
            icon: markerIcon,
            animation: google.maps.Animation.DROP
        });

        // Create info window content
        const infoWindowContent = rdmCreateAgentInfoWindowContent(rdmAgentLocationData);

        // Create info window
        const infoWindow = new google.maps.InfoWindow({
            content: infoWindowContent
        });

        // Show info window automatically
        infoWindow.open(map, marker);

        // Add click listener to marker
        marker.addListener('click', function() {
            infoWindow.open(map, marker);
        });

        // Add accuracy circle if available
        if (rdmAgentLocationData.accuracy && rdmAgentLocationData.accuracy > 0) {
            const accuracyCircle = new google.maps.Circle({
                strokeColor: '#4285f4',
                strokeOpacity: 0.8,
                strokeWeight: 2,
                fillColor: '#4285f4',
                fillOpacity: 0.15,
                map: map,
                center: agentPosition,
                radius: parseFloat(rdmAgentLocationData.accuracy)
            });
        }
    } else {
        // No location data available - show message in map
        const noLocationOverlay = new google.maps.OverlayView();
        noLocationOverlay.draw = function() {
            const div = document.createElement('div');
            div.style.position = 'absolute';
            div.style.top = '50%';
            div.style.left = '50%';
            div.style.transform = 'translate(-50%, -50%)';
            div.style.backgroundColor = 'rgba(255, 255, 255, 0.9)';
            div.style.border = '2px solid #d63638';
            div.style.borderRadius = '8px';
            div.style.padding = '15px';
            div.style.textAlign = 'center';
            div.style.color = '#d63638';
            div.style.fontWeight = 'bold';
            div.innerHTML = 'No location data available for ' + rdmAgentLocationData.agent_name;
            
            this.getPanes().floatPane.appendChild(div);
        };
        noLocationOverlay.setMap(map);
    }
}

/**
 * Get marker icon for agent live view based on battery level
 */
function rdmGetAgentLiveViewMarkerIcon(batteryLevel) {
    let color = '#4285f4'; // Default blue
    
    if (batteryLevel !== null && batteryLevel !== undefined) {
        if (batteryLevel > 50) {
            color = '#34a853'; // Green for good battery
        } else if (batteryLevel > 20) {
            color = '#fbbc04'; // Yellow for medium battery
        } else {
            color = '#ea4335'; // Red for low battery
        }
    }

    return {
        path: google.maps.SymbolPath.CIRCLE,
        scale: 12,
        fillColor: color,
        fillOpacity: 0.8,
        strokeColor: '#ffffff',
        strokeWeight: 3
    };
}

/**
 * Create info window content for agent live view
 */
function rdmCreateAgentInfoWindowContent(agentData) {
    let content = '<div style="max-width: 250px;">';
    content += '<h4 style="margin: 0 0 10px 0; color: #23282d;">' + agentData.agent_name + '</h4>';
    
    if (agentData.formatted_time) {
        content += '<p style="margin: 5px 0;"><strong>Last Update:</strong><br>' + agentData.formatted_time + '</p>';
    }
    
    if (agentData.accuracy !== null && agentData.accuracy !== undefined) {
        content += '<p style="margin: 5px 0;"><strong>Accuracy:</strong> ' + parseFloat(agentData.accuracy).toFixed(1) + 'm</p>';
    }
    
    if (agentData.battery_level !== null && agentData.battery_level !== undefined) {
        const batteryColor = agentData.battery_level > 20 ? '#34a853' : '#ea4335';
        content += '<p style="margin: 5px 0;"><strong>Battery:</strong> <span style="color: ' + batteryColor + ';">' + agentData.battery_level + '%</span></p>';
    }
    
    content += '<p style="margin: 5px 0; font-size: 12px; color: #666;">Lat: ' + parseFloat(agentData.lat).toFixed(6) + '<br>';
    content += 'Lng: ' + parseFloat(agentData.lng).toFixed(6) + '</p>';
    content += '</div>';
    
    return content;
}

/**
 * Initialize order route map for WooCommerce order meta box
 */
function rdmInitAdminOrderRouteMap() {
    // Enhanced error checking and logging
    console.log('RestroReach: Starting order route map initialization');
    
    // Check if we have the necessary data
    if (typeof rdmOrderRouteData === 'undefined') {
        console.error('RestroReach: Order route data not found');
        const mapCanvas = document.getElementById('rdm-order-route-map-canvas');
        if (mapCanvas) {
            mapCanvas.innerHTML = '<div style="padding: 20px; text-align: center; color: #dc3545; border: 1px solid #f5c6cb; background-color: #f8d7da; border-radius: 4px;">Order route data is not available. Please refresh the page.</div>';
        }
        return;
    }

    const mapCanvas = document.getElementById('rdm-order-route-map-canvas');
    if (!mapCanvas) {
        console.error('RestroReach: Order route map canvas not found');
        return;
    }

    // Validate required data
    const requiredData = ['customerAddress', 'googleMapsApiKey'];
    const missingData = requiredData.filter(key => !rdmOrderRouteData[key]);
    
    if (missingData.length > 0) {
        console.error('RestroReach: Missing required data:', missingData);
        mapCanvas.innerHTML = `<div style="padding: 20px; text-align: center; color: #856404; background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;">
            <strong>Cannot display route map</strong><br>
            Missing data: ${missingData.join(', ')}<br>
            Please check your configuration.
        </div>`;
        return;
    }

    console.log('RestroReach: Order route data validated:', rdmOrderRouteData);

    // Determine map center
    let mapCenter = { 
        lat: parseFloat(rdmOrderRouteData.defaultLat), 
        lng: parseFloat(rdmOrderRouteData.defaultLng) 
    };

    if (rdmOrderRouteData.restaurantCoords && rdmOrderRouteData.restaurantCoords.lat) {
        mapCenter = { 
            lat: parseFloat(rdmOrderRouteData.restaurantCoords.lat), 
            lng: parseFloat(rdmOrderRouteData.restaurantCoords.lng) 
        };
    }

    // Initialize the map
    const map = new google.maps.Map(mapCanvas, {
        center: mapCenter,
        zoom: 12,
        mapTypeId: google.maps.MapTypeId.ROADMAP,
        styles: [
            {
                featureType: 'poi',
                elementType: 'labels',
                stylers: [{ visibility: 'off' }]
            }
        ]
    });

    // Initialize directions service and renderer
    const directionsService = new google.maps.DirectionsService();
    const directionsRenderer = new google.maps.DirectionsRenderer({
        suppressMarkers: false, // We'll add custom markers
        polylineOptions: {
            strokeColor: '#4285F4',
            strokeWeight: 4,
            strokeOpacity: 0.8
        }
    });
    directionsRenderer.setMap(map);

    // Initialize geocoder
    const geocoder = new google.maps.Geocoder();

    // Add restaurant marker if coordinates available
    let restaurantMarker = null;
    if (rdmOrderRouteData.restaurantCoords && rdmOrderRouteData.restaurantCoords.lat) {
        restaurantMarker = new google.maps.Marker({
            position: {
                lat: parseFloat(rdmOrderRouteData.restaurantCoords.lat),
                lng: parseFloat(rdmOrderRouteData.restaurantCoords.lng)
            },
            map: map,
            title: 'Restaurant',
            icon: {
                url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="12" cy="12" r="10" fill="#e74c3c" stroke="#fff" stroke-width="2"/>
                        <text x="12" y="16" text-anchor="middle" fill="white" font-size="12" font-weight="bold">R</text>
                    </svg>
                `),
                scaledSize: new google.maps.Size(24, 24),
                anchor: new google.maps.Point(12, 12)
            }
        });

        // Restaurant info window
        const restaurantInfoWindow = new google.maps.InfoWindow({
            content: '<div style="padding: 5px;"><strong>Restaurant Location</strong><br/>Starting point for delivery</div>'
        });

        restaurantMarker.addListener('click', function() {
            restaurantInfoWindow.open(map, restaurantMarker);
        });
    }

    // Add agent marker if location available
    let agentMarker = null;
    if (rdmOrderRouteData.agentLocation && rdmOrderRouteData.agentLocation.latitude) {
        agentMarker = new google.maps.Marker({
            position: {
                lat: parseFloat(rdmOrderRouteData.agentLocation.latitude),
                lng: parseFloat(rdmOrderRouteData.agentLocation.longitude)
            },
            map: map,
            title: 'Delivery Agent: ' + rdmOrderRouteData.agentName,
            icon: {
                url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="12" cy="12" r="10" fill="#2ecc71" stroke="#fff" stroke-width="2"/>
                        <text x="12" y="16" text-anchor="middle" fill="white" font-size="12" font-weight="bold">A</text>
                    </svg>
                `),
                scaledSize: new google.maps.Size(24, 24),
                anchor: new google.maps.Point(12, 12)
            }
        });

        // Agent info window
        const agentInfoContent = `
            <div style="padding: 5px;">
                <strong>${rdmOrderRouteData.agentName}</strong><br/>
                Current Location<br/>
                <small>Updated: ${new Date(rdmOrderRouteData.agentLocation.timestamp).toLocaleString()}</small>
                ${rdmOrderRouteData.agentLocation.battery_level ? '<br/><small>Battery: ' + rdmOrderRouteData.agentLocation.battery_level + '%</small>' : ''}
            </div>
        `;

        const agentInfoWindow = new google.maps.InfoWindow({
            content: agentInfoContent
        });

        agentMarker.addListener('click', function() {
            agentInfoWindow.open(map, agentMarker);
        });
    }

    // Geocode customer address and create route
    if (rdmOrderRouteData.customerAddress && rdmOrderRouteData.restaurantCoords) {
        geocoder.geocode({ 'address': rdmOrderRouteData.customerAddress }, function(results, status) {
            if (status === 'OK' && results[0]) {
                const customerLocation = results[0].geometry.location;

                // Add customer marker
                const customerMarker = new google.maps.Marker({
                    position: customerLocation,
                    map: map,
                    title: 'Customer Delivery Address',
                    icon: {
                        url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="12" cy="12" r="10" fill="#f39c12" stroke="#fff" stroke-width="2"/>
                                <text x="12" y="16" text-anchor="middle" fill="white" font-size="12" font-weight="bold">C</text>
                            </svg>
                        `),
                        scaledSize: new google.maps.Size(24, 24),
                        anchor: new google.maps.Point(12, 12)
                    }
                });

                // Customer info window
                const customerInfoWindow = new google.maps.InfoWindow({
                    content: '<div style="padding: 5px;"><strong>Customer Delivery Address</strong><br/>' + 
                             rdmOrderRouteData.customerAddress.replace(/\n/g, '<br/>') + '</div>'
                });

                customerMarker.addListener('click', function() {
                    customerInfoWindow.open(map, customerMarker);
                });

                // Calculate and display route
                const request = {
                    origin: new google.maps.LatLng(
                        parseFloat(rdmOrderRouteData.restaurantCoords.lat),
                        parseFloat(rdmOrderRouteData.restaurantCoords.lng)
                    ),
                    destination: customerLocation,
                    travelMode: google.maps.TravelMode.DRIVING,
                    unitSystem: google.maps.UnitSystem.METRIC,
                    optimizeWaypoints: true
                };

                directionsService.route(request, function(response, status) {
                    if (status === 'OK') {
                        directionsRenderer.setDirections(response);
                        
                        // Show route information
                        const route = response.routes[0];
                        const leg = route.legs[0];
                        
                        mapCanvas.insertAdjacentHTML('afterend', `
                            <div style="margin-top: 10px; padding: 10px; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px;">
                                <h5 style="margin: 0 0 5px 0;">Route Information</h5>
                                <p style="margin: 5px 0;"><strong>Distance:</strong> ${leg.distance.text}</p>
                                <p style="margin: 5px 0;"><strong>Estimated Time:</strong> ${leg.duration.text}</p>
                                <p style="margin: 5px 0;"><strong>Start:</strong> ${leg.start_address}</p>
                                <p style="margin: 5px 0;"><strong>End:</strong> ${leg.end_address}</p>
                            </div>
                        `);
                        
                        console.log('RestroReach: Route successfully calculated and displayed');
                    } else {
                        console.error('RestroReach: Directions request failed due to ' + status);
                        mapCanvas.innerHTML = '<div style="padding: 20px; text-align: center; color: #dc3545;">Could not display route. Reason: ' + status + '</div>';
                    }
                });

                // Adjust map bounds to include all markers
                const bounds = new google.maps.LatLngBounds();
                bounds.extend(new google.maps.LatLng(
                    parseFloat(rdmOrderRouteData.restaurantCoords.lat),
                    parseFloat(rdmOrderRouteData.restaurantCoords.lng)
                ));
                bounds.extend(customerLocation);
                
                if (agentMarker) {
                    bounds.extend(agentMarker.getPosition());
                }
                
                map.fitBounds(bounds);
                
                // Ensure minimum zoom level
                google.maps.event.addListenerOnce(map, 'bounds_changed', function() {
                    if (map.getZoom() > 15) {
                        map.setZoom(15);
                    }
                });

            } else {
                console.error('RestroReach: Geocoding failed due to ' + status);
                mapCanvas.innerHTML = '<div style="padding: 20px; text-align: center; color: #dc3545;">Could not geocode customer address. Status: ' + status + '</div>';
            }
        });
    } else {
        console.warn('RestroReach: Missing required data for route calculation');
        mapCanvas.innerHTML = '<div style="padding: 20px; text-align: center; color: #6c757d;">Insufficient data to display route map.</div>';
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('RestroReach Admin: DOM ready, waiting for Google Maps API');
});

// Cleanup when page unloads
window.addEventListener('beforeunload', function() {
    rdmStopAdminAgentTracking();
});

// Expose functions globally
window.rdmInitAdminMaps = rdmInitAdminMaps;
window.rdmCenterOnAgent = rdmCenterOnAgent;
window.rdmViewAgentHistory = rdmViewAgentHistory;
window.rdmContactAgent = rdmContactAgent;
