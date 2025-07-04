/**
 * Restaurant Delivery Manager - Customer Order Tracking
 * Real-time order tracking interface for customers
 *
 * @package RestaurantDeliveryManager
 * @subpackage CustomerTracking
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Configuration
    const RDM_TRACKING_CONFIG = {
        updateInterval: 30000, // 30 seconds
        mapOptions: {
            zoom: 14,
            center: { lat: 40.7128, lng: -74.0060 }, // Default to NYC
            disableDefaultUI: false,
            zoomControl: true,
            streetViewControl: false,
            fullscreenControl: true
        }
    };

    // State management
    const RDM_TRACKING_STATE = {
        orderId: null,
        orderKey: null,
        map: null,
        markers: {
            restaurant: null,
            customer: null,
            agent: null
        },
        updateTimer: null,
        isTracking: false,
        lastAgentPosition: null
    };

    /**
     * Initialize customer tracking
     */
    function init() {
        $('#tracking-form').on('submit', handleTrackingForm);
        
        // If order details are pre-filled, start tracking automatically
        const orderIdInput = $('#order-id');
        const orderKeyInput = $('#order-key');
        
        if (orderIdInput.val() && orderKeyInput.val()) {
            RDM_TRACKING_STATE.orderId = orderIdInput.val();
            RDM_TRACKING_STATE.orderKey = orderKeyInput.val();
            loadOrderStatus();
        }
    }

    /**
     * Handle tracking form submission
     */
    function handleTrackingForm(e) {
        e.preventDefault();
        
        const orderId = $('#order-id').val().trim();
        const orderKey = $('#order-key').val().trim();
        
        if (!orderId || !orderKey) {
            showError(rdm_tracking.strings.error);
            return;
        }
        
        RDM_TRACKING_STATE.orderId = orderId;
        RDM_TRACKING_STATE.orderKey = orderKey;
        
        loadOrderStatus();
    }

    /**
     * Load order status and tracking information
     */
    function loadOrderStatus() {
        showLoading();
        
        $.ajax({
            url: rdm_tracking.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'rdm_get_order_status',
                order_id: RDM_TRACKING_STATE.orderId,
                order_key: RDM_TRACKING_STATE.orderKey,
                nonce: rdm_tracking.nonce
            },
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    displayTrackingResults(response.data);
                    startRealTimeUpdates();
                } else {
                    showError(response.data || rdm_tracking.strings.error);
                }
            },
            error: function() {
                hideLoading();
                showError(rdm_tracking.strings.error);
            }
        });
    }

    /**
     * Display tracking results
     */
    function displayTrackingResults(data) {
        const $results = $('#tracking-results');
        
        // Hide form and show results
        $('.tracking-form').hide();
        $results.show();
        
        // Build tracking interface
        const html = `
            <div class="rdm-tracking-header">
                <h3>Order #${data.order_id} Tracking</h3>
                <p class="order-info">
                    <span class="order-total">${data.total}</span> • 
                    <span class="order-date">${formatDate(data.date_created)}</span>
                </p>
            </div>
            
            <div class="rdm-tracking-status">
                <div class="status-indicator status-${data.status}">
                    <span class="status-text">${data.status_name}</span>
                    <div class="status-progress">
                        <div class="progress-bar" data-status="${data.status}"></div>
                    </div>
                </div>
            </div>
            
            <div class="rdm-tracking-timeline">
                ${buildStatusTimeline(data)}
            </div>
            
            <div class="rdm-tracking-details">
                ${buildTrackingDetails(data.tracking_info)}
            </div>
            
            <div id="rdm-tracking-map" class="rdm-tracking-map" style="height: 400px; margin-top: 20px;"></div>
            
            <div class="rdm-tracking-actions">
                <button id="refresh-tracking" class="rdm-btn rdm-btn-primary">
                    <span class="dashicons dashicons-update"></span>
                    Refresh Status
                </button>
                <button id="new-tracking" class="rdm-btn rdm-btn-secondary">
                    Track Another Order
                </button>
            </div>
        `;
        
        $results.html(html);
        
        // Initialize map
        initializeTrackingMap(data);
        
        // Bind events
        bindTrackingEvents();
        
        // Update progress bar
        updateProgressBar(data.status);
    }

    /**
     * Build status timeline
     */
    function buildStatusTimeline(data) {
        const statuses = [
            { key: 'processing', label: 'Order Received', icon: 'dashicons-yes' },
            { key: 'preparing', label: 'Preparing Food', icon: 'dashicons-clock' },
            { key: 'ready', label: 'Ready for Pickup', icon: 'dashicons-food' },
            { key: 'out-for-delivery', label: 'Out for Delivery', icon: 'dashicons-car' },
            { key: 'delivered', label: 'Delivered', icon: 'dashicons-yes-alt' }
        ];
        
        let timelineHtml = '<div class="status-timeline">';
        
        statuses.forEach((status, index) => {
            const isCompleted = getStatusOrder(data.status) >= getStatusOrder(status.key);
            const isCurrent = data.status === status.key;
            
            timelineHtml += `
                <div class="timeline-item ${isCompleted ? 'completed' : ''} ${isCurrent ? 'current' : ''}">
                    <div class="timeline-icon">
                        <span class="dashicons ${status.icon}"></span>
                    </div>
                    <div class="timeline-content">
                        <h4>${status.label}</h4>
                        ${isCurrent ? '<p class="current-status">Current Status</p>' : ''}
                    </div>
                </div>
            `;
        });
        
        timelineHtml += '</div>';
        return timelineHtml;
    }

    /**
     * Build tracking details section
     */
    function buildTrackingDetails(trackingInfo) {
        if (!trackingInfo || trackingInfo.status === 'pending') {
            return `
                <div class="tracking-message">
                    <p>${trackingInfo ? trackingInfo.message : 'Order is being prepared'}</p>
                </div>
            `;
        }
        
        let detailsHtml = '<div class="tracking-details-grid">';
        
        if (trackingInfo.agent_name) {
            detailsHtml += `
                <div class="detail-item">
                    <h4>Delivery Agent</h4>
                    <p>${trackingInfo.agent_name}</p>
                </div>
            `;
        }
        
        if (trackingInfo.agent_phone) {
            detailsHtml += `
                <div class="detail-item">
                    <h4>Contact</h4>
                    <p><a href="tel:${trackingInfo.agent_phone}">${trackingInfo.agent_phone}</a></p>
                </div>
            `;
        }
        
        if (trackingInfo.assigned_at) {
            detailsHtml += `
                <div class="detail-item">
                    <h4>Assigned At</h4>
                    <p>${formatDate(trackingInfo.assigned_at)}</p>
                </div>
            `;
        }
        
        if (trackingInfo.current_location) {
            detailsHtml += `
                <div class="detail-item">
                    <h4>Agent Location</h4>
                    <p id="agent-location-text">Updating...</p>
                </div>
            `;
        }
        
        detailsHtml += '</div>';
        return detailsHtml;
    }

    /**
     * Initialize tracking map
     */
    function initializeTrackingMap(data) {
        const mapElement = document.getElementById('rdm-tracking-map');
        if (!mapElement || typeof google === 'undefined') {
            return;
        }
        
        RDM_TRACKING_STATE.map = new google.maps.Map(mapElement, RDM_TRACKING_CONFIG.mapOptions);
        
        // Load order locations and display on map
        loadOrderLocations(data.order_id);
    }

    /**
     * Load order locations for map display
     */
    function loadOrderLocations(orderId) {
        $.ajax({
            url: rdm_tracking.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'rdm_get_order_locations',
                order_id: orderId,
                order_key: RDM_TRACKING_STATE.orderKey,
                nonce: rdm_tracking.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    displayMapLocations(response.data);
                }
            },
            error: function() {
                console.log('Failed to load map locations');
            }
        });
    }

    /**
     * Display locations on map
     */
    function displayMapLocations(locations) {
        if (!RDM_TRACKING_STATE.map) return;
        
        const bounds = new google.maps.LatLngBounds();
        
        // Restaurant marker
        if (locations.restaurant) {
            RDM_TRACKING_STATE.markers.restaurant = new google.maps.Marker({
                position: { lat: locations.restaurant.lat, lng: locations.restaurant.lng },
                map: RDM_TRACKING_STATE.map,
                title: 'Restaurant',
                icon: {
                    url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="32" height="32">
                            <circle cx="12" cy="12" r="10" fill="#e74c3c"/>
                            <text x="12" y="16" text-anchor="middle" fill="white" font-size="12" font-weight="bold">R</text>
                        </svg>
                    `),
                    scaledSize: new google.maps.Size(32, 32)
                }
            });
            bounds.extend(RDM_TRACKING_STATE.markers.restaurant.getPosition());
        }
        
        // Customer marker
        if (locations.customer) {
            RDM_TRACKING_STATE.markers.customer = new google.maps.Marker({
                position: { lat: locations.customer.lat, lng: locations.customer.lng },
                map: RDM_TRACKING_STATE.map,
                title: 'Delivery Address',
                icon: {
                    url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="32" height="32">
                            <circle cx="12" cy="12" r="10" fill="#2ecc71"/>
                            <text x="12" y="16" text-anchor="middle" fill="white" font-size="12" font-weight="bold">D</text>
                        </svg>
                    `),
                    scaledSize: new google.maps.Size(32, 32)
                }
            });
            bounds.extend(RDM_TRACKING_STATE.markers.customer.getPosition());
        }
        
        // Agent marker (if available)
        if (locations.agent) {
            updateAgentLocation(locations.agent);
            bounds.extend({ lat: locations.agent.lat, lng: locations.agent.lng });
        }
        
        // Fit map to show all markers
        if (!bounds.isEmpty()) {
            RDM_TRACKING_STATE.map.fitBounds(bounds);
            const zoom = RDM_TRACKING_STATE.map.getZoom();
            if (zoom > 16) {
                RDM_TRACKING_STATE.map.setZoom(16);
            }
        }
    }

    /**
     * Update agent location on map
     */
    function updateAgentLocation(agentData) {
        if (!RDM_TRACKING_STATE.map) return;
        
        const position = { lat: agentData.lat, lng: agentData.lng };
        
        if (RDM_TRACKING_STATE.markers.agent) {
            // Update existing marker
            RDM_TRACKING_STATE.markers.agent.setPosition(position);
        } else {
            // Create new marker
            RDM_TRACKING_STATE.markers.agent = new google.maps.Marker({
                position: position,
                map: RDM_TRACKING_STATE.map,
                title: 'Delivery Agent',
                icon: {
                    url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="32" height="32">
                            <circle cx="12" cy="12" r="10" fill="#3498db"/>
                            <text x="12" y="16" text-anchor="middle" fill="white" font-size="12" font-weight="bold">A</text>
                        </svg>
                    `),
                    scaledSize: new google.maps.Size(32, 32)
                }
            });
        }
        
        // Update location text
        const $locationText = $('#agent-location-text');
        if ($locationText.length) {
            const lastUpdate = new Date(agentData.timestamp);
            const timeAgo = getTimeAgo(lastUpdate);
            $locationText.text(`Updated ${timeAgo}`);
        }
        
        RDM_TRACKING_STATE.lastAgentPosition = position;
    }

    /**
     * Start real-time updates
     */
    function startRealTimeUpdates() {
        if (RDM_TRACKING_STATE.updateTimer) {
            clearInterval(RDM_TRACKING_STATE.updateTimer);
        }
        
        RDM_TRACKING_STATE.isTracking = true;
        
        RDM_TRACKING_STATE.updateTimer = setInterval(() => {
            updateTrackingData();
        }, RDM_TRACKING_CONFIG.updateInterval);
    }

    /**
     * Update tracking data
     */
    function updateTrackingData() {
        $.ajax({
            url: rdm_tracking.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'rdm_get_order_status',
                order_id: RDM_TRACKING_STATE.orderId,
                order_key: RDM_TRACKING_STATE.orderKey,
                nonce: rdm_tracking.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateTrackingDisplay(response.data);
                }
            }
        });
    }

    /**
     * Update tracking display
     */
    function updateTrackingDisplay(data) {
        // Update status text
        $('.status-text').text(data.status_name);
        $('.status-indicator').attr('class', `status-indicator status-${data.status}`);
        
        // Update progress bar
        updateProgressBar(data.status);
        
        // Update timeline
        updateTimeline(data.status);
        
        // Update agent location if available
        if (data.tracking_info && data.tracking_info.current_location) {
            updateAgentLocation(data.tracking_info.current_location);
        }
        
        // Stop updates if order is delivered
        if (data.status === 'delivered' || data.status === 'completed') {
            stopRealTimeUpdates();
        }
    }

    /**
     * Update progress bar
     */
    function updateProgressBar(status) {
        const progressPercent = getProgressPercent(status);
        $('.progress-bar').css('width', progressPercent + '%');
    }

    /**
     * Update timeline display
     */
    function updateTimeline(currentStatus) {
        $('.timeline-item').each(function() {
            const $item = $(this);
            const itemStatus = $item.find('.timeline-icon').data('status');
            
            $item.removeClass('completed current');
            
            if (getStatusOrder(currentStatus) > getStatusOrder(itemStatus)) {
                $item.addClass('completed');
            } else if (currentStatus === itemStatus) {
                $item.addClass('current completed');
            }
        });
    }

    /**
     * Stop real-time updates
     */
    function stopRealTimeUpdates() {
        if (RDM_TRACKING_STATE.updateTimer) {
            clearInterval(RDM_TRACKING_STATE.updateTimer);
            RDM_TRACKING_STATE.updateTimer = null;
        }
        RDM_TRACKING_STATE.isTracking = false;
    }

    /**
     * Bind tracking events
     */
    function bindTrackingEvents() {
        $('#refresh-tracking').on('click', function() {
            $(this).prop('disabled', true);
            updateTrackingData();
            setTimeout(() => {
                $(this).prop('disabled', false);
            }, 2000);
        });
        
        $('#new-tracking').on('click', function() {
            resetTracking();
        });
    }

    /**
     * Reset tracking interface
     */
    function resetTracking() {
        stopRealTimeUpdates();
        
        // Clear state
        RDM_TRACKING_STATE.orderId = null;
        RDM_TRACKING_STATE.orderKey = null;
        RDM_TRACKING_STATE.lastAgentPosition = null;
        
        // Clear markers
        Object.values(RDM_TRACKING_STATE.markers).forEach(marker => {
            if (marker) marker.setMap(null);
        });
        RDM_TRACKING_STATE.markers = { restaurant: null, customer: null, agent: null };
        
        // Reset UI
        $('#tracking-results').hide();
        $('.tracking-form').show();
        $('#order-id, #order-key').val('');
    }

    /**
     * Utility functions
     */
    function getStatusOrder(status) {
        const order = {
            'pending': 0,
            'processing': 1,
            'preparing': 2,
            'ready': 3,
            'out-for-delivery': 4,
            'delivered': 5,
            'completed': 5
        };
        return order[status] || 0;
    }

    function getProgressPercent(status) {
        return (getStatusOrder(status) / 5) * 100;
    }

    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString();
    }

    function getTimeAgo(date) {
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        
        if (diffMins < 1) return 'just now';
        if (diffMins < 60) return `${diffMins} minutes ago`;
        
        const diffHours = Math.floor(diffMins / 60);
        if (diffHours < 24) return `${diffHours} hours ago`;
        
        const diffDays = Math.floor(diffHours / 24);
        return `${diffDays} days ago`;
    }

    function showLoading() {
        $('#tracking-results').html('<div class="rdm-loading"><span class="spinner"></span> ' + rdm_tracking.strings.loading + '</div>').show();
    }

    function hideLoading() {
        // Loading will be replaced by content
    }

    function showError(message) {
        $('#tracking-results').html('<div class="rdm-error"><p>' + message + '</p></div>').show();
    }

    // Initialize when document is ready
    $(document).ready(init);

})(jQuery);
