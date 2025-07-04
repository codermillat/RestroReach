/**
 * Restaurant Delivery Manager - Enhanced Mobile Agent Interface
 * Production-ready mobile application with full order management capabilities
 */

(function($) {
    'use strict';

    // ========================================
    // Configuration & State Management
    // ========================================

    const RDM_CONFIG = {
        locationUpdateInterval: 45000, // 45 seconds - battery optimized
        offlineRetryInterval: 30000, // 30 seconds
        maxRetryAttempts: 3,
        cacheExpiry: 300000, // 5 minutes
        geolocationOptions: {
            enableHighAccuracy: false, // Battery optimization
            timeout: 15000,
            maximumAge: 60000
        }
    };

    const RDM_STATE = {
        isOnline: navigator.onLine,
        isTracking: false,
        watchId: null,
        batteryLevel: null,
        currentOrders: [],
        offlineQueue: [],
        lastSync: null,
        currentOrderId: null,
        retryAttempts: 0
    };

    // ========================================
    // Utility Functions
    // ========================================

    /**
     * Show toast notification
     */
    function showToast(message, type = 'info', duration = 4000) {
        const $container = $('#rrm-toast-container');
        const $toast = $(`
            <div class="rrm-toast rrm-${type}">
                ${escapeHtml(message)}
            </div>
        `);
        
        $container.append($toast);
        
        setTimeout(() => {
            $toast.fadeOut(300, () => $toast.remove());
        }, duration);
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Format currency
     */
    function formatCurrency(amount) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount);
    }

    /**
     * Store data in localStorage with timestamp
     */
    function storeOfflineData(key, data) {
        try {
            const item = {
                data: data,
                timestamp: Date.now()
            };
            localStorage.setItem('rdm_' + key, JSON.stringify(item));
        } catch (e) {
            console.warn('Failed to store offline data:', e);
        }
    }

    /**
     * Get data from localStorage if not expired
     */
    function getOfflineData(key, maxAge = RDM_CONFIG.cacheExpiry) {
        try {
            const stored = localStorage.getItem('rdm_' + key);
            if (stored) {
                const item = JSON.parse(stored);
                if (Date.now() - item.timestamp < maxAge) {
                    return item.data;
                }
            }
        } catch (e) {
            console.warn('Failed to get offline data:', e);
        }
        return null;
    }

    /**
     * Add action to offline queue
     */
    function queueOfflineAction(action, data) {
        RDM_STATE.offlineQueue.push({
            action: action,
            data: data,
            timestamp: Date.now(),
            attempts: 0
        });
        storeOfflineData('queue', RDM_STATE.offlineQueue);
    }

    /**
     * Process offline queue when back online
     */
    function processOfflineQueue() {
        if (!RDM_STATE.isOnline || RDM_STATE.offlineQueue.length === 0) {
            return;
        }

        const queue = [...RDM_STATE.offlineQueue];
        RDM_STATE.offlineQueue = [];

        queue.forEach(item => {
            if (item.attempts < RDM_CONFIG.maxRetryAttempts) {
                executeOfflineAction(item);
            }
        });

        storeOfflineData('queue', RDM_STATE.offlineQueue);
    }

    /**
     * Execute queued offline action
     */
    function executeOfflineAction(item) {
        item.attempts++;

        const actionMap = {
            'update_location': sendLocationUpdate,
            'update_order_status': updateOrderStatus,
            'collect_payment': collectCODPayment
        };

        const handler = actionMap[item.action];
        if (handler) {
            handler(item.data)
                .fail(() => {
                    if (item.attempts < RDM_CONFIG.maxRetryAttempts) {
                        RDM_STATE.offlineQueue.push(item);
                    }
                });
        }
    }

    // ========================================
    // Network & Connectivity Management
    // ========================================

    /**
     * Handle online/offline status changes
     */
    function handleNetworkChange() {
        RDM_STATE.isOnline = navigator.onLine;
        updateNetworkUI();

        if (RDM_STATE.isOnline) {
            processOfflineQueue();
            syncData();
        }
    }

    /**
     * Update network status UI
     */
    function updateNetworkUI() {
        const $status = $('#rrm-network-status');
        const $indicator = $status.find('.rrm-status-indicator');
        const $text = $status.find('.rrm-status-text');
        const $offline = $('#rrm-offline-indicator');

        if (RDM_STATE.isOnline) {
            $indicator.removeClass('rrm-offline').addClass('rrm-online');
            $text.text('Online');
            $offline.hide();
        } else {
            $indicator.removeClass('rrm-online').addClass('rrm-offline');
            $text.text('Offline');
            $offline.show();
        }
    }

    /**
     * Sync data when coming back online
     */
    function syncData() {
        if (!RDM_STATE.isOnline) return;

        loadOrders();
        RDM_STATE.lastSync = Date.now();
    }

    // ========================================
    // GPS Location Tracking
    // ========================================

    /**
     * Initialize GPS tracking
     */
    function initGPSTracking() {
        // Get battery level if supported
        if ('getBattery' in navigator) {
            navigator.getBattery().then(battery => {
                RDM_STATE.batteryLevel = Math.round(battery.level * 100);
                
                battery.addEventListener('levelchange', () => {
                    RDM_STATE.batteryLevel = Math.round(battery.level * 100);
                    adjustTrackingFrequency();
                });
            });
        }

        // GPS toggle handler
        $('#rrm-gps-toggle').on('change', function() {
            const isEnabled = $(this).prop('checked');
            if (isEnabled) {
                startLocationTracking();
            } else {
                stopLocationTracking();
            }
        });
    }

    /**
     * Adjust tracking frequency based on battery level
     */
    function adjustTrackingFrequency() {
        if (RDM_STATE.batteryLevel < 20) {
            RDM_CONFIG.locationUpdateInterval = 120000; // 2 minutes
        } else if (RDM_STATE.batteryLevel < 50) {
            RDM_CONFIG.locationUpdateInterval = 60000; // 1 minute
        } else {
            RDM_CONFIG.locationUpdateInterval = 45000; // 45 seconds
        }
    }

    /**
     * Start location tracking
     */
    function startLocationTracking() {
        if (!navigator.geolocation) {
            showToast('Geolocation not supported by your device', 'error');
            return;
        }

        navigator.geolocation.getCurrentPosition(
            position => {
                RDM_STATE.isTracking = true;
                RDM_STATE.watchId = navigator.geolocation.watchPosition(
                    handleLocationUpdate,
                    handleLocationError,
                    RDM_CONFIG.geolocationOptions
                );
                updateGPSToggleUI(true);
                showToast('Location sharing started', 'success');
            },
            error => {
                handleLocationError(error);
                updateGPSToggleUI(false);
            },
            RDM_CONFIG.geolocationOptions
        );
    }

    /**
     * Stop location tracking
     */
    function stopLocationTracking() {
        if (RDM_STATE.watchId !== null) {
            navigator.geolocation.clearWatch(RDM_STATE.watchId);
            RDM_STATE.watchId = null;
        }
        RDM_STATE.isTracking = false;
        updateGPSToggleUI(false);
        showToast('Location sharing stopped', 'info');
    }

    /**
     * Handle location updates
     */
    function handleLocationUpdate(position) {
        const locationData = {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy: position.coords.accuracy,
            battery_level: RDM_STATE.batteryLevel
        };

        if (RDM_STATE.isOnline) {
            sendLocationUpdate(locationData);
        } else {
            queueOfflineAction('update_location', locationData);
        }
    }

    /**
     * Send location update to server
     */
    function sendLocationUpdate(locationData) {
        return $.ajax({
            url: rrmAgent.ajaxUrl,
            type: 'POST',
            data: {
                action: 'rdm_update_agent_location',
                nonce: rrmAgent.nonce,
                ...locationData
            },
            success: function(response) {
                if (response.success) {
                    updateGPSStatusUI('Active');
                } else {
                    console.warn('Location update failed:', response.data);
                }
            },
            error: function() {
                if (RDM_STATE.isOnline) {
                    queueOfflineAction('update_location', locationData);
                }
            }
        });
    }

    /**
     * Handle location errors
     */
    function handleLocationError(error) {
        let errorMessage = 'Location error: ';
        
        switch(error.code) {
            case error.PERMISSION_DENIED:
                errorMessage += 'Permission denied. Please enable location access.';
                break;
            case error.POSITION_UNAVAILABLE:
                errorMessage += 'Location unavailable. Check your GPS.';
                break;
            case error.TIMEOUT:
                errorMessage += 'Location request timed out.';
                break;
            default:
                errorMessage += 'Unknown error occurred.';
        }

        showToast(errorMessage, 'error');
        stopLocationTracking();
    }

    /**
     * Update GPS toggle UI
     */
    function updateGPSToggleUI(isEnabled) {
        const $toggle = $('#rrm-gps-toggle');
        $toggle.prop('checked', isEnabled);
        updateGPSStatusUI(isEnabled ? 'Active' : 'Inactive');
    }

    /**
     * Update GPS status text
     */
    function updateGPSStatusUI(status) {
        $('#rrm-gps-status').text(status);
    }

    // ========================================
    // Order Management
    // ========================================

    /**
     * Load orders from server
     */
    function loadOrders() {
        const $loading = $('#rrm-order-list-loading');
        const $container = $('#rrm-order-list-container');
        const $noOrders = $('#rrm-no-orders');

        $loading.show();
        $container.hide();
        $noOrders.hide();

        $.ajax({
            url: rrmAgent.ajaxUrl,
            type: 'POST',
            data: {
                action: 'rdm_get_agent_orders',
                nonce: rrmAgent.nonce
            },
            success: function(response) {
                $loading.hide();
                
                if (response.success && response.data.orders) {
                    RDM_STATE.currentOrders = response.data.orders;
                    storeOfflineData('orders', RDM_STATE.currentOrders);
                    renderOrders(response.data.orders);
                } else {
                    showNoOrdersState();
                }
            },
            error: function() {
                $loading.hide();
                
                // Try to load from offline cache
                const cachedOrders = getOfflineData('orders');
                if (cachedOrders) {
                    renderOrders(cachedOrders);
                    showToast('Showing cached orders (offline)', 'warning');
                } else {
                    showNoOrdersState();
                    showToast('Failed to load orders', 'error');
                }
            }
        });
    }

    /**
     * Render orders in the UI
     */
    function renderOrders(orders) {
        const $container = $('#rrm-order-list-container');
        const $stats = $('#rrm-order-stats .rrm-stat');
        
        if (!orders || orders.length === 0) {
            showNoOrdersState();
            return;
        }

        $container.empty().show();
        $stats.text(`${orders.length} orders`);

        orders.forEach(order => {
            const $orderItem = createOrderElement(order);
            $container.append($orderItem);
        });

        $('#rrm-no-orders').hide();
    }

    /**
     * Create order element
     */
    function createOrderElement(order) {
        const statusClass = getStatusClass(order.status);
        const isCOD = order.payment_method === 'cod';
        
        return $(`
            <div class="rrm-order-item ${isCOD ? 'rrm-cod' : ''}" data-order-id="${order.id}">
                <div class="rrm-order-header">
                    <span class="rrm-order-id">#${order.id}</span>
                    <span class="rrm-order-status ${statusClass}">${order.status}</span>
                </div>
                <div class="rrm-order-info">
                    <div class="rrm-order-customer">${escapeHtml(order.customer)}</div>
                    <div class="rrm-order-address">${escapeHtml(order.address)}</div>
                </div>
                <div class="rrm-order-meta">
                    <span class="rrm-order-total">${order.formatted_total || formatCurrency(order.total)}</span>
                    <span class="rrm-order-payment">${order.payment_method_title || order.payment_method}</span>
                    ${isCOD ? '<span class="rrm-cod-badge">COD</span>' : ''}
                </div>
                <div class="rrm-order-actions">
                    ${generateOrderActions(order)}
                </div>
            </div>
        `);
    }

    /**
     * Generate action buttons based on order status
     */
    function generateOrderActions(order) {
        const actions = [];
        
        switch (order.status) {
            case 'assigned':
                actions.push('<button class="rrm-btn-small rrm-btn-accept" data-action="accept">Accept</button>');
                break;
            case 'accepted':
            case 'preparing':
                actions.push('<button class="rrm-btn-small rrm-btn-pickup" data-action="pickup">Mark Picked Up</button>');
                break;
            case 'ready-for-pickup':
                actions.push('<button class="rrm-btn-small rrm-btn-pickup" data-action="pickup">Mark Picked Up</button>');
                break;
            case 'out-for-delivery':
                if (order.payment_method === 'cod') {
                    actions.push('<button class="rrm-btn-small rrm-btn-secondary" data-action="collect-payment">Collect Payment</button>');
                }
                actions.push('<button class="rrm-btn-small rrm-btn-delivered" data-action="delivered">Mark Delivered</button>');
                break;
        }
        
        actions.push('<button class="rrm-btn-small rrm-btn-secondary" data-action="view-details">View Details</button>');
        
        return actions.join('');
    }

    /**
     * Get CSS class for order status
     */
    function getStatusClass(status) {
        const statusMap = {
            'preparing': 'rrm-preparing',
            'ready-for-pickup': 'rrm-ready',
            'out-for-delivery': 'rrm-delivering'
        };
        return statusMap[status] || '';
    }

    /**
     * Show no orders state
     */
    function showNoOrdersState() {
        $('#rrm-order-list-container').hide();
        $('#rrm-no-orders').show();
        $('#rrm-order-stats .rrm-stat').text('0 orders');
    }

    /**
     * Handle order actions
     */
    function handleOrderAction(orderId, action) {
        RDM_STATE.currentOrderId = orderId;
        
        switch (action) {
            case 'accept':
                acceptOrder(orderId);
                break;
            case 'pickup':
                updateOrderStatus(orderId, 'out-for-delivery', 'Order picked up');
                break;
            case 'delivered':
                showPhotoModal(orderId);
                break;
            case 'collect-payment':
                showCODModal(orderId);
                break;
            case 'view-details':
                showOrderDetails(orderId);
                break;
        }
    }

    /**
     * Accept order
     */
    function acceptOrder(orderId) {
        $.ajax({
            url: rrmAgent.ajaxUrl,
            type: 'POST',
            data: {
                action: 'rdm_accept_order',
                nonce: rrmAgent.nonce,
                order_id: orderId
            },
            success: function(response) {
                if (response.success) {
                    showToast('Order accepted successfully', 'success');
                    loadOrders();
                } else {
                    showToast(response.data.message || 'Failed to accept order', 'error');
                }
            },
            error: function() {
                showToast('Network error. Try again.', 'error');
            }
        });
    }

    /**
     * Update order status
     */
    function updateOrderStatus(orderId, status, notes = '') {
        const data = {
            order_id: orderId,
            status: status,
            notes: notes
        };

        if (RDM_STATE.isOnline) {
            sendOrderStatusUpdate(data);
        } else {
            queueOfflineAction('update_order_status', data);
            showToast('Order status queued for sync', 'warning');
        }
    }

    /**
     * Send order status update to server
     */
    function sendOrderStatusUpdate(data) {
        return $.ajax({
            url: rrmAgent.ajaxUrl,
            type: 'POST',
            data: {
                action: 'rdm_update_order_status',
                nonce: rrmAgent.nonce,
                ...data
            },
            success: function(response) {
                if (response.success) {
                    showToast('Order status updated', 'success');
                    loadOrders();
                } else {
                    showToast(response.data.message || 'Failed to update status', 'error');
                }
            },
            error: function() {
                showToast('Network error. Status queued for sync.', 'warning');
                queueOfflineAction('update_order_status', data);
            }
        });
    }

    // ========================================
    // COD Payment Collection
    // ========================================

    /**
     * Show COD collection modal
     */
    function showCODModal(orderId) {
        const order = RDM_STATE.currentOrders.find(o => o.id == orderId);
        if (!order) {
            showToast('Order not found', 'error');
            return;
        }

        const $modal = $('#rrm-cod-modal');
        const $total = $('#rrm-payment-total');
        const $collectedInput = $('#rrm-collected-amount');
        const $changeSection = $('#rrm-change-section');
        const $changeAmount = $('#rrm-change-amount');
        const $confirmBtn = $('#rrm-confirm-payment');

        $total.text(order.formatted_total || formatCurrency(order.total));
        $collectedInput.val('').trigger('input');
        $changeSection.hide();
        $('#rrm-payment-notes').val('');
        
        $modal.data('order-id', orderId).data('order-total', order.total).show();
        $collectedInput.focus();

        // Real-time change calculation
        $collectedInput.off('input.cod').on('input.cod', function() {
            const collected = parseFloat($(this).val()) || 0;
            const total = parseFloat($modal.data('order-total'));
            
            if (collected > 0) {
                calculateChange(total, collected);
            } else {
                $changeSection.hide();
                $confirmBtn.prop('disabled', true);
            }
        });
    }

    /**
     * Calculate and display change
     */
    function calculateChange(orderTotal, collectedAmount) {
        $.ajax({
            url: rrmAgent.ajaxUrl,
            type: 'POST',
            data: {
                action: 'rdm_calculate_change',
                nonce: rrmAgent.nonce,
                order_total: orderTotal,
                collected_amount: collectedAmount
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    const $changeSection = $('#rrm-change-section');
                    const $changeAmount = $('#rrm-change-amount');
                    const $confirmBtn = $('#rrm-confirm-payment');
                    
                    if (data.sufficient_payment) {
                        $changeAmount.text(data.formatted_change);
                        $changeSection.show();
                        $confirmBtn.prop('disabled', false);
                    } else {
                        $changeSection.hide();
                        $confirmBtn.prop('disabled', true);
                    }
                }
            }
        });
    }

    /**
     * Confirm COD payment collection
     */
    function confirmCODPayment() {
        const $modal = $('#rrm-cod-modal');
        const orderId = $modal.data('order-id');
        const collectedAmount = parseFloat($('#rrm-collected-amount').val());
        const notes = $('#rrm-payment-notes').val();

        if (!collectedAmount || collectedAmount <= 0) {
            showToast('Please enter a valid amount', 'error');
            return;
        }

        const data = {
            order_id: orderId,
            collected_amount: collectedAmount,
            notes: notes
        };

        if (RDM_STATE.isOnline) {
            collectCODPayment(data);
        } else {
            queueOfflineAction('collect_payment', data);
            showToast('Payment queued for sync', 'warning');
            $modal.hide();
        }
    }

    /**
     * Process COD payment collection
     */
    function collectCODPayment(data) {
        return $.ajax({
            url: rrmAgent.ajaxUrl,
            type: 'POST',
            data: {
                action: 'rdm_collect_cod_payment',
                nonce: rrmAgent.nonce,
                ...data
            },
            success: function(response) {
                if (response.success) {
                    showToast('Payment collected successfully', 'success');
                    $('#rrm-cod-modal').hide();
                    loadOrders();
                } else {
                    showToast(response.data.message || 'Payment collection failed', 'error');
                }
            },
            error: function() {
                showToast('Network error. Payment queued for sync.', 'warning');
                queueOfflineAction('collect_payment', data);
                $('#rrm-cod-modal').hide();
            }
        });
    }

    // ========================================
    // Photo Upload for Delivery Confirmation
    // ========================================

    /**
     * Show photo upload modal
     */
    function showPhotoModal(orderId) {
        const $modal = $('#rrm-photo-modal');
        const $preview = $('#rrm-photo-preview');
        const $camera = $('.rrm-camera-section');
        
        $modal.data('order-id', orderId).show();
        $preview.hide();
        $camera.show();
    }

    /**
     * Handle photo capture
     */
    function handlePhotoCapture() {
        const $input = $('#rrm-photo-input');
        $input.trigger('click');
    }

    /**
     * Handle photo selection
     */
    function handlePhotoSelection(file) {
        if (!file) return;

        // Validate file
        if (!file.type.startsWith('image/')) {
            showToast('Please select an image file', 'error');
            return;
        }

        if (file.size > 5 * 1024 * 1024) { // 5MB limit
            showToast('Image too large. Maximum 5MB allowed.', 'error');
            return;
        }

        // Show preview
        const reader = new FileReader();
        reader.onload = function(e) {
            $('#rrm-preview-image').attr('src', e.target.result);
            $('#rrm-photo-preview').show();
            $('.rrm-camera-section').hide();
        };
        reader.readAsDataURL(file);
    }

    /**
     * Upload delivery photo
     */
    function uploadDeliveryPhoto() {
        const $modal = $('#rrm-photo-modal');
        const orderId = $modal.data('order-id');
        const fileInput = document.getElementById('rrm-photo-input');
        
        if (!fileInput.files[0]) {
            showToast('No photo selected', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'rdm_upload_delivery_photo');
        formData.append('nonce', rrmAgent.nonce);
        formData.append('order_id', orderId);
        formData.append('delivery_photo', fileInput.files[0]);

        $.ajax({
            url: rrmAgent.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showToast('Photo uploaded successfully', 'success');
                    $modal.hide();
                    
                    // Mark order as delivered after photo upload
                    updateOrderStatus(orderId, 'delivered', 'Delivery confirmed with photo');
                } else {
                    showToast(response.data.message || 'Photo upload failed', 'error');
                }
            },
            error: function() {
                showToast('Network error. Please try again.', 'error');
            }
        });
    }

    /**
     * Retake photo
     */
    function retakePhoto() {
        $('#rrm-photo-preview').hide();
        $('.rrm-camera-section').show();
        $('#rrm-photo-input').val('');
    }

    // ========================================
    // Order Details Modal
    // ========================================

    /**
     * Show order details
     */
    function showOrderDetails(orderId) {
        $.ajax({
            url: rrmAgent.ajaxUrl,
            type: 'POST',
            data: {
                action: 'rdm_get_order_details',
                nonce: rrmAgent.nonce,
                order_id: orderId
            },
            success: function(response) {
                if (response.success) {
                    renderOrderDetails(response.data);
                } else {
                    showToast(response.data.message || 'Failed to load order details', 'error');
                }
            },
            error: function() {
                showToast('Network error. Please try again.', 'error');
            }
        });
    }

    /**
     * Render order details in modal
     */
    function renderOrderDetails(order) {
        const $modal = $('#rrm-order-modal');
        const $title = $('#rrm-order-title');
        const $content = $('#rrm-order-details-content');
        
        $title.text(`Order #${order.id}`);
        
        const detailsHtml = `
            <div class="rrm-order-details-section">
                <h4>Customer Information</h4>
                <div class="rrm-order-details-item">
                    <span class="rrm-order-details-label">Name:</span>
                    <span class="rrm-order-details-value">${escapeHtml(order.customer.name)}</span>
                </div>
                <div class="rrm-order-details-item">
                    <span class="rrm-order-details-label">Phone:</span>
                    <span class="rrm-order-details-value">
                        <a href="tel:${escapeHtml(order.customer.phone)}">${escapeHtml(order.customer.phone)}</a>
                    </span>
                </div>
                <div class="rrm-order-details-item">
                    <span class="rrm-order-details-label">Address:</span>
                    <span class="rrm-order-details-value">${order.shipping_address}</span>
                </div>
            </div>
            
            <div class="rrm-order-details-section">
                <h4>Order Information</h4>
                <div class="rrm-order-details-item">
                    <span class="rrm-order-details-label">Status:</span>
                    <span class="rrm-order-details-value">${escapeHtml(order.status)}</span>
                </div>
                <div class="rrm-order-details-item">
                    <span class="rrm-order-details-label">Total:</span>
                    <span class="rrm-order-details-value">${order.formatted_total}</span>
                </div>
                <div class="rrm-order-details-item">
                    <span class="rrm-order-details-label">Payment:</span>
                    <span class="rrm-order-details-value">${escapeHtml(order.payment_method_title)}</span>
                </div>
            </div>
            
            <div class="rrm-order-details-section">
                <h4>Items</h4>
                ${order.items.map(item => `
                    <div class="rrm-order-details-item">
                        <span class="rrm-order-details-label">${escapeHtml(item.name)} (${item.quantity}x):</span>
                        <span class="rrm-order-details-value">${formatCurrency(item.total)}</span>
                    </div>
                `).join('')}
            </div>
            
            ${order.notes ? `
                <div class="rrm-order-details-section">
                    <h4>Customer Notes</h4>
                    <p class="rrm-customer-notes">${escapeHtml(order.notes)}</p>
                </div>
            ` : ''}
        `;
        
        $content.html(detailsHtml);
        $modal.show();
    }

    // ========================================
    // Pull to Refresh
    // ========================================

    /**
     * Initialize pull to refresh
     */
    function initPullToRefresh() {
        let startY = 0;
        let currentY = 0;
        let pulling = false;
        const threshold = 80;
        
        const $container = $('#rrm-order-list-container');
        
        $container.on('touchstart', function(e) {
            if ($container.scrollTop() === 0) {
                startY = e.originalEvent.touches[0].pageY;
                pulling = true;
            }
        });
        
        $container.on('touchmove', function(e) {
            if (pulling) {
                currentY = e.originalEvent.touches[0].pageY;
                const deltaY = currentY - startY;
                
                if (deltaY > 0 && deltaY < threshold * 2) {
                    e.preventDefault();
                    
                    if (deltaY > threshold) {
                        // Show ready to refresh state
                        showPullRefreshIndicator(true);
                    } else {
                        showPullRefreshIndicator(false);
                    }
                }
            }
        });
        
        $container.on('touchend', function() {
            if (pulling) {
                const deltaY = currentY - startY;
                
                if (deltaY > threshold) {
                    loadOrders();
                    showToast('Refreshing orders...', 'info');
                }
                
                hidePullRefreshIndicator();
                pulling = false;
            }
        });
    }

    /**
     * Show pull refresh indicator
     */
    function showPullRefreshIndicator(ready) {
        // Implementation for pull refresh visual feedback
        console.log('Pull refresh:', ready ? 'Ready' : 'Pulling');
    }

    /**
     * Hide pull refresh indicator
     */
    function hidePullRefreshIndicator() {
        // Implementation for hiding pull refresh indicator
        console.log('Hide pull refresh');
    }

    // ========================================
    // Event Handlers & Initialization
    // ========================================

    /**
     * Initialize all functionality
     */
    function init() {
        // Network event listeners
        window.addEventListener('online', handleNetworkChange);
        window.addEventListener('offline', handleNetworkChange);
        
        // Load stored offline queue
        const storedQueue = getOfflineData('queue', Infinity);
        if (storedQueue) {
            RDM_STATE.offlineQueue = storedQueue;
        }

        // Initialize components
        initGPSTracking();
        initPullToRefresh();
        
        // Load initial data
        loadOrders();
        
        // Update network UI
        handleNetworkChange();
        
        // Set up auto-refresh
        setInterval(() => {
            if (RDM_STATE.isOnline) {
                loadOrders();
            }
        }, 60000); // Refresh every minute
        
        console.log('Mobile Agent Interface initialized');
    }

    // ========================================
    // jQuery Document Ready
    // ========================================

    $(document).ready(function() {
        // Initialize main functionality
        init();

        // Refresh button
        $('#rrm-refresh-btn').on('click', function() {
            loadOrders();
            showToast('Refreshing...', 'info');
        });

        // Logout button
        $('#rrm-logout-btn').on('click', function() {
            // Clear auth cookie
            document.cookie = 'rdm_agent_logged_in=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
            
            // Clear offline data
            localStorage.removeItem('rdm_orders');
            localStorage.removeItem('rdm_queue');
            
            // Redirect to login
            window.location.href = rrmAgent.loginUrl;
        });

        // Emergency button
        $('#rrm-emergency-btn').on('click', function() {
            $('#rrm-emergency-modal').show();
        });

        // Modal close buttons
        $('.rrm-modal-close').on('click', function() {
            $(this).closest('.rrm-modal').hide();
        });

        // Modal backdrop click to close
        $('.rrm-modal').on('click', function(e) {
            if (e.target === this) {
                $(this).hide();
            }
        });

        // Order action handlers
        $(document).on('click', '.rrm-order-item .rrm-btn-small', function(e) {
            e.stopPropagation();
            const orderId = $(this).closest('.rrm-order-item').data('order-id');
            const action = $(this).data('action');
            handleOrderAction(orderId, action);
        });

        // Order item click for details
        $(document).on('click', '.rrm-order-item', function() {
            const orderId = $(this).data('order-id');
            showOrderDetails(orderId);
        });

        // COD payment confirmation
        $('#rrm-confirm-payment').on('click', confirmCODPayment);

        // Photo capture handlers
        $('#rrm-take-photo').on('click', handlePhotoCapture);
        $('#rrm-upload-photo').on('click', uploadDeliveryPhoto);
        $('#rrm-retake-photo').on('click', retakePhoto);

        // Photo input change handler
        $('#rrm-photo-input').on('change', function() {
            const file = this.files[0];
            if (file) {
                handlePhotoSelection(file);
            }
        });

        // Bottom navigation (future tabs)
        $('.rrm-nav-item').on('click', function() {
            $('.rrm-nav-item').removeClass('rrm-active');
            $(this).addClass('rrm-active');
            
            const tab = $(this).data('tab');
            // Future implementation for different tabs
            console.log('Navigate to tab:', tab);
        });
    });

})(jQuery); 